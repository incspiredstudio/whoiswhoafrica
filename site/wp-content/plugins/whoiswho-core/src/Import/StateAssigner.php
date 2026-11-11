<?php
/**
 * Sets the publication state of an imported profile (contract section 9, REQ-210, REQ-216, REQ-148). WordPress decides, never the
 * pipeline; the rules run in order and the first match wins: the import-everything-as-not-public setting is on, the record is a
 * probable duplicate, the record is an individual or entrepreneur, otherwise an organisation becomes Published (review pending),
 * which carries the label and noindex through the state policy. The state is written only through the StateMachine, and the
 * importer's user id is recorded on the state row so the importer cannot approve what it imported.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Review\StateMachine;

/**
 * State assigner.
 */
final class StateAssigner {

	public const SETTING = 'import_all_not_public';

	/**
	 * State machine.
	 *
	 * @var StateMachine
	 */
	private StateMachine $machine;

	/**
	 * Constructor.
	 *
	 * @param StateMachine|null $machine State machine.
	 */
	public function __construct( ?StateMachine $machine = null ) {
		$this->machine = $machine ?? new StateMachine();
	}

	/**
	 * The state a record lands in. Pure.
	 *
	 * @param array<string, mixed> $record     A validated record (decoded to arrays).
	 * @param bool                 $import_all The import-everything-as-not-public setting.
	 */
	public static function decide( array $record, bool $import_all ): string {
		if ( $import_all ) {
			return PubState::IMPORTED_NOT_PUBLIC;
		}
		if ( ! empty( $record['flags']['probable_duplicate'] ) ) {
			return PubState::IMPORTED_NOT_PUBLIC;
		}
		if ( 'organisation' === ( $record['entity_type'] ?? '' ) ) {
			return PubState::PUBLISHED_PENDING;
		}
		return PubState::IMPORTED_NOT_PUBLIC;
	}

	/**
	 * Whether the setting is on, as stored (default on until the launch safeguards pass).
	 *
	 * @param SettingsService $settings Settings service.
	 */
	public static function import_all_is_on( SettingsService $settings ): bool {
		return (bool) $settings->get( self::SETTING );
	}

	/**
	 * Give a newly created profile its state and its state row. The content hash is stored as 64 hex characters: the contract's
	 * "sha256:" prefix is removed.
	 *
	 * @param int                  $profile_id  Profile post id (created by the importer, no state yet).
	 * @param array<string, mixed> $record      A validated record.
	 * @param bool                 $import_all  The setting in force when the batch was registered (the batch snapshot).
	 * @param int                  $importer_id The user who confirmed the import.
	 * @param string               $batch_id    Batch id.
	 * @param string               $source      Source name for the staff list filter (one source id of the record).
	 * @return string The state the profile was given.
	 */
	public function assign( int $profile_id, array $record, bool $import_all, int $importer_id, string $batch_id, string $source = '' ): string {
		$state  = self::decide( $record, $import_all );
		$values = array(
			'entity_type'  => (string) $record['entity_type'],
			'tier'         => (string) ( $record['tier'] ?? '' ),
			'origin'       => 'import',
			'import_batch' => $batch_id,
			'content_hash' => preg_replace( '/^sha256:/', '', (string) ( $record['content_hash'] ?? '' ) ),
			'importer_id'  => $importer_id,
		);
		if ( '' !== $source ) {
			$values['source'] = $source;
		}
		$this->machine->enter( $profile_id, $state, $values, 'Imported in batch ' . $batch_id );
		return $state;
	}
}
