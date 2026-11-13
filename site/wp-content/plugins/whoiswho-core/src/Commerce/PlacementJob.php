<?php
/**
 * The placement job and Sponsored label service (REQ-182, REQ-116, REQ-183). Once a day it brings each story's sponsor fields in
 * line with its placements: the label and slot are written while a placement is active (between its dates, not ended, deal not
 * cancelled) and cleared when it ends, expires or the deal is cancelled. Nothing is deleted from the placement tables. The only
 * writer is StoryFlags::commerce_write, which accepts stories only and never touches the Featured flag, a profile or a search
 * index, so a slot never reorders unsponsored stories or search.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce;

use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Placement job.
 */
final class PlacementJob {

	public const JOB = 'wiw_sponsored_placements';

	/**
	 * Placements.
	 *
	 * @var PlacementRepository
	 */
	private PlacementRepository $placements;

	/**
	 * Story flags: the one writer of the sponsor fields.
	 *
	 * @var StoryFlags
	 */
	private StoryFlags $flags;

	/**
	 * Constructor.
	 *
	 * @param PlacementRepository $placements Placements.
	 * @param StoryFlags          $flags      Story flags.
	 */
	public function __construct( PlacementRepository $placements, StoryFlags $flags ) {
		$this->placements = $placements;
		$this->flags      = $flags;
	}

	/**
	 * The job for this site.
	 */
	public static function for_site(): self {
		return new self( new PlacementRepository(), new StoryFlags( AuditLogger::for_site() ) );
	}

	/**
	 * Register the hourly job.
	 */
	public static function register(): void {
		JobRunner::for_site()->recurring(
			self::JOB,
			3600,
			static function (): void {
				self::for_site()->run( gmdate( 'Y-m-d' ) );
			}
		);
	}

	/**
	 * Bring every placed story in line with its placements.
	 *
	 * @param string $today Date as YYYY-MM-DD (UTC).
	 * @return array{applied: int, cleared: int}
	 */
	public function run( string $today ): array {
		$active  = array();
		$stories = array();
		foreach ( $this->placements->all_with_deal_state() as $row ) {
			$story             = (int) $row['story_id'];
			$stories[ $story ] = true;
			if ( empty( $row['ended'] ) && empty( $row['deal_cancelled'] ) && (string) $row['starts_on'] <= $today && (string) $row['ends_on'] >= $today && ! isset( $active[ $story ] ) ) {
				$active[ $story ] = $row;
			}
		}
		$applied = 0;
		$cleared = 0;
		foreach ( array_keys( $stories ) as $story ) {
			if ( 'post' !== get_post_type( $story ) ) {
				continue;
			}
			if ( isset( $active[ $story ] ) ) {
				$row = $active[ $story ];
				$this->flags->commerce_write( $story, (string) $row['sponsor_name'], (string) $row['slot'], (string) $row['starts_on'], (string) $row['ends_on'] );
				++$applied;
			} elseif ( '' !== (string) get_post_meta( $story, 'wiw_sponsor_name', true ) ) {
				$this->flags->commerce_write( $story, '', '', '', '' );
				++$cleared;
			}
		}
		return array(
			'applied' => $applied,
			'cleared' => $cleared,
		);
	}
}
