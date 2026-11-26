<?php
/**
 * One run of a translations file (REQ-276): the checked file is kept in the private folder outside the web root, and the run's
 * progress (position, counts, skipped items) in one option, so the screen can apply the file a batch at a time and pick up where
 * it stopped. Only one run exists at a time. A finished run writes one audit row with the counts.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Translate;

use WhoIsWho\Core\Import\Admin\BundleStore;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Translation run.
 */
final class TranslationRun {

	public const OPTION = 'wiw_translation_run';
	public const LOCK   = 'wiw_translation_lock';
	public const BATCH  = 20;

	/**
	 * Statuses.
	 */
	public const PREVIEW = 'preview';
	public const RUNNING = 'running';
	public const DONE    = 'done';

	/**
	 * Skipped items listed on the result, at most.
	 */
	private const SKIPPED_MAX = 500;

	/**
	 * Private folder.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Constructor.
	 *
	 * @param string|null $root Folder for stored files; null uses the private folder's translations folder.
	 */
	public function __construct( ?string $root = null ) {
		$this->root = rtrim( str_replace( '\\', '/', $root ?? dirname( BundleStore::default_root() ) . '/translations' ), '/' );
	}

	/**
	 * The current run, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function current(): ?array {
		$run = get_option( self::OPTION, null );
		return is_array( $run ) && isset( $run['id'], $run['status'] ) ? $run : null;
	}

	/**
	 * Forget the current run and its file.
	 */
	public function discard(): void {
		$run = $this->current();
		if ( null !== $run ) {
			$path = $this->path( (string) $run['id'] );
			if ( is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
		delete_option( self::OPTION );
	}

	/**
	 * Start a run from a file's text: check it, store it, and preview every item. A refused file throws
	 * \InvalidArgumentException (from TranslationFile::parse) with a message fit to show.
	 *
	 * @param string $json      File text.
	 * @param string $file_name File name as uploaded.
	 * @param int    $actor_id  Staff member.
	 * @return array<string, mixed> The run.
	 * @throws \RuntimeException When the file cannot be stored.
	 */
	public function start( string $json, string $file_name, int $actor_id ): array {
		$doc = TranslationFile::parse( $json );
		$this->discard();
		( new BundleStore( $this->root ) )->ensure_root();
		$id = substr( hash( 'sha256', $json ), 0, 16 );
		if ( false === file_put_contents( $this->path( $id ), $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- our own private folder, outside the web root.
			throw new \RuntimeException( 'The file could not be stored.' );
		}
		$applier = new TranslationApplier();
		$counts  = self::empty_counts();
		$sample  = array();
		foreach ( $doc['items'] as $item ) {
			$row = $applier->preview( $item );
			++$counts[ $row['outcome'] ];
			if ( count( $sample ) < 20 && '' !== $row['new_name'] && in_array( $row['outcome'], array( TranslationApplier::APPLIED, TranslationApplier::PARTLY ), true ) ) {
				$sample[] = array( $row['old_name'], $row['new_name'] );
			}
		}
		$terms = self::empty_counts();
		foreach ( $doc['terms'] as $term ) {
			++$terms[ $applier->term( $term, false ) ];
		}
		$run = array(
			'id'          => $id,
			'file'        => sanitize_file_name( $file_name ),
			'status'      => self::PREVIEW,
			'total'       => count( $doc['items'] ),
			'offset'      => 0,
			'preview'     => $counts,
			'terms_plan'  => $terms,
			'sample'      => $sample,
			'counts'      => self::empty_counts(),
			'terms'       => self::empty_counts(),
			'duplicates'  => 0,
			'skipped'     => array(),
			'started_by'  => $actor_id,
			'finished_at' => '',
		);
		update_option( self::OPTION, $run, false );
		return $run;
	}

	/**
	 * Apply the next batch. Terms are renamed with the first batch.
	 *
	 * @param int $actor_id Staff member.
	 * @return array<string, mixed> The run afterwards.
	 * @throws \RuntimeException When there is no run, its file is gone, or another batch is running.
	 */
	public function step( int $actor_id ): array {
		$run = $this->current();
		if ( null === $run ) {
			throw new \RuntimeException( 'There is no translations file to apply.' );
		}
		if ( self::DONE === $run['status'] ) {
			return $run;
		}
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			if ( time() - (int) get_option( self::LOCK, 0 ) < 120 ) {
				throw new \RuntimeException( 'Another batch is being applied. Wait a moment.' );
			}
			update_option( self::LOCK, time(), false );
		}
		try {
			$run = $this->batch( $run, $actor_id );
		} finally {
			delete_option( self::LOCK );
		}
		return $run;
	}

	/**
	 * One batch of a run.
	 *
	 * @param array<string, mixed> $run      Run.
	 * @param int                  $actor_id Staff member.
	 * @return array<string, mixed>
	 * @throws \RuntimeException When the file is gone.
	 */
	private function batch( array $run, int $actor_id ): array {
		$path = $this->path( (string) $run['id'] );
		$json = is_file( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- our own private file.
		if ( '' === $json ) {
			throw new \RuntimeException( 'The stored file is missing. Upload it again.' );
		}
		$doc     = TranslationFile::parse( $json );
		$applier = new TranslationApplier();
		if ( self::PREVIEW === $run['status'] ) {
			$run['status'] = self::RUNNING;
			foreach ( $doc['terms'] as $term ) {
				++$run['terms'][ $applier->term( $term, true ) ];
			}
		}
		$items = array_slice( $doc['items'], (int) $run['offset'], self::BATCH );
		foreach ( $items as $item ) {
			$row = $applier->apply( $item, $actor_id, (string) $run['file'] );
			++$run['counts'][ $row['outcome'] ];
			$run['duplicates'] += $row['duplicates'];
			if ( ! in_array( $row['outcome'], array( TranslationApplier::APPLIED, TranslationApplier::DONE ), true ) && count( $run['skipped'] ) < self::SKIPPED_MAX ) {
				$run['skipped'][] = array(
					'label'   => '' !== $item['record_id'] ? $item['record_id'] : '#' . $item['profile_id'],
					'profile' => $row['profile_id'],
					'outcome' => $row['outcome'],
					'fields'  => $row['changed'],
				);
			}
		}
		$run['offset'] = (int) $run['offset'] + count( $items );
		if ( $run['offset'] >= (int) $run['total'] ) {
			$run['status']      = self::DONE;
			$run['finished_at'] = gmdate( 'Y-m-d H:i:s' );
			AuditLogger::for_site()->log(
				'translations.apply',
				array(
					'type' => 'translations_file',
					'id'   => (string) $run['id'],
				),
				null,
				array(
					'profiles'   => $run['counts'],
					'terms'      => $run['terms'],
					'duplicates' => $run['duplicates'],
				),
				'Translations file ' . (string) $run['file'] . ' (issue 885)'
			);
		}
		update_option( self::OPTION, $run, false );
		return $run;
	}

	/**
	 * Where a run's file is kept.
	 *
	 * @param string $id Run id (hex).
	 */
	private function path( string $id ): string {
		return $this->root . '/' . preg_replace( '/[^a-f0-9]/', '', $id ) . '.json';
	}

	/**
	 * Zero counts for every outcome.
	 *
	 * @return array<string, int>
	 */
	public static function empty_counts(): array {
		return array_fill_keys(
			array( TranslationApplier::APPLIED, TranslationApplier::PARTLY, TranslationApplier::DONE, TranslationApplier::CHANGED, TranslationApplier::NOT_FOUND, TranslationApplier::REFUSED ),
			0
		);
	}
}
