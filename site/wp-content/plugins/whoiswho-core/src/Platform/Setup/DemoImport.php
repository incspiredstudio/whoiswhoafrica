<?php
/**
 * Demo content, chosen part by part, in small resumable steps (REQ-208, REQ-052, REQ-056). The administrator ticks the parts on
 * the Setup screen (Setup\DemoParts adds what a part needs); only the seeder steps for those parts run. A shared host stops a PHP
 * request after a few seconds, and seeding takes much longer, so the Setup screen asks for one batch at a time and shows a
 * progress bar. Every batch is safe to repeat (Cli\Sample\SampleSeeder keys each item), the position is stored, and a run that
 * stopped halfway resumes where it was. Everything made carries the `_wiw_sample` marker, so each part can be removed on its own
 * (Setup\DemoPartRemover keeps what a person changed) or all at once, and the launch safeguards still flag it. Profiles come from
 * the sample bundle shipped in the plugin (Setup\SampleBundle) when there is one, otherwise from the invented dataset. Only counts
 * are stored or logged, never personal data. The CLI commands use the same seeder.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Cli\Sample\SampleComments;
use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Cli\Sample\SampleSeeder;

/**
 * Demo import.
 */
final class DemoImport {

	public const OPTION       = 'wiw_demo_run';
	public const PARTS_OPTION = 'wiw_demo_parts';
	public const LOCK         = 'wiw_demo_lock';
	public const CONFIRM      = 'IMPORT DEMO';
	public const BATCH        = 8;
	public const LOCK_TTL     = 60;

	/**
	 * The dataset.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data;

	/**
	 * Whether this is a production site.
	 *
	 * @var bool
	 */
	private bool $production;

	/**
	 * The sample profiles shipped with the plugin.
	 *
	 * @var SampleBundle
	 */
	private SampleBundle $bundle;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>|null $data       Dataset (tests pass a small one); the plugin's file by default.
	 * @param bool|null                 $production Whether the site is in production; the site's environment by default.
	 * @param SampleBundle|null         $bundle     Shipped sample profiles (tests pass a temporary folder); the plugin's own by default.
	 */
	public function __construct( ?array $data = null, ?bool $production = null, ?SampleBundle $bundle = null ) {
		$this->data       = $data;
		$this->production = $production ?? ( 'production' === wp_get_environment_type() );
		$this->bundle     = $bundle ?? new SampleBundle();
	}

	/**
	 * The service for this site.
	 */
	public static function for_site(): self {
		return new self();
	}

	/**
	 * The dataset.
	 *
	 * @return array<string, mixed>
	 */
	private function data(): array {
		if ( null === $this->data ) {
			$this->data = SampleSeeder::load_data();
		}
		return $this->data;
	}

	/**
	 * A seeder for this import.
	 *
	 * @param int $actor Administrator.
	 */
	private function seeder( int $actor ): SampleSeeder {
		return new SampleSeeder( $this->data(), $actor, $this->bundle );
	}

	/**
	 * Whether the profiles come from the sample bundle shipped with the plugin (otherwise invented ones are used).
	 */
	public function ships_profiles(): bool {
		return $this->bundle->available();
	}

	/**
	 * What the demo makes, by kind, for the confirmation text.
	 *
	 * @return array<string, int>
	 */
	public function plan(): array {
		$data     = $this->data();
		$comments = 0;
		foreach ( (array) $data['stories'] as $row ) {
			if ( empty( $row['scheduled'] ) && (int) $row['days'] <= 0 ) {
				$comments += SampleComments::count_for( (string) $row['slug'] );
			}
		}
		return array(
			'profiles' => $this->ships_profiles() ? $this->bundle->total() : count( (array) $data['profiles'] ),
			'stories'  => count( (array) $data['stories'] ),
			'comments' => $comments,
			'events'   => count( (array) $data['events'] ),
			'awards'   => count( (array) $data['categories'] ),
			'pins'     => count( (array) $data['pin_profiles'] ) + count( (array) $data['pin_stories'] ),
			'pages'    => 6,
		);
	}

