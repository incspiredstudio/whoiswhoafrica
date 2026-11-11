<?php
/**
 * The profile field allowlists (REQ-139, REQ-140): which fields each entity type may hold. Reads config/profile-fields.php.
 * Plain PHP, no WordPress calls.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Fields;

/**
 * Allowlists.
 */
final class FieldAllowlist {

	/**
	 * Prefix of the post meta keys.
	 */
	public const META_PREFIX = 'wiw_';

	/**
	 * Config.
	 *
	 * @var array{types: array<string, array<string, array<string, mixed>>>, forbidden: string[]}
	 */
	private array $config;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed>|null $config Config; defaults to config/profile-fields.php.
	 */
	public function __construct( ?array $config = null ) {
		// phpcs:ignore Generic.Commenting.DocComment.MissingShort -- inline type hint for static analysis.
		/** @var array{types: array<string, array<string, array<string, mixed>>>, forbidden: string[]} $loaded */
		$loaded       = $config ?? require dirname( __DIR__, 3 ) . '/config/profile-fields.php';
		$this->config = $loaded;
	}

	/**
	 * Entity types that have an allowlist.
	 *
	 * @return string[]
	 */
	public function entity_types(): array {
		return array_keys( $this->config['types'] );
	}

	/**
	 * Fields of one entity type: key => rule.
	 *
	 * @param string $entity_type Entity type.
	 * @return array<string, array<string, mixed>>
	 */
	public function for_type( string $entity_type ): array {
		return $this->config['types'][ $entity_type ] ?? array();
	}

	/**
	 * Whether a key is allowed for an entity type.
	 *
	 * @param string $entity_type Entity type.
	 * @param string $key         Field key.
	 */
	public function is_allowed( string $entity_type, string $key ): bool {
		return isset( $this->for_type( $entity_type )[ $key ] );
	}

	/**
	 * Every allowed key across all types.
	 *
	 * @return string[]
	 */
	public function all_keys(): array {
		$keys = array();
		foreach ( $this->config['types'] as $fields ) {
			$keys = array_merge( $keys, array_keys( $fields ) );
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Attributes that must never be stored.
	 *
	 * @return string[]
	 */
	public function forbidden(): array {
		return $this->config['forbidden'];
	}

	/**
	 * Whether a key names a forbidden attribute.
	 *
	 * @param string $key Field key.
	 */
	public function is_forbidden( string $key ): bool {
		return in_array( strtolower( $key ), $this->config['forbidden'], true );
	}

	/**
	 * The post meta key for a field, or null for the name (stored in the post title).
	 *
	 * @param string $key Field key.
	 */
	public static function meta_key( string $key ): ?string {
		return 'name' === $key ? null : self::META_PREFIX . $key;
	}

	/**
	 * The field key for a post meta key, or null when it is not a profile field meta key.
	 *
	 * @param string $meta_key Meta key.
	 */
	public function field_for_meta_key( string $meta_key ): ?string {
		if ( 0 !== strpos( $meta_key, self::META_PREFIX ) ) {
			return null;
		}
		$key = substr( $meta_key, strlen( self::META_PREFIX ) );
		return in_array( $key, $this->all_keys(), true ) ? $key : null;
	}
}
