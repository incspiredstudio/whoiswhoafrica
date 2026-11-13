<?php
/**
 * The one validator of the enquiry form (REQ-177, REQ-039, REQ-018). The form carries no price, package or tier field: enquiries
 * are about listing and contact, never about purchases.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Support;

use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Enquiry validator.
 */
final class EnquiryValidator extends Validator {

	public const SOURCES = array( 'get-listed', 'contact', 'profile' );

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'enquiry';
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
				'type' => self::TYPE_TEXT,
				'max'  => 120,
			),
			'email'        => array(
				'type'     => self::TYPE_EMAIL,
				'required' => true,
			),
			'phone'        => array(
				'type' => self::TYPE_TEXT,
				'max'  => 40,
			),
			'message'      => array(
				'type'     => self::TYPE_TEXTAREA,
				'required' => true,
				'min'      => 5,
			),
			'source'       => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 20,
			),
		);
	}

	/**
	 * Validate, and refuse a source that is not one of the three entry points.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		if ( ! isset( $errors['source'] ) && ! in_array( $result->value( 'source' ), self::SOURCES, true ) ) {
			$errors['source'] = 'invalid';
			return new ValidationResult( $result->values(), $errors );
		}
		return $result;
	}
}
