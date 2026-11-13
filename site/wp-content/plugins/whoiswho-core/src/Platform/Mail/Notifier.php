<?php
/**
 * The service every feature calls to send an event email. send() resolves who gets it from the event's audience, queues one
 * delivery job per recipient on the job runner (high priority, retried with backoff), and each delivery attempt writes a log row
 * with the event, the result and a keyed hash of the recipient, never the address. Links come from the signed-link service:
 * callers pass a token, never a name or address in a URL. Contexts carry ids, references and short text only.
 *
 * Known limit: for public events the address travels in the queued job's arguments until the job is cleaned up by Action
 * Scheduler's own retention, so it sits in the queue table for that time.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Mail;

use WhoIsWho\Core\Platform\Jobs\JobRunner;
use WhoIsWho\Core\Platform\Links\SignedLinks;
use WhoIsWho\Core\Platform\Mail\Marketing\Marketing;

/**
 * Notifier.
 */
final class Notifier {

	public const JOB = 'mail.send';

	/**
	 * Job runner.
	 *
	 * @var JobRunner
	 */
	private JobRunner $jobs;

	/**
	 * Log.
	 *
	 * @var EmailLogStore
	 */
	private EmailLogStore $log;

	/**
	 * Resolves a staff audience to addresses: fn( string $audience ): array of addresses. Audience is "capability:<name>" or "administrators".
	 *
	 * @var callable
	 */
	private $resolver;

	/**
	 * Sends one message: fn( string $to, string $subject, string $text, string $html, array $headers ): bool.
	 *
	 * @var callable
	 */
	private $mailer;

	/**
	 * Key for hashing recipients.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Signed links, for building link URLs from tokens.
	 *
	 * @var SignedLinks|null
	 */
	private ?SignedLinks $links;

	/**
	 * Marketing gate (consent and unsubscribe), or null when marketing is not wired.
	 *
	 * @var Marketing|null
	 */
	private ?Marketing $marketing;

	/**
	 * Constructor.
	 *
	 * @param JobRunner        $jobs     Job runner.
	 * @param EmailLogStore    $log      Log.
	 * @param callable         $resolver Staff audience resolver.
	 * @param callable         $mailer   Mailer.
	 * @param string           $key      Hash key.
	 * @param SignedLinks|null $links    Signed links.
	 * @param Marketing|null   $marketing Marketing gate; without it a marketing message is never sent.
	 */
	public function __construct( JobRunner $jobs, EmailLogStore $log, callable $resolver, callable $mailer, string $key, ?SignedLinks $links = null, ?Marketing $marketing = null ) {
		$this->jobs      = $jobs;
		$this->log       = $log;
		$this->resolver  = $resolver;
		$this->mailer    = $mailer;
		$this->key       = $key;
		$this->links     = $links;
		$this->marketing = $marketing;
		$this->jobs->handle( self::JOB, array( $this, 'deliver' ) );
	}

	/**
	 * Notifier for this site.
	 */
	public static function for_site(): self {
		static $shared = null;
		if ( null === $shared ) {
			$shared = new self(
				JobRunner::for_site(),
				new WpdbEmailLog(),
				array( StaffAudience::class, 'addresses' ),
				array( self::class, 'wp_mail_send' ),
				wp_salt( 'secure_auth' ),
				SignedLinks::for_site(),
				Marketing::for_site()
			);
		}
		return $shared;
	}

	/**
	 * Queue an event email.
	 *
	 * @param string               $event     Event id from Events.
	 * @param string               $recipient Address for "public" events; ignored for staff audiences.
	 * @param array<string, mixed> $context   Ids, reference, reason (short text), link_token (from SignedLinks::issue).
	 * @return int Number of messages queued.
	 * @throws \InvalidArgumentException For an unknown event.
	 */
	public function send( string $event, string $recipient = '', array $context = array() ): int {
		$events = Events::all();
		if ( ! isset( $events[ $event ] ) ) {
			throw new \InvalidArgumentException( 'Unknown email event.' );
		}
		$audience = $events[ $event ]['audience'];
		if ( 'public' === $audience ) {
			$addresses = '' !== $recipient ? array( $recipient ) : array();
		} else {
			$addresses = (array) ( $this->resolver )( $audience );
		}
		$queued = 0;
		foreach ( array_unique( array_map( 'strval', $addresses ) ) as $address ) {
			if ( ! self::valid_address( $address ) ) {
				continue;
			}
			$this->jobs->enqueue(
				self::JOB,
				array(
					'event'   => $event,
					'to'      => $address,
					'context' => $context,
				),
				true
			);
			++$queued;
		}
		return $queued;
	}

