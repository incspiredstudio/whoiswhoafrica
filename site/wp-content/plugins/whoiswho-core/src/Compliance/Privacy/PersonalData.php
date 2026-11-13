<?php
/**
 * The registry of personal-data exporters and erasers (REQ-059, REQ-063, REQ-064), plugged into the WordPress Privacy tools so a
 * data request for one email address exports and erases everything held for it. This milestone's handlers are the enquiries and
 * other support items, the claims, the signed links and the email send log; the nominations, comments and newsletter register
 * their own handlers with WordPress directly or through the wiw_privacy_handlers filter. coverage() compares the PII catalogue
 * with what is handled, so a new personal-data table that nobody handles shows up as a failing test.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Privacy;

use WhoIsWho\Core\Awards\NominationPrivacy;
use WhoIsWho\Core\Content\Comments\CommentPrivacy;
use WhoIsWho\Core\Platform\Mail\Notifier;
use WhoIsWho\Core\Platform\Pii\PiiCatalogue;

/**
 * Personal data registry.
 */
final class PersonalData {

	/**
	 * Tables WordPress core handles itself (its own exporters and erasers), without prefix.
	 */
	public const CORE_TABLES = array( 'users', 'comments' );

	/**
	 * Catalogue tables that hold no personal data about a requester by email address, and why. A table here needs no handler. A
	 * table whose every catalogue entry is kept on purpose (the audit log, the suppression list) needs none either.
	 */
	public const NOT_BY_EMAIL = array(
		'wiw_notes'            => 'Staff notes about a profile or a case; erased with the case, never looked up by address.',
		'wiw_profile_search'   => 'Profile names for search; erased with the profile.',
		'wiw_profile_versions' => 'Profile edit history; erased with the profile.',
		'wiw_claims'           => 'Sentences extracted from public sources about a profile; no requester.',
		'wiw_passages'         => 'Source passages about a profile; no requester.',
		'wiw_consents'         => 'Newsletter subscriber rows: handled by the newsletter privacy exporter and eraser (M2-NWS-06).',
	);

	/**
	 * Attach the exporters and erasers to the WordPress privacy tools.
	 */
	public static function hooks(): void {
		add_filter(
			'wp_privacy_personal_data_exporters',
			static function ( $exporters ): array {
				$exporters = is_array( $exporters ) ? $exporters : array();
				foreach ( self::handlers() as $handler ) {
					$exporters[ 'wiw-' . $handler->id() ] = array(
						'exporter_friendly_name' => $handler->label(),
						'callback'               => static fn ( string $email, int $page = 1 ): array => self::export_page( $handler, $email, $page ),
					);
				}
				return $exporters;
			}
		);
		add_filter(
			'wp_privacy_personal_data_erasers',
			static function ( $erasers ): array {
				$erasers = is_array( $erasers ) ? $erasers : array();
				foreach ( self::handlers() as $handler ) {
					$erasers[ 'wiw-' . $handler->id() ] = array(
						'eraser_friendly_name' => $handler->label(),
						'callback'             => static fn ( string $email, int $page = 1 ): array => self::erase_page( $handler, $email, $page ),
					);
				}
				return $erasers;
			}
		);
	}

