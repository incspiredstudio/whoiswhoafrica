<?php
/**
 * Seeds fictional sample content for local and staging previews, only through the plugin's own services: profiles enter and change
 * state through Review\StateMachine, fields go through Directory\Fields\ProfileFields, sponsored and featured stories through
 * Content\Stories\StoryFlags, award categories and nominations through the Awards services, curation through
 * Content\Curation\CurationSettings, and the Nominations switch through the settings service. Every post and term it makes carries
 * the `_wiw_sample` marker, so content:find-sample finds it and sample:remove deletes it. Running it twice makes nothing twice.
 * All names, organisations, events and numbers are invented (REQ-208, REQ-052, REQ-226, REQ-262).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli\Sample;

use WhoIsWho\Core\Awards\CategoryService;
use WhoIsWho\Core\Awards\NominationService;
use WhoIsWho\Core\Compliance\Legal\LegalPages;
use WhoIsWho\Core\Content\Curation\CurationSettings;
use WhoIsWho\Core\Content\Events\EventFields;
use WhoIsWho\Core\Content\Events\EventPostType;
use WhoIsWho\Core\Content\Events\EventValidator;
use WhoIsWho\Core\Content\Stories\LinkedProfiles;
use WhoIsWho\Core\Content\Stories\StoryFlags;
use WhoIsWho\Core\Content\Stories\StoryTypes;
use WhoIsWho\Core\Directory\Fields\ProfileFields;
use WhoIsWho\Core\Directory\PlaceSeeder;
use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\ProvenanceRepository;
use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Cli\Output;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Core\Platform\Setup\SampleBundle;
use WhoIsWho\Core\Review\StateMachine;

/**
 * Sample seeder.
 */
final class SampleSeeder {

	/**
	 * Post status of a live page or event (a constant, so the profile-state rule sees no literal here).
	 */
	private const LIVE = 'publish';

	/**
	 * The dataset (config/sample-data.json).
	 *
	 * @var array<string, mixed>
	 */
	private array $data;

	/**
	 * Staff user the services act as.
	 *
	 * @var int
	 */
	private int $actor;

	/**
	 * Audit logger shared by the services.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Registry of what lives outside posts and terms.
	 *
	 * @var array<string, mixed>
	 */
	private array $registry;

	/**
	 * Profile post ids by sample key.
	 *
	 * @var array<string, int>
	 */
	private array $profile_ids = array();

	/**
	 * Story post ids by sample key.
	 *
	 * @var array<string, int>
	 */
	private array $story_ids = array();

	/**
	 * Event post ids by sample key.
	 *
	 * @var array<string, int>
	 */
	private array $event_ids = array();

	/**
	 * Counts of what this run created.
	 *
	 * @var array<string, int>
	 */
	private array $created = array();

	/**
	 * The sample profiles shipped with the plugin, used instead of the invented ones when present.
	 *
	 * @var SampleBundle|null
	 */
	private ?SampleBundle $bundle;

	/**
	 * Where the bundle import stopped in this batch, when it ran.
	 *
	 * @var int|null
	 */
	private ?int $bundle_cursor = null;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $data   The dataset.
	 * @param int                  $actor  Staff user id (an Administrator).
	 * @param SampleBundle|null    $bundle The shipped sample profiles; when it holds a bundle, the Profiles step imports it instead of the invented profiles.
	 */
	public function __construct( array $data, int $actor, ?SampleBundle $bundle = null ) {
		$this->data     = $data;
		$this->actor    = $actor;
		$this->bundle   = null !== $bundle && $bundle->available() ? $bundle : null;
		$this->audit    = AuditLogger::for_site();
		$this->registry = SampleMarker::registry();
	}

