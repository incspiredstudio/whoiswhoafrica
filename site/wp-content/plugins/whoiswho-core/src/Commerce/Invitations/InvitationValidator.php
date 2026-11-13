<?php
/**
 * The one validator of the customer invitation form (REQ-013, REQ-141): name, a profile type from a fixed list, a short summary
 * and a website. The summary and the name of the organisation or person are free text, so the shared scanner blocks phone numbers,
 * email addresses, home addresses, ages and sensitive words before anything is stored. There is no price, package or payment field.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Commerce\Invitations;

use WhoIsWho\Core\Compliance\FreeTextScanner;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Invitation validator.
 */
final class InvitationValidator extends Validator {

	public const TYPES = array( 'organisation', 'individual' );

	/**
	 * Free-text fields the scanner checks.
	 */
	public const FREE_TEXT = array( 'name', 'summary' );

	/**
	 * Scanner.
	 *
	 * @var FreeTextScanner
	 */
	private FreeTextScanner $scanner;

	/**
	 * Constructor.
	 *
	 * @param FreeTextScanner|null $scanner Scanner.
	 */
	public function __construct( ?FreeTextScanner $scanner = null ) {
		$this->scanner = $scanner ?? new FreeTextScanner();
	}

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'invitation';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'name'    => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 190,
			),
			'type'    => array(
				'type'     => self::TYPE_TEXT,
				'required' => true,
				'max'      => 20,
			),
			'summary' => array(
				'type' => self::TYPE_TEXTAREA,
				'max'  => 1500,
			),
			'website' => array(
				'type' => self::TYPE_TEXT,
				'max'  => 200,
			),
		);
	}

	/**
	 * Validate, then check the type, the website and the free text.
	 *
	 * @param FormInput $input Submitted values.
	 */
	public function validate( FormInput $input ): ValidationResult {
		$result = parent::validate( $input );
		$errors = $result->errors();
		if ( ! isset( $errors['type'] ) && ! in_array( $result->value( 'type' ), self::TYPES, true ) ) {
			$errors['type'] = 'invalid';
		}
		$site = $result->value( 'website' );
		if ( '' !== $site && ! isset( $errors['website'] ) && ( 1 !== preg_match( '#^https?://[^\s/$.?\#].[^\s]*$#i', $site ) ) ) {
			$errors['website'] = 'invalid_url';
		}
		if ( $errors !== $result->errors() ) {
			$result = new ValidationResult( $result->values(), $errors );
		}
		return $this->scanner->apply( $this->form(), $result, self::FREE_TEXT );
	}
}