	/**
	 * Queue a public event email without keeping the address in the job: only the record's source and id are stored, and the
	 * address is looked up when the job runs (issue 429). A resolver for the source is registered through the
	 * wiw_mail_recipient_resolvers filter as source => callable( int $id ): string.
	 *
	 * @param string               $event   Event id from Events (public audience only).
	 * @param string               $source  Record source, for example support_item.
	 * @param int                  $id      Record id.
	 * @param array<string, mixed> $context Ids, reference, reason, link_token.
	 * @return int Number of messages queued.
	 * @throws \InvalidArgumentException For an unknown or non-public event.
	 */
	public function send_to_record( string $event, string $source, int $id, array $context = array() ): int {
		$events = Events::all();
		if ( ! isset( $events[ $event ] ) || 'public' !== $events[ $event ]['audience'] ) {
			throw new \InvalidArgumentException( 'Not a public email event.' );
		}
		$this->jobs->enqueue(
			self::JOB,
			array(
				'event'   => $event,
				'record'  => array(
					'source' => $source,
					'id'     => $id,
				),
				'context' => $context,
			),
			true
		);
		return 1;
	}

	/**
	 * Deliver one queued message. Logs the attempt; throws on failure so the runner retries.
	 *
	 * @param array<string, mixed> $args Job arguments.
	 * @throws \RuntimeException When the mailer reports failure.
	 */
	public function deliver( array $args ): void {
		$event = (string) ( $args['event'] ?? '' );
		$to    = isset( $args['record'] ) && is_array( $args['record'] ) ? $this->resolve_record( $args['record'] ) : (string) ( $args['to'] ?? '' );
		$ref   = (string) ( $args['_ref'] ?? '' );
		if ( ! self::valid_address( $to ) ) {
			// The record is gone, erased or has no usable address: nothing to send, and nothing to retry.
			$this->log->add( $ref, $event, '', 'sent', 'skipped' );
			return;
		}
		$context = (array) ( $args['context'] ?? array() );
		$headers = array();
		if ( isset( $args['record'] ) && is_array( $args['record'] ) ) {
			$context['name'] = $this->resolve_name( $args['record'] );
		}
		if ( Marketing::is_marketing( $event ) ) {
			// REQ-061: a marketing message needs opt-in consent and always carries one-click unsubscribe; no gate means no send.
			if ( null === $this->marketing || ! $this->marketing->may_send( $to ) ) {
				$this->log->add( $ref, $event, $this->recipient_ref( $to ), 'sent', 'refused' );
				return;
			}
			$context['unsubscribe_url'] = $this->marketing->unsubscribe_url( $to );
			$headers                    = array_merge( $headers, Marketing::headers( $context['unsubscribe_url'] ) );
		}
		$reply = MailSettings::reply_to();
		if ( '' !== $reply ) {
			$headers[] = 'Reply-To: ' . $reply;
		}
		$message = $this->render( $event, $context );
		$ok      = (bool) ( $this->mailer )( $to, $message['subject'], $message['text'], $message['html'], $headers );
		$this->log->add( $ref, $event, $this->recipient_ref( $to ), 'sent', $ok ? 'sent' : 'failed' );
		if ( ! $ok ) {
			throw new \RuntimeException( 'Send failed.' );
		}
	}

	/**
	 * Look up a record's address when the job runs.
	 *
	 * @param array<string, mixed> $record Source and id.
	 */
	private function resolve_record( array $record ): string {
		$resolvers = function_exists( 'apply_filters' ) ? apply_filters( 'wiw_mail_recipient_resolvers', array() ) : array();
		$source    = (string) ( $record['source'] ?? '' );
		if ( ! is_array( $resolvers ) || ! isset( $resolvers[ $source ] ) || ! is_callable( $resolvers[ $source ] ) ) {
			return '';
		}
		return (string) call_user_func( $resolvers[ $source ], (int) ( $record['id'] ?? 0 ) );
	}

	/**
	 * Look up the recipient's name for the greeting when the job runs, so a name is never queued. A resolver for the source is
	 * registered through the wiw_mail_name_resolvers filter as source => callable( int $id ): string; none means no name.
	 *
	 * @param array<string, mixed> $record Source and id.
	 */
	private function resolve_name( array $record ): string {
		$resolvers = function_exists( 'apply_filters' ) ? apply_filters( 'wiw_mail_name_resolvers', array() ) : array();
		$source    = (string) ( $record['source'] ?? '' );
		if ( ! is_array( $resolvers ) || ! isset( $resolvers[ $source ] ) || ! is_callable( $resolvers[ $source ] ) ) {
			return '';
		}
		return (string) call_user_func( $resolvers[ $source ], (int) ( $record['id'] ?? 0 ) );
	}

	/**
	 * Keyed hash of a recipient, the only form in the log.
	 *
	 * @param string $address Address.
	 */
	public function recipient_ref( string $address ): string {
		return hash_hmac( 'sha256', strtolower( trim( $address ) ), $this->key );
	}

