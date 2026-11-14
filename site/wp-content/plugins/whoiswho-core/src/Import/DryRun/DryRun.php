<?php
/**
 * The import dry run (REQ-237, REQ-141, REQ-207): validates every record of a bundle and reports, per record, the result code the
 * apply step would give, a field-level diff and the flags (probable duplicate, free-text scan, unmapped term), without writing to
 * the profile, state, version, provenance or results tables. Only the batch row is touched: it gets the status dry_run and a
 * summary (file hash and counts by code), so Confirm applies exactly the file that was previewed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\DryRun;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Import\Apply\BatchLock;
use WhoIsWho\Core\Import\Apply\ProfileApplier;
use WhoIsWho\Core\Import\Apply\RecordMap;
use WhoIsWho\Core\Import\BatchRepository;
use WhoIsWho\Core\Import\BundleReader;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Import\RecordValidator;
use WhoIsWho\Core\Import\Scan\FreeTextScanner;

/**
 * Dry run.
 */
final class DryRun {

	public const STATE_PREFIX = 'wiw_import_dry_';

	/**
	 * Records kept for the screen by a stepwise dry run.
	 */
	public const MAX_ROWS = 200;

	/**
	 * Unknown terms kept for the mapping step by a stepwise dry run.
	 */
	public const MAX_MAPPING = 3000;

	/**
	 * Batches.
	 *
	 * @var BatchRepository
	 */
	private BatchRepository $batches;

	/**
	 * Validator.
	 *
	 * @var RecordValidator
	 */
	private RecordValidator $validator;

	/**
	 * Suppression list.
	 *
	 * @var Suppression
	 */
	private Suppression $suppression;

	/**
	 * Record map.
	 *
	 * @var RecordMap
	 */
	private RecordMap $map;

	/**
	 * Term mapper.
	 *
	 * @var TermMapper
	 */
	private TermMapper $terms;

	/**
	 * Constructor.
	 *
	 * @param Suppression          $suppression The live suppression list.
	 * @param BatchRepository|null $batches     Batches.
	 * @param RecordValidator|null $validator   Validator.
	 */
	public function __construct( Suppression $suppression, ?BatchRepository $batches = null, ?RecordValidator $validator = null ) {
		$this->suppression = $suppression;
		$this->batches     = $batches ?? new BatchRepository();
		$this->validator   = $validator ?? new RecordValidator();
		$this->map         = new RecordMap();
		$this->terms       = new TermMapper();
	}

	/**
	 * Preview a registered batch.
	 *
	 * @param BundleReader $bundle   The opened bundle.
	 * @param string       $batch_id Batch id (registered).
	 * @return array{file_hash: string, counts: array<string, int>, records: array<int, array<string, mixed>>}
	 * @throws \RuntimeException For an unknown batch or a bundle that is not the registered file.
	 */
	public function run( BundleReader $bundle, string $batch_id ): array {
		$batch = $this->batches->get( $batch_id );
		if ( null === $batch ) {
			throw new \RuntimeException( 'Unknown batch.' );
		}
		if ( $bundle->file_hash() !== (string) $batch['file_hash'] ) {
			throw new \RuntimeException( 'The file is not the one registered for this batch.' );
		}
		$records = array();
		$counts  = array();
		foreach ( $bundle->lines() as $n => $line ) {
			$entry                    = $this->preview_line( $n, $line );
			$records[]                = $entry;
			$counts[ $entry['code'] ] = ( $counts[ $entry['code'] ] ?? 0 ) + 1;
		}
		ksort( $counts );
		if ( in_array( (string) $batch['status'], array( 'checked', 'dry_run' ), true ) ) {
			$this->batches->record_dry_run( $batch_id, $bundle->file_hash(), $counts );
		}
		return array(
			'file_hash' => $bundle->file_hash(),
			'counts'    => $counts,
			'records'   => $records,
		);
	}

