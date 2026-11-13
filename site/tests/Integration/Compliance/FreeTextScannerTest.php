<?php
/**
 * The free-text scanner for forms (REQ-141, REQ-067, REQ-068). Needs WordPress for the term filter and the failure action.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Compliance;

use WhoIsWho\Core\Compliance\FreeTextScanner;
use WhoIsWho\Core\Platform\Forms\FormInput;
use WhoIsWho\Core\Platform\Forms\ValidationResult;
use WhoIsWho\Core\Platform\Forms\Validator;
use WhoIsWho\Tests\TestCase;

final class FreeTextScannerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->requireWordPress();
	}

	protected function tearDown(): void {
		remove_all_filters( 'wiw_sensitive_terms' );
		remove_all_actions( 'wiw_form_failure' );
		parent::tearDown();
	}

	/**
	 * A validator with one free-text field, to prove the hook works on a real result.
	 */
	private function validated( string $text ): ValidationResult {
		$validator = new class() extends Validator {
			/**
			 * Form type.
			 */
			public function form(): string {
				return 'probe';
			}

			/**
			 * Fields.
			 *
			 * @return array<string, array<string, mixed>>
			 */
			protected function fields(): array {
				return array(
					'message' => array( 'type' => self::TYPE_TEXTAREA ),
					'other'   => array( 'type' => self::TYPE_TEXTAREA ),
				);
			}
		};
		return $validator->validate(
			new FormInput(
				array(
					'message' => $text,
					'other'   => 'Fine text about the programme.',
				)
			)
		);
	}

	public function test_REQ_141_scanner_blocks_phone_email_age_and_sensitive_word(): void {
		$scanner = new FreeTextScanner();
		$cases   = array(
			'phone'          => 'You can reach me on +234 803 555 0142.',
			'email'          => 'Write to jane.doe@example.com.',
			'age'            => 'She is 34 years old.',
			'address'        => 'I live at 12 Adeola Odeku Street.',
			'sensitive_term' => 'He was convicted last year.',
		);
		foreach ( $cases as $rule => $text ) {
			$result = $scanner->apply( 'probe', $this->validated( $text ), array( 'message', 'other' ) );
			$this->assertFalse( $result->is_valid(), $rule );
			$this->assertSame( array( 'message' => FreeTextScanner::ERROR_CODE ), $result->errors(), $rule . ': only the failing field is flagged' );
			$this->assertSame(
				array(
					array(
						'field' => 'message',
						'rule'  => $rule,
					),
				),
				$scanner->scan( $result->values(), array( 'message', 'other' ) ),
				$rule
			);
		}
		$this->assertTrue( $scanner->apply( 'probe', $this->validated( 'A plain description of the work.' ), array( 'message' ) )->is_valid() );
	}

	public function test_REQ_141_a_field_with_an_existing_error_keeps_it(): void {
		$result = new ValidationResult( array( 'message' => 'Call 0803 555 0142' ), array( 'message' => 'too_long' ) );
		$out    = ( new FreeTextScanner() )->apply( 'probe', $result, array( 'message' ) );
		$this->assertSame( array( 'message' => 'too_long' ), $out->errors() );
	}

	public function test_REQ_067_sensitive_keyword_list_is_configurable(): void {
		$text = 'Known for her work on zebrafish.';
		$this->assertTrue( ( new FreeTextScanner() )->apply( 'probe', $this->validated( $text ), array( 'message' ) )->is_valid() );
		add_filter(
			'wiw_sensitive_terms',
			static function ( $terms ): array {
				$terms   = is_array( $terms ) ? $terms : array();
				$terms[] = 'zebrafish';
				return $terms;
			}
		);
		$this->assertFalse( ( new FreeTextScanner() )->apply( 'probe', $this->validated( $text ), array( 'message' ) )->is_valid() );
		$config = require dirname( __DIR__, 3 ) . '/wp-content/plugins/whoiswho-core/config/sensitive-terms.php';
		foreach ( array( 'biometric', 'sexual orientation', 'trade union', 'criminal record', 'cancer', 'christian', 'political party' ) as $category_word ) {
			$this->assertContains( $category_word, $config['terms'], 'the list in config covers ' . $category_word );
		}
	}

	public function test_REQ_141_scanner_logs_no_text(): void {
		$seen = array();
		add_action(
			'wiw_form_failure',
			static function ( $form, $reason, $fields ) use ( &$seen ): void {
				$seen[] = array( $form, $reason, $fields );
			},
			10,
			3
		);
		$text = 'Ring me on +234 803 555 0142 about my cancer.';
		( new FreeTextScanner() )->apply( 'probe', $this->validated( $text ), array( 'message' ) );
		$scan_events = array_values( array_filter( $seen, static fn ( array $event ): bool => 'free_text_scan' === $event[1] ) );
		$this->assertCount( 1, $scan_events );
		$this->assertSame( 'probe', $scan_events[0][0] );
		$this->assertSame( array( 'message:phone', 'message:sensitive_term' ), $scan_events[0][2], 'field and rule, nothing else' );
		$this->assertStringNotContainsString( '803', (string) wp_json_encode( $seen ) );
		$this->assertStringNotContainsString( 'cancer', (string) wp_json_encode( $seen ) );
	}
}
