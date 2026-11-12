<?php
/**
 * The Home page settings screen (REQ-257, REQ-244): one section per curated block with the mode, count, fill and (for stories)
 * featured first, plus pinned ids. Needs wiw_curate_home. Saving goes through CurationSettings, which audits each changed block
 * with the actor and the old and new values and announces wiw_curation_changed so the cache is purged.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Curation\Admin;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Curation screen.
 */
final class CurationScreen {

	public const SCREEN = 'wiw-home-settings';
	public const ACTION = 'wiw_save_curation';
	public const CAP    = CurationSettings::CAP;

	/**
	 * Block labels.
	 */
	public const LABELS = array(
		CurationSettings::ON_THE_RECORD => 'On the record',
		CurationSettings::STORIES_HOME  => 'Stories on the home page',
		CurationSettings::STORIES_PAGE  => 'Stories page',
	);

	/**
	 * Settings.
	 *
	 * @var CurationSettings
	 */
	private CurationSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param CurationSettings $settings Settings.
	 */
	public function __construct( CurationSettings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register the screen and the save action, in the admin only.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				$registry = ScreenRegistry::shared();
				$registry->register(
					self::SCREEN,
					__( 'Home page settings', 'whoiswho' ),
					self::CAP,
					static function (): void {
						( new self( new CurationSettings( AuditLogger::for_site() ) ) )->render( Request::from_globals() );
					}
				);
				$registry->hook();
				ActionGuard::register(
					self::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$notice = ( new self( new CurationSettings( AuditLogger::for_site() ) ) )->handle( $request );
						wp_safe_redirect( add_query_arg( 'wiw_notice', rawurlencode( $notice ), admin_url( 'admin.php?page=' . self::SCREEN ) ) );
						exit;
					}
				);
			},
			1
		);
	}

	/**
	 * Save one block. A refusal changes nothing and comes back as the message.
	 *
	 * @param Request $request Request (block, mode, count, fill, featured_first, pinned as comma-separated ids, reason).
	 * @return string Message for the person.
	 */
	public function handle( Request $request ): string {
		$block = $request->string( 'block' );
		if ( ! in_array( $block, CurationSettings::BLOCKS, true ) ) {
			return 'Unknown block.';
		}
		$pinned = array();
		foreach ( explode( ',', $request->string( 'pinned' ) ) as $part ) {
			if ( '' !== trim( $part ) ) {
				$pinned[] = trim( $part );
			}
		}
		try {
			$this->settings->set(
				$block,
				array(
					'mode'           => $request->string( 'mode' ),
					'count'          => $request->int( 'count' ),
					'fill'           => $request->string( 'fill' ),
					'featured_first' => '1' === $request->string( 'featured_first' ),
					'pinned'         => $pinned,
				),
				$request->string( 'reason' )
			);
		} catch ( \DomainException | \InvalidArgumentException $refusal ) {
			return $refusal->getMessage();
		}
		return 'Saved.';
	}

	/**
	 * One block's form.
	 *
	 * @param string $block Block.
	 */
	private function section( string $block ): string {
		$setting = $this->settings->get( $block );
		$out     = '<h2>' . esc_html( self::LABELS[ $block ] ) . '</h2>';
		$out    .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out    .= '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		$out    .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::ACTION ) ) . '" />';
		$out    .= '<input type="hidden" name="block" value="' . esc_attr( $block ) . '" />';
		$out    .= '<p><label>' . esc_html__( 'Mode', 'whoiswho' ) . ' ' . $this->select( 'mode', CurationSettings::MODES, (string) $setting['mode'] ) . '</label> ';
		$out    .= '<label>' . esc_html__( 'Count', 'whoiswho' ) . ' <input type="number" name="count" min="1" max="' . (int) CurationSettings::MAX_COUNT . '" value="' . (int) $setting['count'] . '" /></label> ';
		$out    .= '<label>' . esc_html__( 'Fill', 'whoiswho' ) . ' ' . $this->select( 'fill', CurationSettings::FILLS, (string) $setting['fill'] ) . '</label></p>';
		if ( CurationSettings::ON_THE_RECORD !== $block ) {
			$out .= '<p><label><input type="checkbox" name="featured_first" value="1"' . ( $setting['featured_first'] ? ' checked="checked"' : '' ) . ' /> ' . esc_html__( 'Featured first', 'whoiswho' ) . '</label></p>';
		}
		$out .= '<p><label>' . esc_html__( 'Pinned ids (comma separated)', 'whoiswho' ) . ' <input type="text" name="pinned" class="regular-text" value="' . esc_attr( implode( ', ', array_map( 'intval', $setting['pinned'] ) ) ) . '" /></label></p>';
		$out .= '<p><label>' . esc_html__( 'Reason (optional)', 'whoiswho' ) . ' <input type="text" name="reason" maxlength="300" class="regular-text" /></label></p>';
		return $out . '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'whoiswho' ) . '</button></p></form>';
	}

	/**
	 * A select.
	 *
	 * @param string   $name     Field name.
	 * @param string[] $options  Values.
	 * @param string   $selected Selected value.
	 */
	private function select( string $name, array $options, string $selected ): string {
		$out = '<select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $option ) {
			$out .= '<option value="' . esc_attr( $option ) . '"' . ( $option === $selected ? ' selected="selected"' : '' ) . '>' . esc_html( str_replace( '_', ' ', $option ) ) . '</option>';
		}
		return $out . '</select>';
	}

	/**
	 * The screen markup.
	 *
	 * @param string $notice Message after a save.
	 * @throws \DomainException When the user may not open the screen.
	 */
	public function html( string $notice = '' ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to change the home page settings.' );
		}
		$out = '<div class="wrap"><h1>' . esc_html__( 'Home page settings', 'whoiswho' ) . '</h1>';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		foreach ( CurationSettings::BLOCKS as $block ) {
			$out .= $this->section( $block );
		}
		return $out . '</div>';
	}

	/**
	 * Print the screen.
	 *
	 * @param Request $request Request.
	 */
	public function render( Request $request ): void {
		echo wp_kses(
			$this->html( $request->string( 'wiw_notice' ) ),
			array(
				'div'    => array( 'class' => true ),
				'h1'     => array(),
				'h2'     => array(),
				'p'      => array(),
				'label'  => array(),
				'form'   => array(
					'method' => true,
					'action' => true,
				),
				'input'  => array(
					'type'      => true,
					'name'      => true,
					'value'     => true,
					'min'       => true,
					'max'       => true,
					'maxlength' => true,
					'class'     => true,
					'checked'   => true,
				),
				'select' => array( 'name' => true ),
				'option' => array(
					'value'    => true,
					'selected' => true,
				),
				'button' => array(
					'type'  => true,
					'class' => true,
				),
			)
		);
	}
}
