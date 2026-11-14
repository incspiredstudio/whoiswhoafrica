<?php
/**
 * Puts the plugin's true-colour GD editor in WordPress's list of image editors (REQ-042). It names the class as a string, so the
 * editor class (which extends WordPress's GD editor) is loaded only when WordPress has loaded its own editors.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Media;

/**
 * Image editor list.
 */
final class ImageEditors {

	/**
	 * Hook the list.
	 */
	public static function register(): void {
		add_filter( 'wp_image_editors', array( self::class, 'prepend' ) );
	}

	/**
	 * Put the true-colour editor just before WordPress's GD editor (Imagick, when present, keeps its place).
	 *
	 * @param mixed $editors Editor class names.
	 * @return mixed
	 */
	public static function prepend( $editors ) {
		if ( ! is_array( $editors ) || in_array( TrueColourGdEditor::class, $editors, true ) ) {
			return $editors;
		}
		$at = array_search( 'WP_Image_Editor_GD', $editors, true );
		if ( false === $at ) {
			return $editors;
		}
		array_splice( $editors, (int) $at, 0, array( TrueColourGdEditor::class ) );
		return $editors;
	}
}