	/**
	 * Render an event: subject, plain text and HTML in the shared layout (MailLayout). The wording comes from EmailCopy; a caller can
	 * pass its own opening line as context "lead", short "details" (label => text), a "reference", a "reason", a signed "link_token"
	 * or a plain "cta_url". Templates are overridable with the wiw_mail_templates filter (event => callable( context ): array
	 * with subject and lines). The greeting uses the recipient's first name when "name" is in the context (looked up when the job
	 * runs, never queued); no name ever goes into a subject.
	 *
	 * @param string               $event   Event id.
	 * @param array<string, mixed> $context Context.
	 * @return array{subject: string, text: string, html: string}
	 */
	public function render( string $event, array $context ): array {
		$definition = Events::all()[ $event ] ?? array(
			'subject'  => 'Notification',
			'audience' => 'public',
		);
		$public     = 'public' === $definition['audience'];
		$subject    = $definition['subject'];
		$copy       = EmailCopy::for_event( $event );

		$window = MailSettings::response_window();
		$body   = array();
		foreach ( $copy['body'] as $line ) {
			$body[] = '{window}' === $line ? ( '' !== $window ? 'We aim to reply within ' . $window . '.' : 'We will reply as soon as we can.' ) : $line;
		}
		if ( isset( $context['lead'] ) && is_string( $context['lead'] ) && '' !== $context['lead'] ) {
			$body = array( $context['lead'] );
		}

		$details = array();
		foreach ( (array) ( $context['details'] ?? array() ) as $label => $value ) {
			if ( is_string( $label ) && is_string( $value ) && '' !== $value ) {
				$details[ $label ] = $value;
			}
		}
		if ( isset( $context['reason'] ) && is_string( $context['reason'] ) && '' !== $context['reason'] ) {
			$details['Reason'] = $context['reason'];
		}
		if ( isset( $context['reference'] ) && is_string( $context['reference'] ) && '' !== $context['reference'] ) {
			$details[ $public ? 'Your reference' : 'Reference' ] = $context['reference'];
		}

		$cta   = null;
		$admin = $public ? '' : EmailCopy::admin_url( $event, $context );
		if ( isset( $context['link_token'] ) && is_string( $context['link_token'] ) && null !== $this->links ) {
			$cta = array(
				'label' => $copy['cta'] ?? 'Open the link',
				'url'   => $this->links->url( $context['link_token'] ),
			);
		} elseif ( isset( $context['cta_url'] ) && is_string( $context['cta_url'] ) && 1 === preg_match( '~^https?://~i', $context['cta_url'] ) ) {
			$cta = array(
				'label' => $copy['cta'] ?? 'Open the link',
				'url'   => $context['cta_url'],
			);
		} elseif ( '' !== $admin ) {
			$cta = array(
				'label' => 'Open it in the admin',
				'url'   => $admin,
			);
		}

		if ( function_exists( 'apply_filters' ) ) {
			$custom = apply_filters( 'wiw_mail_templates', array() );
			if ( is_array( $custom ) && isset( $custom[ $event ] ) && is_callable( $custom[ $event ] ) ) {
				$out     = (array) $custom[ $event ]( $context );
				$subject = (string) ( $out['subject'] ?? $subject );
				if ( isset( $out['lines'] ) ) {
					$body = array_map( 'strval', (array) $out['lines'] );
				}
			}
		}

		$greeting = '';
		if ( $public ) {
			$name     = isset( $context['name'] ) && is_string( $context['name'] ) ? trim( $context['name'] ) : '';
			$parts    = '' !== $name ? preg_split( '/\s+/', $name ) : array();
			$first    = is_array( $parts ) && isset( $parts[0] ) ? (string) $parts[0] : '';
			$greeting = '' !== $first ? 'Hello ' . $first . ',' : 'Hello,';
		}
		$layout = MailLayout::render(
			array(
				'subject'         => $subject,
				'preheader'       => $body[0] ?? '',
				'heading'         => $copy['heading'],
				'greeting'        => $greeting,
				'paragraphs'      => $body,
				'details'         => $details,
				'act'             => $public ? '' : ( $copy['act'] ?? '' ),
				'cta'             => $cta,
				'class'           => Marketing::is_marketing( $event ) ? 'marketing' : 'transactional',
				'bare'            => ! empty( $copy['bare'] ),
				'unsubscribe_url' => isset( $context['unsubscribe_url'] ) && is_string( $context['unsubscribe_url'] ) ? $context['unsubscribe_url'] : '',
			)
		);
		return array(
			'subject' => $subject,
			'text'    => $layout['text'],
			'html'    => $layout['html'],
		);
	}

	/**
	 * Default mailer: wp_mail with an HTML body and a plain-text alternative.
	 *
	 * @param string   $to      Address.
	 * @param string   $subject Subject.
	 * @param string   $text    Plain text.
	 * @param string   $html    HTML.
	 * @param string[] $headers Extra headers, for example the one-click unsubscribe pair.
	 */
	public static function wp_mail_send( string $to, string $subject, string $text, string $html, array $headers = array() ): bool {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 120 );
			// A slow mail server must not end the job after it accepted the message.
		}
		$alt = static function ( $mailer ) use ( $text ): void {
			$mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		};
		add_action( 'phpmailer_init', $alt );
		$sent = wp_mail( $to, $subject, $html, array_merge( array( 'Content-Type: text/html; charset=UTF-8' ), $headers ) );
		remove_action( 'phpmailer_init', $alt );
		return $sent;
	}

	/**
	 * A plausible address.
	 *
	 * @param string $address Address.
	 */
	private static function valid_address( string $address ): bool {
		return false !== filter_var( $address, FILTER_VALIDATE_EMAIL );
	}
}
