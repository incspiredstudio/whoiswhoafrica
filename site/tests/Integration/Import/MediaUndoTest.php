<?php
/**
 * The media part of an import undo (REQ-237, #810): an image the import reused for a profile of another batch (same checksum)
 * stays; only images no profile outside the undone batch uses are deleted; and the work can be done in steps under a time
 * budget. Needs the WordPress test suite and the real tables; cleans up after itself.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Import;

use WhoIsWho\Core\Directory\ImageMeta;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\ResultRepository;
use WhoIsWho\Core\Import\Undo\MediaUndo;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Tests\TestCase;

final class MediaUndoTest extends TestCase {

	/**
	 * Posts to remove.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
		( new Runner( dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/migrations', false, false ) )->run();
		if ( ! post_type_exists( ProfileType::POST_TYPE ) ) {
			( new ProfileType() )->register();
		}
	}

	protected function tearDown(): void {
		global $wpdb;
		foreach ( $this->posts as $id ) {
			wp_delete_post( $id, true );
		}
		$this->posts = array();
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ResultRepository::table() . ' WHERE batch_id LIKE %s', 'B-mu-%' ) ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- fixed table name.
		delete_option( MediaUndo::OPTION_PREFIX . substr( md5( 'B-mu-1' ), 0, 20 ) );
		parent::tearDown();
	}

	private function profile( string $name ): int {
		$id            = (int) wp_insert_post(
			array(
				'post_type'  => ProfileType::POST_TYPE,
				'post_title' => $name,
			)
		);
		$this->posts[] = $id;
		return $id;
	}

	private function image( int $owner, string $batch ): int {
		$id            = (int) wp_insert_attachment(
			array(
				'post_title'     => 'img',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			),
			false,
			$owner
		);
		$this->posts[] = $id;
		update_post_meta( $id, ImageMeta::BATCH, $batch );
		return $id;
	}

	public function test_REQ_237_undo_keeps_an_image_a_later_batch_reuses(): void {
		$first  = $this->profile( 'First Batch Org Qwv' );
		$later  = $this->profile( 'Later Batch Org Qwv' );
		$shared = $this->image( $first, 'B-mu-1' );
		$own    = $this->image( $first, 'B-mu-1' );
		( new ResultRepository() )->record( 'B-mu-1', 'rec_first', ProfileApplier::CREATED, $first );
		( new ResultRepository() )->record( 'B-mu-2', 'rec_later', ProfileApplier::CREATED, $later );
		update_post_meta( $first, PublicProfile::IMAGE_META, $shared );
		update_post_meta( $later, PublicProfile::IMAGE_META, $shared );
		$other = $this->profile( 'Only Own Image Qwv' );
		( new ResultRepository() )->record( 'B-mu-1', 'rec_other', ProfileApplier::CREATED, $other );
		update_post_meta( $other, PublicProfile::IMAGE_META, $own );

		$removed = ( new MediaUndo() )->remove( 'B-mu-1' );

		$this->assertSame( 1, $removed, 'only the image no other batch uses is deleted' );
		$this->assertNotNull( get_post( $shared ), 'the image the later batch re-uses stays' );
		$this->assertNull( get_post( $own ) );
		$this->assertSame( $shared, (int) get_post_meta( $later, PublicProfile::IMAGE_META, true ), 'the later profile keeps its image' );
		$this->assertSame( $later, (int) wp_get_post_parent_id( $shared ), 'it now belongs to the profile that uses it' );
		$this->assertSame( '', (string) get_post_meta( $shared, ImageMeta::BATCH, true ), 'and is no longer counted as the undone batch\'s' );
		$this->assertSame( '', (string) get_post_meta( $first, PublicProfile::IMAGE_META, true ), 'the undone profile lets go of it' );
	}

	public function test_REQ_237_media_undo_runs_in_steps_under_a_budget(): void {
		$profile = $this->profile( 'Stepped Media Qwv' );
		( new ResultRepository() )->record( 'B-mu-1', 'rec_step', ProfileApplier::CREATED, $profile );
		$a = $this->image( $profile, 'B-mu-1' );
		$b = $this->image( $profile, 'B-mu-1' );

		$undo  = new MediaUndo();
		$calls = 0;
		$first = $undo->step(
			'B-mu-1',
			static function () use ( &$calls ): bool {
				++$calls;
				return $calls > 1;
			}
		);
		$this->assertFalse( $first['done'], 'stops when the budget is spent' );
		$this->assertSame( 1, $first['removed'] );
		$second = $undo->step( 'B-mu-1' );
		$this->assertTrue( $second['done'] );
		$this->assertSame( 1, $second['removed'] );
		$this->assertNull( get_post( $a ) );
		$this->assertNull( get_post( $b ) );
	}
}
