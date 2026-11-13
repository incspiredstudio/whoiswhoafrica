<?php
/**
 * The marketing-mail gate: consent, one-click unsubscribe and honouring an unsubscribe (REQ-061).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit;

use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\EmailLogStore;
use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Marketing\ConsentChecker;
use WhoIsWho\Core\Platform\Mail\Marketing\DenyAllConsent;
use WhoIsWho\Core\Platform\Mail\Marketing\Marketing;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Tests\Support\MemoryLinkStore;
use WhoIsWho\Tests\Support\MemoryQueue;
use WhoIsWho\Tests\TestCase;

final class MarketingMailTest extends TestCase {

	/**
	 * Log rows.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $rows = array();

	/**
	 * Messages sent.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $sent = array();

	/**
	 * Runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $runner;

	/**
	 * Signed links.
	 *
	 * @var SignedLinks
	 */
	private SignedLinks $links;

	protected function setUp(): void {
		parent::setUp();
		if ( function_exists( 'remove_all_actions' ) ) {
			remove_all_actions( 'wiw_marketing_unsubscribed' ); // The newsletter listener needs its table; this test is about the gate.
		}
		$this->rows    = array();
		$this->sent    = array();
		$options       = new \ArrayObject();
		$queue         = new MemoryQueue();
		$this->runner  = new JobRunner(
			$queue,
			static fn (): int => 1800000000,
			array(
				'get' => static fn ( string $key, $fallback ) => $options[ $key ] ?? $fallback,
				'set' => static function ( string $key, $value ) use ( $options ): void {
					$options[ $key ] = $value;
				},
			)
		);
		$queue->runner = $this->runner;
		$this->links   = new SignedLinks( new MemoryLinkStore(), static fn (): int => 1800000000 );
	}

	/**
	 * A notifier with a gate that uses the given consent checker.
	 *
	 * @param ConsentChecker|null $consent Checker; null means no gate at all.
	 * @param Marketing|null      $gate    Receives the gate that was built.
	 */
	private function notifier( ?ConsentChecker $consent, ?Marketing &$gate = null ): Notifier {
		$rows = &$this->rows;
		$log  = new class( $rows ) implements EmailLogStore {
			/**
			 * Rows.
			 *
			 * @var array<int, array<string, string>>
			 */
			private array $rows;

			/**
			 * Constructor.
			 *
			 * @param array<int, array<string, string>> $rows Rows.
			 */
			public function __construct( array &$rows ) {
				$this->rows = &$rows;
			}

			/**
			 * Add.
			 *
			 * @param string $message_id    Id.
			 * @param string $event         Event.
			 * @param string $recipient_ref Ref.
			 * @param string $type          Type.
			 * @param string $result        Result.
			 */
			public function add( string $message_id, string $event, string $recipient_ref, string $type, string $result ): void {
				$this->rows[] = compact( 'message_id', 'event', 'recipient_ref', 'type', 'result' );
			}
		};
		$list = new \ArrayObject();
		$gate = null === $consent ? null : new Marketing(
			$consent,
			$this->links,
			static fn ( string $address ): string => hash_hmac( 'sha256', strtolower( trim( $address ) ), 'hash-key' ),
			static function ( ?array $write = null ) use ( $list ): array {
				if ( null !== $write ) {
					$list->exchangeArray( $write );
				}
				return $list->getArrayCopy();
			}
		);
		$gate?->register();
		return new Notifier(
			$this->runner,
			$log,
			static fn (): array => array(),
			function ( string $to, string $subject, string $text, string $html, array $headers = array() ): bool {
				$this->sent[] = array(
					'to'      => $to,
					'subject' => $subject,
					'text'    => $text,
					'html'    => $html,
					'headers' => $headers,
				);
				return true;
			},
			'hash-key',
			$this->links,
			$gate
		);
	}

	private static function opted_in(): ConsentChecker {
		return new class() implements ConsentChecker {
			/**
			 * Everyone has opted in.
			 *
			 * @param string $address Address.
			 */
			public function has_opted_in( string $address ): bool {
				unset( $address );
				return true;
			}
		};
	}

	public function test_REQ_061_marketing_message_is_refused_without_consent(): void {
		$notifier = $this->notifier( new DenyAllConsent() );
		$notifier->send( Events::NEWSLETTER_ISSUE, 'reader@example.org' );
		$this->runner->run_due( 60 );
		$this->assertSame( array(), $this->sent );
		$this->assertSame( 'refused', $this->rows[0]['result'] );
		$this->assertSame( 'newsletter_issue', $this->rows[0]['event'] );
		$this->assertStringNotContainsString(
			'reader@example.org',
			(string) json_encode( $this->rows ) // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- plain PHP unit test.
		);

		// With no gate wired at all, a marketing message is never sent either.
		$this->rows = array();
		$this->notifier( null )->send( Events::NEWSLETTER_ISSUE, 'reader@example.org' );
		$this->runner->run_due( 60 );
		$this->assertSame( array(), $this->sent );
	}

	public function test_REQ_061_marketing_message_carries_unsubscribe_headers(): void {
		$this->notifier( self::opted_in() )->send( Events::NEWSLETTER_ISSUE, 'reader@example.org' );
		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent );
		$headers = $this->sent[0]['headers'];
		$this->assertMatchesRegularExpression( '#^List-Unsubscribe: <http\S*/link/[A-Za-z0-9_-]{20,}>$#', $headers[0] );
		$this->assertSame( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $headers[1] );
		$this->assertStringContainsString( '/link/', $this->sent[0]['text'] );
	}

	public function test_REQ_061_transactional_message_is_never_blocked(): void {
		$this->notifier( new DenyAllConsent() )->send( Events::ENQUIRY_ACKNOWLEDGED, 'reader@example.org' );
		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent );
		$this->assertSame( array(), $this->sent[0]['headers'] );
	}

	public function test_REQ_061_unsubscribing_is_honoured_on_the_next_send(): void {
		$gate     = null;
		$notifier = $this->notifier( self::opted_in(), $gate );
		$notifier->send( Events::NEWSLETTER_ISSUE, 'reader@example.org' );
		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent );
		$this->assertTrue( preg_match( '#/link/([A-Za-z0-9_-]+)>#', $this->sent[0]['headers'][0], $m ) === 1 );

		$this->assertSame( 'You have been unsubscribed.', $this->links->use_link( $m[1] ) );
		$this->assertTrue( $gate->is_unsubscribed( 'Reader@Example.org' ) );

		$notifier->send( Events::NEWSLETTER_ISSUE, 'reader@example.org' );
		$this->runner->run_due( 60 );
		$this->assertCount( 1, $this->sent, 'no second message after unsubscribing' );
		$this->assertSame( 'refused', $this->rows[ count( $this->rows ) - 1 ]['result'] );
	}
}
