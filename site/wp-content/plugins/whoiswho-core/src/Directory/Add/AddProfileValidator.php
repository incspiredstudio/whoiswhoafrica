<?php
/**
 * The one validator of the Add profile form (REQ-209, REQ-139, REQ-143). The fields follow the chosen entity type's allowlist
 * (AddProfileFields). Beyond the base checks (required, length, clean text) it checks the entity type, that the country, industry,
 * organisation type, sector and city are terms of their lists, that URLs are http or https, that at least one source URL is given,
 * that the external ids look right, and that an image, when chosen, is a JPEG, PNG or WebP in the media library with an open
 * licence, a credit and a source.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Add;

use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Import\Images\ImagePolicy;
use WhoIsWho\Core\Import\Mapping\TermMapper;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Add profile validator.
 */
final class AddProfileValidator extends Validator {

	/**
	 * Most source URLs on one form.
	 */
	public const MAX_SOURCES = 10;

	/**
	 * Organisation types (they may carry a company registration number).
	 */
	public const ORG_TYPES = array( 'organisation', 'institution' );

	/**
	 * Entity type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Form fields.
	 *
	 * @var AddProfileFields
	 */
	private AddProfileFields $form;

	/**
	 * Constructor.
	 *
	 * @param string                $type Entity type as submitted.
	 * @param AddProfileFields|null $form Form fields.
	 */
	public function __construct( string $type, ?AddProfileFields $form = null ) {
		$this->type = $type;
		$this->form = $form ?? new AddProfileFields();
	}

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'profile_add';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		$out = array(
			'entity_type' => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 20,
			),
			'name'        => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 200,
			),
			'country'     => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
		);
		foreach ( $this->form->for_type( $this->type ) as $key => $rule ) {
			$out[ 'f_' . $key ] = array(
				'type'     => $rule['long'] ? self::TYPE_TEXTAREA : self::TYPE_TEXT,
				'required' => $rule['required'],
				'max'      => $rule['max'],
			);
		}
		$out['sources']       = array(
			'type'     => self::TYPE_TEXTAREA,
			'required' => true,
			'max'      => 5000,
		);
		$out['wikidata_qid']  = array(
			'type' => self::TYPE_TEXT,
			'max'  => 20,
		);
		$out['rc_number']     = array(
			'type' => self::TYPE_TEXT,
			'max'  => 40,
		);
		$out['image_id']      = array(
			'type' => self::TYPE_TEXT,
			'max'  => 12,
		);
		$out['image_licence'] = array(
			'type' => self::TYPE_TEXT,
			'max'  => 40,
		);
		$out['image_credit']  = array(
			'type' => self::TYPE_TEXT,
			'max'  => 300,
		);
		$out['image_source']  = array(
			'type' => self::TYPE_TEXT,
			'max'  => 2000,
		);
		return $out;
	}

	/**
	 * Validate.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$values = $result->values();
		$errors = $result->errors();
		$flag   = static function ( string $field, string $code ) use ( &$errors ): void {
			if ( ! isset( $errors[ $field ] ) ) {
				$errors[ $field ] = $code;
			}
		};
		if ( ! $this->form->is_type( $this->type ) || $values['entity_type'] !== $this->type ) {
			$flag( 'entity_type', 'invalid' );
		}
		if ( '' !== $values['country'] && ! self::is_country( $values['country'] ) ) {
			$flag( 'country', 'invalid' );
		}
		foreach ( $this->form->for_type( $this->type ) as $key => $rule ) {
			$value = $values[ 'f_' . $key ];
			if ( '' === $value ) {
				continue;
			}
			if ( isset( TermMapper::FIELDS[ $key ] ) && ! self::is_term( $value, TermMapper::FIELDS[ $key ] ) ) {
				$flag( 'f_' . $key, 'invalid' );
			} elseif ( 'url' === $rule['type'] && ! self::is_url( $value ) ) {
				$flag( 'f_' . $key, 'invalid_url' );
			} elseif ( 'int' === $rule['type'] && ! ctype_digit( $value ) ) {
				$flag( 'f_' . $key, 'invalid' );
			}
		}
		$sources = self::lines( $values['sources'] );
		if ( '' !== $values['sources'] ) {
			if ( count( $sources ) > self::MAX_SOURCES ) {
				$flag( 'sources', 'too_many' );
			}
			foreach ( $sources as $url ) {
				if ( ! self::is_url( $url ) ) {
					$flag( 'sources', 'invalid_url' );
				}
			}
		}
		if ( '' !== $values['wikidata_qid'] && 1 !== preg_match( '/^Q[1-9][0-9]*$/', $values['wikidata_qid'] ) ) {
			$flag( 'wikidata_qid', 'invalid' );
		}
		if ( '' !== $values['rc_number'] && ( ! in_array( $this->type, self::ORG_TYPES, true ) || 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9 \-\/]*$/', $values['rc_number'] ) ) ) {
			$flag( 'rc_number', 'invalid' );
		}
		$this->check_image( $values, $flag );
		return $errors === $result->errors() ? $result : new ValidationResult( $values, $errors );
	}

	/**
	 * Check the image fields when an image is chosen.
	 *
	 * @param array<string, string> $values Values.
	 * @param callable              $flag   Records an error.
	 */
	private function check_image( array $values, callable $flag ): void {
		if ( '' === $values['image_id'] || '0' === $values['image_id'] ) {
			return;
		}
		$id = ctype_digit( $values['image_id'] ) ? (int) $values['image_id'] : 0;
		if ( $id < 1 || 'attachment' !== get_post_type( $id ) || ! isset( ImagePolicy::TYPES[ (string) get_post_mime_type( $id ) ] ) ) {
			$flag( 'image_id', 'invalid' );
		}
		if ( '' === $values['image_licence'] ) {
			$flag( 'image_licence', 'required' );
		} elseif ( ! ImagePolicy::licence_allowed( $values['image_licence'] ) ) {
			$flag( 'image_licence', 'not_allowed' );
		}
		if ( '' === $values['image_credit'] ) {
			$flag( 'image_credit', 'required' );
		}
		if ( '' === $values['image_source'] ) {
			$flag( 'image_source', 'required' );
		} elseif ( ! self::is_url( $values['image_source'] ) ) {
			$flag( 'image_source', 'invalid_url' );
		}
	}

	/**
	 * The non-empty lines of a text.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function lines( string $text ): array {
		$lines = preg_split( '/\R/', $text );
		return array_values( array_filter( array_map( 'trim', is_array( $lines ) ? $lines : array() ), static fn ( string $line ): bool => '' !== $line ) );
	}

	/**
	 * Whether a value is an absolute http or https URL.
	 *
	 * @param string $value Value.
	 */
	public static function is_url( string $value ): bool {
		if ( false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$scheme = strtolower( (string) wp_parse_url( $value, PHP_URL_SCHEME ) );
		return in_array( $scheme, array( 'http', 'https' ), true ) && '' !== (string) wp_parse_url( $value, PHP_URL_HOST );
	}

	/**
	 * Whether a value is the id of a term in a list.
	 *
	 * @param string $value    Value.
	 * @param string $taxonomy Taxonomy.
	 */
	private static function is_term( string $value, string $taxonomy ): bool {
		return ctype_digit( $value ) && (int) $value > 0 && null !== term_exists( (int) $value, $taxonomy ) && 0 !== term_exists( (int) $value, $taxonomy );
	}

	/**
	 * Whether a value is the id of a country (or pan-African body) in the places list.
	 *
	 * @param string $value Value.
	 */
	private static function is_country( string $value ): bool {
		if ( ! self::is_term( $value, Taxonomies::PLACE ) ) {
			return false;
		}
		$kind = (string) get_term_meta( (int) $value, Taxonomies::META_KIND, true );
		return in_array( $kind, array( Taxonomies::KIND_COUNTRY, Taxonomies::KIND_BODY ), true );
	}
}
