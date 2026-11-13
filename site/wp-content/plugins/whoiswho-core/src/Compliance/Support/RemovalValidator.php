<?php
/**
 * The one validator of the removal form (REQ-157, REQ-158, REQ-232): name, relationship, reason and email. The reason is
 * composed into the message the staff item shows.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Removal validator.
 */
final class RemovalValidator extends Validator {

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'removal';
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
			'relationship' => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 20,
			),
			'reason'       => array(
				'type'     => self::TYPE_TEXTAREA,
				'required' => true,
				'min'      => 5,
				'max'      => 2000,
			),
			'email'        => array(
				'type'     => self::TYPE_EMAIL,
				'required' => true,
			),
		);
	}

	/**
	 * Validate, refuse an unknown relationship, and compose the message.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		$values = $result->values();
		if ( ! isset( $errors['relationship'] ) && ! in_array( $values['relationship'], CorrectionValidator::RELATIONSHIPS, true ) ) {
			$errors['relationship'] = 'invalid';
		}
		$values['message'] = 'Reason: ' . ( $values['reason'] ?? '' );
		return new ValidationResult( $values, $errors );
	}
}
