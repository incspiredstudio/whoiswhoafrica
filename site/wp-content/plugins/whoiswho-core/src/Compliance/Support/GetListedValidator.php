<?php
/**
 * The one validator of the Get listed form (REQ-177, REQ-058): name, an email or a phone number, an interest from the fixed list
 * and consent are required. There is no price, package or tier field: the form is about listing and contact, never about purchases.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Get listed validator.
 */
final class GetListedValidator extends Validator {

	public const INTERESTS = array( 'listing_a_profile', 'sponsored_story', 'media_or_editorial_work', 'awards_sponsorship', 'not_sure' );

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'get_listed';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'name'         => array(
				'type'     => self::TYPE_NAME,
				'required' => true,
			),
			'organisation' => array(
				'type' => self::TYPE_TEXT,
				'max'  => 190,
			),
			'interest'     => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 40,
			),
			'email'        => array( 'type' => self::TYPE_EMAIL ),
			'phone'        => array(
				'type' => self::TYPE_TEXT,
				'max'  => 40,
			),
			'message'      => array( 'type' => self::TYPE_TEXTAREA ),
			'consent'      => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
		);
	}

	/**
	 * Validate, then require an email or a phone number, a known interest and a ticked consent box.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		if ( '' === $result->value( 'email' ) && '' === $result->value( 'phone' ) ) {
			$errors['contact'] = 'required';
		}
		if ( ! isset( $errors['interest'] ) && ! in_array( $result->value( 'interest' ), self::INTERESTS, true ) ) {
			$errors['interest'] = 'invalid';
		}
		if ( ! isset( $errors['consent'] ) && ! in_array( strtolower( $result->value( 'consent' ) ), array( '1', 'on', 'yes', 'true' ), true ) ) {
			$errors['consent'] = 'required';
		}
		return $errors === $result->errors() ? $result : new ValidationResult( $result->values(), $errors );
	}
}
