<?php
/**
 * The one writer of the audit log: append-only, hash-chained. Each row's hash is SHA-256 over the previous row's hash and a
 * fixed serialisation of the row, so changing or removing any row breaks the chain from that row on. There is no update or
 * delete method. Appends run under a lock so concurrent writers keep one chain.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Audit;

/**
 * Audit logger.
 */
final class AuditLogger {

	/**
	 * Column and key definitions for the table, used by the migration (and by tests that need a scratch copy).
	 */
	public const TABLE_DEFINITION = 'id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		at datetime NOT NULL,
		actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
		action varchar(100) NOT NULL,
		subject_type varchar(60) NOT NULL,
		subject_id varchar(64) NOT NULL,
		old_value longtext NULL,
		new_value longtext NULL,
		reason text NULL,
		batch_ref varchar(40) NULL,
		prev_hash char(64) NOT NULL,
		hash char(64) NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY hash (hash),
		KEY action (action),
		KEY subject (subject_type, subject_id),
		KEY actor (actor_id),
		KEY at (at),
		KEY batch_ref (batch_ref)';

	/**
	 * The "previous hash" of the first row.
	 */
	public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

	/**
	 * Connection.
	 *
	 * @var Connection
	 */
	private Connection $db;

	/**
	 * Clock returning a UTC time string.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Returns the acting user id (0 for the system).
	 *
	 * @var callable
	 */
	private $actor;

	/**
	 * Constructor.
	 *
	 * @param Connection    $db    Connection.
	 * @param callable|null $clock Returns the time as "Y-m-d H:i:s" UTC.
	 * @param callable|null $actor Returns the acting user id.
	 */
	public function __construct( Connection $db, ?callable $clock = null, ?callable $actor = null ) {
		$this->db    = $db;
		$this->clock = $clock ?? static fn (): string => gmdate( 'Y-m-d H:i:s' );
		$this->actor = $actor ?? static fn (): int => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}

	/**
	 * Logger for this site.
	 */
	public static function for_site(): self {
		return new self( new WpdbConnection() );
	}

	/**
	 * Record one audited action.
	 *
	 * @param string                              $action  Action name, for example "profile.unpublish".
	 * @param array{type: string, id: string|int} $subject What it was done to.
	 * @param mixed                               $before  Value before, as ids or plain values (never raw personal data).
	 * @param mixed                               $after_value Value after.
	 * @param string                              $reason  Why, when the action needs one.
	 * @param string|null                         $batch   Batch reference when part of a batch.
	 * @return int Row id.
	 * @throws \RuntimeException When the log is busy or the row cannot be written.
	 */
	public function log( string $action, array $subject, $before = null, $after_value = null, string $reason = '', ?string $batch = null ): int {
		if ( ! $this->db->lock() ) {
			throw new \RuntimeException( 'Audit log is busy.' );
		}
		try {
			$row              = array(
				'at'           => (string) ( $this->clock )(),
				'actor_id'     => (int) ( $this->actor )(),
				'action'       => $action,
				'subject_type' => (string) $subject['type'],
				'subject_id'   => (string) $subject['id'],
				'old_value'    => null === $before ? null : $this->json( $before ),
				'new_value'    => null === $after_value ? null : $this->json( $after_value ),
				'reason'       => '' === $reason ? null : $reason,
				'batch_ref'    => $batch,
			);
			$row['prev_hash'] = $this->db->last_hash() ?? self::GENESIS;
			$row['hash']      = self::hash_row( $row );
			return $this->db->insert( $row );
		} finally {
			$this->db->unlock();
		}
	}

	/**
	 * Record one action done to many subjects: a batch row, then one row per subject, all sharing the batch reference.
	 *
	 * @param string                                          $action   Action name.
	 * @param array<int, array{type: string, id: string|int}> $subjects Subjects.
	 * @param string                                          $reason   Why.
	 * @return string The batch reference.
	 */
	public function log_batch( string $action, array $subjects, string $reason = '' ): string {
		$batch = bin2hex( random_bytes( 8 ) );
		$this->log(
			$action . '.batch',
			array(
				'type' => 'batch',
				'id'   => $batch,
			),
			null,
			array( 'count' => count( $subjects ) ),
			$reason,
			$batch
		);
		foreach ( $subjects as $subject ) {
			$this->log( $action, $subject, null, null, $reason, $batch );
		}
		return $batch;
	}

	/**
	 * Check the whole chain.
	 *
	 * @return array{intact: bool, broken_id: int|null} broken_id is the first row that does not fit.
	 */
	public function verify(): array {
		$prev  = self::GENESIS;
		$after = 0;
		$more  = true;
		while ( $more ) {
			$rows = $this->db->rows( $after, 500 );
			foreach ( $rows as $row ) {
				if ( $row['prev_hash'] !== $prev || self::hash_row( $row ) !== $row['hash'] ) {
					return array(
						'intact'    => false,
						'broken_id' => (int) $row['id'],
					);
				}
				$prev  = (string) $row['hash'];
				$after = (int) $row['id'];
			}
			$more = 500 === count( $rows );
		}

		return array(
			'intact'    => true,
			'broken_id' => null,
		);
	}

	/**
	 * The hash of a row: SHA-256 over the previous hash and a fixed-order serialisation of the row's content.
	 *
	 * @param array<string, mixed> $row Row with prev_hash and the content columns.
	 */
	public static function hash_row( array $row ): string {
		$content = array(
			(string) $row['at'],
			(int) $row['actor_id'],
			(string) $row['action'],
			(string) $row['subject_type'],
			(string) $row['subject_id'],
			$row['old_value'],
			$row['new_value'],
			$row['reason'],
			$row['batch_ref'],
		);
		return hash(
			'sha256',
			$row['prev_hash'] . '|' . json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP on purpose: this class also runs without WordPress.
				$content,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			)
		);
	}

	/**
	 * Encode a value as JSON.
	 *
	 * @param mixed $value Value.
	 */
	private function json( $value ): string {
		$json = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP on purpose: this class also runs without WordPress.
		return false === $json ? '"unencodable"' : $json;
	}
}
