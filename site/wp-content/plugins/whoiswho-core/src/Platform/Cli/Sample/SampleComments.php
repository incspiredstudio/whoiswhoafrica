<?php
/**
 * Invented reader comments for the demo stories (REQ-208, REQ-258): two to four core WordPress comments on each published demo
 * story, alternately approved and held for moderation, so both the public list and the moderation queue have something in them.
 * They follow the site's comment rules (Content\Comments\CommentPolicy): stories only, a guest name and an email, no account, no
 * avatar. The names say they are demo readers and every email uses the example.com domain. They are plain core comments, so they
 * show with the theme's comment list and with wpDiscuz alike. Each carries the sample marker as comment meta.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli\Sample;

use WhoIsWho\Core\Content\Comments\CommentPolicy;

/**
 * Sample comments.
 */
final class SampleComments {

	/**
	 * Invented commenters: a display name and the local part of an example.com address.
	 */
	private const PEOPLE = array(
		array( 'Amara (demo reader)', 'demo-amara' ),
		array( 'Kwame (demo reader)', 'demo-kwame' ),
		array( 'Zainab (demo reader)', 'demo-zainab' ),
		array( 'Tendai (demo reader)', 'demo-tendai' ),
		array( 'Lindiwe (demo reader)', 'demo-lindiwe' ),
		array( 'Yusuf (demo reader)', 'demo-yusuf' ),
	);

	/**
	 * Invented comment texts.
	 */
	private const TEXTS = array(
		'Thank you for this story. It is good to read about work like this from our region.',
		'I would like to know more about how this started. Will there be a follow-up?',
		'Inspiring. Sharing this with my team today.',
		'A clear and fair piece. The quote near the end says a lot.',
		'Great to see this recognised. More stories like this, please.',
		'Interesting read. I had not heard of this before.',
	);

	/**
	 * Make the comments for a batch of stories.
	 *
	 * @param array<string, mixed> $data    The dataset.
	 * @param int                  $offset  First story of the batch.
	 * @param int                  $limit   Stories in the batch.
	 * @param array<string, int>   $created Counts of what was made, by kind (added to).
	 */
	public static function make( array $data, int $offset, int $limit, array &$created ): void {
		foreach ( array_slice( (array) $data['stories'], $offset, $limit ) as $row ) {
			$slug  = (string) $row['slug'];
			$story = SampleMarker::find_post( 'story:' . $slug, CommentPolicy::STORY_TYPE );
			if ( null === $story || 'publish' !== get_post_status( $story ) ) {
				continue;
			}
			$count = self::count_for( $slug );
			for ( $n = 0; $n < $count; $n++ ) {
				$key = 'comment:' . $slug . ':' . $n;
				if ( null !== SampleMarker::find_comment( $key ) ) {
					continue;
				}
				$id = self::insert( $story, $slug, $n );
				if ( $id > 0 ) {
					SampleMarker::mark_comment( $id, $key );
					$created['comments'] = ( $created['comments'] ?? 0 ) + 1;
				}
			}
		}
	}

	/**
	 * How many comments a story gets: two to four, the same on every run.
	 *
	 * @param string $slug Story slug.
	 */
	public static function count_for( string $slug ): int {
		return 2 + (int) ( crc32( $slug ) % 3 );
	}

	/**
	 * Insert one comment.
	 *
	 * @param int    $story Story post id.
	 * @param string $slug  Story slug.
	 * @param int    $n     Position on the story.
	 * @return int Comment id, or 0.
	 */
	private static function insert( int $story, string $slug, int $n ): int {
		$pick   = (int) ( ( crc32( $slug ) + $n ) % count( self::PEOPLE ) );
		$person = self::PEOPLE[ $pick ];
		$when   = min( time() - HOUR_IN_SECONDS, (int) get_post_time( 'U', true, $story ) + ( $n + 1 ) * 3 * HOUR_IN_SECONDS );
		$id     = wp_insert_comment(
			array(
				'comment_post_ID'      => $story,
				'comment_author'       => $person[0],
				'comment_author_email' => $person[1] . '@example.com',
				'comment_author_url'   => '',
				'comment_author_IP'    => '',
				'comment_agent'        => '',
				'comment_content'      => self::TEXTS[ ( $pick + $n ) % count( self::TEXTS ) ],
				'comment_type'         => 'comment',
				'comment_parent'       => 0,
				'user_id'              => 0,
				// Alternately approved by a moderator and still waiting, as the policy holds every guest comment first.
				'comment_approved'     => 0 === $n % 2 ? 1 : 0,
				'comment_date'         => wp_date( 'Y-m-d H:i:s', $when ),
				'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $when ),
			)
		);
		return false === $id ? 0 : (int) $id;
	}
}
