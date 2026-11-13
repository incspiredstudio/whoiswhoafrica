<?php
/**
 * The one validator of the nomination itself (REQ-030, REQ-267): the nominee's name, an optional organisation and email (kept as
 * the staff-only Contact email), a category, the evidence, and the nominator's declaration of any relationship with the nominee.
 * A declared relationship needs a short note. The category must be a current one; that check needs the database and is made by
 * the intake service.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Nomination validator.
 */
final class NominationValidator extends Validator {

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'nomination';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'nominee_name'      => array(
				'type'     => self::TYPE_NAME,
				'required' => true,
			),
			'nominee_org'       => array(
				'type' => self::TYPE_TEXT,
				'max'  => 190,
			),
			'nominee_email'     => array( 'type' => self::TYPE_EMAIL ),
			'category_id'       => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
			'evidence'          => array(
				'type'     => self::TYPE_TEXTAREA,
				'required' => true,
				'min'      => 20,
				'max'      => 3000,
			),
			'relationship'      => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
			'relationship_note' => array(
				'type' => self::TYPE_TEXTAREA,
				'max'  => 500,
			),
		);
	}

	/**
	 * Validate, and check the category number, the yes or no answer and the note a declared relationship needs.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		if ( ! isset( $errors['category_id'] ) && ! ctype_digit( $result->value( 'category_id' ) ) ) {
			$errors['category_id'] = 'invalid';
		}
		$answer = strtolower( $result->value( 'relationship' ) );
		if ( ! isset( $errors['relationship'] ) && ! in_array( $answer, array( 'none', 'yes' ), true ) ) {
			$errors['relationship'] = 'invalid';
		}
		if ( 'yes' === $answer && '' === trim( $result->value( 'relationship_note' ) ) ) {
			$errors['relationship_note'] = 'required';
		}
		return $errors === $result->errors() ? $result : new ValidationResult( $result->values(), $errors );
	}
}
