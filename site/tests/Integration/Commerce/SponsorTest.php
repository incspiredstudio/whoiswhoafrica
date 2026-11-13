<?php
/**
 * Sponsor records for the Supported by disclosure (REQ-186, REQ-199, REQ-183). Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Commerce;

use WhoIsWho\Core\Commerce\SponsorPostType;
use WhoIsWho\Core\Content\PublicSponsor;
use WhoIsWho\Tests\TestCase;

final class SponsorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		wp_set_current_user( 0 );
		SponsorPostType::register();
		foreach ( get_posts(
			array(
				'post_type'   => PublicSponsor::TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		) as $leftover ) {
			wp_delete_post( (int) $leftover, true );
		}
	}

	/**
	 * Create a sponsor.
	 *
	 * @param string $title  Name.
	 * @param string $status Status.
	 * @param string $link   Link.
	 * @param string $ref    Attachment ref.
	 */
	private function sponsor( string $title, string $status = 'publish', string $link = 'https://sponsor.example/', string $ref = 'page:5' ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => PublicSponsor::TYPE,
				'post_title'  => $title,
				'post_status' => $status,
			)
		);
		$this->assertIsInt( $id );
		update_post_meta( $id, PublicSponsor::META_LINK, $link );
		add_post_meta( $id, PublicSponsor::META_ATT, $ref );
		return $id;
	}

	public function test_REQ_186_sponsor_record_has_name_logo_link_only(): void {
		$type = get_post_type_object( PublicSponsor::TYPE );
		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertTrue( post_type_supports( PublicSponsor::TYPE, 'title' ) );
		$this->assertTrue( post_type_supports( PublicSponsor::TYPE, 'thumbnail' ) );
		foreach ( array( 'editor', 'excerpt', 'custom-fields', 'comments' ) as $feature ) {
			$this->assertFalse( post_type_supports( PublicSponsor::TYPE, $feature ), $feature );
		}
		$keys = array_keys( get_registered_meta_keys( 'post', PublicSponsor::TYPE ) );
		sort( $keys );
		$this->assertSame( array( PublicSponsor::META_ATT, PublicSponsor::META_LINK ), $keys );
		$this->sponsor( 'Acme' );
		$cards = PublicSponsor::for_subject( 'page', 5 );
		$this->assertCount( 1, $cards );
		$this->assertSame( array( 'name', 'logo_url', 'link' ), array_keys( $cards[0] ) );
		$this->assertSame( 'Acme', $cards[0]['name'] );
		$this->assertSame( 'https://sponsor.example/', $cards[0]['link'] );
	}

	public function test_REQ_186_only_published_sponsors_attached_to_the_subject_show(): void {
		$this->sponsor( 'Shown' );
		$this->sponsor( 'Draft one', 'draft' );
		$this->sponsor( 'Elsewhere', 'publish', 'https://x.example/', 'award_category:9' );
		$this->assertSame( array( 'Shown' ), array_column( PublicSponsor::for_subject( 'page', 5 ), 'name' ) );
		$this->assertSame( array( 'Elsewhere' ), array_column( PublicSponsor::for_subject( 'award_category', 9 ), 'name' ) );
		$this->assertSame( '', SponsorPostType::sanitize_ref( 'profile:3' ), 'Only a page or an award category can be attached.' );
		$this->assertNotSame( '', PublicSponsor::disclosure() );
	}

	public function test_REQ_199_awards_reads_sponsors_without_referencing_commerce(): void {
		$root = dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/src';
		$this->assertFileExists( $root . '/Content/PublicSponsor.php' );
		$this->assertStringNotContainsString( 'Commerce', (string) file_get_contents( $root . '/Content/PublicSponsor.php' ), 'The read model does not name Commerce.' );
		$found = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/Awards', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			if ( $file instanceof \SplFileInfo && 'php' === $file->getExtension() && str_contains( (string) file_get_contents( $file->getPathname() ), 'Core\\Commerce' ) ) {
				$found[] = $file->getFilename();
			}
		}
		$this->assertSame( array(), $found, 'No Awards class references Commerce.' );
	}
}
