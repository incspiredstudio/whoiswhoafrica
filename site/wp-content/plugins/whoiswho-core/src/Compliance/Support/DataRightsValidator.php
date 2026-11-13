<?php
/**
 * The one validator of the data-rights form (REQ-059, REQ-081): the kind of request, the requester's name and email, and what
 * they are asking for. Error codes never echo what was typed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Data rights validator.
 */
final class DataRightsValidator extends Validator {

	public const KINDS = array( 'access', 'correction', 'erasure', 'objection', 'portability' );

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'data_rights';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'kind'    => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 20,
			),
			'name'    => array(
				'type'     => self::TYPE_NAME,
				'required' => true,
			),
			'email'   => array(
				'type'     => self::TYPE_EMAIL,
				'required' => true,
			),
			'details' => array(
				'type'     => self::TYPE_TEXTAREA,
				'required' => true,
				'min'      => 5,
			),
		);
	}

	/**
	 * Validate, then require one of the five kinds.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		if ( ! isset( $errors['kind'] ) && ! in_array( $result->value( 'kind' ), self::KINDS, true ) ) {
			$errors['kind'] = 'invalid';
		}
		return $errors === $result->errors() ? $result : new ValidationResult( $result->values(), $errors );
	}
}
