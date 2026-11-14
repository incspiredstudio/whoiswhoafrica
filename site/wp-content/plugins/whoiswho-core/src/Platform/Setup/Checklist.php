<?php
/**
 * The setup checklist (REQ-056): nine live checks, each with a plain-language status, what to do when it is not ready and, where it
 * is safe, a one-click fix. Nothing here shows a secret: the email line says only whether the settings are present.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Platform\Mail\Transport;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Plugins\RequiredPlugins;
use WhoIsWho\Core\Platform\TwoFactor\PluginStatus;

/**
 * Checklist.
 */
final class Checklist {

	public const OK    = 'ok';
	public const WARN  = 'warn';
	public const ERROR = 'error';

	/**
	 * The checks, in the order the owner works through them.
	 *
	 * @return array<int, array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}>
	 */
	public static function items(): array {
		return array(
			self::database(),
			self::permalinks(),
			self::theme(),
			self::guard(),
			self::two_factor(),
			self::email(),
			self::plugin( 'fluent-smtp' ),
			self::plugin( 'wpdiscuz' ),
			self::demo(),
		);
	}

	/**
	 * How many of the checks are ready.
	 *
	 * @param array<int, array{status: string}> $items Items.
	 */
	public static function ready_count( array $items ): int {
		return count( array_filter( $items, static fn ( array $item ): bool => self::OK === $item['status'] ) );
	}