	/**
	 * The handlers: this milestone's, then those other modules add through the filter.
	 *
	 * @return PrivacyHandler[]
	 */
	public static function handlers(): array {
		$handlers = array(
			new TableHandler(
				'support-items',
				'Enquiries and requests',
				'wiw_support_items',
				'requester_email',
				array(
					'reference'              => 'Reference',
					'item_type'              => 'Type',
					'requester_name'         => 'Name',
					'requester_email'        => 'Email address',
					'requester_phone'        => 'Phone',
					'organisation'           => 'Organisation',
					'requester_relationship' => 'Relationship',
					'message'                => 'Message',
					'created_at'             => 'Received',
				),
				array(
					'requester_name'         => 'redact',
					'requester_email'        => 'redact',
					'requester_phone'        => 'redact',
					'organisation'           => 'redact',
					'requester_relationship' => 'redact',
					'message'                => 'redact',
					'ip_hash'                => null,
				)
			),
			new TableHandler(
				'claims',
				'Profile claims',
				'wiw_profile_claims',
				'work_email',
				array(
					'reference'    => 'Reference',
					'relationship' => 'Relationship to the profile',
					'name'         => 'Name',
					'role'         => 'Role',
					'work_email'   => 'Work email address',
					'phone'        => 'Phone',
					'evidence'     => 'Evidence',
					'received_at'  => 'Received',
				),
				array(
					'name'       => 'redact',
					'role'       => 'redact',
					'work_email' => 'redact',
					'phone'      => 'redact',
					'evidence'   => 'redact',
				)
			),
			new TableHandler(
				'signed-links',
				'Email confirmation links',
				'wiw_signed_links',
				'email',
				array(
					'purpose'    => 'Purpose',
					'email'      => 'Email address',
					'created_at' => 'Created',
					'used_at'    => 'Used',
				)
			),
			new TableHandler(
				'email-log',
				'Email send log',
				'wiw_email_events',
				'recipient_ref',
				array(
					'event'      => 'Message',
					'result'     => 'Result',
					'created_at' => 'Sent',
				),
				array(),
				static fn ( string $email ): string => Notifier::for_site()->recipient_ref( $email )
			),
		);
		$extra    = apply_filters( 'wiw_privacy_handlers', array() );
		foreach ( is_array( $extra ) ? $extra : array() as $handler ) {
			if ( $handler instanceof PrivacyHandler ) {
				$handlers[] = $handler;
			}
		}
		return $handlers;
	}

	/**
	 * Every table something handles: this registry's handlers, the modules that register their own, and core's.
	 *
	 * @return string[] Table names without prefix.
	 */
	public static function handled_tables(): array {
		$tables = self::CORE_TABLES;
		foreach ( self::handlers() as $handler ) {
			$tables = array_merge( $tables, $handler->tables() );
		}
		$tables = array_merge( $tables, NominationPrivacy::TABLES, array_keys( CommentPrivacy::TABLES ) );
		return array_values( array_unique( $tables ) );
	}

	/**
	 * Catalogue tables that nothing handles and nothing explains. Empty when every personal-data table has an exporter and an
	 * eraser or a stated reason in NOT_BY_EMAIL.
	 *
	 * @param PiiCatalogue|null $catalogue Catalogue; config/pii.php by default.
	 * @return string[]
	 */
	public static function coverage_gaps( ?PiiCatalogue $catalogue = null ): array {
		$catalogue = $catalogue ?? new PiiCatalogue();
		$handled   = self::handled_tables();
		$gaps      = array();
		$erased    = array();
		foreach ( $catalogue->entries() as $entry ) {
			$erased[ (string) strstr( $entry['name'], '.', true ) ][] = $entry['erase'];
		}
		foreach ( $catalogue->names( 'column' ) as $name ) {
			$table = (string) strstr( $name, '.', true );
			if ( array( 'keep' ) === array_values( array_unique( $erased[ $table ] ?? array() ) ) ) {
				continue;
			}
			if ( in_array( $table, $handled, true ) || isset( self::NOT_BY_EMAIL[ $table ] ) ) {
				continue;
			}
			$gaps[ $table ] = $table;
		}
		return array_values( $gaps );
	}

	/**
	 * The WordPress exporter answer for one handler.
	 *
	 * @param PrivacyHandler $handler Handler.
	 * @param string         $email   Address.
	 * @param int            $page    Page.
	 * @return array{data: array<int, array<string, mixed>>, done: bool}
	 */
	public static function export_page( PrivacyHandler $handler, string $email, int $page = 1 ): array {
		unset( $page );
		$items = array();
		foreach ( $handler->export( $email ) as $item ) {
			$items[] = array(
				'group_id'    => 'wiw-' . $handler->id(),
				'group_label' => $handler->label(),
				'item_id'     => $item['item_id'],
				'data'        => $item['data'],
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * The WordPress eraser answer for one handler.
	 *
	 * @param PrivacyHandler $handler Handler.
	 * @param string         $email   Address.
	 * @param int            $page    Page.
	 * @return array{items_removed: bool, items_retained: bool, messages: array<int, string>, done: bool}
	 */
	public static function erase_page( PrivacyHandler $handler, string $email, int $page = 1 ): array {
		unset( $page );
		return array(
			'items_removed'  => $handler->erase( $email ) > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
