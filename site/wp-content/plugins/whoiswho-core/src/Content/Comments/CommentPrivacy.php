<?php
/**
 * Commenter data in data-request exports and erasure (REQ-258, REQ-059). The comments themselves live in the WordPress comments
 * table, which core's own exporter and eraser already cover by email address (the eraser anonymises the name, email and IP and
 * keeps the comment text unless it is removed, so threads stay readable). This class adds what core does not know about: the
 * wpDiscuz subscription and follow tables, when that plugin has created them. Each is exported and deleted by email address.
 * Nothing is logged but a count.
 *
 * Checked against wpDiscuz 7.7.0, which creates six tables: wc_comments_subscription (email) and wc_follow_users (names and
 * emails) are covered here; wc_users_voted (a hash of a visitor's IP address) and wc_users_rated (a visitor's IP address) hold
 * nothing that can be found by email, so WpdiscuzSettings switches off the votes and star ratings that would fill them;
 * wc_phrases and wc_feedback_forms hold no personal data. Its own exporter covers only custom form fields, and it has no eraser.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Comments;

/**
 * Comment privacy.
 */
final class CommentPrivacy {

	public const GROUP = 'whoiswho-wpdiscuz';

	/**
	 * The wpDiscuz tables, without the site prefix: table => array( email columns, columns shown in an export ).
	 *
	 * @var array<string, array{emails: string[], show: array<string, string>}>
	 */
	public const TABLES = array(
		'wc_comments_subscription' => array(
			'emails' => array( 'email' ),
			'show'   => array(
				'email'             => 'Email address',
				'post_id'           => 'Story',
				'subscribtion_type' => 'Subscription',
				'subscription_date' => 'Subscribed',
			),
		),
		'wc_follow_users'          => array(
			'emails' => array( 'user_email', 'follower_email' ),
			'show'   => array(
				'user_email'     => 'Email address',
				'user_name'      => 'Name',
				'follower_email' => 'Follower email address',
				'follower_name'  => 'Follower name',
				'follow_date'    => 'Followed',
			),
		),
	);

	/**
	 * Attach hooks.
	 */
	public static function hooks(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			static function ( $exporters ): array {
				$exporters                = is_array( $exporters ) ? $exporters : array();
				$exporters[ self::GROUP ] = array(
					'exporter_friendly_name' => 'Comment subscriptions',
					'callback'               => static fn ( string $email, int $page = 1 ): array => ( new self() )->export( $email, $page ),
				);
				return $exporters;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			static function ( $erasers ): array {
				$erasers                = is_array( $erasers ) ? $erasers : array();
				$erasers[ self::GROUP ] = array(
					'eraser_friendly_name' => 'Comment subscriptions',
					'callback'             => static fn ( string $email, int $page = 1 ): array => ( new self() )->erase( $email, $page ),
				);
				return $erasers;
			}
		);
	}

	/**
	 * Export the subscription and follow rows for an address.
	 *
	 * @param string $email Address from the data request.
	 * @param int    $page  Page (one page holds every row).
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		unset( $page );
		$items = array();
		foreach ( self::TABLES as $table => $spec ) {
			foreach ( $this->rows( $table, $spec['emails'], $email ) as $row ) {
				$fields = array();
				foreach ( $spec['show'] as $column => $label ) {
					if ( isset( $row[ $column ] ) && '' !== (string) $row[ $column ] ) {
						$fields[] = array(
							'name'  => $label,
							'value' => (string) $row[ $column ],
						);
					}
				}
				$items[] = array(
					'group_id'    => self::GROUP,
					'group_label' => 'Comment subscriptions',
					'item_id'     => $table . '-' . (int) ( $row['id'] ?? 0 ),
					'data'        => $fields,
				);
			}
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Delete the subscription and follow rows for an address.
	 *
	 * @param string $email Address from the data request.
	 * @param int    $page  Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		unset( $page );
		global $wpdb;
		$removed = 0;
		foreach ( self::TABLES as $table => $spec ) {
			if ( ! $this->exists( $table ) ) {
				continue;
			}
			foreach ( $spec['emails'] as $column ) {
				$removed += (int) $wpdb->delete( $wpdb->prefix . $table, array( $column => $email ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Rows of a wpDiscuz table where any email column equals the address; nothing when the plugin has not created the table.
	 *
	 * @param string   $table   Table without prefix.
	 * @param string[] $columns Email columns.
	 * @param string   $email   Address.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( string $table, array $columns, string $email ): array {
		global $wpdb;
		if ( '' === trim( $email ) || ! $this->exists( $table ) ) {
			return array();
		}
		$full  = $wpdb->prefix . $table;
		$where = implode( ' OR ', array_map( static fn ( string $column ): string => '`' . preg_replace( '/[^a-z_]/', '', $column ) . '` = %s', $columns ) );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$full}` WHERE {$where}", array_fill( 0, count( $columns ), $email ) ), ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- fixed table and column names, value prepared.
	}

	/**
	 * Whether a table exists.
	 *
	 * @param string $table Table without prefix.
	 */
	private function exists( string $table ): bool {
		global $wpdb;
		return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
