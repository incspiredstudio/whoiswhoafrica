<?php
/**
 * Pre-launch check of every upload path (REQ-127, M5-SEC-03): one type allowlist, one size limit, no other code reads uploaded
 * files, and the evidence storage rule is recorded as not yet testable because no evidence upload exists yet.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Security;

use PHPUnit\Framework\TestCase;
use WhoIsWho\Guard\Uploads\Uploads;

final class UploadPathsTest extends TestCase {

	private function source_files(): array {
		$root  = dirname( __DIR__, 2 ) . '/wp-content';
		$files = array();
		foreach ( array( 'plugins/whoiswho-core', 'themes/whoiswho', 'themes/whoiswho-child', 'mu-plugins/whoiswho-guard' ) as $dir ) {
			if ( ! is_dir( $root . '/' . $dir ) ) {
				continue;
			}
			$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/' . $dir, \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				if ( 'php' === $file->getExtension() ) {
					$files[] = str_replace( '\\', '/', $file->getPathname() );
				}
			}
		}
		return $files;
	}

	public function test_REQ_127_disallowed_type_refused_the_allowlists_hold_no_active_or_archive_types(): void {
		$all = array_merge( array_values( Uploads::IMAGE_TYPES ), Uploads::EVIDENCE_TYPES );
		foreach ( $all as $mime ) {
			$this->assertMatchesRegularExpression( '#^(image/(jpeg|png|webp|avif)|application/pdf)$#', $mime );
		}
		$this->assertSame( array( 'image/jpeg', 'image/png', 'application/pdf' ), Uploads::EVIDENCE_TYPES, 'evidence is limited to JPG, PNG and PDF' );
		$this->assertStringNotContainsString( 'svg', implode( ',', array_keys( Uploads::IMAGE_TYPES ) ), 'no SVG uploads' );
	}

	public function test_REQ_127_oversize_file_refused_one_limit_of_ten_megabytes(): void {
		$this->assertSame( 10 * 1024 * 1024, Uploads::MAX_BYTES );
	}

	public function test_REQ_127_no_code_outside_the_guard_reads_uploaded_files(): void {
		$offenders = array();
		foreach ( $this->source_files() as $path ) {
			if ( str_contains( $path, '/mu-plugins/whoiswho-guard/' ) ) {
				continue;
			}
			$code = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( 1 === preg_match( '/\$_FILES|move_uploaded_file|wp_handle_upload|media_handle_upload|wp_handle_sideload/', $code ) ) {
				$offenders[] = basename( $path );
			}
		}
		$this->assertSame( array(), $offenders, 'a new upload path must go through the guard and be added to this matrix: ' . implode( ', ', $offenders ) );
	}

	public function test_REQ_127_evidence_not_served_inline(): void {
		// No claim, correction or nomination form accepts a file yet (the guard only defines the evidence types), so there is no
		// private evidence store to inspect. When one is added, the test above fails first and this one must assert: stored
		// outside the web root, served only through an authenticated download with Content-Disposition: attachment and nosniff.
		$this->markTestSkipped( 'No evidence file upload exists yet (M5-SEC-03; see the follow-up issue).' );
	}
}