	/**
	 * How many real (not sample) profiles and stories the site holds. A fresh WordPress's own "Hello world" post is not real content.
	 */
	public function real_content_count(): int {
		$profiles = get_posts(
			array(
				'post_type'      => ProfileType::POST_TYPE,
				'post_status'    => array_diff( get_post_stati(), array( 'trash', 'auto-draft' ) ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- a one-off check on the setup screen.
					array(
						'key'     => SampleMarker::META,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$query    = new \WP_Query(
			array(
				'post_type'      => 'post',
				'post_status'    => array( 'publish', 'future' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- as above.
					array(
						'key'     => SampleMarker::META,
						'compare' => 'NOT EXISTS',
					),
				),
				'post__not_in'   => $this->default_post_ids(),
			)
		);
		return count( $profiles ) + (int) $query->found_posts;
	}

	/**
	 * The ids of the post WordPress creates on install ("Hello world!"), which is not content someone wrote.
	 *
	 * @return int[]
	 */
	private function default_post_ids(): array {
		$post = get_page_by_path( 'hello-world', OBJECT, 'post' );
		return $post instanceof \WP_Post ? array( $post->ID ) : array();
	}

	/**
	 * Whether any demo content is in the site.
	 */
	public function installed(): bool {
		return array() !== SampleMarker::all_post_ids() || array() !== $this->parts_status();
	}

	/**
	 * The parts on the site: part => count of its items now and when it was imported (a Unix time).
	 *
	 * @return array<string, array{count: int, at: int}>
	 */
	public function parts_status(): array {
		$stored = get_option( self::PARTS_OPTION, array() );
		$out    = array();
		foreach ( DemoParts::PARTS as $part ) {
			if ( is_array( $stored ) && isset( $stored[ $part ] ) && is_array( $stored[ $part ] ) ) {
				$out[ $part ] = array(
					'count' => DemoParts::count( $part ),
					'at'    => (int) ( $stored[ $part ]['at'] ?? 0 ),
				);
			}
		}
		return $out;
	}

	/**
	 * Why the demo cannot start now, or null. In production, a site with real content needs the confirmation to be typed.
	 *
	 * @param string $typed What was typed in the confirmation box.
	 */
	public function refusal( string $typed ): ?string {
		if ( $this->production && $this->real_content_count() > 0 && self::CONFIRM !== trim( $typed ) ) {
			return sprintf(
				/* translators: %s: the words to type. */
				__( 'This site already has real profiles or stories. To add the demo anyway, type %s in the box.', 'whoiswho' ),
				self::CONFIRM
			);
		}
		return null;
	}

	/**
	 * The stored state.
	 *
	 * @return array{status: string, step: int, cursor: int, counts: array<string, int>, error: string, images: bool, parts: string[], steps: string[]}
	 */
	public function state(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$steps  = array_values( array_intersect( SampleSeeder::STEPS, array_map( 'strval', (array) ( $stored['steps'] ?? SampleSeeder::STEPS ) ) ) );
		return array(
			'status' => (string) ( $stored['status'] ?? 'idle' ),
			'step'   => (int) ( $stored['step'] ?? 0 ),
			'cursor' => (int) ( $stored['cursor'] ?? 0 ),
			'counts' => array_map( 'intval', (array) ( $stored['counts'] ?? array() ) ),
			'error'  => (string) ( $stored['error'] ?? '' ),
			'images' => (bool) ( $stored['images'] ?? true ),
			'parts'  => DemoParts::closure( (array) ( $stored['parts'] ?? DemoParts::PARTS ) ),
			'steps'  => $steps,
		);
	}

	/**
	 * Save the state.
	 *
	 * @param array<string, mixed> $state State.
	 */
	private function save( array $state ): void {
		update_option( self::OPTION, $state, false );
	}

	/**
	 * Start, or resume, the import of some parts.
	 *
	 * @param int      $actor Administrator running it.
	 * @param string   $typed Typed confirmation.
	 * @param string[] $parts The chosen parts; what they need is added. All parts by default.
	 * @return array<string, mixed> The progress.
	 */
	public function start( int $actor, string $typed, array $parts = DemoParts::PARTS ): array {
		$state = $this->state();
		if ( 'running' === $state['status'] || 'failed' === $state['status'] ) {
			$state['status'] = 'running';
			$state['error']  = '';
			$this->save( $state );
			return $this->progress();
		}
		$parts = DemoParts::closure( $parts );
		if ( array() === $parts ) {
			return array_merge( $this->progress(), array( 'error' => __( 'Tick at least one part to import.', 'whoiswho' ) ) );
		}
		$refusal = $this->refusal( $typed );
		if ( null !== $refusal ) {
			return array_merge( $this->progress(), array( 'error' => $refusal ) );
		}
		$this->save(
			array(
				'status' => 'running',
				'step'   => 0,
				'cursor' => 0,
				'counts' => array(),
				'error'  => '',
				'images' => function_exists( 'imagecreatetruecolor' ),
				'actor'  => $actor,
				'parts'  => $parts,
				'steps'  => DemoParts::steps( $parts ),
			)
		);
		$this->log( 'demo.import_start', array( 'real_items' => $this->real_content_count() ), array( 'parts' => $parts ) );
		return $this->progress();
	}

	/**
	 * Run one batch. Repeating a request is harmless; a request that arrives while another runs is told to wait.
	 *
	 * @param int $actor Administrator running it.
	 * @return array<string, mixed> The progress.
	 */
	public function advance( int $actor ): array {
		$state = $this->state();
		if ( 'running' !== $state['status'] ) {
			return $this->progress();
		}
		if ( false !== get_transient( self::LOCK ) ) {
			return array_merge( $this->progress(), array( 'busy' => true ) );
		}
		set_transient( self::LOCK, '1', self::LOCK_TTL );
		$steps = $state['steps'];
		try {
			$seeder = $this->seeder( $actor );
			$step   = $steps[ $state['step'] ] ?? '';
			if ( '' === $step ) {
				$state['status'] = 'done';
			} else {
				$total = $seeder->step_total( $step );
				$next  = $seeder->run_step( $step, $state['cursor'], self::BATCH );
				foreach ( $seeder->created() as $kind => $count ) {
					$state['counts'][ $kind ] = ( $state['counts'][ $kind ] ?? 0 ) + $count;
				}
				if ( $next >= $total ) {
					++$state['step'];
					$state['cursor'] = 0;
				} else {
					$state['cursor'] = $next;
				}
				if ( $state['step'] >= count( $steps ) ) {
					$state['status'] = 'done';
				}
			}
			$this->save( $state );
			if ( 'done' === $state['status'] ) {
				$this->record_parts( $state['parts'] );
				$this->log( 'demo.import_done', null, array( 'created' => array_sum( $state['counts'] ) ) );
			}
		} catch ( \Throwable $e ) {
			// The class and step only: a message can carry personal data.
			$state['status'] = 'failed';
			$state['error']  = sprintf(
				/* translators: 1: step name, 2: error class. */
				__( 'The step "%1$s" did not finish (%2$s). Nothing is lost: press Try again to continue from here.', 'whoiswho' ),
				$steps[ $state['step'] ] ?? '',
				get_class( $e )
			);
			$this->save( $state );
		} finally {
			delete_transient( self::LOCK );
		}//end try
		return $this->progress();
	}

	/**
	 * Note when each imported part arrived.
	 *
	 * @param string[] $parts Parts.
	 */
	private function record_parts( array $parts ): void {
		$stored = get_option( self::PARTS_OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		foreach ( $parts as $part ) {
			$stored[ $part ] = array( 'at' => time() );
		}
		update_option( self::PARTS_OPTION, $stored, false );
	}

	/**
	 * Remove all the demo content, for a bounded time, and report whether nothing is left. Items a person changed are kept.
	 *
	 * @param int   $actor   Administrator running it.
	 * @param float $seconds Time budget.
	 * @return array<string, mixed> The progress.
	 */
	public function remove( int $actor, float $seconds = 8.0 ): array {
		return $this->remove_parts( array_reverse( DemoParts::PARTS ), $actor, $seconds, true );
	}

	/**
	 * Remove one part, and first the parts that need it, for a bounded time.
	 *
	 * @param string $part    Part.
	 * @param int    $actor   Administrator running it.
	 * @param float  $seconds Time budget.
	 * @return array<string, mixed> The progress.
	 */
	public function remove_part( string $part, int $actor, float $seconds = 8.0 ): array {
		if ( ! in_array( $part, DemoParts::PARTS, true ) ) {
			return array_merge( $this->progress(), array( 'error' => __( 'Unknown part.', 'whoiswho' ) ) );
		}
		$order = array_merge( array_reverse( DemoParts::dependents( $part ) ), array( $part ) );
		return $this->remove_parts( $order, $actor, $seconds, false );
	}

	/**
	 * Remove parts in order until the time is up.
	 *
	 * @param string[] $order   Parts, dependents first.
	 * @param int      $actor   Administrator running it.
	 * @param float    $seconds Time budget.
	 * @param bool     $all     Whether this removes everything (then the shared terms and records go too).
	 * @return array<string, mixed> The progress.
	 */
	private function remove_parts( array $order, int $actor, float $seconds, bool $all ): array {
		if ( false !== get_transient( self::LOCK ) ) {
			return array_merge( $this->progress(), array( 'busy' => true ) );
		}
		set_transient( self::LOCK, '1', self::LOCK_TTL );
		wp_set_current_user( $actor );
		$until   = microtime( true ) + max( 0.0, $seconds );
		$remover = new DemoPartRemover( $this->bundle );
		$kept    = array();
		$left    = 0;
		$done    = true;
		try {
			foreach ( $order as $part ) {
				$result = $remover->remove( $part, $until );
				$kept   = array_merge( $kept, $result['kept'] );
				if ( ! $result['done'] ) {
					$done = false;
					$left = $result['left'];
					break;
				}
				$this->forget_part( $part );
			}
			if ( $done ) {
				$done = $this->tidy( $all, $until );
			}
		} catch ( \Throwable $e ) {
			delete_transient( self::LOCK );
			return array_merge(
				$this->progress(),
				array(
					'error' => sprintf(
						/* translators: %s: error class. */
						__( 'Removing the demo content stopped (%s). Press Remove again to continue.', 'whoiswho' ),
						get_class( $e )
					),
					'kept'  => $kept,
				)
			);
		}
		delete_transient( self::LOCK );
		if ( $done ) {
			$this->log( $all ? 'demo.remove' : 'demo.remove_part', null, array( 'parts' => $order ) );
		}
		return array_merge(
			$this->progress(),
			array(
				'removing' => true,
				'left'     => $left,
				'done'     => $done,
				'kept'     => $kept,
				'parts'    => $this->parts_status(),
			)
		);
	}

	/**
	 * Forget a removed part.
	 *
	 * @param string $part Part.
	 */
	private function forget_part( string $part ): void {
		$stored = get_option( self::PARTS_OPTION, array() );
		if ( is_array( $stored ) && isset( $stored[ $part ] ) ) {
			unset( $stored[ $part ] );
			array() === $stored ? delete_option( self::PARTS_OPTION ) : update_option( self::PARTS_OPTION, $stored, false );
		}
	}

	/**
	 * When no demo item is left (or everything was asked for): the demo countries, cities and industries that nothing uses any more,
	 * and the demo records. A term something still uses stays, unmarked.
	 *
	 * @param bool  $all   Whether everything was asked for.
	 * @param float $until Deadline.
	 * @return bool Whether the tidy-up is finished.
	 */
	private function tidy( bool $all, float $until ): bool {
		if ( array() !== SampleMarker::all_post_ids() || array() !== DemoParts::comment_keys() ) {
			return true;
		}
		if ( ! $all && array() !== $this->parts_status() ) {
			return true;
		}
		foreach ( SampleMarker::all_terms( Taxonomies::all() ) as $term_id => $taxonomy ) {
			$used = get_objects_in_term( $term_id, $taxonomy );
			if ( is_array( $used ) && array() !== $used ) {
				delete_term_meta( $term_id, SampleMarker::META );
				delete_term_meta( $term_id, SampleMarker::KEY_META );
			} else {
				wp_delete_term( $term_id, $taxonomy );
			}
			if ( microtime( true ) >= $until ) {
				break;
			}
		}
		if ( array() !== SampleMarker::all_terms( Taxonomies::all() ) ) {
			return false;
		}
		delete_option( SampleMarker::REGISTRY );
		delete_option( self::PARTS_OPTION );
		delete_option( self::OPTION );
		flush_rewrite_rules( false );
		return true;
	}

	/**
	 * The progress for the screen: a percentage and the state.
	 *
	 * @return array<string, mixed>
	 */
	public function progress(): array {
		$state  = $this->state();
		$total  = 0;
		$before = 0;
		$seeder = $this->seeder( 0 );
		foreach ( $state['steps'] as $index => $step ) {
			$units  = $seeder->step_total( $step );
			$total += $units;
			if ( $index < $state['step'] ) {
				$before += $units;
			} elseif ( $index === $state['step'] ) {
				$before += min( $units, $state['cursor'] );
			}
		}
		$percent = 'done' === $state['status'] ? 100 : (int) floor( 100 * $before / max( 1, $total ) );
		$step    = $state['steps'][ $state['step'] ] ?? '';
		return array(
			'status'  => $state['status'],
			'percent' => $percent,
			'label'   => self::label( $step, 'done' === $state['status'] ),
			'counts'  => $state['counts'],
			'error'   => $state['error'],
			'images'  => $state['images'],
			'parts'   => $state['parts'],
			'steps'   => $state['steps'],
		);
	}

	/**
	 * A plain-language name for a step.
	 *
	 * @param string $step Step.
	 * @param bool   $done Whether the run has finished.
	 */
	public static function label( string $step, bool $done ): string {
		if ( $done ) {
			return __( 'Finished', 'whoiswho' );
		}
		$labels = array(
			'terms'    => __( 'Adding countries, cities and industries', 'whoiswho' ),
			'profiles' => __( 'Adding profiles', 'whoiswho' ),
			'stories'  => __( 'Adding stories', 'whoiswho' ),
			'comments' => __( 'Adding comments', 'whoiswho' ),
			'events'   => __( 'Adding events', 'whoiswho' ),
			'images'   => __( 'Making placeholder pictures', 'whoiswho' ),
			'awards'   => __( 'Adding awards and nominees', 'whoiswho' ),
			'curation' => __( 'Choosing what the home page shows', 'whoiswho' ),
			'pages'    => __( 'Adding the pages', 'whoiswho' ),
			'settings' => __( 'Finishing', 'whoiswho' ),
		);
		return $labels[ $step ] ?? __( 'Starting', 'whoiswho' );
	}

	/**
	 * Audit the run: counts and part names only.
	 *
	 * @param string                    $action Action.
	 * @param array<string, mixed>|null $before Before.
	 * @param array<string, mixed>|null $after  After.
	 */
	private function log( string $action, ?array $before, ?array $after ): void {
		try {
			AuditLogger::for_site()->log(
				$action,
				array(
					'type' => 'site',
					'id'   => 'demo',
				),
				$before,
				$after
			); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray -- plain subject array.
		} catch ( \Throwable $e ) {
			unset( $e );
			// The audit log being busy must not stop the setup.
		}
	}
}
