<?php
/**
 * The one validator of the claim form (REQ-251, REQ-081). Relationship (the person, or an authorised representative), name, a
 * valid work email, evidence and consent are required; role and phone are optional. No account is needed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Claim validator.
 */
final class ClaimValidator extends Validator {

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'claim';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'relationship' => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 20,
			),
			'name'         => array(
				'type'     => self::TYPE_NAME,
				'required' => true,
			),
			'role'         => array(
				'type' => self::TYPE_TEXT,
				'max'  => 120,
			),
			'work_email'   => array(
				'type'     => self::TYPE_EMAIL,
				'required' => true,
			),
			'phone'        => array(
				'type' => self::TYPE_TEXT,
				'max'  => 40,
			),
			'evidence'     => array(
				'type'     => self::TYPE_TEXTAREA,
				'required' => true,
				'min'      => 10,
			),
			'consent'      => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
		);
	}

	/**
	 * Validate, and refuse an unknown relationship or a consent box that is not ticked.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		if ( ! isset( $errors['relationship'] ) && ! in_array( $result->value( 'relationship' ), Claim::RELATIONSHIPS, true ) ) {
			$errors['relationship'] = 'invalid';
		}
		if ( ! isset( $errors['consent'] ) && ! in_array( strtolower( $result->value( 'consent' ) ), array( '1', 'on', 'yes', 'true' ), true ) ) {
			$errors['consent'] = 'required';
		}
		return $errors === $result->errors() ? $result : new ValidationResult( $result->values(), $errors );
	}
}
