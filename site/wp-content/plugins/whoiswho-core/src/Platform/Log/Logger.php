<?php
/**
 * Structured logging with identifiers only. Each line is JSON with time, level, request id, a short message and a context.
 * The context may carry only identifier-like keys (id, ids, ref, count, code, job, event, form, field names list); every other
 * value is redacted, catalogued personal-data keys are redacted by the PII catalogue, and anything shaped like an email address
 * is removed from messages and values. Callers should still pass ids, never people.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Log;

use WhoIsWho\Core\Platform\Pii\PiiCatalogue;

/**
 * Logger.
 */
final class Logger {

	public const LEVELS = array( 'debug', 'info', 'warning', 'error' );

	/**
	 * Context keys that may carry values.
	 */
	private const ALLOWED_KEY = '/(^|_)(id|ids|ref|count|code|job|event|form|fields|attempts|level)$/';

	/**
	 * Sink.
	 *
	 * @var LogSink
	 */
	private LogSink $sink;

	/**
	 * Catalogue.
	 *
	 * @var PiiCatalogue
	 */
	private PiiCatalogue $pii;

	/**
	 * Request id.
	 *
	 * @var string
	 */
	private string $request_id;

	/**
	 * Clock returning Unix time.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Constructor.
	 *
	 * @param LogSink       $sink       Sink.
	 * @param PiiCatalogue  $pii        PII catalogue.
	 * @param string|null   $request_id Request id; generated when omitted.
	 * @param callable|null $clock      Returns Unix time.
	 */
	public function __construct( LogSink $sink, PiiCatalogue $pii, ?string $request_id = null, ?callable $clock = null ) {
		$this->sink       = $sink;
		$this->pii        = $pii;
		$this->request_id = $request_id ?? bin2hex( random_bytes( 6 ) );
		$this->clock      = $clock ?? static fn (): int => time();
	}

	/**
	 * Logger for this site.
	 */
	public static function for_site(): self {
		static $shared = null;
		if ( null === $shared ) {
			$shared = new self( FileSink::for_site(), new PiiCatalogue() );
		}
		return $shared;
	}

	/**
	 * Attach hooks: form failures and failed jobs are logged (ids only).
	 */
	public function register(): void {
		add_action(
			'wiw_form_failure',
			function ( string $form, string $reason, array $fields = array() ): void {
				$this->log(
					'warning',
					'form_failure',
					array(
						'form'   => $form,
						'code'   => $reason,
						'fields' => implode( ',', $fields ),
					)
				);
			},
			10,
			3
		);
		add_action(
			'wiw_job_failed',
			function ( string $job, string $ref ): void {
				$this->log(
					'error',
					'job_failed',
					array(
						'job' => $job,
						'ref' => $ref,
					)
				);
			},
			10,
			2
		);
	}

	/**
	 * The request id on every line.
	 */
	public function request_id(): string {
		return $this->request_id;
	}

	/**
	 * Write a line.
	 *
	 * @param string               $level   One of LEVELS.
	 * @param string               $message Short fixed message, not user text.
	 * @param array<string, mixed> $context Identifiers only.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = 'info';
		}
		$line = json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			array(
				'time'    => gmdate( 'c', (int) ( $this->clock )() ),
				'level'   => $level,
				'request' => $this->request_id,
				'message' => self::strip_addresses( $message ),
				'context' => $this->clean( $context ),
			),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
		);
		if ( false !== $line ) {
			$this->sink->write( $line );
		}
	}

	/**
	 * Reduce a context to identifiers.
	 *
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	private function clean( array $context ): array {
		$context = $this->pii->scrub( $context );
		$out     = array();
		foreach ( $context as $key => $value ) {
			$key = (string) $key;
			if ( PiiCatalogue::REDACTED === $value || 1 !== preg_match( self::ALLOWED_KEY, $key ) || ! is_scalar( $value ) ) {
				$out[ $key ] = PiiCatalogue::REDACTED;
				continue;
			}
			$out[ $key ] = is_string( $value ) ? self::strip_addresses( $value ) : $value;
		}
		return $out;
	}

	/**
	 * Remove anything shaped like an email address.
	 *
	 * @param string $text Text.
	 */
	private static function strip_addresses( string $text ): string {
		return (string) preg_replace( '/[^\s@,;<>"\']+@[^\s@,;<>"\']+\.[^\s@,;<>"\']+/u', '[email]', $text );
	}
}
