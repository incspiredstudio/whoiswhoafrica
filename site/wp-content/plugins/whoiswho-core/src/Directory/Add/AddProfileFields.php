<?php
/**
 * The fields of the Add profile form for one entity type (REQ-209, REQ-139). They come from the type's allowlist in
 * config/profile-fields.php, so a field added there appears on the form. The four narrative sections (Bio, Achievement,
 * Influence, Legacy) and, for people, lifespan and field are contract v1.1 keys listed there too; the sections come last, in
 * page order. Plain PHP, no WordPress calls.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Add;

use WhoIsWho\Core\Directory\Fields\FieldAllowlist;

/**
 * Add profile fields.
 */
final class AddProfileFields {

	/**
	 * The narrative sections, in page order.
	 */
	public const SECTIONS = array( 'bio', 'achievement', 'influence', 'legacy' );

	/**
	 * Person entity types.
	 */
	public const PERSON_TYPES = array( 'individual', 'entrepreneur' );

	/**
	 * Allowlisted keys the form does not offer: the name has its own input, and the organisation reference is set by the import.
	 */
	private const NOT_ON_FORM = array( 'name', 'current_organisation_ref' );

	/**
	 * Fields rendered as a paragraph box.
	 */
	private const LONG_TEXT = array( 'summary', 'mandate' );

	/**
	 * Allowlists.
	 *
	 * @var FieldAllowlist
	 */
	private FieldAllowlist $allowlist;

	/**
	 * Constructor.
	 *
	 * @param FieldAllowlist|null $allowlist Allowlists.
	 */
	public function __construct( ?FieldAllowlist $allowlist = null ) {
		$this->allowlist = $allowlist ?? new FieldAllowlist();
	}

	/**
	 * Whether an entity type has an allowlist.
	 *
	 * @param string $type Entity type.
	 */
	public function is_type( string $type ): bool {
		return in_array( $type, $this->allowlist->entity_types(), true );
	}

	/**
	 * The entity types.
	 *
	 * @return string[]
	 */
	public function types(): array {
		return $this->allowlist->entity_types();
	}

	/**
	 * A field's label, from config/profile-fields.php through the allowlist (the one label source).
	 *
	 * @param string $type Entity type.
	 * @param string $key  Field key.
	 */
	public function label( string $type, string $key ): string {
		return $this->allowlist->label( $type, $key );
	}

	/**
	 * The form fields of a type: key => rule (type, required, max, long). Sections come last, in page order.
	 *
	 * @param string $type Entity type.
	 * @return array<string, array{type: string, required: bool, max: int, long: bool}>
	 */
	public function for_type( string $type ): array {
		$rules = $this->allowlist->for_type( $type );
		if ( array() === $rules ) {
			return array();
		}
		$out      = array();
		$sections = array();
		foreach ( $rules as $key => $rule ) {
			if ( in_array( $key, self::NOT_ON_FORM, true ) || in_array( $key, self::SECTIONS, true ) ) {
				continue;
			}
			$out[ (string) $key ] = $this->rule( (string) $key, $rule );
		}
		foreach ( self::SECTIONS as $key ) {
			if ( isset( $rules[ $key ] ) ) {
				$sections[ $key ] = $this->rule( $key, $rules[ $key ] );
			}
		}
		return $out + $sections;
	}

	/**
	 * Whether the allowlist lists a key for a type.
	 *
	 * @param string $type Entity type.
	 * @param string $key  Field key.
	 */
	public function is_listed( string $type, string $key ): bool {
		return $this->allowlist->is_allowed( $type, $key );
	}

	/**
	 * A normalised rule.
	 *
	 * @param string               $key  Field key.
	 * @param array<string, mixed> $rule Allowlist rule.
	 * @return array{type: string, required: bool, max: int, long: bool}
	 */
	private function rule( string $key, array $rule ): array {
		$type = (string) ( $rule['type'] ?? 'string' );
		$long = in_array( $key, self::SECTIONS, true ) || in_array( $key, self::LONG_TEXT, true ) || 'list' === $type;
		$max  = isset( $rule['max'] ) ? (int) $rule['max'] : ( 'url' === $type ? 2000 : ( $long ? 5000 : 200 ) );
		return array(
			'type'     => $type,
			'required' => ! empty( $rule['required'] ),
			'max'      => $max,
			'long'     => $long,
		);
	}
}
