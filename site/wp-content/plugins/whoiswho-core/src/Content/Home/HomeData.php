<?php
/**
 * The front page view model (REQ-264, REQ-198, REQ-001): one service returns every block the page needs so the theme holds no
 * logic. On the record and the stories come from the curation engines; Events are the next three upcoming; the nominees block and
 * the award marquee come from providers that Awards supplies (through the wiw_home_nominees_provider and
 * wiw_home_categories_provider filters), so before nominees are published the nominees block is absent, not empty. There is no
 * awards band in the model: the stage-aware band is gone (REQ-001).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Home;

use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Curation\ProfileCuration;
use WhoIsWho\Core\Content\Curation\StoryCuration;
use WhoIsWho\Core\Content\PublicEvent;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Home data.
 */
final class HomeData {

	public const EVENT_COUNT = 3;

	/**
	 * On the record.
	 *
	 * @var ProfileCuration
	 */
	private ProfileCuration $profiles;

	/**
	 * Stories.
	 *
	 * @var StoryCuration
	 */
	private StoryCuration $stories;

	/**
	 * Returns the next upcoming events for a count.
	 *
	 * @var callable
	 */
	private $events;

	/**
	 * Nominees provider, null until Awards supplies one.
	 *
	 * @var NomineesProvider|null
	 */
	private ?NomineesProvider $nominees;

	/**
	 * Categories provider, null until Awards supplies one.
	 *
	 * @var CategoriesProvider|null
	 */
	private ?CategoriesProvider $categories;

	/**
	 * Constructor.
	 *
	 * @param ProfileCuration         $profiles   On the record engine.
	 * @param StoryCuration           $stories    Stories engine.
	 * @param callable|null           $events     Count to upcoming events (PublicEvent by default).
	 * @param NomineesProvider|null   $nominees   Nominees provider.
	 * @param CategoriesProvider|null $categories Categories provider.
	 */
	public function __construct( ProfileCuration $profiles, StoryCuration $stories, ?callable $events = null, ?NomineesProvider $nominees = null, ?CategoriesProvider $categories = null ) {
		$this->profiles   = $profiles;
		$this->stories    = $stories;
		$this->events     = $events ?? array( PublicEvent::class, 'next_upcoming' );
		$this->nominees   = $nominees;
		$this->categories = $categories;
	}

	/**
	 * Home data for this site, with the providers Awards registers.
	 */
	public static function for_site(): self {
		$settings = new CurationSettings( AuditLogger::for_site() );
		$nominees = apply_filters( 'wiw_home_nominees_provider', null );
		$names    = apply_filters( 'wiw_home_categories_provider', null );
		return new self(
			new ProfileCuration( $settings ),
			new StoryCuration( $settings ),
			null,
			$nominees instanceof NomineesProvider ? $nominees : null,
			$names instanceof CategoriesProvider ? $names : null
		);
	}

	/**
	 * Every block of the front page. An absent block is an empty array (events) or null (nominees and marquee).
	 *
	 * @return array{on_the_record: array<int, mixed>, stories: array<string, mixed>, events: array<int, array<string, mixed>>, events_url: string, nominees: array<int, array<string, mixed>>|null, marquee: array<int, string>|null}
	 */
	public function view(): array {
		$events  = array_values( (array) ( $this->events )( self::EVENT_COUNT ) );
		$nominee = null === $this->nominees ? array() : $this->nominees->nominees();
		$names   = null === $this->categories ? array() : $this->clean_names( $this->categories->names() );
		return array(
			'on_the_record' => $this->profiles->items( CurationSettings::ON_THE_RECORD ),
			'stories'       => $this->stories->items( CurationSettings::STORIES_HOME ),
			'events'        => array_slice( $events, 0, self::EVENT_COUNT ),
			'events_url'    => home_url( '/events/' ),
			'nominees'      => array() === $nominee ? null : $nominee,
			'marquee'       => array() === $names ? null : $names,
		);
	}

	/**
	 * Category names as plain text.
	 *
	 * @param array<int, string> $names Names.
	 * @return array<int, string>
	 */
	private function clean_names( array $names ): array {
		$out = array();
		foreach ( $names as $name ) {
			$name = trim( wp_strip_all_tags( (string) $name ) );
			if ( '' !== $name ) {
				$out[] = $name;
			}
		}
		return $out;
	}
}
