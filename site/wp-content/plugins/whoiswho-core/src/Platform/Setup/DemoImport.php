<?php
/**
 * One-click demo content, in small resumable steps (REQ-208, REQ-052, REQ-056). A shared host stops a PHP request after a few
 * seconds, and seeding the whole sample takes much longer, so the Setup screen asks for one batch at a time and shows a progress
 * bar. Every batch is safe to repeat (Cli\Sample\SampleSeeder keys each item), the position is stored, and a run that stopped
 * halfway resumes where it was. Everything made carries the `_wiw_sample` marker, so it can be removed in one click and the
 * launch safeguards still flag it. Only counts are stored or logged, never personal data. The CLI commands use the same seeder.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Setup;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Platform\Audit\AuditLogger;
use WhoIsWho\Core\Platform\Cli\Sample\SampleMarker;
use WhoIsWho\Core\Platform\Cli\Sample\SampleRemover;
use WhoIsWho\Core\Platform\Cli\Sample\SampleSeeder;

/**
 * Demo import.
 */
final class DemoImport {

	public const OPTION   = 'wiw_demo_run';
	public const LOCK     = 'wiw_demo_lock';
	public const CONFIRM  = 'IMPORT DEMO';
	public const BATCH    = 8;
	public const LOCK_TTL = 60;

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
	 * Constructor.
	 *
	 * @param array<string, mixed>|null $data       Dataset (tests pass a small one); the plugin's file by default.
	 * @param bool|null                 $production Whether the site is in production; the site's environment by default.
	 */
	public function __construct( ?array $data = null, ?bool $production = null ) {
		$this->data       = $data;
		$this->production = $production ?? ( 'production' === wp_get_environment_type() );
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
	 * What the demo makes, by kind, for the confirmation text.
	 *
	 * @return array<string, int>
	 */
	public function plan(): array {
		$data = $this->data();
		return array(
			'profiles' => count( (array) $data['profiles'] ),
			'stories'  => count( (array) $data['stories'] ),
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
	 * Whether the demo is already in the site.
	 */
	public function installed(): bool {
		return array() !== SampleMarker::all_post_ids();
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
	 * @return array{status: string, step: int, cursor: int, counts: array<string, int>, error: string, images: bool}
	 */
	public function state(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'status' => (string) ( $stored['status'] ?? 'idle' ),
			'step'   => (int) ( $stored['step'] ?? 0 ),
			'cursor' => (int) ( $stored['cursor'] ?? 0 ),
			'counts' => array_map( 'intval', (array) ( $stored['counts'] ?? array() ) ),
			'error'  => (string) ( $stored['error'] ?? '' ),
			'images' => (bool) ( $stored['images'] ?? true ),
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
	 * Start, or resume, the import.
	 *
	 * @param int    $actor Administrator running it.
	 * @param string $typed Typed confirmation.
	 * @return array<string, mixed> The progress.
	 */
	public function start( int $actor, string $typed ): array {
		$state = $this->state();
		if ( 'running' === $state['status'] || 'failed' === $state['status'] ) {
			$state['status'] = 'running';
			$state['error']  = '';
			$this->save( $state );
			return $this->progress();
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
			)
		);
		$this->log( 'demo.import_start', $this->real_content_count(), 0 );
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
		try {
			$seeder = new SampleSeeder( $this->data(), $actor );
			$step   = SampleSeeder::STEPS[ $state['step'] ] ?? '';
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
				if ( $state['step'] >= count( SampleSeeder::STEPS ) ) {
					$state['status'] = 'done';
				}
			}
			$this->save( $state );
			if ( 'done' === $state['status'] ) {
				$this->log( 'demo.import_done', 0, array_sum( $state['counts'] ) );
			}
		} catch ( \Throwable $e ) {
			// The class and step only: a message can carry personal data.
			$state['status'] = 'failed';
			$state['error']  = sprintf(
				/* translators: 1: step name, 2: error class. */
				__( 'The step "%1$s" did not finish (%2$s). Nothing is lost: press Try again to continue from here.', 'whoiswho' ),
				SampleSeeder::STEPS[ $state['step'] ] ?? '',
				get_class( $e )
			);
			$this->save( $state );
		} finally {
			delete_transient( self::LOCK );
		}//end try
		return $this->progress();
	}

	/**
	 * Remove the demo, for a bounded time, and report whether nothing is left.
	 *
	 * @param int   $actor   Administrator running it.
	 * @param float $seconds Time budget.
	 * @return array<string, mixed> The progress.
	 */
	public function remove( int $actor, float $seconds = 8.0 ): array {
		if ( false !== get_transient( self::LOCK ) ) {
			return array_merge( $this->progress(), array( 'busy' => true ) );
		}
		set_transient( self::LOCK, '1', self::LOCK_TTL );
		try {
			$chunk = ( new SampleRemover( $actor ) )->remove_chunk( $seconds );
		} catch ( \Throwable $e ) {
			delete_transient( self::LOCK );
			$state          = $this->state();
			$state['error'] = sprintf(
				/* translators: %s: error class. */
				__( 'Removing the demo content stopped (%s). Press Remove again to continue.', 'whoiswho' ),
				get_class( $e )
			);
			return array_merge( $this->progress(), array( 'error' => $state['error'] ) );
		}
		delete_transient( self::LOCK );
		if ( $chunk['done'] ) {
			delete_option( self::OPTION );
			$this->log( 'demo.remove', 0, 0 );
		}
		return array_merge(
			$this->progress(),
			array(
				'removing' => true,
				'left'     => $chunk['left'],
				'done'     => $chunk['done'],
			)
		);
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
		$seeder = new SampleSeeder( $this->data(), 0 );
		foreach ( SampleSeeder::STEPS as $index => $step ) {
			$units  = $seeder->step_total( $step );
			$total += $units;
			if ( $index < $state['step'] ) {
				$before += $units;
			} elseif ( $index === $state['step'] ) {
				$before += min( $units, $state['cursor'] );
			}
		}
		$percent = 'done' === $state['status'] ? 100 : (int) floor( 100 * $before / max( 1, $total ) );
		$step    = SampleSeeder::STEPS[ $state['step'] ] ?? '';
		return array(
			'status'  => $state['status'],
			'percent' => $percent,
			'label'   => self::label( $step, 'done' === $state['status'] ),
			'counts'  => $state['counts'],
			'error'   => $state['error'],
			'images'  => $state['images'],
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
	 * Audit the run: counts only.
	 *
	 * @param string $action Action.
	 * @param int    $real   Real items present.
	 * @param int    $made   Items made.
	 */
	private function log( string $action, int $real, int $made ): void {
		try {
			AuditLogger::for_site()->log(
				$action,
				array(
					'type' => 'site',
					'id'   => 'demo',
				),
				array( 'real_items' => $real ),
				array( 'created' => $made )
			); // phpcs:ignore Universal.Arrays.MixedKeyedUnkeyedArray -- plain subject array.
		} catch ( \Throwable $e ) {
			unset( $e );
			// The audit log being busy must not stop the setup.
		}
	}
}
