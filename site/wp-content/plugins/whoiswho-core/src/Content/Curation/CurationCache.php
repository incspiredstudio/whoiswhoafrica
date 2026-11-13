<?php
/**
 * Keeps the curated home blocks fresh (REQ-257). Random mode is fixed for a day: the engines seed the shuffle with the date and
 * cache the candidate list. A daily job drops yesterday's lists and warms today's for the blocks set to Random, and saving the
 * curation settings drops every cached list and purges the Home and Stories pages so the change shows at once.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Curation;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Jobs\JobRunner;

/**
 * Curation cache.
 */
final class CurationCache {

	public const JOB      = 'curation.refresh';
	public const INTERVAL = 86400;

	/**
	 * Hook the purge on save and the daily job.
	 */
	public static function register(): void {
		add_action(
			'wiw_curation_changed',
			static function (): void {
				self::purge();
			}
		);
		JobRunner::for_site()->recurring(
			self::JOB,
			self::INTERVAL,
			static function (): void {
				self::refresh();
			}
		);
	}

	/**
	 * Drop every cached candidate list and tell the page cache that Home and Stories changed.
	 */
	public static function purge(): void {
		ProfileCuration::flush_cache();
		do_action( 'wiw_purge_urls', array( home_url( '/' ), home_url( '/stories/' ) ), false );
	}

	/**
	 * Daily refresh: forget yesterday's seed, then build today's list for each block that uses Random (as the mode or as the
	 * fill), so the first visitor is served from cache. Purges the Home and Stories pages because the selection changed.
	 *
	 * @param CurationSettings|null $settings Settings (the site's by default).
	 */
	public static function refresh( ?CurationSettings $settings = null ): void {
		$settings = $settings ?? new CurationSettings( AuditLogger::for_site() );
		ProfileCuration::flush_cache();
		foreach ( CurationSettings::BLOCKS as $block ) {
			$setting = $settings->get( $block );
			if ( 'random' !== $setting['mode'] && ! ( 'pinned_then_fill' === $setting['mode'] && 'random' === $setting['fill'] ) ) {
				continue;
			}
			if ( CurationSettings::ON_THE_RECORD === $block ) {
				( new ProfileCuration( $settings ) )->items( $block );
			} else {
				( new StoryCuration( $settings ) )->items( $block );
			}
		}
		do_action( 'wiw_purge_urls', array( home_url( '/' ), home_url( '/stories/' ) ), false );
	}
}