	/**
	 * One item.
	 *
	 * @param string $id           Id.
	 * @param string $title        Title.
	 * @param string $status       ok, warn or error.
	 * @param string $detail       What is true now.
	 * @param string $fix          What to do when it is not ready.
	 * @param string $action       One-click action name, or empty.
	 * @param string $action_label Button text.
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function item( string $id, string $title, string $status, string $detail, string $fix = '', string $action = '', string $action_label = '' ): array {
		return array(
			'id'           => $id,
			'title'        => $title,
			'status'       => $status,
			'detail'       => $detail,
			'fix'          => $fix,
			'action'       => $action,
			'action_label' => $action_label,
		);
	}

	/**
	 * 1. The database.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function database(): array {
		$title    = __( 'Database ready', 'whoiswho' );
		$problems = SystemCheck::problems();
		if ( array() !== $problems ) {
			$text = array();
			$fix  = array();
			foreach ( $problems as $problem ) {
				$text[] = $problem['message'];
				$fix[]  = $problem['fix'];
			}
			return self::item( 'database', $title, self::ERROR, implode( ' ', $text ), implode( ' ', $fix ) );
		}
		try {
			$pending = Runner::for_site()->pending();
		} catch ( \Throwable $e ) {
			return self::item( 'database', $title, self::ERROR, __( 'The database could not be read.', 'whoiswho' ), __( 'Check that WordPress itself works (Dashboard > Site Health), then reload this page.', 'whoiswho' ) );
		}
		$stored = (string) get_option( SchemaState::OPTION_ERROR, '' );
		if ( array() === $pending ) {
			return self::item( 'database', $title, self::OK, __( 'The plugin\'s tables are in place and up to date.', 'whoiswho' ) );
		}
		$can_run = 'production' !== wp_get_environment_type() || SchemaState::may_self_initialise();
		if ( $can_run ) {
			return self::item(
				'database',
				$title,
				self::ERROR,
				'' !== $stored ? $stored : __( 'The plugin\'s tables have not been created yet.', 'whoiswho' ),
				__( 'Press the button. It creates the tables the directory needs, once, and is safe to press again.', 'whoiswho' ),
				'database',
				__( 'Run database setup', 'whoiswho' )
			);
		}
		return self::item(
			'database',
			$title,
			self::ERROR,
			__( 'This is a live site and its database has changes waiting.', 'whoiswho' ),
			__( 'Live sites change their database only through the deploy script, so an upgrade cannot go wrong halfway. Run the deploy, or ask whoever manages the server.', 'whoiswho' )
		);
	}

	/**
	 * 2. Pretty permalinks.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function permalinks(): array {
		$title = __( 'Pretty web addresses (permalinks)', 'whoiswho' );
		if ( '' !== (string) get_option( 'permalink_structure' ) ) {
			return self::item( 'permalinks', $title, self::OK, __( 'Web addresses use readable names, for example /directory/.', 'whoiswho' ) );
		}
		return self::item(
			'permalinks',
			$title,
			self::ERROR,
			__( 'WordPress is using plain web addresses (?p=123), so the directory, awards, stories and events pages will not open.', 'whoiswho' ),
			__( 'Press the button to switch to readable addresses. A choice you made yourself is never changed.', 'whoiswho' ),
			'permalinks',
			__( 'Fix permalinks', 'whoiswho' )
		);
	}

	/**
	 * 3. The child theme.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function theme(): array {
		$title = __( 'Site theme active', 'whoiswho' );
		$child = wp_get_theme( 'whoiswho-child' );
		if ( 'whoiswho-child' === get_stylesheet() ) {
			return self::item( 'theme', $title, self::OK, __( 'The child theme is active.', 'whoiswho' ) );
		}
		if ( ! $child->exists() || ! wp_get_theme( 'whoiswho' )->exists() ) {
			return self::item(
				'theme',
				$title,
				self::ERROR,
				__( 'The themes are not installed.', 'whoiswho' ),
				__( 'Go to Appearance > Themes > Add New > Upload Theme and upload whoiswho.zip, then whoiswho-child.zip. Then come back here.', 'whoiswho' )
			);
		}
		return self::item(
			'theme',
			$title,
			self::ERROR,
			__( 'The themes are installed, but the child theme is not the active theme, so the public site does not look right yet.', 'whoiswho' ),
			__( 'Press the button to switch to it (the parent theme is never activated on its own).', 'whoiswho' ),
			'theme',
			__( 'Activate child theme', 'whoiswho' )
		);
	}

	/**
	 * 4. The guard must-use plugin.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function guard(): array {
		$title = __( 'Guard plugin installed (recommended)', 'whoiswho' );
		if ( defined( 'WIW_GUARD_VERSION' ) ) {
			return self::item( 'guard', $title, self::OK, __( 'The guard is running. It keeps unreviewed records out of public pages.', 'whoiswho' ) );
		}
		return self::item(
			'guard',
			$title,
			self::WARN,
			__( 'The guard is not installed. The site works without it, but it is the safety net that keeps unreviewed records off public pages.', 'whoiswho' ),
			__( 'WordPress cannot upload must-use plugins. Unzip whoiswho-guard-mu-plugin.zip from the install bundle into the wp-content/mu-plugins folder of your site (create the folder if it is missing), using your host\'s File Manager or an FTP program. The file whoiswho-guard.php and the folder whoiswho-guard must both end up inside mu-plugins. Then reload this page.', 'whoiswho' )
		);
	}

	/**
	 * 5. Two-factor sign-in for the signed-in staff member.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function two_factor(): array {
		$title  = __( 'Two-factor sign-in set up for you', 'whoiswho' );
		$status = new PluginStatus();
		if ( ! $status->available() ) {
			return self::item( 'two_factor', $title, self::WARN, __( 'The two-factor software did not load.', 'whoiswho' ), __( 'Reinstall the whoiswho-core plugin from the install bundle: it carries the two-factor plugin inside it.', 'whoiswho' ) );
		}
		if ( $status->is_enrolled( get_current_user_id() ) ) {
			return self::item( 'two_factor', $title, self::OK, __( 'Your account has an authenticator app or email code.', 'whoiswho' ) );
		}
		return self::item(
			'two_factor',
			$title,
			self::WARN,
			__( 'Your account does not have a second sign-in step yet. Staff are asked to set one up.', 'whoiswho' ),
			__( 'Open Users > Profile (the link is in the top-right menu, under your name), find the Two-Factor Options section and switch on an authenticator app or the email code.', 'whoiswho' )
		);
	}

	/**
	 * 6. Email settings (read only).
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function email(): array {
		$title = __( 'Email settings', 'whoiswho' );
		if ( Transport::for_site()->has_credentials() ) {
			return self::item( 'email', $title, self::OK, __( 'Email account settings were found. (Their details are never shown here.)', 'whoiswho' ) );
		}
		return self::item(
			'email',
			$title,
			self::WARN,
			__( 'No email account settings were found. Confirmation and invitation emails will not be sent from this site yet. A demo does not need email.', 'whoiswho' ),
			__( 'Email settings are kept out of the admin on purpose, so a stolen login cannot read them. They live in the site\'s secrets file or as WIW_SMTP_* constants in wp-config.php: ask your developer or host, or see the runbook.', 'whoiswho' )
		);
	}

	/**
	 * 7 and 8. A required plugin (FluentSMTP, wpDiscuz), installed and switched on from Settings > Required plugins.
	 *
	 * @param string $slug Plugin slug.
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function plugin( string $slug ): array {
		$plugin = RequiredPlugins::catalogue()[ $slug ];
		$id     = 'plugin_' . str_replace( '-', '_', $slug );
		/* translators: %s: plugin name. */
		$title  = sprintf( __( '%s plugin active', 'whoiswho' ), $plugin['name'] );
		$status = ( new RequiredPlugins() )->status( $slug );
		if ( RequiredPlugins::ACTIVE === $status ) {
			return self::item( $id, $title, self::OK, $plugin['why'] );
		}
		return self::item(
			$id,
			$title,
			self::ERROR,
			RequiredPlugins::INSTALLED === $status
				/* translators: %s: plugin name. */
				? sprintf( __( '%s is installed but not switched on.', 'whoiswho' ), $plugin['name'] ) . ' ' . $plugin['why']
				/* translators: %s: plugin name. */
				: sprintf( __( '%s is not installed yet.', 'whoiswho' ), $plugin['name'] ) . ' ' . $plugin['why'],
			__( 'Open Settings > Required plugins in the admin menu and press Install, then Activate. It takes under a minute and needs no file or command.', 'whoiswho' )
		);
	}

	/**
	 * 9. Demo content.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string}
	 */
	private static function demo(): array {
		$title = __( 'Demo content (optional)', 'whoiswho' );
		if ( DemoImport::for_site()->installed() ) {
			return self::item( 'demo', $title, self::OK, __( 'The demo content is on the site. Everything in it is invented and marked, and one click removes it.', 'whoiswho' ) );
		}
		return self::item( 'demo', $title, self::WARN, __( 'The site is empty. Import the demo content to see every page filled in, then remove it when you are ready to add real records.', 'whoiswho' ), __( 'Optional: press Import demo content below.', 'whoiswho' ) );
	}
}
