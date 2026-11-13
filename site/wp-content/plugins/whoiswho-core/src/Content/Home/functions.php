<?php
/**
 * The theme-facing helper of the home hero: the editor's choice of hero clips and posters. The theme calls this one function
 * and never reads an option or an attachment itself (issue 799, REQ-001, REQ-041).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_home_hero_overrides' ) ) {
	/**
	 * The slots (1 to 4) an editor has filled with a valid clip and poster. Other slots are absent: the theme uses its bundled clip.
	 * Returns nothing if the service cannot answer, so the hero is never blank.
	 *
	 * @return array<int, array{video: string, webm: string, poster: string, sets: array<string, string>, width: int, height: int, bytes: int}>
	 */
	function wiw_home_hero_overrides(): array {
		try {
			return ( new \WhoIsWho\Core\Content\Home\HeroVideos( \WhoIsWho\Core\Platform\Audit\AuditLogger::for_site() ) )->resolved();
		} catch ( \Throwable $e ) {
			unset( $e );
			return array();
		}
	}
}
