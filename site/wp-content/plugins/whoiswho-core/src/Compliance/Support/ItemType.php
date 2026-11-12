<?php
/**
 * The kinds of support item (REQ-089, REQ-270, REQ-271).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

/**
 * Item types.
 */
final class ItemType {

	public const ENQUIRY      = 'enquiry';
	public const CORRECTION   = 'correction';
	public const REMOVAL      = 'removal';
	public const REPORT       = 'report';
	public const COMPLAINT    = 'complaint';
	public const DATA_REQUEST = 'data_request';

	/**
	 * Every type.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::ENQUIRY, self::CORRECTION, self::REMOVAL, self::REPORT, self::COMPLAINT, self::DATA_REQUEST );
	}

	/**
	 * Whether a value is a type.
	 *
	 * @param string $type Value.
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}

	/**
	 * Which staff screen an item type lands on: Enquiries, Corrections, or Requests and reports (REQ-270, REQ-271).
	 *
	 * @param string $type Type.
	 */
	public static function screen( string $type ): string {
		switch ( $type ) {
			case self::CORRECTION:
				return 'corrections';
			case self::REMOVAL:
			case self::REPORT:
			case self::DATA_REQUEST:
				return 'requests';
			default:
				return 'enquiries';
		}
	}
}
