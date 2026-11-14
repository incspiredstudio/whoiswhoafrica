<?php
/**
 * Applies one validated record (REQ-237, REQ-161, REQ-221, REQ-207, REQ-242, contract section 12).
 *
 * The site decides. A suppressed record gets SUPPRESSED and nothing is written. A new record id gets CREATED: profile, fields,
 * provenance, source and batch, with the state set through the StateMachine. The same record id and hash gets UNCHANGED. A changed
 * hash on an unreviewed profile gets UPDATED: a new version, fields replaced, state re-set. A changed hash on a reviewed profile
 * gets FLAGGED_CHANGE: a pending version for the review queue, reviewed fields untouched.
 *
 * Each record is written in one transaction, so a refusal or a failure leaves no partial profile. Results carry only the record
 * id and a code.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import\Apply;

use WhoIsWho\Core\Compliance\Suppression\Suppression;
use WhoIsWho\Core\Directory\Fields\FieldAllowlist;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Directory\State\VersionRepository;
use WhoIsWho\Core\Import\EvidenceRepository;
use WhoIsWho\Core\Import\Images\ImageImporter;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Import\Scan\FreeTextScanner;
use WhoIsWho\Core\Import\Scan\ScanBlock;
use WhoIsWho\Core\Import\StateAssigner;
use WhoIsWho\Core\Platform\Db\Transaction;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Profile applier.
 */
final class ProfileApplier {

	public const CREATED        = 'CREATED';
	public const UNCHANGED      = 'UNCHANGED';
	public const UPDATED        = 'UPDATED';
	public const FLAGGED_CHANGE = 'FLAGGED_CHANGE';

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
	 * State assigner.
	 *
	 * @var StateAssigner
	 */
	private StateAssigner $assigner;

	/**
	 * Free-text scanner.
	 *
	 * @var FreeTextScanner
	 */
	private FreeTextScanner $scanner;

	/**
	 * Scan block.
	 *
	 * @var ScanBlock
	 */
	private ScanBlock $block;

	/**
	 * The bundle folder images are read from, or null.
	 *
	 * @var string|null
	 */
	private ?string $bundle_dir = null;

	/**
	 * Constructor.
	 *
	 * @param Suppression        $suppression The live suppression list.
	 * @param RecordMap|null     $map         Record map.
	 * @param TermMapper|null    $terms       Term mapper.
	 * @param StateAssigner|null $assigner    State assigner.
	 */
	public function __construct( Suppression $suppression, ?RecordMap $map = null, ?TermMapper $terms = null, ?StateAssigner $assigner = null ) {
		$this->suppression = $suppression;
		$this->map         = $map ?? new RecordMap();
		$this->terms       = $terms ?? new TermMapper();
		$this->assigner    = $assigner ?? new StateAssigner();
		$this->scanner     = new FreeTextScanner();
		$this->block       = new ScanBlock();
	}