	/**
	 * Preview the next part of a registered batch within a time budget, for bundles too large for one request (REQ-237). The
	 * cursor, the counts by code, the first MAX_ROWS records (values shortened) and the unknown terms are kept in one option per
	 * batch that is not autoloaded, so memory stays small whatever the bundle size. When the last line is read, the batch gets
	 * the status dry_run as run() gives it.
	 *
	 * @param BundleReader  $bundle   The opened bundle.
	 * @param string        $batch_id Batch id (registered).
	 * @param callable|null $stop     Returns true when the time budget is spent; checked before each record.
	 * @return array{done: bool, cursor: int, total: int, counts: array<string, int>, busy: bool}
	 * @throws \RuntimeException For an unknown batch or a bundle that is not the registered file.
	 */
	public function step( BundleReader $bundle, string $batch_id, ?callable $stop = null ): array {
		$batch = $this->batches->get( $batch_id );
		if ( null === $batch ) {
			throw new \RuntimeException( 'Unknown batch.' );
		}
		if ( $bundle->file_hash() !== (string) $batch['file_hash'] ) {
			throw new \RuntimeException( 'The file is not the one registered for this batch.' );
		}
		$total = (int) $batch['record_count'];
		$state = self::stored( $batch_id );
		if ( ! in_array( (string) $batch['status'], array( 'checked', 'dry_run' ), true ) || ( 'dry_run' === $batch['status'] && $state['cursor'] >= $total ) ) {
			return $this->progress( $state, $total, true );
		}
		if ( ! BatchLock::acquire( $batch_id ) ) {
			return $this->progress( $state, $total, false, true );
		}
		try {
			$state = self::stored( $batch_id );
			foreach ( $bundle->lines() as $n => $line ) {
				if ( $n <= $state['cursor'] ) {
					continue;
				}
				if ( null !== $stop && $stop() ) {
					break;
				}
				$entry                             = $this->preview_line( $n, $line );
				$state['counts'][ $entry['code'] ] = ( $state['counts'][ $entry['code'] ] ?? 0 ) + 1;
				foreach ( (array) $entry['unmapped'] as $term ) {
					if ( count( $state['mapping'] ) < self::MAX_MAPPING ) {
						$state['mapping'][ $term['field'] . '|' . $term['value'] ] = $term;
					}
				}
				if ( count( $state['rows'] ) < self::MAX_ROWS ) {
					$state['rows'][] = self::compact( $entry );
				}
				$state['cursor'] = $n;
			}
			ksort( $state['counts'] );
			update_option( self::state_key( $batch_id ), $state, false );
			$done = $state['cursor'] >= $total;
			if ( $done ) {
				$this->batches->record_dry_run( $batch_id, $bundle->file_hash(), $state['counts'] );
			}
			return $this->progress( $state, $total, $done );
		} finally {
			BatchLock::release( $batch_id );
		}//end try
	}

	/**
	 * The stored stepwise dry run of a batch.
	 *
	 * @param string $batch_id Batch id.
	 * @return array{cursor: int, counts: array<string, int>, rows: array<int, array<string, mixed>>, mapping: array<string, array{field: string, value: string}>}
	 */
	public static function stored( string $batch_id ): array {
		$state = get_option( self::state_key( $batch_id ), array() );
		$state = is_array( $state ) ? $state : array();
		return array(
			'cursor'  => (int) ( $state['cursor'] ?? 0 ),
			'counts'  => is_array( $state['counts'] ?? null ) ? $state['counts'] : array(),
			'rows'    => is_array( $state['rows'] ?? null ) ? $state['rows'] : array(),
			'mapping' => is_array( $state['mapping'] ?? null ) ? $state['mapping'] : array(),
		);
	}

	/**
	 * Forget the stored dry run of a batch (after a mapping is saved, so it runs again; or when the batch is finished).
	 *
	 * @param string $batch_id Batch id.
	 */
	public static function reset( string $batch_id ): void {
		delete_option( self::state_key( $batch_id ) );
	}

	/**
	 * The option holding a batch's stepwise dry run.
	 *
	 * @param string $batch_id Batch id.
	 */
	private static function state_key( string $batch_id ): string {
		return self::STATE_PREFIX . substr( md5( $batch_id ), 0, 20 );
	}

	/**
	 * Progress of a stepwise dry run.
	 *
	 * @param array<string, mixed> $state State.
	 * @param int                  $total Records in the bundle.
	 * @param bool                 $done  Finished.
	 * @param bool                 $busy  Another worker holds the batch.
	 * @return array{done: bool, cursor: int, total: int, counts: array<string, int>, busy: bool}
	 */
	private function progress( array $state, int $total, bool $done, bool $busy = false ): array {
		return array(
			'done'   => $done,
			'cursor' => (int) $state['cursor'],
			'total'  => $total,
			'counts' => (array) $state['counts'],
			'busy'   => $busy,
		);
	}

	/**
	 * An entry with every diff value cut to a short text, for storage.
	 *
	 * @param array<string, mixed> $entry Entry.
	 * @return array<string, mixed>
	 */
	private static function compact( array $entry ): array {
		$short = static function ( $value ) {
			if ( null === $value ) {
				return null;
			}
			$text = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
			return function_exists( 'mb_substr' ) && mb_strlen( $text ) > 200 ? mb_substr( $text, 0, 200 ) . '...' : $text;
		};
		foreach ( (array) $entry['diff'] as $i => $change ) {
			$entry['diff'][ $i ]['old'] = $short( $change['old'] );
			$entry['diff'][ $i ]['new'] = $short( $change['new'] );
		}
		unset( $entry['unmapped'] );
		return $entry;
	}

