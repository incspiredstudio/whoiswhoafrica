<?php
/**
 * Public form routes (REQ-251, REQ-177, REQ-039, REQ-058, REQ-081, REQ-122).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Unit\Compliance;

use PHPUnit\Framework\TestCase;
use WhoIsWho\Core\Compliance\Forms\FormRoutes;
use WhoIsWho\Core\Compliance\Forms\PublicSubmission;
use WhoIsWho\Core\Platform\Forms\FormInput;

final class FormRoutesTest extends TestCase {

	/**
	 * Calls recorded by the fake handlers.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $calls = array();

	private function routes_with( string $form ): FormRoutes {
		return new FormRoutes(
			array(
				$form => function () use ( $form ): array {
					$this->calls[] = array( 'form' => $form );
					return array(
						'status'    => PublicSubmission::RECEIVED,
						'message'   => 'm',
						'errors'    => array(),
						'reference' => 'DSR-0001',
					);
				},
			)
		);
	}

	private function routes( string $status = PublicSubmission::RECEIVED ): FormRoutes {
		$make = function ( string $form ) use ( $status ): callable {
			return function ( FormInput $input, string $ip, string $slug = '' ) use ( $form, $status ): array {
				$this->calls[] = array(
					'form' => $form,
					'ip'   => $ip,
					'slug' => $slug,
					'name' => $input->raw( 'name' ),
				);
				return array(
					'status'    => $status,
					'message'   => 'm',
					'errors'    => array(),
					'reference' => 'REF-1',
				);
			};
		};
		return new FormRoutes(
			array(
				'claim'           => $make( 'claim' ),
				'get_listed'      => $make( 'get_listed' ),
				'contact'         => $make( 'contact' ),
				'profile_enquiry' => $make( 'profile_enquiry' ),
				'newsletter'      => $make( 'newsletter' ),
			)
		);
	}

	public function test_REQ_251_post_claim_slug_calls_claim_with_the_slug(): void {
		$result = $this->routes()->dispatch( 'POST', 'claim/acme-ltd/', new FormInput( array( 'name' => 'Ada' ) ), '203.0.113.9' );
		$this->assertSame( 'claim', $this->calls[0]['form'] );
		$this->assertSame( 'acme-ltd', $this->calls[0]['slug'] );
		$this->assertSame( '203.0.113.9', $this->calls[0]['ip'] );
		$this->assertSame( 'Ada', $this->calls[0]['name'] );
		$this->assertSame( 303, $result['http'] );
	}

	/**
	 * A non-Latin slug arrives percent-encoded; the claim and enquiry forms still reach their handler with it (#810).
	 */
	public function test_REQ_003_a_percent_encoded_slug_reaches_the_form(): void {
		$routes = $this->routes();
		$routes->dispatch( 'POST', 'claim/%d9%85%d8%a4-qwv/', new FormInput( array() ), '203.0.113.9' );
		$routes->dispatch( 'POST', 'enquire/%d9%85%d8%a4-qwv', new FormInput( array() ), '203.0.113.9' );
		$this->assertSame( array( 'claim', 'profile_enquiry' ), array_column( $this->calls, 'form' ) );
		$this->assertSame( array( '%d9%85%d8%a4-qwv', '%d9%85%d8%a4-qwv' ), array_column( $this->calls, 'slug' ) );
	}

	public function test_REQ_177_post_subscribe_is_the_get_listed_form(): void {
		$this->routes()->dispatch( 'POST', '/subscribe', new FormInput( array() ), '' );
		$this->assertSame( 'get_listed', $this->calls[0]['form'] );
	}

	public function test_REQ_039_post_contact_is_the_contact_form(): void {
		$this->routes()->dispatch( 'POST', 'contact', new FormInput( array() ), '' );
		$this->assertSame( 'contact', $this->calls[0]['form'] );
	}

	public function test_REQ_018_post_enquire_slug_calls_profile_enquiry_with_the_slug(): void {
		$result = $this->routes()->dispatch( 'POST', 'enquire/acme-ltd', new FormInput( array( 'name' => 'Bola' ) ), '203.0.113.4' );
		$this->assertSame( 'profile_enquiry', $this->calls[0]['form'] );
		$this->assertSame( 'acme-ltd', $this->calls[0]['slug'] );
		$this->assertSame( 303, $result['http'] );
		$this->assertNull( $this->routes()->dispatch( 'POST', 'enquire/', new FormInput( array() ), '' ) );
	}

	public function test_REQ_061_post_newsletter_is_the_newsletter_form(): void {
		$this->routes()->dispatch( 'POST', '/newsletter/', new FormInput( array() ), '' );
		$this->assertSame( 'newsletter', $this->calls[0]['form'] );
	}

	public function test_REQ_059_post_data_rights_is_the_data_rights_form_and_done_url_names_the_kind(): void {
		$result = $this->routes_with( 'data_rights' )->dispatch( 'POST', '/data-rights/', new FormInput( array() ), '' );
		$this->assertSame( 'data_rights', $this->calls[0]['form'] );
		$this->assertSame( 303, $result['http'] );
		$this->assertStringContainsString( 'kind=data_rights', FormRoutes::done_url( $result ) );
	}

	public function test_REQ_060_enquiries_switched_off_is_refused_with_409(): void {
		$this->assertSame( 409, FormRoutes::http_status( 'enquiries_off' ) );
	}

	public function test_REQ_058_get_requests_and_other_paths_are_left_alone(): void {
		$routes = $this->routes();
		$this->assertNull( $routes->dispatch( 'GET', 'contact', new FormInput( array() ), '' ) );
		$this->assertNull( $routes->dispatch( 'POST', 'about', new FormInput( array() ), '' ) );
		$this->assertNull( $routes->dispatch( 'POST', 'claim/', new FormInput( array() ), '' ) );
		$this->assertNull( $routes->dispatch( 'POST', 'claim/a/b', new FormInput( array() ), '' ) );
		$this->assertSame( array(), $this->calls );
	}

	public function test_REQ_122_refusals_map_to_error_statuses_without_revealing_records(): void {
		$this->assertSame( 422, FormRoutes::http_status( 'invalid' ) );
		$this->assertSame( 429, FormRoutes::http_status( PublicSubmission::TRY_LATER ) );
		$this->assertSame( 400, FormRoutes::http_status( PublicSubmission::EXPIRED ) );
		$this->assertSame( 404, FormRoutes::http_status( 'not_found' ) );
		$this->assertSame( 409, FormRoutes::http_status( 'already_claimed' ) );
		$this->assertSame( 409, FormRoutes::http_status( 'review_first' ) );
		$this->assertSame( 400, FormRoutes::http_status( 'something_else' ) );
	}

	public function test_REQ_030_post_nominate_routes_reach_the_nomination_steps(): void {
		$make   = function ( string $form ): callable {
			return function ( FormInput $input, string $ip ) use ( $form ): array {
				$this->calls[] = array(
					'form' => $form,
					'ip'   => $ip,
					'slug' => '',
					'name' => $input->raw( 'name' ),
				);
				return array(
					'status'    => 'duplicate',
					'message'   => 'm',
					'errors'    => array(),
					'reference' => '',
				);
			};
		};
		$routes = new FormRoutes(
			array(
				'nomination_start' => $make( 'nomination_start' ),
				'nomination'       => $make( 'nomination' ),
			)
		);
		$start  = $routes->dispatch( 'POST', 'nominate/start/', new FormInput( array() ), '203.0.113.7' );
		$final  = $routes->dispatch( 'POST', '/nominate', new FormInput( array() ), '203.0.113.7' );
		$this->assertSame( 'nomination_start', $start['form'] );
		$this->assertSame( 'nomination', $final['form'] );
		$this->assertSame( 409, $final['http'], 'a duplicate warning is a 409 the visitor can continue past' );
		$this->assertNull( $routes->dispatch( 'GET', 'nominate', new FormInput( array() ), '' ), 'the page itself is the theme\'s' );
		$this->assertSame( 409, FormRoutes::http_status( 'closed' ) );
		$this->assertSame( 409, FormRoutes::http_status( 'unverified' ) );
	}
}