	/**
	 * Apply one validated record.
	 *
	 * @param array<string, mixed> $record      A validated record, as arrays.
	 * @param string               $batch_id    Batch id.
	 * @param int                  $importer_id The staff member who confirmed the import.
	 * @param bool                 $import_all  The import-everything-as-not-public setting in force for the batch.
	 * @return array{code: string, profile_id: int|null, images?: array{imported: int, refused: array<int, array{image_id: string, reason: string}>}}
	 *         A result code (never a field value), the profile id when one is involved and, for a record with images, what
	 *         happened to them.
	 */
	public function apply( array $record, string $batch_id, int $importer_id, bool $import_all ): array {
		$fields = (array) $record['fields'];
		$name   = (string) ( $fields['name']['value'] ?? '' );
		if ( null !== $this->suppression->refusal_for_import(
			array(
				'name'         => $name,
				'entity_type'  => $record['entity_type'],
				'external_ids' => $record['external_ids'],
			)
		) ) {
			return $this->result( Suppression::REFUSAL_CODE, null );
		}
		try {
			$this->terms->assert_mapped( $record );
		} catch ( \RuntimeException $e ) {
			return $this->result( 'SCHEMA', null );
		}

		$record_id  = (string) $record['record_id'];
		$profile_id = $this->map->profile_for( $record_id );
		if ( null === $profile_id ) {
			$revived = $this->revive( $record, $record_id, $batch_id, $importer_id, $import_all );
			if ( null !== $revived ) {
				return $this->with_images( $revived, $record, $batch_id );
			}
		}
		if ( null === $profile_id || null === get_post( $profile_id ) ) {
			$result = Transaction::run(
				function () use ( $record, $batch_id, $importer_id, $import_all, $record_id, $profile_id ): array {
					if ( null !== $profile_id ) {
						$this->map->unlink_profile( $profile_id );
					}
					return $this->create( $record, $record_id, $batch_id, $importer_id, $import_all );
				}
			);
			return $this->with_images( $result, $record, $batch_id );
		}

		$state = new StateRepository();
		$row   = $state->get( $profile_id );
		$hash  = (string) preg_replace( '/^sha256:/', '', (string) $record['content_hash'] );
		if ( null !== $row && hash_equals( (string) $row['content_hash'], $hash ) ) {
			return $this->result( self::UNCHANGED, $profile_id );
		}
		if ( null === $row || self::is_reviewed( $row ) ) {
			return Transaction::run( fn (): array => $this->flag( $profile_id, $record ) );
		}
		$result = Transaction::run( fn (): array => $this->update( $profile_id, $record, $row, $batch_id, $importer_id, $import_all ) );
		return $this->with_images( $result, $record, $batch_id );
	}

	/**
	 * A batch imported again after an undo takes back the profile its first import made for this record (UndoneBatchReset), so
	 * there is no duplicate: the record is linked to it again, its fields and state are set as for an update, and the result is
	 * CREATED, so a later undo takes it down again. Null when there is no such profile.
	 *
	 * @param array<string, mixed> $record      Record.
	 * @param string               $record_id   Record id.
	 * @param string               $batch_id    Batch id.
	 * @param int                  $importer_id Importer.
	 * @param bool                 $import_all  Setting.
	 * @return array{code: string, profile_id: int|null}|null
	 */
	private function revive( array $record, string $record_id, string $batch_id, int $importer_id, bool $import_all ): ?array {
		$profile_id = \WhoIsWho\Core\Import\Undo\UndoneBatchReset::take( $batch_id, $record_id );
		$row        = null === $profile_id ? null : ( new StateRepository() )->get( $profile_id );
		if ( null === $profile_id || null === $row || null === get_post( $profile_id ) || null !== $this->map->record_for( $profile_id ) ) {
			return null;
		}
		return Transaction::run(
			function () use ( $record, $record_id, $profile_id, $row, $batch_id, $importer_id, $import_all ): array {
				$this->map->link( $record_id, $profile_id );
				$this->update( $profile_id, $record, $row, $batch_id, $importer_id, $import_all );
				return $this->result( self::CREATED, $profile_id );
			}
		);
	}

	/**
	 * Read images from this bundle folder (contract 1.1). Without one, records apply without their images.
	 *
	 * @param string|null $dir The bundle folder.
	 */
	public function set_bundle_dir( ?string $dir ): void {
		$this->bundle_dir = $dir;
	}

	/**
	 * Import the record's images after its profile was created or updated (outside the record's transaction, so a slow or
	 * failing image never undoes the profile). Each image is accepted or refused on its own.
	 *
	 * @param array{code: string, profile_id: int|null} $result   The record's result.
	 * @param array<string, mixed>                      $record   Record.
	 * @param string                                    $batch_id Batch.
	 * @return array{code: string, profile_id: int|null, images?: array{imported: int, refused: array<int, array{image_id: string, reason: string}>}}
	 */
	private function with_images( array $result, array $record, string $batch_id ): array {
		$images = (array) ( $record['images'] ?? array() );
		if ( array() === $images || null === $result['profile_id'] ) {
			return $result;
		}
		$split = ImagePolicy::split( (string) $record['contract_version'], $images );
		$out   = array(
			'imported' => 0,
			'refused'  => $split['refused'],
		);
		if ( array() !== $split['accepted'] ) {
			if ( null === $this->bundle_dir ) {
				foreach ( $split['accepted'] as $image ) {
					$out['refused'][] = array(
						'image_id' => (string) $image['image_id'],
						'reason'   => 'missing',
					);
				}
			} else {
				$done            = ( new ImageImporter() )->import( (int) $result['profile_id'], (string) $record['entity_type'], (string) ( $record['fields']['name']['value'] ?? '' ), $split['accepted'], $this->bundle_dir, $batch_id );
				$out['imported'] = $done['imported'];
				$out['refused']  = array_merge( $out['refused'], $done['refused'] );
			}
		}
		$result['images'] = $out;
		return $result;
	}