	/**
	 * Preview one line.
	 *
	 * @param int    $n    Line number.
	 * @param string $line Record line.
	 * @return array{line: int, record_id: string, code: string, field: string, diff: array<int, array<string, mixed>>, flags: string[], unmapped: array<int, array{field: string, value: string}>}
	 */
	private function preview_line( int $n, string $line ): array {
		$valid = $this->validator->validate( $line );
		if ( ! $valid['ok'] ) {
			return array(
				'line'      => $n,
				'record_id' => '' === $valid['record_id'] ? 'line:' . $n : (string) $valid['record_id'],
				'code'      => (string) $valid['code'],
				'field'     => (string) $valid['field'],
				'diff'      => array(),
				'flags'     => array(),
				'unmapped'  => array(),
			);
		}
		$record = (array) $valid['record'];
		$fields = (array) $record['fields'];
		$flags  = array();
		if ( ! empty( $record['flags']['probable_duplicate'] ) ) {
			$flags[] = 'duplicate';
		}
		if ( array() !== ( new FreeTextScanner() )->scan( $fields ) ) {
			$flags[] = 'free_text_scan';
		}
		$unmapped = $this->terms->unmapped( $record );
		if ( array() !== $unmapped ) {
			$flags[] = 'unmapped_term';
		}
		if ( array() !== ( $valid['images_refused'] ?? array() ) ) {
			$flags[] = 'image_not_allowed';
		}
		$entry = array(
			'line'      => $n,
			'record_id' => (string) $record['record_id'],
			'code'      => '',
			'field'     => '',
			'diff'      => array(),
			'flags'     => $flags,
			'unmapped'  => $unmapped,
		);
		if ( null !== $this->suppression->refusal_for_import(
			array(
				'name'         => (string) ( $fields['name']['value'] ?? '' ),
				'entity_type'  => $record['entity_type'],
				'external_ids' => $record['external_ids'],
			)
		) ) {
			$entry['code'] = Suppression::REFUSAL_CODE;
			return $entry;
		}
		if ( array() !== $unmapped ) {
			$entry['code']  = 'SCHEMA';
			$entry['field'] = (string) $unmapped[0]['field'];
			return $entry;
		}
		return $this->classify( $entry, $record );
	}

	/**
	 * Work out the code and diff of a valid, unsuppressed, mapped record, reading only.
	 *
	 * @param array<string, mixed> $entry  Entry so far.
	 * @param array<string, mixed> $record Record.
	 * @return array<string, mixed>
	 */
	private function classify( array $entry, array $record ): array {
		$new        = ProfileApplier::new_values( $record );
		$profile_id = $this->map->profile_for( (string) $record['record_id'] );
		$row        = null === $profile_id || null === get_post( $profile_id ) ? null : ( new StateRepository() )->get( $profile_id );
		if ( null === $profile_id || null === get_post( $profile_id ) ) {
			$entry['code'] = ProfileApplier::CREATED;
			$entry['diff'] = self::diff( array(), $new );
			return $entry;
		}
		$hash = (string) preg_replace( '/^sha256:/', '', (string) $record['content_hash'] );
		if ( null !== $row && hash_equals( (string) $row['content_hash'], $hash ) ) {
			$entry['code'] = ProfileApplier::UNCHANGED;
			return $entry;
		}
		$type          = (string) ( $row['entity_type'] ?? $record['entity_type'] );
		$current       = ( new ProfileApplier( $this->suppression ) )->current_values( $profile_id, $type );
		$entry['diff'] = self::diff( $current, $new );
		$entry['code'] = null === $row || ProfileApplier::is_reviewed( $row ) ? ProfileApplier::FLAGGED_CHANGE : ProfileApplier::UPDATED;
		return $entry;
	}

	/**
	 * Field-level diff of two value maps. Pure.
	 *
	 * @param array<string, mixed> $old Current values.
	 * @param array<string, mixed> $incoming Incoming values.
	 * @return array<int, array{field: string, change: string, old: mixed, new: mixed}> Fields that are added, changed or removed.
	 */
	public static function diff( array $old, array $incoming ): array {
		$out  = array();
		$keys = array_unique( array_merge( array_keys( $old ), array_keys( $incoming ) ) );
		sort( $keys );
		foreach ( $keys as $key ) {
			$in_old = array_key_exists( $key, $old );
			$in_new = array_key_exists( $key, $incoming );
			if ( $in_old && $in_new && wp_json_encode( $old[ $key ] ) === wp_json_encode( $incoming[ $key ] ) ) {
				continue;
			}
			$out[] = array(
				'field'  => (string) $key,
				'change' => ! $in_old ? 'added' : ( ! $in_new ? 'removed' : 'changed' ),
				'old'    => $old[ $key ] ?? null,
				'new'    => $incoming[ $key ] ?? null,
			);
		}
		return $out;
	}
}
