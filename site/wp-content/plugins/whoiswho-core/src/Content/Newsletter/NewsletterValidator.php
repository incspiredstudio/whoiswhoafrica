<?php
/**
 * The one validator of the newsletter sign-up form (REQ-061, REQ-058): a valid email address and the ticked consent box.
 * Error codes never echo what the visitor typed.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Content\Newsletter;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Newsletter validator.
 */
final class NewsletterValidator extends Validator {

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'newsletter';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'email'   => array(
				'type'     => self::TYPE_EMAIL,
				'required' => true,
			),
			'consent' => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 10,
			),
		);
	}

	/**
	 * Validate, then require the consent tick.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		if ( ! isset( $errors['consent'] ) && ! in_array( strtolower( $result->value( 'consent' ) ), array( '1', 'on', 'yes', 'true' ), true ) ) {
			$errors['consent'] = 'required';
		}
		return $errors === $result->errors() ? $result : new ValidationResult( $result->values(), $errors );
	}
}
