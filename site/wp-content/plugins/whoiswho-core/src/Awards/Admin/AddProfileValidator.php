<?php
/**
 * The one validator of the "Add profile to nominations" form (REQ-267, REQ-268). There is no nominee name field: the nominee is
 * an existing profile, chosen by id. The category is a number, the note is optional plain text and the Contact email is an
 * optional staff-only address. Whether the profile and category really exist is checked by NominationService.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards\Admin;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Add profile validator.
 */
final class AddProfileValidator extends Validator {

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'nomination_add_profile';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'profile_id'    => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
			'category_id'   => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
			'note'          => array(
				'type' => self::TYPE_TEXTAREA,
				'max'  => 1000,
			),
			'contact_email' => array( 'type' => self::TYPE_EMAIL ),
		);
	}

	/**
	 * Validate, and check that the profile and category are numbers.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		foreach ( array( 'profile_id', 'category_id' ) as $field ) {
			if ( ! isset( $errors[ $field ] ) && ! ctype_digit( $result->value( $field ) ) ) {
				$errors[ $field ] = 'invalid';
			}
		}
		return $errors === $result->errors() ? $result : new ValidationResult( $result->values(), $errors );
	}
}
