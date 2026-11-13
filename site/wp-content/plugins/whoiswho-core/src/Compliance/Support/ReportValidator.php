<?php
/**
 * The one validator of the report form (REQ-090, REQ-157, REQ-270): a reason from the fixed list and details. The address is
 * optional (the docs say any visitor can report); when given it is confirmed by link. The reason and details are composed into
 * the message the staff item shows.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Report validator.
 */
final class ReportValidator extends Validator {

	public const REASONS = array( 'inaccurate', 'defamatory_or_harmful', 'privacy', 'copyright', 'other' );

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'report';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'reason'  => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 30,
			),
			'details' => array(
				'type'     => self::TYPE_TEXTAREA,
				'required' => true,
				'min'      => 5,
				'max'      => 3000,
			),
			'email'   => array( 'type' => self::TYPE_EMAIL ),
		);
	}

	/**
	 * Validate, refuse a reason that is not on the list, and compose the message.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		$values = $result->values();
		if ( ! isset( $errors['reason'] ) && ! in_array( $values['reason'], self::REASONS, true ) ) {
			$errors['reason'] = 'invalid';
		}
		$values['message'] = 'Reason: ' . ( $values['reason'] ?? '' ) . "\nDetails: " . ( $values['details'] ?? '' );
		return new ValidationResult( $values, $errors );
	}
}
