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
	 * Preview one line.
	 *
	 * @param int    $n    Line number.
	 * @param string $line Record line.
	 * @return array{line: int, record_id: string, code: string, field: string, diff: array<int, array<string, mixed>>, flags: string[]}
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
		$entry = array(
			'line'      => $n,
			'record_id' => (string) $record['record_id'],
			'code'      => '',
			'field'     => '',
			'diff'      => array(),
			'flags'     => $flags,
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
	 * @param array<string, mixed> $new Incoming values.
	 * @return array<int, array{field: string, change: string, old: mixed, new: mixed}> Fields that are added, changed or removed.
	 */
	public static function diff( array $old, array $new ): array {
		$out  = array();
		$keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );
		sort( $keys );
		foreach ( $keys as $key ) {
			$in_old = array_key_exists( $key, $old );
			$in_new = array_key_exists( $key, $new );
			if ( $in_old && $in_new && wp_json_encode( $old[ $key ] ) === wp_json_encode( $new[ $key ] ) ) {
				continue;
			}
			$out[] = array(
				'field'  => (string) $key,
				'change' => ! $in_old ? 'added' : ( ! $in_new ? 'removed' : 'changed' ),
				'old'    => $old[ $key ] ?? null,
				'new'    => $new[ $key ] ?? null,
			);
		}
		return $out;
	}
}
