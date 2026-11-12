<?php
/**
 * The review queue read model (REQ-084, REQ-222, REQ-239): what a reviewer has to look at, in the default order, and the plain
 * counts by state for the dashboard tiles.
 *
 * Items: new profiles (imported, not public, or published and pending), claims and customer submissions, pending revisions on
 * claimed profiles, and FLAGGED_CHANGE items (a pending version created by an import: a source changed a reviewed field and the
 * importer did not overwrite it). Order (Q31, answered; REQ-222): claims, submissions and corrections first, then pilot (tier
 * T1) before the rest, then organisations and institutions before individuals and entrepreneurs, then the oldest.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review\Admin;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;

/**
 * Review queue.
 */
final class ReviewQueue {

	public const PER_PAGE = 50;

	public const KIND_NEW        = 'new_profile';
	public const KIND_CLAIM      = 'claim';
	public const KIND_SUBMISSION = 'submission';
	public const KIND_REVISION   = 'pending_revision';
	public const KIND_FLAGGED    = 'flagged_change';

	public const ASSIGNEE_META = '_wiw_review_assignee';

	/**
	 * One page of queue items in the default order.
	 *
	 * @param int $page Page, from 1.
	 * @return array{items: array<int, array<string, mixed>>, total: int, pages: int}
	 */
	public function items( int $page = 1 ): array {
		global $wpdb;
		$states = StateRepository::table();
		$vers   = VersionRepository::table();
		$prov   = ProvenanceRepository::table();
		$in     = "'" . PubState::IMPORTED_NOT_PUBLIC . "','" . PubState::PUBLISHED_PENDING . "'";
		$rank   = "CASE WHEN s.tier = 'T1' THEN 0 ELSE 1 END AS pilot, CASE WHEN s.entity_type IN ('organisation','institution') THEN 0 ELSE 1 END AS grp";
		$ai     = "EXISTS (SELECT 1 FROM {$prov} f WHERE f.profile_id = s.profile_id AND f.method = 'ai') AS ai";
		$edited = "EXISTS (SELECT 1 FROM {$vers} e WHERE e.profile_id = s.profile_id AND e.created_via = 'edit') AS edited";

		$union = "SELECT s.profile_id, 0 AS version_id, CASE WHEN s.claimed = 1 THEN 'claim' WHEN s.origin = 'customer' THEN 'submission' ELSE 'new_profile' END AS kind,
				CASE WHEN s.claimed = 1 OR s.origin = 'customer' THEN 0 ELSE 1 END AS prio, {$rank}, s.updated_at AS ts, {$ai}, {$edited}
			FROM {$states} s INNER JOIN {$wpdb->posts} p ON p.ID = s.profile_id
			WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft') AND s.pub_state IN ({$in})
			UNION ALL
			SELECT s.profile_id, v.id AS version_id, CASE WHEN v.created_via = 'import' THEN 'flagged_change' ELSE 'pending_revision' END AS kind,
				0 AS prio, {$rank}, v.created_at AS ts, {$ai}, {$edited}
			FROM {$vers} v INNER JOIN {$states} s ON s.profile_id = v.profile_id INNER JOIN {$wpdb->posts} p ON p.ID = s.profile_id
			WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft') AND v.approval_status = 'pending'
				AND NOT EXISTS (SELECT 1 FROM {$vers} d WHERE d.revision_of = v.id)";

		$type  = ProfileType::POST_TYPE;
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM ({$union}) q", $type, $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders -- fixed names; values prepared.
		$pages = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page  = max( 1, min( $page, $pages ) );
		$found = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT q.*, p.post_title, st.entity_type, st.pub_state FROM ({$union}) q INNER JOIN {$wpdb->posts} p ON p.ID = q.profile_id INNER JOIN {$states} st ON st.profile_id = q.profile_id ORDER BY q.prio ASC, q.pilot ASC, q.grp ASC, q.ts ASC, q.profile_id ASC, q.version_id ASC LIMIT %d OFFSET %d", $type, $type, self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE ), // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed names; values prepared.
			ARRAY_A
		);
		$items = array();
		foreach ( $found as $row ) {
			$id      = (int) $row['profile_id'];
			$items[] = array(
				'profile_id' => $id,
				'version_id' => (int) $row['version_id'],
				'kind'       => (string) $row['kind'],
				'name'       => (string) $row['post_title'],
				'type'       => (string) $row['entity_type'],
				'state'      => (string) $row[ StateRepository::STATE_COLUMN ],
				'pilot'      => 0 === (int) $row['pilot'],
				'since'      => (string) $row['ts'],
				'ai_drafted' => (bool) (int) $row['ai'],
				'edited'     => (bool) (int) $row['edited'],
				'assignee'   => (int) get_post_meta( $id, self::ASSIGNEE_META, true ),
			);
		}
		return array(
			'items' => $items,
			'total' => $total,
			'pages' => $pages,
		);
	}

	/**
	 * Plain counts by state for the dashboard tiles: imported, pending review, reviewed and published (REQ-239). No target, no
	 * progress bar, no capacity figure.
	 *
	 * @return array{imported: int, pending: int, reviewed: int, published: int}
	 */
	public function tiles(): array {
		$states = new StateRepository();
		$count  = static fn ( string $state ): int => $states->count( array( StateRepository::STATE_COLUMN => $state ) );
		$pend   = $count( PubState::PUBLISHED_PENDING );
		$rev    = $count( PubState::PUBLISHED_REVIEWED );
		return array(
			'imported'  => $count( PubState::IMPORTED_NOT_PUBLIC ),
			'pending'   => $pend,
			'reviewed'  => $rev,
			'published' => $pend + $rev,
		);
	}

	/**
	 * Assign a queue item's profile to a reviewer.
	 *
	 * @param int $profile_id Profile.
	 * @param int $user_id    Reviewer; 0 clears.
	 */
	public function assign( int $profile_id, int $user_id ): void {
		if ( $user_id > 0 ) {
			update_post_meta( $profile_id, self::ASSIGNEE_META, $user_id );
		} else {
			delete_post_meta( $profile_id, self::ASSIGNEE_META );
		}
	}
}
