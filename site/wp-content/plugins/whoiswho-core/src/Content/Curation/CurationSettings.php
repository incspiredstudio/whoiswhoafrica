<?php
/**
 * Curation settings: one record per block (mode, item count, pinned ids, fill mode) with one validator. Changing them needs
 * wiw_curate_home and is audited with the actor, block, old and new values (REQ-257, REQ-051). Nominees have no mode.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Curation;

use WhoIsWho\Core\Directory\ProfileType;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Curation settings.
 */
final class CurationSettings {

	public const ON_THE_RECORD = 'on_the_record';
	public const STORIES_HOME  = 'stories_home';
	public const STORIES_PAGE  = 'stories_page';
	public const BLOCKS        = array( self::ON_THE_RECORD, self::STORIES_HOME, self::STORIES_PAGE );
	public const MODES         = array( 'latest', 'random', 'manual', 'pinned_then_fill' );
	public const FILLS         = array( 'latest', 'random' );
	public const CAP           = 'wiw_curate_home';
	public const OPTION_PREFIX = 'wiw_curation_';
	public const MAX_COUNT     = 24;
	public const MAX_PINNED    = 24;

	/**
	 * Audit logger.
	 *
	 * @var AuditLogger
	 */
	private AuditLogger $audit;

	/**
	 * Whether an id is a real item for a block: fn( string $block, int $id ): bool.
	 *
	 * @var callable
	 */
	private $exists;

	/**
	 * Constructor.
	 *
	 * @param AuditLogger   $audit  Audit logger.
	 * @param callable|null $exists Existence check for pinned ids; by default profiles for On the record, posts for stories.
	 */
	public function __construct( AuditLogger $audit, ?callable $exists = null ) {
		$this->audit  = $audit;
		$this->exists = $exists ?? array( self::class, 'item_exists' );
	}

	/**
	 * Default existence check.
	 *
	 * @param string $block Block.
	 * @param int    $id    Item id.
	 */
	public static function item_exists( string $block, int $id ): bool {
		$expected = self::ON_THE_RECORD === $block ? ProfileType::POST_TYPE : 'post';
		return get_post_type( $id ) === $expected;
	}

	/**
	 * Recommended starting setup.
	 *
	 * @param string $block Block.
	 * @return array{mode: string, count: int, pinned: array<int, int>, fill: string, featured_first: bool}
	 * @throws \InvalidArgumentException For an unknown block.
	 */
	public static function defaults( string $block ): array {
		switch ( $block ) {
			case self::ON_THE_RECORD:
				return array(
					'mode'           => 'pinned_then_fill',
					'count'          => 8,
					'pinned'         => array(),
					'fill'           => 'random',
					'featured_first' => false,
				);
			case self::STORIES_HOME:
				return array(
					'mode'           => 'latest',
					'count'          => 3,
					'pinned'         => array(),
					'fill'           => 'latest',
					'featured_first' => true,
				);
			case self::STORIES_PAGE:
				return array(
					'mode'           => 'latest',
					'count'          => 12,
					'pinned'         => array(),
					'fill'           => 'latest',
					'featured_first' => true,
				);
		}//end switch
		throw new \InvalidArgumentException( 'Unknown curation block.' );
	}

	/**
	 * The stored setting for a block, or its default.
	 *
	 * @param string $block Block.
	 * @return array{mode: string, count: int, pinned: array<int, int>, fill: string, featured_first: bool}
	 */
	public function get( string $block ): array {
		$defaults = self::defaults( $block );
		$stored   = get_option( self::OPTION_PREFIX . $block, null );
		if ( ! is_array( $stored ) ) {
			return $defaults;
		}
		return array(
			'mode'           => in_array( $stored['mode'] ?? '', self::MODES, true ) ? (string) $stored['mode'] : $defaults['mode'],
			'count'          => isset( $stored['count'] ) ? (int) $stored['count'] : $defaults['count'],
			'pinned'         => isset( $stored['pinned'] ) && is_array( $stored['pinned'] ) ? array_map( 'intval', $stored['pinned'] ) : array(),
			'fill'           => in_array( $stored['fill'] ?? '', self::FILLS, true ) ? (string) $stored['fill'] : $defaults['fill'],
			'featured_first' => isset( $stored['featured_first'] ) ? (bool) $stored['featured_first'] : $defaults['featured_first'],
		);
	}

	/**
	 * Validate and save a block's setting.
	 *
	 * @param string               $block  Block.
	 * @param array<string, mixed> $input  Submitted values; missing ones keep the current value.
	 * @param string               $reason Optional reason for the audit row.
	 * @throws \DomainException          When the user lacks wiw_curate_home.
	 */
	public function set( string $block, array $input, string $reason = '' ): void {
		if ( ! current_user_can( self::CAP ) ) {
			throw new \DomainException( 'Not allowed to change curation.' );
		}
		$old = $this->get( $block );
		// Throws for an unknown block.
		$new = $this->validate( $block, array_merge( $old, $input ) );
		if ( $old === $new ) {
			return;
		}
		update_option( self::OPTION_PREFIX . $block, $new, false );
		$this->audit->log(
			'curation.change',
			array(
				'type' => 'curation_block',
				'id'   => $block,
			),
			$old,
			$new,
			$reason
		); // phpcs:ignore Universal.Arrays.MultipleStatementAlignment
	}

	/**
	 * The one validator.
	 *
	 * @param string               $block Block.
	 * @param array<string, mixed> $input Values.
	 * @return array{mode: string, count: int, pinned: array<int, int>, fill: string, featured_first: bool}
	 * @throws \InvalidArgumentException When a value is invalid.
	 */
	private function validate( string $block, array $input ): array {
		$mode = is_string( $input['mode'] ?? null ) ? $input['mode'] : '';
		if ( ! in_array( $mode, self::MODES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown mode.' );
		}
		$fill = is_string( $input['fill'] ?? null ) ? $input['fill'] : '';
		if ( ! in_array( $fill, self::FILLS, true ) ) {
			throw new \InvalidArgumentException( 'Fill must be latest or random.' );
		}
		$count = $input['count'] ?? 0;
		if ( ! is_int( $count ) || $count < 1 || $count > self::MAX_COUNT ) {
			throw new \InvalidArgumentException( 'Count must be a whole number from 1 to 24.' );
		}
		$pinned = array();
		foreach ( (array) ( $input['pinned'] ?? array() ) as $id ) {
			if ( ! is_int( $id ) && ! ( is_string( $id ) && ctype_digit( $id ) ) ) {
				throw new \InvalidArgumentException( 'Pinned items are whole numbers.' );
			}
			$id = (int) $id;
			if ( $id < 1 || ! ( $this->exists )( $block, $id ) ) {
				throw new \InvalidArgumentException( 'Pinned item does not exist.' );
			}
			$pinned[ $id ] = $id;
		}
		$pinned = array_values( $pinned );
		if ( count( $pinned ) > self::MAX_PINNED ) {
			throw new \InvalidArgumentException( 'Too many pinned items.' );
		}
		return array(
			'mode'           => $mode,
			'count'          => $count,
			'pinned'         => $pinned,
			'fill'           => $fill,
			'featured_first' => (bool) ( $input['featured_first'] ?? false ),
		);
	}
}
