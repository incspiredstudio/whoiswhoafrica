<?php
/**
 * Internal notes never reach a public surface (REQ-187): no public read model (a Public*.php file) and no theme file uses the
 * notes service or names the wiw_notes table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch\Rules;

use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;

/**
 * Notes stay inside.
 */
final class PublicSurfacesNeverReadNotes implements Rule {

	/**
	 * Whether every file in the set is a public surface (true for a theme folder), or only the Public* read models.
	 *
	 * @var bool
	 */
	private bool $all_files;

	/**
	 * Constructor.
	 *
	 * @param bool $all_files Treat every file as public.
	 */
	public function __construct( bool $all_files = false ) {
		$this->all_files = $all_files;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param SourceSet $sources Sources.
	 */
	public function violations( SourceSet $sources ): array {
		$bad = array();
		foreach ( $sources->paths() as $path ) {
			if ( ! $this->all_files && 1 !== preg_match( '#(^|/)Public[A-Za-z]*\.php$#', $path ) ) {
				continue;
			}
			foreach ( $sources->names( $path ) as $name ) {
				if ( 1 === preg_match( '/(^|\\\\)Notes(\\\\|$)|NotesService$/', $name ) ) {
					$bad[] = "$path uses the notes service ($name)";
				}
			}
			foreach ( $sources->strings( $path ) as $text ) {
				if ( false !== strpos( $text, 'wiw_notes' ) ) {
					$bad[] = "$path names the wiw_notes table";
				}
			}
		}
		return array_values( array_unique( $bad ) );
	}
}
