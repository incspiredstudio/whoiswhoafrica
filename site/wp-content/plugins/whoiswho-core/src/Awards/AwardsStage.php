<?php
/**
 * The public Awards stage (REQ-259, REQ-033, REQ-194): one read model that the Awards overview, the Nominate page, the nav label
 * and the home page read, so none of them decides for itself. It is derived from the one feature flag and from what is Live:
 * Winners announced (a Live winner exists), Nominees announced (a Live nominee exists), Nominations open (the switch is on) and
 * Closed. Each stage carries its main button label and link. There is no new setting and no sign-up while closed (REQ-259).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

/**
 * Awards stage.
 */
final class AwardsStage {

	public const WINNERS  = 'winners';
	public const NOMINEES = 'nominees';
	public const OPEN     = 'open';
	public const CLOSED   = 'closed';

	/**
	 * Whether the switch is on.
	 *
	 * @var callable
	 */
	private $is_open;

	/**
	 * Whether a Live winner exists.
	 *
	 * @var callable
	 */
	private $has_winner;

	/**
	 * Whether a Live nominee exists.
	 *
	 * @var callable
	 */
	private $has_nominee;

	/**
	 * Constructor.
	 *
	 * @param callable|null $is_open     Switch state; the settings service by default.
	 * @param callable|null $has_winner  Whether a Live winner exists; PublicWinner by default.
	 * @param callable|null $has_nominee Whether a Live nominee exists; PublicNominee by default.
	 */
	public function __construct( ?callable $is_open = null, ?callable $has_winner = null, ?callable $has_nominee = null ) {
		$this->is_open     = $is_open ?? static fn (): bool => wiw_nominations_open();
		$this->has_winner  = $has_winner ?? static fn (): bool => array() !== PublicWinner::all( 1 );
		$this->has_nominee = $has_nominee ?? static fn (): bool => array() !== PublicNominee::live( 0, 1 );
	}

	/**
	 * Stage for this site.
	 */
	public static function for_site(): self {
		return new self();
	}

	/**
	 * Hook the menu filter.
	 */
	public static function hooks(): void {
		add_filter( 'wp_nav_menu_objects', array( self::class, 'filter_menu_items' ), 20 );
	}

	/**
	 * The current stage key.
	 */
	public function stage(): string {
		if ( ( $this->has_winner )() ) {
			return self::WINNERS;
		}
		if ( ( $this->has_nominee )() ) {
			return self::NOMINEES;
		}
		return ( $this->is_open )() ? self::OPEN : self::CLOSED;
	}

	/**
	 * Whether the public nomination form is open.
	 */
	public function nominations_open(): bool {
		return (bool) ( $this->is_open )();
	}

	/**
	 * The stage, its status label, and the main button.
	 *
	 * @return array{stage: string, status: string, label: string, url: string}
	 */
	public function button(): array {
		$stage = $this->stage();
		switch ( $stage ) {
			case self::WINNERS:
				return $this->model( $stage, __( 'Winners announced', 'whoiswho' ), __( 'See the winners', 'whoiswho' ), '/awards/winners/' );
			case self::NOMINEES:
				return $this->model( $stage, __( 'Nominees announced', 'whoiswho' ), __( 'See the nominees', 'whoiswho' ), '/awards/nominees/' );
			case self::OPEN:
				return $this->model( $stage, __( 'Nominations open', 'whoiswho' ), __( 'Nominate someone', 'whoiswho' ), '/nominate/' );
		}
		return $this->model( $stage, __( 'Nominations are closed', 'whoiswho' ), __( 'Read the awards rules', 'whoiswho' ), '/awards/rules/' );
	}

	/**
	 * The label for the Nominate entry in navigation: the switch alone decides it.
	 */
	public function nav_label(): string {
		return $this->nominations_open() ? __( 'Nominate', 'whoiswho' ) : __( 'Nominations closed', 'whoiswho' );
	}

	/**
	 * The Nominate link, or null while the switch is off so menus drop it.
	 */
	public function nominate_url(): ?string {
		return $this->nominations_open() ? home_url( '/nominate/' ) : null;
	}

	/**
	 * Whether the home page shows an awards button: only once nominees or winners are public.
	 *
	 * @return array{stage: string, status: string, label: string, url: string}|null
	 */
	public function home_button(): ?array {
		$button = $this->button();
		return in_array( $button['stage'], array( self::WINNERS, self::NOMINEES ), true ) ? $button : null;
	}

	/**
	 * Drop the Nominate entry from menus while Nominations is off.
	 *
	 * @param array<int, object> $items Menu items.
	 * @return array<int, object>
	 */
	public static function filter_menu_items( $items ) {
		if ( ! is_array( $items ) || self::for_site()->nominations_open() ) {
			return $items;
		}
		return array_values(
			array_filter(
				$items,
				static function ( $item ): bool {
					$path = is_object( $item ) && isset( $item->url ) ? wp_parse_url( (string) $item->url, PHP_URL_PATH ) : '';
					return '/nominate' !== rtrim( (string) $path, '/' );
				}
			)
		);
	}

	/**
	 * Build the model.
	 *
	 * @param string $stage  Stage.
	 * @param string $status Status label.
	 * @param string $label  Button label.
	 * @param string $path   Path.
	 * @return array{stage: string, status: string, label: string, url: string}
	 */
	private function model( string $stage, string $status, string $label, string $path ): array {
		return array(
			'stage'  => $stage,
			'status' => $status,
			'label'  => $label,
			'url'    => home_url( $path ),
		);
	}
}
