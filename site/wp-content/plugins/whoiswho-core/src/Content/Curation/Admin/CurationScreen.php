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
use WhoIsWho\Core\Content\Home\HeroVideos;
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
	public const PIN    = 'wiw_pin_change';
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
	 * Picker and preview.
	 *
	 * @var PinPicker
	 */
	private PinPicker $picker;

	/**
	 * Hero video slots.
	 *
	 * @var HeroVideos
	 */
	private HeroVideos $hero;

	/**
	 * Constructor.
	 *
	 * @param CurationSettings $settings Settings.
	 * @param PinPicker|null   $picker   Picker; the default one when omitted.
	 * @param HeroVideos|null  $hero     Hero video service; the site's one when omitted.
	 */
	public function __construct( CurationSettings $settings, ?PinPicker $picker = null, ?HeroVideos $hero = null ) {
		$this->settings = $settings;
		$this->picker   = $picker ?? new PinPicker();
		$this->hero     = $hero ?? new HeroVideos( AuditLogger::for_site() );
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
					},
					false,
					'directory'
				);
				$registry->hook();
				add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
				ActionGuard::register(
					self::PIN,
					self::CAP,
					static function ( Request $request ): void {
						$notice = ( new self( new CurationSettings( AuditLogger::for_site() ) ) )->handle_pin( $request );
						wp_safe_redirect( add_query_arg( 'wiw_notice', rawurlencode( $notice ), admin_url( 'admin.php?page=' . self::SCREEN ) ) );
						exit;
					}
				);
				ActionGuard::register(
					HeroVideosSection::ACTION,
					self::CAP,
					static function ( Request $request ): void {
						$notice = ( new HeroVideosSection( new HeroVideos( AuditLogger::for_site() ) ) )->handle( $request );
						wp_safe_redirect( add_query_arg( 'wiw_notice', rawurlencode( $notice ), admin_url( 'admin.php?page=' . self::SCREEN ) ) . '#wiw-hero-videos' );
						exit;
					}
				);
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
	 * Load the drag-to-order script on this screen only.
	 */
	public static function enqueue(): void {
		if ( self::SCREEN !== Request::from_globals()->string( 'page' ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		wp_enqueue_script( 'wiw-curation-pins', plugins_url( 'assets/admin/curation/pins.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
		// The hero video slots choose files from the media library through the WordPress media modal.
		wp_enqueue_media();
		wp_enqueue_script( 'wiw-hero-videos', plugins_url( 'assets/admin/curation/hero-videos.js', WIW_CORE_FILE ), array( 'media-views' ), WIW_CORE_VERSION, true );
	}

	/**
	 * Add, remove or move one pin and save the block. A refusal changes nothing and comes back as the message.
	 *
	 * @param Request $request Request (block, op add|remove|up|down, id).
	 * @return string Message for the person.
	 */
	public function handle_pin( Request $request ): string {
		$block = $request->string( 'block' );
		$id    = $request->int( 'id' );
		$op    = $request->string( 'op' );
		if ( ! in_array( $block, CurationSettings::BLOCKS, true ) ) {
			return 'Unknown block.';
		}
		$pinned = $this->settings->get( $block )['pinned'];
		$at     = array_search( $id, $pinned, true );
		switch ( $op ) {
			case 'add':
				if ( false !== $at ) {
					return 'Already pinned.';
				}
				if ( ! $this->picker->can_pin( $block, $id ) ) {
					return 'That item cannot be pinned: it is not public now.';
				}
				$pinned[] = $id;
				break;
			case 'remove':
				if ( false === $at ) {
					return 'That item is not pinned.';
				}
				array_splice( $pinned, (int) $at, 1 );
				break;
			case 'up':
			case 'down':
				$to = 'up' === $op ? (int) $at - 1 : (int) $at + 1;
				if ( false === $at || $to < 0 || $to >= count( $pinned ) ) {
					return 'Saved.';
				}
				$moved               = $pinned[ $to ];
				$pinned[ $to ]       = $pinned[ (int) $at ];
				$pinned[ (int) $at ] = $moved;
				break;
			default:
				return 'Unknown change.';
		}//end switch
		try {
			$this->settings->set( $block, array( 'pinned' => array_values( $pinned ) ), 'pin ' . $op );
		} catch ( \DomainException | \InvalidArgumentException $refusal ) {
			return $refusal->getMessage();
		}
		return 'Saved.';
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
		$saved = $this->settings->get( $block )['pinned'];
		foreach ( $pinned as $new ) {
			if ( ctype_digit( $new ) && ! in_array( (int) $new, $saved, true ) && ! $this->picker->can_pin( $block, (int) $new ) ) {
				return 'Item ' . (int) $new . ' cannot be pinned: it is not public now.';
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
	 * @param string       $block   Block.
	 * @param Request|null $request Request, for the picker search and a preview.
	 */
	private function section( string $block, ?Request $request = null ): string {
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
		$out .= '<input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'whoiswho' ) . '</button> ';
		$out .= '<button type="submit" name="preview" value="1" formmethod="get" formaction="' . esc_url( admin_url( 'admin.php' ) ) . '" class="button">' . esc_html__( 'Preview without saving', 'whoiswho' ) . '</button></p></form>';
		return $out . $this->pins( $block, $setting, $request ) . $this->preview( $block, $setting, $request );
	}

	/**
	 * The pinned items with order controls, and the picker that lists only items visitors can see.
	 *
	 * @param string               $block   Block.
	 * @param array<string, mixed> $setting Saved setting.
	 * @param Request|null         $request Request, for the search text.
	 */
	private function pins( string $block, array $setting, ?Request $request ): string {
		$pinned = array_map( 'intval', (array) $setting['pinned'] );
		$rows   = $this->picker->describe( $block, $pinned );
		$out    = '<h3>' . esc_html__( 'Pinned items', 'whoiswho' ) . '</h3>';
		if ( array() === $rows ) {
			$out .= '<p>' . esc_html__( 'Nothing is pinned.', 'whoiswho' ) . '</p>';
		} else {
			$out .= '<ol class="wiw-pins" data-block="' . esc_attr( $block ) . '">';
			foreach ( $rows as $row ) {
				$out .= '<li data-id="' . (int) $row['id'] . '"><span>' . esc_html( $row['label'] ) . '</span>';
				if ( ! $row['public'] ) {
					$out .= ' <em>' . esc_html__( '(not public now: visitors do not see it)', 'whoiswho' ) . '</em>';
				}
				$out .= $this->pin_form( $block, (int) $row['id'], array( 'up', 'down', 'remove' ) ) . '</li>';
			}
			$out .= '</ol>';
		}
		$term = null !== $request && $block === $request->string( 'pick' ) ? $request->string( 'q' ) : null;
		$out .= '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
		$out .= '<input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		$out .= '<input type="hidden" name="pick" value="' . esc_attr( $block ) . '" />';
		$out .= '<p><label>' . esc_html__( 'Find an item to pin', 'whoiswho' ) . ' <input type="search" name="q" class="regular-text" value="' . esc_attr( (string) $term ) . '" /></label> ';
		$out .= '<button type="submit" class="button">' . esc_html__( 'Search', 'whoiswho' ) . '</button></p></form>';
		if ( null === $term ) {
			return $out;
		}
		$found = $this->picker->candidates( $block, $term );
		if ( array() === $found ) {
			return $out . '<p>' . esc_html__( 'Nothing public matches. Hidden and pending-review profiles cannot be pinned.', 'whoiswho' ) . '</p>';
		}
		$out .= '<ul class="wiw-pin-results">';
		foreach ( $found as $row ) {
			$out .= '<li><span>' . esc_html( $row['label'] ) . '</span>';
			$out .= in_array( $row['id'], $pinned, true ) ? ' <em>' . esc_html__( '(pinned)', 'whoiswho' ) . '</em>' : $this->pin_form( $block, $row['id'], array( 'add' ) );
			$out .= '</li>';
		}
		return $out . '</ul>';
	}

	/**
	 * A small form posting one pin change.
	 *
	 * @param string   $block Block.
	 * @param int      $id    Item id.
	 * @param string[] $ops   Operations to offer as buttons.
	 */
	private function pin_form( string $block, int $id, array $ops ): string {
		$labels = array(
			'add'    => __( 'Pin', 'whoiswho' ),
			'up'     => __( 'Move up', 'whoiswho' ),
			'down'   => __( 'Move down', 'whoiswho' ),
			'remove' => __( 'Remove pin', 'whoiswho' ),
		);
		$out    = ' <form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wiw-pin-form">';
		$out   .= '<input type="hidden" name="action" value="' . esc_attr( self::PIN ) . '" />';
		$out   .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( ActionGuard::nonce_for( self::PIN ) ) . '" />';
		$out   .= '<input type="hidden" name="block" value="' . esc_attr( $block ) . '" />';
		$out   .= '<input type="hidden" name="id" value="' . (int) $id . '" />';
		foreach ( $ops as $op ) {
			$out .= '<button type="submit" name="op" value="' . esc_attr( $op ) . '" class="button button-small">' . esc_html( $labels[ $op ] ) . '</button> ';
		}
		return $out . '</form>';
	}

	/**
	 * The block as the public page will show it: the saved setting, or the unsaved values of a Preview request, run through the
	 * same engine as the public page.
	 *
	 * @param string               $block   Block.
	 * @param array<string, mixed> $setting Saved setting.
	 * @param Request|null         $request Request.
	 */
	private function preview( string $block, array $setting, ?Request $request ): string {
		$unsaved = null !== $request && '1' === $request->string( 'preview' ) && $block === $request->string( 'block' );
		if ( null !== $request && $unsaved ) {
			$setting = $this->requested_setting( $request, $setting );
		}
		$out  = '<h3>' . esc_html( $unsaved ? __( 'Preview of your unsaved changes', 'whoiswho' ) : __( 'The block now', 'whoiswho' ) ) . '</h3>';
		$out .= $unsaved ? '<p>' . esc_html__( 'Nothing is saved until you press Save.', 'whoiswho' ) . '</p>' : '';
		$rows = $this->picker->preview( $block, $setting );
		if ( array() === $rows ) {
			return $out . '<p>' . esc_html__( 'The block would show nothing.', 'whoiswho' ) . '</p>';
		}
		$out .= '<ol class="wiw-preview">';
		foreach ( $rows as $row ) {
			$out .= '<li>' . esc_html( $row['label'] ) . '</li>';
		}
		return $out . '</ol>';
	}

	/**
	 * The setting a Preview request describes; a bad value falls back to the saved one.
	 *
	 * @param Request              $request Request.
	 * @param array<string, mixed> $saved   Saved setting.
	 * @return array<string, mixed>
	 */
	private function requested_setting( Request $request, array $saved ): array {
		$mode   = $request->string( 'mode' );
		$fill   = $request->string( 'fill' );
		$count  = $request->int( 'count' );
		$pinned = array();
		foreach ( explode( ',', $request->string( 'pinned' ) ) as $part ) {
			if ( ctype_digit( trim( $part ) ) && (int) trim( $part ) > 0 ) {
				$pinned[] = (int) trim( $part );
			}
		}
		return array(
			'mode'           => in_array( $mode, CurationSettings::MODES, true ) ? $mode : $saved['mode'],
			'count'          => $count >= 1 && $count <= CurationSettings::MAX_COUNT ? $count : $saved['count'],
			'fill'           => in_array( $fill, CurationSettings::FILLS, true ) ? $fill : $saved['fill'],
			'featured_first' => '1' === $request->string( 'featured_first' ),
			'pinned'         => array_slice( array_values( array_unique( $pinned ) ), 0, CurationSettings::MAX_PINNED ),
		);
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
	 * @param string       $notice  Message after a save.
	 * @param Request|null $request Request, for the picker search and a preview.
	 * @throws \DomainException When the user may not open the screen.
	 */
	public function html( string $notice = '', ?Request $request = null ): string {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'You are not allowed to change the home page settings.' );
		}
		$out = '<div class="wrap"><h1>' . esc_html__( 'Home page settings', 'whoiswho' ) . '</h1>';
		if ( '' !== $notice ) {
			$out .= '<div class="notice notice-info"><p>' . esc_html( $notice ) . '</p></div>';
		}
		foreach ( CurationSettings::BLOCKS as $block ) {
			$out .= $this->section( $block, $request );
		}
		$out .= ( new HeroVideosSection( $this->hero ) )->html();
		return $out . '</div>';
	}

	/**
	 * Print the screen.
	 *
	 * @param Request $request Request.
	 */
	public function render( Request $request ): void {
		echo wp_kses(
			$this->html( $request->string( 'wiw_notice' ), $request ),
			array_replace_recursive(
				array(
					'div'    => array( 'class' => true ),
					'h1'     => array(),
					'h2'     => array(),
					'h3'     => array(),
					'p'      => array(),
					'ol'     => array(
						'class'      => true,
						'data-block' => true,
					),
					'ul'     => array( 'class' => true ),
					'li'     => array( 'data-id' => true ),
					'span'   => array(),
					'em'     => array(),
					'label'  => array(),
					'form'   => array(
						'method' => true,
						'action' => true,
						'class'  => true,
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
						'type'       => true,
						'class'      => true,
						'name'       => true,
						'value'      => true,
						'formmethod' => true,
						'formaction' => true,
					),
				),
				HeroVideosSection::allowed_html()
			)
		);
	}
}
