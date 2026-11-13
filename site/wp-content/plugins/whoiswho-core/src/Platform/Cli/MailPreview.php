<?php
/**
 * The mail:preview command: renders every email event with sample data into HTML and text files, plus an index page, so the wording
 * and layout can be reviewed without sending anything. Local and staging only: it refuses to run in production. The samples carry
 * no real personal data and nothing is queued, sent or logged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Mail\Events;
use WhoIsWho\Core\Platform\Mail\Notifier;

/**
 * Mail preview command.
 */
final class MailPreview implements Command {

	/**
	 * Notifier whose render() is used.
	 *
	 * @var Notifier
	 */
	private Notifier $notifier;

	/**
	 * Whether this is a production site.
	 *
	 * @var bool
	 */
	private bool $production;

	/**
	 * Constructor.
	 *
	 * @param Notifier $notifier   Notifier.
	 * @param bool     $production Whether the site is production (the command refuses).
	 */
	public function __construct( Notifier $notifier, bool $production ) {
		$this->notifier   = $notifier;
		$this->production = $production;
	}

	/**
	 * Name.
	 */
	public function name(): string {
		return 'mail:preview';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Render every email with sample data to HTML and text files, without sending (--dir=PATH; local and staging only).';
	}

	/**
	 * Sample context for an event: no real data, links on a reserved test domain.
	 *
	 * @param string $event Event id.
	 * @return array<string, mixed>
	 */
	public static function sample_context( string $event ): array {
		$context = array(
			'reference' => 'REF-0001',
			'id'        => 1,
			'name'      => 'Alex Sample',
		);
		if ( in_array( $event, array( Events::ENQUIRY_ACKNOWLEDGED, Events::ENQUIRY_STAFF_NOTICE, Events::ENQUIRY_RECEIVED ), true ) ) {
			$context['details'] = array(
				'Type'          => 'General enquiry',
				'Interested in' => 'Advertising',
			);
		}
		$cta = array( Events::VERIFY_EMAIL, Events::NEWSLETTER_CONFIRMATION, Events::CUSTOMER_INVITATION );
		if ( in_array( $event, $cta, true ) ) {
			$context['cta_url'] = 'https://example.test/link/sample-token/';
		}
		if ( Events::NEWSLETTER_ISSUE === $event ) {
			$context['unsubscribe_url'] = 'https://example.test/unsubscribe/sample-token/';
		}
		if ( in_array( $event, array( Events::REQUEST_RESOLVED, Events::CLAIM_DECISION ), true ) ) {
			$context['reason'] = 'A short reason that staff typed.';
		}
		return $context;
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		if ( $this->production ) {
			$output->line( 'mail:preview is for local and staging sites only.' );
			return 1;
		}
		$dir = '';
		foreach ( $args as $arg ) {
			if ( str_starts_with( $arg, '--dir=' ) ) {
				$dir = rtrim( substr( $arg, 6 ), '/\\' );
			}
		}
		if ( '' === $dir ) {
			$uploads = wp_upload_dir();
			$dir     = rtrim( (string) $uploads['basedir'], '/\\' ) . '/wiw-mail-preview';
		}
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			$output->line( 'Could not create the output folder.' );
			return 1;
		}
		$rows = '';
		foreach ( array_keys( Events::all() ) as $event ) {
			$message = $this->notifier->render( $event, self::sample_context( $event ) );
			file_put_contents( $dir . '/' . $event . '.html', $message['html'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local review files.
			file_put_contents( $dir . '/' . $event . '.txt', $message['text'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local review files.
			$rows .= '<li><a href="' . esc_attr( $event ) . '.html">' . esc_html( $event ) . '</a> (<a href="' . esc_attr( $event ) . '.txt">text</a>): ' . esc_html( $message['subject'] ) . '</li>';
		}
		file_put_contents( $dir . '/index.html', '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Email preview</title></head><body><h1>Email preview</h1><ul>' . $rows . '</ul></body></html>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local review files.
		$output->line( 'Rendered ' . count( Events::all() ) . ' emails to ' . $dir );
		return 0;
	}
}
