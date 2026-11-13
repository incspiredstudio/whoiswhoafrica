<?php
/**
 * The sample:seed command: fills a local or staging site with fictional sample content so every public page can be seen with data
 * (see Sample\SampleSeeder). It refuses to run on a production site and exits 1. Nothing it makes is real, and everything it makes
 * carries the `_wiw_sample` marker that content:find-sample and sample:remove use. Safe to run again.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Cli;

use WhoIsWho\Core\Platform\Cli\Sample\SampleSeeder;

/**
 * Sample seed command.
 */
final class SampleSeedCommand implements Command {

	/**
	 * Environment types the sample commands run in.
	 */
	public const ALLOWED_ENVIRONMENTS = array( 'local', 'development', 'staging' );

	/**
	 * Environment type; the site's by default.
	 *
	 * @var string
	 */
	private string $environment;

	/**
	 * Dataset; the plugin's file by default.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $data;

	/**
	 * Constructor.
	 *
	 * @param string|null               $environment Environment type (tests pass one).
	 * @param array<string, mixed>|null $data        Dataset (tests pass a small one).
	 */
	public function __construct( ?string $environment = null, ?array $data = null ) {
		$this->environment = $environment ?? wp_get_environment_type();
		$this->data        = $data;
	}

	/**
	 * Name.
	 */
	public function name(): string {
		return 'sample:seed';
	}

	/**
	 * Description.
	 */
	public function description(): string {
		return 'Create fictional sample content (local and staging only; refuses in production).';
	}

	/**
	 * Why the sample commands refuse here, or null when they may run.
	 *
	 * @param string $environment Environment type.
	 */
	public static function refusal( string $environment ): ?string {
		return in_array( $environment, self::ALLOWED_ENVIRONMENTS, true ) ? null : 'Refused: sample content is for local and staging sites only (environment: ' . $environment . ').';
	}

	/**
	 * The first Administrator's id, or 0.
	 */
	public static function administrator(): int {
		$ids = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'fields'  => 'ID',
			)
		);
		return array() === $ids ? 0 : (int) $ids[0];
	}

	/**
	 * Run.
	 *
	 * @param string[] $args   Arguments.
	 * @param Output   $output Output.
	 */
	public function run( array $args, Output $output ): int {
		unset( $args );
		$refusal = self::refusal( $this->environment );
		if ( null !== $refusal ) {
			$output->line( $refusal );
			return 1;
		}
		$actor = self::administrator();
		if ( $actor < 1 ) {
			$output->line( 'No Administrator user was found. Install WordPress first.' );
			return 1;
		}
		$seeder = new SampleSeeder( $this->data ?? SampleSeeder::load_data(), $actor );
		$made   = $seeder->seed( $output );
		$output->line( array() === $made ? 'Nothing to create: the sample content is already there.' : 'Done.' );
		return 0;
	}
}
