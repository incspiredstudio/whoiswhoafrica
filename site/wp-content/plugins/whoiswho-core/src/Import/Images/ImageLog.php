<?php
/**
 * What happened to the images of one batch (contract 1.1 section 10): how many were imported and how many were refused with
 * IMAGE_NOT_ALLOWED, by reason, with the record and image ids of the first refusals for staff. Ids and counts only, never a
 * value. Kept in one option per batch that is not autoloaded.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Images;

/**
 * Image log.
 */
final class ImageLog {

	public const PREFIX = 'wiw_import_images_';

	/**
	 * How many refused image ids are kept for the screen.
	 */
	public const KEEP = 200;

	/**
	 * Add the image outcome of one record.
	 *
	 * @param string                                                                             $batch_id  Batch.
	 * @param string                                                                             $record_id Record.
	 * @param array{imported: int, refused: array<int, array{image_id: string, reason: string}>} $outcome   Outcome.
	 */
	public static function add( string $batch_id, string $record_id, array $outcome ): void {
		$log              = self::get( $batch_id );
		$log['imported'] += $outcome['imported'];
		foreach ( $outcome['refused'] as $refusal ) {
			++$log['refused'];
			$log['reasons'][ $refusal['reason'] ] = ( $log['reasons'][ $refusal['reason'] ] ?? 0 ) + 1;
			if ( count( $log['examples'] ) < self::KEEP ) {
				$log['examples'][] = array(
					'record_id' => $record_id,
					'image_id'  => $refusal['image_id'],
					'reason'    => $refusal['reason'],
				);
			}
		}
		update_option( self::key( $batch_id ), $log, false );
	}

	/**
	 * The log of a batch.
	 *
	 * @param string $batch_id Batch.
	 * @return array{imported: int, refused: int, reasons: array<string, int>, examples: array<int, array{record_id: string, image_id: string, reason: string}>}
	 */
	public static function get( string $batch_id ): array {
		$log = get_option( self::key( $batch_id ), array() );
		$log = is_array( $log ) ? $log : array();
		return array(
			'imported' => (int) ( $log['imported'] ?? 0 ),
			'refused'  => (int) ( $log['refused'] ?? 0 ),
			'reasons'  => is_array( $log['reasons'] ?? null ) ? $log['reasons'] : array(),
			'examples' => is_array( $log['examples'] ?? null ) ? $log['examples'] : array(),
		);
	}

	/**
	 * Forget the log of a batch.
	 *
	 * @param string $batch_id Batch.
	 */
	public static function clear( string $batch_id ): void {
		delete_option( self::key( $batch_id ) );
	}

	/**
	 * The option name.
	 *
	 * @param string $batch_id Batch.
	 */
	private static function key( string $batch_id ): string {
		return self::PREFIX . substr( md5( $batch_id ), 0, 20 );
	}
}
