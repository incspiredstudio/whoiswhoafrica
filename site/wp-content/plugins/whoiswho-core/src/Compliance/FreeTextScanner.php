<?php
/**
 * The free-text scanner for public and staff forms (REQ-141, REQ-067, REQ-068). It reuses the rules the import already applies
 * (phones, emails, addresses, ages, sensitive terms) so a form and an import judge the same text the same way. A form validator
 * calls apply() after its own checks: a field that fails gets a field-level error asking the person to remove the text. The
 * sensitive terms come from config/sensitive-terms.php, which Legal can adjust, and the wiw_sensitive_terms filter. Only the
 * form, the field and the rule are ever reported; the text itself is never logged or returned.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance;

use WhoIsWho\Core\Import\Scan\FreeTextScanner as RuleSet;
use WhoIsWho\Core\Platform\Forms\FailureReport;
use WhoIsWho\Core\Platform\Forms\ValidationResult;

/**
 * Free text scanner.
 */
final class FreeTextScanner {

	public const ERROR_CODE = 'remove_personal_text';

	/**
	 * Rules.
	 *
	 * @var RuleSet
	 */
	private RuleSet $rules;

	/**
	 * Constructor.
	 *
	 * @param RuleSet|null $rules Rule set; null builds one from the config list and the wiw_sensitive_terms filter.
	 */
	public function __construct( ?RuleSet $rules = null ) {
		$this->rules = $rules ?? new RuleSet( self::configured_terms() );
	}

	/**
	 * The sensitive terms: the config list, then the filter.
	 *
	 * @return string[]
	 */
	public static function configured_terms(): array {
		$config = require dirname( __DIR__, 2 ) . '/config/sensitive-terms.php';
		$terms  = (array) $config['terms'];
		if ( function_exists( 'apply_filters' ) ) {
			$terms = (array) apply_filters( 'wiw_sensitive_terms', $terms );
		}
		return array_values( array_map( 'strval', $terms ) );
	}

	/**
	 * Scan named fields.
	 *
	 * @param array<string, string> $values Field => text.
	 * @param string[]              $fields Which fields hold free text.
	 * @return array<int, array{field: string, rule: string}> One entry per field and rule that failed; never the text.
	 */
	public function scan( array $values, array $fields ): array {
		$found = array();
		foreach ( $fields as $field ) {
			$text = (string) ( $values[ $field ] ?? '' );
			if ( '' === trim( $text ) ) {
				continue;
			}
			foreach ( $this->rules->categories( $text ) as $rule ) {
				$found[] = array(
					'field' => $field,
					'rule'  => $rule,
				);
			}
		}
		return $found;
	}

	/**
	 * The validator hook: add a field-level error to a result for every free-text field that fails, and report the failure
	 * (form, field names and rule codes only).
	 *
	 * @param string           $form   Form type, for the failure report.
	 * @param ValidationResult $result Result from the form's validator.
	 * @param string[]         $fields Free-text fields to scan.
	 */
	public function apply( string $form, ValidationResult $result, array $fields ): ValidationResult {
		$found = $this->scan( $result->values(), $fields );
		if ( array() === $found ) {
			return $result;
		}
		$errors = $result->errors();
		$failed = array();
		foreach ( $found as $hit ) {
			if ( ! isset( $errors[ $hit['field'] ] ) ) {
				$errors[ $hit['field'] ] = self::ERROR_CODE;
			}
			$failed[] = $hit['field'] . ':' . $hit['rule'];
		}
		FailureReport::fire( $form, 'free_text_scan', $failed );
		return new ValidationResult( $result->values(), $errors );
	}

	/**
	 * The message to show under a field that failed the scan.
	 */
	public static function message(): string {
		return 'Please remove personal details such as phone numbers, email addresses, home addresses, ages or sensitive personal information.';
	}
}
