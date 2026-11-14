<?php
/**
 * The GD image editor, with one change (REQ-042): a palette (indexed-colour) image is turned into true colour as it loads. GD
 * cannot write a palette image as WebP ("Palette image not supported by webp"), and WordPress keeps a resized copy of a palette
 * PNG in palette form, so without this a palette PNG gets no WebP sizes and logs a warning on every upload or sideload.
 *
 * Named only by ImageEditors in the wp_image_editors filter, so it loads after WordPress has loaded its own GD editor class.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Media;

/**
 * GD editor that loads every image as true colour.
 */
class TrueColourGdEditor extends \WP_Image_Editor_GD {

	/**
	 * Load the image, then make a palette image true colour, keeping its transparency.
	 *
	 * @return true|\WP_Error
	 */
	public function load() {
		$loaded = parent::load();
		if ( true === $loaded && false !== $this->image && ! imageistruecolor( $this->image ) ) {
			imagepalettetotruecolor( $this->image );
			imagealphablending( $this->image, false );
			imagesavealpha( $this->image, true );
		}
		return $loaded;
	}
}
