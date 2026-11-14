<?php
/**
 * The setup checklist (REQ-056): live checks, each with a plain-language status, what to do when it is not ready and, where it
 * is safe, a one-click fix. Nothing here shows a secret: the email line says only whether the settings are present.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Compliance\Forms\TurnstileSettings;
use WhoIsWho\Core\Platform\Mail\Transport;
use WhoIsWho\Core\Platform\Migrations\Runner;
use WhoIsWho\Core\Platform\Plugins\RequiredPlugins;
use WhoIsWho\Core\Platform\Plugins\RequiredPluginsScreen;
use WhoIsWho\Core\Platform\TwoFactor\StaffStatus;

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
	 * @return array<int, array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}>
	 */
	public static function items(): array {
		return array(
			self::database(),
			self::permalinks(),
			self::theme(),
			self::guard(),
			self::two_factor(),
			self::email(),
			self::turnstile(),
			self::plugin( 'fluent-smtp' ),
			self::plugin( 'wpdiscuz' ),
			self::staff_names(),
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
	 * @param string $link         A screen to open (for example another plugin's settings), or empty.
	 * @param string $link_label   Link text.
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
	 */
	private static function item( string $id, string $title, string $status, string $detail, string $fix = '', string $action = '', string $action_label = '', string $link = '', string $link_label = '' ): array {
		return array(
			'id'           => $id,
			'title'        => $title,
			'status'       => $status,
			'detail'       => $detail,
			'fix'          => $fix,
			'action'       => $action,
			'action_label' => $action_label,
			'link'         => $link,
			'link_label'   => $link_label,
		);
	}

	/**
	 * 1. The database.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
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
		if ( SchemaState::may_self_initialise() ) {
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
			'' !== $stored ? $stored : __( 'A plugin update brought database changes that are not applied yet. Until they are, some screens may not work.', 'whoiswho' ),
			__( 'Make a backup of the site first (most hosts have a Backup button in their control panel), then press the button. It applies the changes once and is safe to press again.', 'whoiswho' ),
			'database',
			__( 'Update the database', 'whoiswho' )
		);
	}

	/**
	 * 2. Pretty permalinks.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
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
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
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
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
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
	 * 5. Two-factor sign-in for staff: advice only (D-070). Lists who has it on.
	 *
	 * @param StaffStatus|null $staff Staff status (tests pass one).
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
	 */
	public static function two_factor( ?StaffStatus $staff = null ): array {
		$title = __( 'Two-factor sign-in for staff (recommended)', 'whoiswho' );
		$staff = $staff ?? StaffStatus::for_site();
		$how   = __( 'Two-factor sign-in is optional, but it stops someone who guesses or steals a password from getting in. Each person opens Users > Profile (the link is under their name in the top-right menu), finds the Two-Factor Options section and switches on an authenticator app or the email code.', 'whoiswho' );
		if ( ! $staff->available() ) {
			return self::item( 'two_factor', $title, self::WARN, __( 'The two-factor software did not load.', 'whoiswho' ), __( 'Upload the whoiswho-core plugin again from the install bundle (Plugins > Add New > Upload Plugin): it carries the two-factor software inside it.', 'whoiswho' ) );
		}
		$summary = $staff->summary();
		$parts   = array();
		if ( array() !== $summary['on'] ) {
			/* translators: %s: list of names. */
			$parts[] = sprintf( __( 'On for: %s.', 'whoiswho' ), implode( ', ', $summary['on'] ) );
		}
		if ( array() !== $summary['off'] ) {
			/* translators: %s: list of names. */
			$parts[] = sprintf( __( 'Not yet on for: %s.', 'whoiswho' ), implode( ', ', $summary['off'] ) );
		}
		if ( $summary['total'] >= StaffStatus::LIMIT ) {
			/* translators: %d: how many accounts are listed. */
			$parts[] = sprintf( __( '(The first %d staff accounts are listed.)', 'whoiswho' ), StaffStatus::LIMIT );
		}
		if ( array() === $summary['off'] ) {
			return self::item( 'two_factor', $title, self::OK, implode( ' ', array_merge( array( __( 'Every staff account has a second sign-in step.', 'whoiswho' ) ), $parts ) ) );
		}
		return self::item( 'two_factor', $title, self::WARN, implode( ' ', $parts ), $how );
	}

	/**
	 * 6. Email: green when FluentSMTP is active and connected, or when the host set an email server for the site.
	 *
	 * @param Transport|null $transport Transport (tests pass one).
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
	 */
	public static function email( ?Transport $transport = null ): array {
		$title     = __( 'Email', 'whoiswho' );
		$transport = $transport ?? Transport::for_site();
		$link      = Transport::fluent_smtp_url();
		if ( Transport::fluent_smtp_configured() ) {
			return self::item( 'email', $title, self::OK, __( 'FluentSMTP is active and connected to an email account, so the site\'s emails go out through it.', 'whoiswho' ), '', '', '', $link, __( 'Open FluentSMTP settings', 'whoiswho' ) );
		}
		if ( $transport->has_server() ) {
			return self::item( 'email', $title, self::OK, __( 'Your host has set an email account for this site. (Its details are never shown here.)', 'whoiswho' ) );
		}
		if ( Transport::fluent_smtp_active() ) {
			return self::item(
				'email',
				$title,
				self::WARN,
				__( 'FluentSMTP is installed but not connected to an email account yet, so emails may not arrive.', 'whoiswho' ),
				__( 'Open FluentSMTP settings, add a connection (your host\'s email account works: use its outgoing server, port, user name and password), then send a test email.', 'whoiswho' ),
				'',
				'',
				$link,
				__( 'Open FluentSMTP settings', 'whoiswho' )
			);
		}
		return self::item(
			'email',
			$title,
			self::WARN,
			__( 'The site sends email with WordPress\'s built-in method. It works on many hosts, but messages often land in spam or are not sent at all.', 'whoiswho' ),
			__( 'Install the free FluentSMTP plugin on the Required plugins screen (press Install, then Activate), then open its settings and connect your email account. A demo does not need email.', 'whoiswho' ),
			'',
			'',
			RequiredPluginsScreen::url(),
			__( 'Open Required plugins', 'whoiswho' )
		);
	}

	/**
	 * 7. Spam protection for public forms (optional): Cloudflare Turnstile. The form to enter the keys is on the Setup screen.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
	 */
	public static function turnstile(): array {
		$title = __( 'Spam protection for forms (optional)', 'whoiswho' );
		if ( TurnstileSettings::active() ) {
			return self::item(
				'turnstile',
				$title,
				self::OK,
				TurnstileSettings::from_constants()
					? __( 'Cloudflare Turnstile is on. Your host set its keys for this site.', 'whoiswho' )
					: __( 'Cloudflare Turnstile is on for the public forms.', 'whoiswho' )
			);
		}
		return self::item(
			'turnstile',
			$title,
			self::WARN,
			__( 'The public forms use the built-in spam check (a hidden field and a timer). That is enough for most sites.', 'whoiswho' ),
			__( 'For stronger protection, create a free Turnstile widget in your Cloudflare dashboard (Turnstile > Add widget, with your site\'s address), then paste its site key and secret key below.', 'whoiswho' )
		);
	}

	/**
	 * 8 and 9. A required plugin (FluentSMTP, wpDiscuz), installed and switched on from Site settings > Required plugins.
	 *
	 * @param string $slug Plugin slug.
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
	 */
	private static function plugin( string $slug ): array {
		$plugin = RequiredPlugins::catalogue()[ $slug ];
		$id     = 'plugin_' . str_replace( '-', '_', $slug );
		/* translators: %s: plugin name. */
		$title  = sprintf( __( '%s plugin active', 'whoiswho' ), $plugin['name'] );
		$status = ( new RequiredPlugins() )->status( $slug );
		if ( RequiredPlugins::ACTIVE === $status ) {
			return self::item( $id, $title, self::OK, $plugin['why'], '', '', '', RequiredPluginsScreen::url(), __( 'Open Required plugins', 'whoiswho' ) );
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
			__( 'Open Site settings > Required plugins in the admin menu and press Install, then Activate. It takes under a minute and needs no file or command.', 'whoiswho' ),
			'',
			'',
			RequiredPluginsScreen::url(),
			__( 'Open Required plugins', 'whoiswho' )
		);
	}

	/**
	 * Public display names for staff who write stories: advice only. A story by someone whose display name is still their sign-in
	 * name shows the site's editorial name instead (PublicStory::byline), so the login is never public.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
	 */
	public static function staff_names(): array {
		$title   = __( 'Set a public display name for staff who write stories', 'whoiswho' );
		$writers = get_users(
			array(
				'capability' => 'edit_posts',
				'number'     => 50,
				'orderby'    => 'ID',
				'fields'     => array( 'ID' ),
			)
		);
		$unnamed = array();
		foreach ( $writers as $writer ) {
			$id = (int) $writer->ID;
			if ( \WhoIsWho\Core\Content\PublicStory::byline_needs_name( $id ) ) {
				$unnamed[] = (string) get_the_author_meta( 'display_name', $id );
			}
		}
		if ( array() === $unnamed ) {
			return self::item( 'staff_names', $title, self::OK, __( 'Every staff account that can write stories has a public display name.', 'whoiswho' ) );
		}
		return self::item(
			'staff_names',
			$title,
			self::WARN,
			/* translators: %s: list of account names. */
			sprintf( __( 'These accounts show their sign-in name as their public name: %s. Their stories are signed with the site\'s editorial name until they set one, so the sign-in name never appears in public.', 'whoiswho' ), implode( ', ', $unnamed ) ),
			__( 'Each person opens Users > Profile, fills in First name and Last name (or a Nickname), picks it under "Display name publicly as" and presses Update Profile.', 'whoiswho' ),
			'',
			'',
			admin_url( 'users.php' ),
			__( 'Open Users', 'whoiswho' )
		);
	}

	/**
	 * 10. Demo content.
	 *
	 * @return array{id: string, title: string, status: string, detail: string, fix: string, action: string, action_label: string, link: string, link_label: string}
	 */
	private static function demo(): array {
		$title = __( 'Demo content (optional)', 'whoiswho' );
		if ( DemoImport::for_site()->installed() ) {
			return self::item( 'demo', $title, self::OK, __( 'Demo content is on the site. It is marked as sample content, and each part has its own Remove button below. You can import more parts at any time.', 'whoiswho' ) );
		}
		return self::item( 'demo', $title, self::WARN, __( 'The site is empty. Import the demo content to see every page filled in, then remove it when you are ready to add real records.', 'whoiswho' ), __( 'Optional: tick the parts you want below and press Import demo content.', 'whoiswho' ) );
	}
}
