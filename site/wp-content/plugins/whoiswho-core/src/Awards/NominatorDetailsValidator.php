<?php
/**
 * The one validator of step 1 of the nomination form (REQ-030, REQ-196): the nominator's name and a valid email. No account is
 * created; the email is verified by a single-use link before the nomination can be submitted.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Platform\Forms\Validator;

/**
 * Nominator details validator.
 */
final class NominatorDetailsValidator extends Validator {

	/**
	 * Form type.
	 */
	public function form(): string {
		return 'nomination_start';
	}

	/**
	 * Fields.
	 *
	 * @return array<string, array{type?: string, required?: bool, min?: int, max?: int}>
	 */
	protected function fields(): array {
		return array(
			'nominator_name'  => array(
				'type'     => self::TYPE_NAME,
				'required' => true,
			),
			'nominator_email' => array(
				'type'     => self::TYPE_EMAIL,
				'required' => true,
			),
		);
	}
}
