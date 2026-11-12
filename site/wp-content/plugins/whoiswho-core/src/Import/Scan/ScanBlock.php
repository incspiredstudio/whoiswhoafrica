<?php
/**
 * The scan block on an imported profile (REQ-141). A record whose free text failed the scan keeps its flags here (field and
 * category only, never the matched text) and cannot be approved until a reviewer clears it. Clearing is a reviewer action and
 * writes an audit row. The review service reads is_blocked() before it approves.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Scan;

use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Audit\WpdbConnection;
use WhoIsWho\Core\Review\StateException;

/**
 * Scan block.
 */
final class ScanBlock {

	public const META_KEY = '_wiw_scan_flags';

	public const CAPABILITY = 'wiw_review_records';

	/**
	 * Audit logger, or null to log as the acting reviewer.
	 *
	 * @var AuditLogger|null
	 */
	private ?AuditLogger $audit;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger|null $audit Logger.
	 */
	public function __construct( ?AuditLogger $audit = null ) {
		$this->audit = $audit;
	}

	/**
	 * Whether a profile is blocked by the scan.
	 *
	 * @param int $profile_id Profile post id.
	 */
	public static function is_blocked( int $profile_id ): bool {
		return array() !== self::flags( $profile_id );
	}

	/**
	 * The flags on a profile, for the review item.
	 *
	 * @param int $profile_id Profile post id.
	 * @return array<int, array{field: string, category: string}>
	 */
	public static function flags( int $profile_id ): array {
		$raw   = (string) get_post_meta( $profile_id, self::META_KEY, true );
		$flags = '' === $raw ? array() : json_decode( $raw, true );
		return is_array( $flags ) ? array_values( $flags ) : array();
	}

	/**
	 * Set the flags of a profile, or remove them when the scan found nothing.
	 *
	 * @param int                                                $profile_id Profile post id.
	 * @param array<int, array{field: string, category: string}> $flags      Scan result.
	 */
	public function set( int $profile_id, array $flags ): void {
		if ( array() === $flags ) {
			delete_post_meta( $profile_id, self::META_KEY );
			return;
		}
		update_post_meta( $profile_id, self::META_KEY, wp_slash( (string) wp_json_encode( array_values( $flags ) ) ) );
	}

	/**
	 * A reviewer clears the block. Writes an audit row naming the fields and categories that were flagged.
	 *
	 * @param int    $profile_id Profile post id.
	 * @param int    $actor_id   Reviewer.
	 * @param string $reason     Why the flags do not apply; required.
	 * @throws StateException When the actor lacks the capability, the reason is empty or nothing is flagged.
	 */
	public function clear( int $profile_id, int $actor_id, string $reason ): void {
		if ( ! user_can( $actor_id, self::CAPABILITY ) ) {
			throw StateException::refuse( 'You may not clear this block.' );
		}
		if ( '' === trim( $reason ) ) {
			throw StateException::refuse( 'A reason is required.' );
		}
		$flags = self::flags( $profile_id );
		if ( array() === $flags ) {
			throw StateException::refuse( 'Nothing is flagged on this profile.' );
		}
		$audit = $this->audit ?? new AuditLogger( new WpdbConnection(), null, static fn (): int => $actor_id );
		$audit->log(
			'import.scan_cleared',
			array(
				'type' => 'profile',
				'id'   => $profile_id,
			),
			array( 'flags' => $flags ),
			array( 'flags' => array() ),
			$reason
		);
		delete_post_meta( $profile_id, self::META_KEY );
	}
}
