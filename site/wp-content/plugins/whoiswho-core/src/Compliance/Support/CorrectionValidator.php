<?php
/**
 * The one validator of the correction form (REQ-157, REQ-232): who is asking, what they need, the field, the new value, evidence
 * and an email. The needs are the five the page offers. The details are composed into one message for the staff item.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Correction validator.
 */
final class CorrectionValidator extends Validator {

	public const RELATIONSHIPS = array( 'person', 'representative', 'someone_else' );

	public const NEEDS = array( 'correct_fact', 'right_of_reply', 'contact_details', 'enquiries_switch', 'view_count_sources' );

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'correction';
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
			'need'         => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 30,
			),
			'field'        => array(
				'type' => self::TYPE_TEXT,
				'max'  => 120,
			),
			'new_value'    => array(
				'type'     => self::TYPE_TEXTAREA,
				'required' => true,
				'max'      => 2000,
			),
			'evidence'     => array(
				'type' => self::TYPE_TEXTAREA,
				'max'  => 2000,
			),
			'email'        => array(
				'type'     => self::TYPE_EMAIL,
				'required' => true,
			),
		);
	}

	/**
	 * Validate, refuse an unknown relationship or need, and compose the message.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		$values = $result->values();
		if ( ! isset( $errors['relationship'] ) && ! in_array( $values['relationship'], self::RELATIONSHIPS, true ) ) {
			$errors['relationship'] = 'invalid';
		}
		if ( ! isset( $errors['need'] ) && ! in_array( $values['need'], self::NEEDS, true ) ) {
			$errors['need'] = 'invalid';
		}
		$values['message'] = implode(
			"\n",
			array(
				'Need: ' . ( $values['need'] ?? '' ),
				'Field: ' . ( $values['field'] ?? '' ),
				'New value: ' . ( $values['new_value'] ?? '' ),
				'Evidence: ' . ( $values['evidence'] ?? '' ),
			)
		);
		return new ValidationResult( $values, $errors );
	}
}