	/**
	 * Whether a person has already reviewed the profile (or decided about it): its fields are then not overwritten by an import.
	 *
	 * @param array<string, mixed> $row State row.
	 */
	public static function is_reviewed( array $row ): bool {
		return ! empty( $row['reviewer_id'] ) || ! in_array( (string) $row['pub_state'], array( PubState::IMPORTED_NOT_PUBLIC, PubState::PUBLISHED_PENDING ), true );
	}

	/**
	 * A result.
	 *
	 * @param string   $code       Code.
	 * @param int|null $profile_id Profile.
	 * @return array{code: string, profile_id: int|null}
	 */
	private function result( string $code, ?int $profile_id ): array {
		return array(
			'code'       => $code,
			'profile_id' => $profile_id,
		);
	}

	/**
	 * Create a profile.
	 *
	 * @param array<string, mixed> $record      Record.
	 * @param string               $record_id   Record id.
	 * @param string               $batch_id    Batch id.
	 * @param int                  $importer_id Importer.
	 * @param bool                 $import_all  Setting.
	 * @return array{code: string, profile_id: int|null}
	 * @throws \RuntimeException When the post cannot be created.
	 */
	private function create( array $record, string $record_id, string $batch_id, int $importer_id, bool $import_all ): array {
		$post = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_title'  => wp_slash( (string) $record['fields']['name']['value'] ),
				'post_status' => ProfileType::STATUS_HIDDEN,
			),
			true
		);
		if ( is_wp_error( $post ) || (int) $post < 1 ) {
			throw new \RuntimeException( 'The profile could not be created.' );
		}
		$profile_id = (int) $post;
		$this->map->link( $record_id, $profile_id );
		$flags = $this->scanner->scan( (array) $record['fields'] );
		// A record that failed the free-text scan lands as Imported not public, with a block a reviewer must clear (REQ-141).
		$this->assigner->assign( $profile_id, $record, $import_all || array() !== $flags, $importer_id, $batch_id, $this->source_of( $record ) );
		$this->write_fields( $profile_id, $record );
		$this->block->set( $profile_id, $flags );
		return $this->result( self::CREATED, $profile_id );
	}

	/**
	 * Update an unreviewed profile as a new version and re-set its state.
	 *
	 * @param int                  $profile_id  Profile.
	 * @param array<string, mixed> $record      Record.
	 * @param array<string, mixed> $row         State row.
	 * @param string               $batch_id    Batch id.
	 * @param int                  $importer_id Importer.
	 * @param bool                 $import_all  Setting.
	 * @return array{code: string, profile_id: int|null}
	 */
	private function update( int $profile_id, array $record, array $row, string $batch_id, int $importer_id, bool $import_all ): array {
		( new VersionRepository() )->append(
			$profile_id,
			0,
			'import',
			'Updated by import batch ' . $batch_id,
			$this->current_values( $profile_id, (string) $row['entity_type'] ),
			self::new_values( $record )
		);
		$this->write_fields( $profile_id, $record );
		$this->block->set( $profile_id, $this->scanner->scan( (array) $record['fields'] ) );
		$hash = (string) preg_replace( '/^sha256:/', '', (string) $record['content_hash'] );
		( new StateRepository() )->update(
			$profile_id,
			array(
				'content_hash' => $hash,
				'import_batch' => $batch_id,
				'tier'         => (string) $record['tier'],
				'source'       => $this->source_of( $record ),
				'importer_id'  => $importer_id,
			)
		);
		$target = StateAssigner::decide( $record, $import_all );
		if ( $target !== $row['pub_state'] && StateMachine::can_move( (string) $row['pub_state'], $target ) ) {
			( new StateMachine() )->transition( $profile_id, $target, 'Re-set by import batch ' . $batch_id );
		}
		return $this->result( self::UPDATED, $profile_id );
	}

	/**
	 * Raise a changed record on a reviewed profile for the review queue without touching it: a pending import version.
	 *
	 * @param int                  $profile_id Profile.
	 * @param array<string, mixed> $record     Record.
	 * @return array{code: string, profile_id: int|null}
	 */
	private function flag( int $profile_id, array $record ): array {
		$versions = new VersionRepository();
		$new      = self::new_values( $record );
		$json     = (string) wp_json_encode( $new );
		foreach ( $versions->pending_for_profile( $profile_id ) as $pending ) {
			if ( 'import' === $pending['created_via'] && (string) $pending['new_value'] === $json ) {
				return $this->result( self::FLAGGED_CHANGE, $profile_id );
			}
		}
		$type = (string) ( new StateRepository() )->get( $profile_id )['entity_type'];
		$versions->append( $profile_id, 0, 'import', 'Source changed a reviewed record', $this->current_values( $profile_id, $type ), $new, 'pending' );
		return $this->result( self::FLAGGED_CHANGE, $profile_id );
	}

	/**
	 * Store fields, terms, provenance and evidence for a profile that already has its state row.
	 *
	 * @param int                  $profile_id Profile.
	 * @param array<string, mixed> $record     Record.
	 */
	private function write_fields( int $profile_id, array $record ): void {
		$fields = new ProfileFields();
		$prov   = new ProvenanceRepository();
		foreach ( (array) $record['fields'] as $key => $field ) {
			$fields->set( $profile_id, (string) $key, $field['value'] );
			foreach ( (array) $field['provenance'] as $entry ) {
				$prov->record(
					$profile_id,
					array(
						'field'        => (string) $key,
						'source_url'   => $entry['source_url'] ?? '',
						'retrieved_at' => $entry['retrieved_at'] ?? '',
						'confidence'   => $entry['confidence'] ?? '',
						'method'       => $entry['method'] ?? '',
						'source_hash'  => (string) preg_replace( '/^sha256:/', '', (string) ( $entry['source_hash'] ?? '' ) ),
					)
				);
			}
		}
		foreach ( $this->terms->terms_for( $record ) as $field => $term_id ) {
			wp_set_object_terms( $profile_id, array( $term_id ), TermMapper::FIELDS[ $field ] );
		}
		$evidence = new EvidenceRepository();
		$evidence->clear( $profile_id );
		$evidence->store( $profile_id, $record );
	}

	/**
	 * The record's field values, for a version row (values only, no provenance).
	 *
	 * @param array<string, mixed> $record Record.
	 * @return array<string, mixed>
	 */
	public static function new_values( array $record ): array {
		$out = array();
		foreach ( (array) $record['fields'] as $key => $field ) {
			$out[ (string) $key ] = $field['value'];
		}
		ksort( $out );
		return $out;
	}

	/**
	 * The profile's stored values for the allowed fields of its type.
	 *
	 * @param int    $profile_id  Profile.
	 * @param string $entity_type Entity type.
	 * @return array<string, mixed>
	 */
	public function current_values( int $profile_id, string $entity_type ): array {
		$out = array();
		foreach ( array_keys( ( new FieldAllowlist() )->for_type( $entity_type ) ) as $key ) {
			if ( 'name' === $key ) {
				$out['name'] = get_the_title( $profile_id );
				continue;
			}
			$value = get_post_meta( $profile_id, (string) FieldAllowlist::meta_key( (string) $key ), true );
			if ( '' !== $value && false !== $value ) {
				$out[ (string) $key ] = $value;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * The source id shown in the staff list: the first source of the name field.
	 *
	 * @param array<string, mixed> $record Record.
	 */
	private function source_of( array $record ): string {
		return (string) ( $record['fields']['name']['provenance'][0]['source_id'] ?? '' );
	}
}