	/**
	 * The dataset file shipped with the plugin.
	 *
	 * @return array<string, mixed>
	 * @throws \RuntimeException When the file is missing or invalid.
	 */
	public static function load_data(): array {
		$file = dirname( __DIR__, 4 ) . '/config/sample-data.json';
		$json = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file inside the plugin.
		$data = false === $json ? null : json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'The sample dataset is missing.' );
		}
		return $data;
	}

	/**
	 * Seed everything. Safe to run again.
	 *
	 * @param Output $output Output.
	 * @return array<string, int> What was created, by kind.
	 */
	public function seed( Output $output ): array {
		foreach ( self::STEPS as $step ) {
			$total  = $this->step_total( $step );
			$offset = 0;
			do {
				$offset = $this->run_step( $step, $offset, max( 1, $total ) );
			} while ( $offset < $total );
		}
		foreach ( $this->created as $kind => $count ) {
			$output->line( sprintf( 'Created %d %s.', $count, $kind ) );
		}
		return $this->created;
	}

	/**
	 * The steps, in order. Each is safe to repeat; the setup screen runs them a batch at a time (see Setup\DemoImport).
	 */
	public const STEPS = array( 'terms', 'profiles', 'stories', 'comments', 'events', 'images', 'awards', 'curation', 'pages', 'settings' );

	/**
	 * How many units of work a step has, so a caller can run it in batches and show progress.
	 *
	 * @param string $step One of STEPS.
	 */
	public function step_total( string $step ): int {
		$profiles = count( (array) $this->data['profiles'] );
		$stories  = count( (array) $this->data['stories'] );
		$events   = count( (array) $this->data['events'] );
		switch ( $step ) {
			case 'profiles':
				return null === $this->bundle ? $profiles : max( 1, $this->bundle->total() );
			case 'stories':
			case 'comments':
				return $stories;
			case 'events':
				return $events;
			case 'images':
				return $profiles + $stories + $events;
			case 'awards':
				return count( (array) $this->data['categories'] ) + count( (array) $this->data['nominations'] );
			default:
				return 1;
		}
	}

	/**
	 * What this run created so far, by kind.
	 *
	 * @return array<string, int>
	 */
	public function created(): array {
		return $this->created;
	}

	/**
	 * Run one batch of a step: `$limit` units starting at `$offset`. Safe to repeat, so a batch that failed or timed out is simply run again.
	 *
	 * @param string $step   One of STEPS.
	 * @param int    $offset Where to start.
	 * @param int    $limit  Units in this batch.
	 * @return int The offset to continue from (the step's total or more once the step is finished).
	 * @throws \InvalidArgumentException For an unknown step.
	 */
	public function run_step( string $step, int $offset, int $limit ): int {
		if ( ! in_array( $step, self::STEPS, true ) ) {
			throw new \InvalidArgumentException( 'Unknown sample step.' );
		}
		wp_set_current_user( $this->actor );
		$offset = max( 0, $offset );
		$limit  = max( 1, $limit );
		switch ( $step ) {
			case 'terms':
				$this->terms();
				break;
			case 'profiles':
				$this->profiles( $offset, $limit );
				break;
			case 'stories':
				$this->stories( $offset, $limit );
				break;
			case 'comments':
				SampleComments::make( $this->data, $offset, $limit, $this->created );
				break;
			case 'events':
				$this->events( $offset, $limit );
				break;
			case 'images':
				$this->images( $offset, $limit );
				break;
			case 'awards':
				$this->awards( $offset, $limit );
				break;
			case 'curation':
				$this->curation();
				break;
			case 'pages':
				$this->pages();
				break;
			case 'settings':
				$this->nominations_switch();
				break;
		}//end switch
		SampleMarker::save_registry( $this->registry );
		if ( 'settings' === $step ) {
			flush_rewrite_rules( false );
		}
		if ( 'profiles' === $step && null !== $this->bundle_cursor ) {
			return max( $this->bundle_cursor, $offset + 1 );
		}
		return $offset + $limit;
	}

	/**
	 * The post id of a sample profile, or 0.
	 *
	 * @param string $key Sample key.
	 */
	private function profile_id( string $key ): int {
		if ( ! isset( $this->profile_ids[ $key ] ) ) {
			$this->profile_ids[ $key ] = SampleMarker::find_post( 'profile:' . $key, ProfileType::POST_TYPE ) ?? 0;
		}
		return $this->profile_ids[ $key ];
	}

	/**
	 * The profile an award nomination or a home page pin uses for a sample key. It is always an invented profile, never one of
	 * the shipped sample profiles: those are real people and organisations, and the demo's awards, citations and home page picks
	 * are invented, so they must not name anyone real. When the shipped profiles replaced the invented ones, a few invented
	 * stand-ins (STAND_INS of them) are created here for the awards and the home page; other keys are skipped.
	 *
	 * @param string $key Sample key.
	 */
	private function linked_profile_id( string $key ): int {
		$id = $this->profile_id( $key );
		if ( $id > 0 || null === $this->bundle || ! in_array( $key, $this->stand_in_keys(), true ) ) {
			return $id;
		}
		foreach ( (array) $this->data['profiles'] as $row ) {
			if ( (string) $row['key'] === $key ) {
				return $this->invent_profile( (array) $row, new StateMachine(), new ProfileFields(), new ProvenanceRepository() );
			}
		}
		return 0;
	}

	/**
	 * How many invented stand-in profiles the awards and the home page use when the shipped sample profiles are imported.
	 */
	public const STAND_INS = 8;

	/**
	 * The invented profile keys used as stand-ins: the home page pins first, then the nominees, at most STAND_INS.
	 *
	 * @return string[]
	 */
	private function stand_in_keys(): array {
		$keys = array_map( 'strval', (array) $this->data['pin_profiles'] );
		foreach ( (array) $this->data['nominations'] as $row ) {
			$keys[] = (string) $row['profile'];
		}
		return array_slice( array_values( array_unique( $keys ) ), 0, self::STAND_INS );
	}

	/**
	 * The post id of a sample story, or 0.
	 *
	 * @param string $key Sample key (slug).
	 */
	private function story_id( string $key ): int {
		if ( ! isset( $this->story_ids[ $key ] ) ) {
			$this->story_ids[ $key ] = SampleMarker::find_post( 'story:' . $key, 'post' ) ?? 0;
		}
		return $this->story_ids[ $key ];
	}

	/**
	 * The post id of a sample event, or 0.
	 *
	 * @param string $key Sample key (slug).
	 */
	private function event_id( string $key ): int {
		if ( ! isset( $this->event_ids[ $key ] ) ) {
			$this->event_ids[ $key ] = SampleMarker::find_post( 'event:' . $key, EventPostType::TYPE ) ?? 0;
		}
		return $this->event_ids[ $key ];
	}

	/**
	 * Count something created.
	 *
	 * @param string $kind Kind.
	 */
	private function count( string $kind ): void {
		$this->created[ $kind ] = ( $this->created[ $kind ] ?? 0 ) + 1;
	}

	/**
	 * Industries, organisation types and cities under their countries.
	 */
	private function terms(): void {
		( new PlaceSeeder() )->seed();
		foreach ( (array) $this->data['industries'] as $name ) {
			$this->term( (string) $name, Taxonomies::INDUSTRY, 0 );
		}
		foreach ( (array) $this->data['org_types'] as $name ) {
			$this->term( (string) $name, Taxonomies::ORG_TYPE, 0 );
		}
		foreach ( (array) $this->data['places'] as $country => $cities ) {
			$parent_id = $this->country_id( (string) $country );
			foreach ( (array) $cities as $city ) {
				$id = $this->term( (string) $city, Taxonomies::PLACE, $parent_id );
				if ( $id > 0 && '' === (string) get_term_meta( $id, Taxonomies::META_KIND, true ) ) {
					update_term_meta( $id, Taxonomies::META_KIND, Taxonomies::KIND_CITY );
				}
			}
		}
	}

	/**
	 * The top-level place term for a country name.
	 *
	 * @param string $name Country.
	 */
	private function country_id( string $name ): int {
		$found = term_exists( $name, Taxonomies::PLACE, 0 );
		if ( is_array( $found ) ) {
			return (int) $found['term_id'];
		}
		$made = wp_insert_term( $name, Taxonomies::PLACE, array( 'parent' => 0 ) );
		if ( is_wp_error( $made ) ) {
			return 0;
		}
		SampleMarker::mark_term( (int) $made['term_id'], 'place:' . $name );
		update_term_meta( (int) $made['term_id'], Taxonomies::META_KIND, Taxonomies::KIND_COUNTRY );
		return (int) $made['term_id'];
	}

	/**
	 * Find or create a term; terms this run creates are marked.
	 *
	 * @param string $name     Name.
	 * @param string $taxonomy Taxonomy.
	 * @param int    $parent_id   Parent term id.
	 */
	private function term( string $name, string $taxonomy, int $parent_id ): int {
		$found = term_exists( $name, $taxonomy, $parent_id );
		if ( is_array( $found ) ) {
			return (int) $found['term_id'];
		}
		$made = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent_id ) );
		if ( is_wp_error( $made ) ) {
			return 0;
		}
		SampleMarker::mark_term( (int) $made['term_id'], $taxonomy . ':' . $parent_id . ':' . $name );
		$this->count( 'terms' );
		return (int) $made['term_id'];
	}

	/**
	 * The profiles, each through the state machine.
	 *
	 * @param int $offset First profile of the batch.
	 * @param int $limit  Profiles in the batch.
	 * @throws \RuntimeException When a sample item cannot be created.
	 */
	private function profiles( int $offset, int $limit ): void {
		if ( null !== $this->bundle ) {
			$before              = count( SampleBundle::public_profile_ids() );
			$this->bundle_cursor = $this->bundle->run( $this->actor, $limit );
			$made                = count( SampleBundle::public_profile_ids() ) - $before;
			if ( $made > 0 ) {
				$this->created['profiles'] = ( $this->created['profiles'] ?? 0 ) + $made;
			}
			return;
		}
		$machine    = new StateMachine();
		$fields     = new ProfileFields();
		$provenance = new ProvenanceRepository();
		foreach ( array_slice( (array) $this->data['profiles'], $offset, $limit ) as $row ) {
			$this->invent_profile( (array) $row, $machine, $fields, $provenance );
		}
	}

	/**
	 * Create one invented profile through the state machine, once: an existing one is reused.
	 *
	 * @param array<string, mixed>  $row        Sample row.
	 * @param StateMachine          $machine    State machine.
	 * @param ProfileFields         $fields     Field store.
	 * @param ProvenanceRepository  $provenance Provenance.
	 * @return int The profile id.
	 * @throws \RuntimeException When the profile cannot be created.
	 */
	private function invent_profile( array $row, StateMachine $machine, ProfileFields $fields, ProvenanceRepository $provenance ): int {
		$key = (string) $row['key'];
		$id  = SampleMarker::find_post( 'profile:' . $key, ProfileType::POST_TYPE );
		if ( null !== $id ) {
			$this->profile_ids[ $key ] = $id;
			return $id;
		}
		$id = wp_insert_post(
			array(
				'post_type'   => ProfileType::POST_TYPE,
				'post_status' => ProfileType::STATUS_HIDDEN,
				'post_title'  => (string) $row['name'],
				'post_name'   => (string) $row['slug'],
			),
			true
		);
		if ( is_wp_error( $id ) || (int) $id < 1 ) {
			throw new \RuntimeException( 'A sample profile could not be created.' );
		}
		$id = (int) $id;
		SampleMarker::mark_post( $id, 'profile:' . $key );
		$this->profile_ids[ $key ] = $id;
		$state                     = (string) $row['state'];
		$start                     = in_array( $state, array( 'rejected', 'imported', 'removed' ), true ) ? PubState::IMPORTED_NOT_PUBLIC : PubState::PUBLISHED_PENDING;
		$machine->enter(
			$id,
			$start,
			array(
				'entity_type' => (string) $row['type'],
				'origin'      => 'import',
				'source'      => 'sample',
			)
		);
		$values = (array) $row['fields'];
		if ( isset( $row['industry'] ) ) {
			$values['industry'] = (string) $row['industry'];
		}
		foreach ( $values as $name => $value ) {
			$fields->set( $id, (string) $name, $value );
			if ( 'name' !== $name ) {
				// A fact shows publicly only with a source; this one is an invented address.
				$provenance->record(
					$id,
					array(
						'field'        => (string) $name,
						'source_url'   => 'https://example.net/sample-sources/' . (string) $row['slug'],
						'retrieved_at' => gmdate( 'Y-m-d H:i:s' ),
						'confidence'   => 'high',
						'method'       => 'manual',
					)
				);
			}
		}
		$this->attach_terms( $id, $row );
		$this->settle( $machine, $id, $state );
		SampleMarker::seal_post( $id );
		$this->count( 'profiles' );
		return $id;
	}

	/**
	 * Move a new profile to the state the sample row asks for.
	 *
	 * @param StateMachine $machine StateMachine.
	 * @param int          $id      Profile.
	 * @param string       $state   reviewed, pending, unpublished, rejected, imported or removed.
	 */
	private function settle( StateMachine $machine, int $id, string $state ): void {
		switch ( $state ) {
			case 'reviewed':
				$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', $this->actor );
				break;
			case 'unpublished':
				$machine->transition( $id, PubState::PUBLISHED_REVIEWED, '', $this->actor );
				$machine->transition( $id, PubState::UNPUBLISHED, 'Sample: taken down to exercise the state.', $this->actor );
				break;
			case 'rejected':
				$machine->transition( $id, PubState::REJECTED, 'Sample: rejected to exercise the state.', $this->actor );
				break;
			case 'removed':
				$machine->transition( $id, PubState::REMOVED_SUPPRESSED, 'Sample: removed to exercise the state.', $this->actor );
				break;
		}
	}

	/**
	 * Industry, organisation type and place terms for a profile.
	 *
	 * @param int                  $id  Profile.
	 * @param array<string, mixed> $row Sample row.
	 */
	private function attach_terms( int $id, array $row ): void {
		$industry = term_exists( (string) ( $row['industry'] ?? '' ), Taxonomies::INDUSTRY, 0 );
		if ( is_array( $industry ) ) {
			wp_set_object_terms( $id, array( (int) $industry['term_id'] ), Taxonomies::INDUSTRY );
		}
		$org_type = (string) ( $row['fields']['org_type'] ?? '' );
		$type     = '' === $org_type ? false : term_exists( $org_type, Taxonomies::ORG_TYPE, 0 );
		if ( is_array( $type ) ) {
			wp_set_object_terms( $id, array( (int) $type['term_id'] ), Taxonomies::ORG_TYPE );
		}
		$country = term_exists( (string) ( $row['country'] ?? '' ), Taxonomies::PLACE, 0 );
		if ( is_array( $country ) ) {
			$city = term_exists( (string) ( $row['city'] ?? '' ), Taxonomies::PLACE, (int) $country['term_id'] );
			wp_set_object_terms( $id, array( is_array( $city ) ? (int) $city['term_id'] : (int) $country['term_id'] ), Taxonomies::PLACE );
		}
	}

	/**
	 * The stories: a type first (a story without one cannot publish), then dates, links, Featured and Sponsored.
	 *
	 * @param int $offset First story of the batch.
	 * @param int $limit  Stories in the batch.
	 * @throws \RuntimeException When a sample item cannot be created.
	 */
	private function stories( int $offset, int $limit ): void {
		$flags = new StoryFlags( $this->audit );
		foreach ( array_slice( (array) $this->data['stories'], $offset, $limit ) as $row ) {
			$key = (string) $row['slug'];
			$id  = SampleMarker::find_post( 'story:' . $key, 'post' );
			if ( null !== $id ) {
				$this->story_ids[ $key ] = $id;
				continue;
			}
			$stamp = time() + ( (int) $row['days'] ) * DAY_IN_SECONDS;
			$id    = wp_insert_post(
				array(
					'post_type'    => 'post',
					'post_status'  => 'draft',
					'post_title'   => (string) $row['title'],
					'post_name'    => $key,
					'post_author'  => $this->actor,
					'post_excerpt' => (string) $row['excerpt'],
					'post_content' => self::blocks( (array) $row['paras'], (string) $row['quote'] ),
				),
				true
			);
			if ( is_wp_error( $id ) || (int) $id < 1 ) {
				throw new \RuntimeException( 'A sample story could not be created.' );
			}
			$id = (int) $id;
			SampleMarker::mark_post( $id, 'story:' . $key );
			$this->story_ids[ $key ] = $id;
			wp_set_object_terms( $id, (string) $row['type'], StoryTypes::TAXONOMY );
			$industry = term_exists( (string) $row['industry'], Taxonomies::INDUSTRY, 0 );
			if ( is_array( $industry ) && taxonomy_exists( Taxonomies::INDUSTRY ) ) {
				wp_set_object_terms( $id, array( (int) $industry['term_id'] ), Taxonomies::INDUSTRY );
			}
			$linked = array();
			foreach ( (array) $row['profiles'] as $profile_key ) {
				if ( $this->profile_id( (string) $profile_key ) > 0 ) {
					$linked[] = $this->profile_id( (string) $profile_key );
				}
			}
			update_post_meta( $id, LinkedProfiles::META, LinkedProfiles::sanitise( $linked ) );
			$when = $stamp > time() || ! empty( $row['scheduled'] ) ? 'future' : self::LIVE;
			wp_update_post(
				array(
					'ID'            => $id,
					'post_status'   => $when,
					'edit_date'     => true,
					'post_date'     => wp_date( 'Y-m-d H:i:s', $stamp ),
					'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $stamp ),
				)
			);
			if ( ! empty( $row['featured'] ) ) {
				$flags->set_featured( $id, true );
			}
			if ( is_array( $row['sponsor'] ) ) {
				$flags->commerce_write(
					$id,
					(string) $row['sponsor']['name'],
					(string) $row['sponsor']['placement'],
					gmdate( 'Y-m-d', time() + ( (int) $row['sponsor']['start'] ) * DAY_IN_SECONDS ),
					gmdate( 'Y-m-d', time() + ( (int) $row['sponsor']['end'] ) * DAY_IN_SECONDS )
				);
			}
			SampleMarker::seal_post( $id );
			$this->count( 'stories' );
		}//end foreach
	}

	/**
	 * The events, checked by the same validator the staff screen uses.
	 *
	 * @param int $offset First event of the batch.
	 * @param int $limit  Events in the batch.
	 * @throws \RuntimeException When a sample item cannot be created.
	 */
	private function events( int $offset, int $limit ): void {
		$validator = new EventValidator();
		foreach ( array_slice( (array) $this->data['events'], $offset, $limit ) as $row ) {
			$key = (string) $row['slug'];
			$id  = SampleMarker::find_post( 'event:' . $key, EventPostType::TYPE );
			if ( null !== $id ) {
				$this->event_ids[ $key ] = $id;
				continue;
			}
			$start  = time() + ( (int) $row['days'] ) * DAY_IN_SECONDS;
			$end    = (int) $row['span'] > 0 ? $start + (int) $row['span'] * DAY_IN_SECONDS : 0;
			$day    = wp_date( 'Y-m-d', $start );
			$result = $validator->validate(
				array(
					'start'            => $day . ' ' . (string) $row['time'],
					'end'              => 0 === $end ? '' : wp_date( 'Y-m-d', $end ) . ' 17:00',
					'online'           => ! empty( $row['online'] ) ? '1' : '0',
					'venue'            => (string) $row['venue'],
					'city'             => (string) $row['city'],
					'country'          => (string) $row['country'],
					'organiser'        => (string) $row['organiser'],
					'registration_url' => (string) $row['url'],
					'price_note'       => (string) $row['price'],
				)
			);
			if ( array() !== $result['errors'] ) {
				throw new \RuntimeException( esc_html( 'A sample event is not valid: ' . implode( ', ', array_keys( $result['errors'] ) ) . '.' ) );
			}
			$id = wp_insert_post(
				array(
					'post_type'    => EventPostType::TYPE,
					'post_status'  => self::LIVE,
					'post_title'   => (string) $row['title'],
					'post_name'    => $key,
					'post_author'  => $this->actor,
					'post_excerpt' => (string) $row['summary'],
					'post_content' => self::blocks( (array) $row['paras'], '' ),
				),
				true
			);
			if ( is_wp_error( $id ) || (int) $id < 1 ) {
				throw new \RuntimeException( 'A sample event could not be created.' );
			}
			$id = (int) $id;
			SampleMarker::mark_post( $id, 'event:' . $key );
			$this->event_ids[ $key ] = $id;
			foreach ( $result['values'] as $name => $value ) {
				update_post_meta( $id, EventFields::META_PREFIX . $name, $value );
			}
			$industry = term_exists( (string) $row['industry'], Taxonomies::INDUSTRY, 0 );
			if ( is_array( $industry ) && taxonomy_exists( Taxonomies::INDUSTRY ) ) {
				wp_set_object_terms( $id, array( (int) $industry['term_id'] ), Taxonomies::INDUSTRY );
			}
			SampleMarker::seal_post( $id );
			$this->count( 'events' );
		}//end foreach
		if ( $offset + $limit < count( (array) $this->data['events'] ) ) {
			return;
		}
		foreach ( (array) $this->data['stories'] as $row ) {
			$story = $this->story_id( (string) $row['slug'] );
			$event = $this->event_id( (string) $row['event'] );
			if ( $story > 0 && $event > 0 && 0 === (int) get_post_meta( $story, 'related_event_id', true ) ) {
				update_post_meta( $story, 'related_event_id', $event );
			}
		}
	}

	/**
	 * Generated placeholder images: a portrait for each profile, a cover for each story and a poster for each event. A item that
	 * already has one keeps it, so running again makes nothing twice. The units are the profiles, then the stories, then the events.
	 *
	 * @param int $offset First unit of the batch.
	 * @param int $limit  Units in the batch.
	 */
	private function images( int $offset, int $limit ): void {
		$profiles = (array) $this->data['profiles'];
		$stories  = (array) $this->data['stories'];
		$events   = (array) $this->data['events'];
		$last     = min( $offset + $limit, count( $profiles ) + count( $stories ) + count( $events ) );
		for ( $unit = $offset; $unit < $last; $unit++ ) {
			if ( $unit < count( $profiles ) ) {
				$row = $profiles[ $unit ];
				$id  = $this->profile_id( (string) $row['key'] );
				if ( $id < 1 || (int) get_post_meta( $id, PublicProfile::IMAGE_META, true ) > 0 ) {
					continue;
				}
				$image = SampleImages::portrait( 'profile:' . (string) $row['key'], (string) $row['name'] . ' (sample portrait)' );
				if ( $image > 0 ) {
					update_post_meta( $id, PublicProfile::IMAGE_META, $image );
					$this->count( 'images' );
				}
				continue;
			}
			$is_story = $unit < count( $profiles ) + count( $stories );
			$row      = $is_story ? $stories[ $unit - count( $profiles ) ] : $events[ $unit - count( $profiles ) - count( $stories ) ];
			$id       = $is_story ? $this->story_id( (string) $row['slug'] ) : $this->event_id( (string) $row['slug'] );
			if ( $id < 1 || (int) get_post_thumbnail_id( $id ) > 0 ) {
				continue;
			}
			$image = SampleImages::cover( ( $is_story ? 'story:' : 'event:' ) . (string) $row['slug'], (string) $row['title'] . ' (sample cover)' );
			if ( $image > 0 ) {
				set_post_thumbnail( $id, $image );
				$this->count( 'images' );
			}
		}//end for
	}

	/**
	 * Award categories, and nominees and winners for the profiles above, through the Awards services. The units are the categories,
	 * then the nominations.
	 *
	 * @param int $offset First unit of the batch.
	 * @param int $limit  Units in the batch.
	 */
	private function awards( int $offset, int $limit ): void {
		$categories = CategoryService::for_site();
		$ids        = (array) ( $this->registry['categories'] ?? array() );
		$cats       = (array) $this->data['categories'];
		foreach ( array_slice( $cats, $offset, $limit ) as $row ) {
			$title = (string) $row['title'];
			if ( isset( $ids[ $title ] ) && null !== $categories->find( (int) $ids[ $title ] ) ) {
				continue;
			}
			$ids[ $title ] = $categories->add( (string) $row['group'], $title );
			$this->count( 'award categories' );
		}
		$this->registry['categories'] = $ids;
		$service                      = NominationService::for_site();
		$noms                         = (array) ( $this->registry['nominations'] ?? array() );
		$first                        = max( 0, $offset - count( $cats ) );
		$room                         = $limit - max( 0, count( $cats ) - $offset );
		if ( $room > 0 ) {
			foreach ( array_slice( (array) $this->data['nominations'], $first, $room ) as $row ) {
				$key = (string) $row['profile'] . '|' . (string) $row['category'];
				if ( isset( $noms[ $key ] ) && null !== $service->find( (int) $noms[ $key ] ) ) {
					continue;
				}
				$profile  = $this->linked_profile_id( (string) $row['profile'] );
				$category = (int) ( $ids[ (string) $row['category'] ] ?? 0 );
				if ( $profile < 1 || $category < 1 ) {
					continue;
				}
				$id = $service->add_profile( $profile, $category, (string) $row['citation'] );
				if ( ! empty( $row['winner'] ) ) {
					$service->set_stage( $id, 'winner' );
				}
				$service->set_status( $id, 'live' );
				$noms[ $key ] = $id;
				$this->count( 'nominations' );
			}
		}
		$this->registry['nominations'] = $noms;
	}

	/**
	 * Pin profiles and stories on the home page.
	 */
	private function curation(): void {
		$settings = new CurationSettings( $this->audit );
		$pins     = array(
			CurationSettings::ON_THE_RECORD => array_values( array_unique( array_filter( array_map( fn ( $key ) => $this->linked_profile_id( (string) $key ), (array) $this->data['pin_profiles'] ) ) ) ),
			CurationSettings::STORIES_HOME  => array_values( array_filter( array_map( fn ( $key ) => $this->story_id( (string) $key ), (array) $this->data['pin_stories'] ) ) ),
		);
		foreach ( $pins as $block => $pinned ) {
			$option = CurationSettings::OPTION_PREFIX . $block;
			if ( ! array_key_exists( 'curation:' . $block, (array) ( $this->registry['prev'] ?? array() ) ) ) {
				$this->registry['prev'][ 'curation:' . $block ] = get_option( $option, null );
			}
			$settings->set(
				$block,
				array(
					'mode'   => 'pinned_then_fill',
					'pinned' => $pinned,
					'count'  => CurationSettings::ON_THE_RECORD === $block ? 8 : 3,
				),
				'Sample data'
			);
			// What the demo set, so removal can tell whether a person changed it since.
			$this->registry['set'][ 'curation:' . $block ] = get_option( $option, null );
		}
	}

	/**
	 * The pages the templates need. A page that already exists is left alone and not marked.
	 */
	private function pages(): void {
		$home = $this->page( 'home', 'Home', '', 0 );
		if ( $home > 0 ) {
			foreach ( array( 'show_on_front', 'page_on_front' ) as $option ) {
				if ( ! array_key_exists( 'option:' . $option, (array) ( $this->registry['prev'] ?? array() ) ) ) {
					$this->registry['prev'][ 'option:' . $option ] = get_option( $option, null );
				}
			}
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
		}
		$this->page( 'about', 'About', self::pattern( 'whoiswho/about-page', 'Fictional About page for the sample directory.' ), 0 );
		$this->page( 'faq', 'Frequently asked questions', SamplePageCopy::faq_blocks(), 0 );
		$awards = $this->page( 'awards', 'Awards', self::blocks( array( 'Fictional awards programme for the sample directory. Categories, nominees and winners here are invented.' ), '' ), 0 );
		$this->page( 'rules', 'Awards rules', SamplePageCopy::rules_blocks(), $awards );
		$this->page( 'nominate', 'Nominate', self::blocks( array( 'Fictional nomination page. The nomination form is not built yet.' ), '' ), 0 );
		$this->legal_pages();
	}

	/**
	 * Make a page once and mark it; reuse a page that is already there.
	 *
	 * @param string $slug    Slug.
	 * @param string $title   Title.
	 * @param string $content Content.
	 * @param int    $parent_id  Parent page.
	 * @throws \RuntimeException When a sample item cannot be created.
	 */
	private function page( string $slug, string $title, string $content, int $parent_id ): int {
		$found = SampleMarker::find_post( 'page:' . $slug, 'page' );
		if ( null === $found ) {
			$path  = 0 === $parent_id ? $slug : (string) get_post_field( 'post_name', $parent_id ) . '/' . $slug;
			$other = get_page_by_path( $path, OBJECT, 'page' );
			if ( $other instanceof \WP_Post ) {
				return $other->ID;
			}
		} else {
			return $found;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => self::LIVE,
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_parent'  => $parent_id,
				'post_content' => $content,
				'post_author'  => $this->actor,
			),
			true
		);
		if ( is_wp_error( $id ) || (int) $id < 1 ) {
			throw new \RuntimeException( 'A sample page could not be created.' );
		}
		SampleMarker::mark_post( (int) $id, 'page:' . $slug );
		SampleMarker::seal_post( (int) $id );
		$this->count( 'pages' );
		return (int) $id;
	}

	/**
	 * Privacy, terms and the other legal pages, from the plugin's own draft wording, marked as draft legal text.
	 */
	private function legal_pages(): void {
		$before = $this->existing_slugs();
		if ( ! array_key_exists( 'option:' . LegalPages::PRIVACY_OPTION, (array) ( $this->registry['prev'] ?? array() ) ) ) {
			// LegalPages sets WordPress's privacy policy page when it creates the privacy page; removal puts this value back.
			$this->registry['prev'][ 'option:' . LegalPages::PRIVACY_OPTION ] = get_option( LegalPages::PRIVACY_OPTION, null );
		}
		( new LegalPages() )->ensure();
		foreach ( array_diff( $this->existing_slugs(), $before ) as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $page instanceof \WP_Post ) {
				SampleMarker::mark_post( $page->ID, 'page:' . $slug );
				update_post_meta( $page->ID, '_wiw_draft_legal', '1' );
				SampleMarker::seal_post( $page->ID );
				$this->count( 'pages' );
			}
		}
	}

	/**
	 * Legal page slugs that exist now.
	 *
	 * @return string[]
	 */
	private function existing_slugs(): array {
		$out = array();
		foreach ( LegalPages::SLUGS as $slug ) {
			if ( get_page_by_path( $slug, OBJECT, 'page' ) instanceof \WP_Post ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * Turn Nominations on so the Nominate links show, and remember the earlier value.
	 */
	private function nominations_switch(): void {
		$settings = SettingsService::for_site();
		if ( ! array_key_exists( 'nominations', (array) ( $this->registry['prev'] ?? array() ) ) ) {
			$this->registry['prev']['nominations'] = $settings->nominations_enabled();
		}
		if ( ! $settings->nominations_enabled() ) {
			$settings->set( SettingsService::NOMINATIONS_ENABLED, true, 'Sample data' );
		}
	}

	/**
	 * Block markup for paragraphs and an optional quote.
	 *
	 * @param string[] $paras Paragraphs.
	 * @param string   $quote Pull quote.
	 */
	public static function blocks( array $paras, string $quote ): string {
		$out = '';
		foreach ( $paras as $para ) {
			$out .= '<!-- wp:paragraph --><p>' . esc_html( (string) $para ) . '</p><!-- /wp:paragraph -->' . "\n";
		}
		if ( '' !== $quote ) {
			$out .= '<!-- wp:quote --><blockquote class="wp-block-quote"><p>' . esc_html( $quote ) . '</p></blockquote><!-- /wp:quote -->' . "\n";
		}
		return $out;
	}

	/**
	 * A registered block pattern's content, or paragraphs when the pattern is not there.
	 *
	 * @param string $pattern  Pattern name.
	 * @param string $fallback Fallback paragraph.
	 */
	private static function pattern( string $pattern, string $fallback ): string {
		$registry = \WP_Block_Patterns_Registry::get_instance();
		if ( $registry->is_registered( $pattern ) ) {
			$found = $registry->get_registered( $pattern );
			if ( is_array( $found ) && '' !== trim( (string) ( $found['content'] ?? '' ) ) ) {
				return (string) $found['content'];
			}
		}
		return self::blocks( array( $fallback ), '' );
	}
}
