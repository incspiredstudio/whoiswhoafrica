<?php
/**
 * Module boundary rules (REQ-199, REQ-184, REQ-210, REQ-051). Each rule passes on the real source and fails on a
 * fixture that breaks it, so a rule that silently stopped matching would be noticed. Run alone with
 * `composer test -- --testsuite Arch`.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Arch;

use WhoIsWho\Tests\Arch\Rules\AwardsNeverReferencesCommerce;
use WhoIsWho\Tests\Arch\Rules\CommerceNeverWritesClaimedOrFeatured;
use WhoIsWho\Tests\Arch\Rules\OnlyAuditLoggerWritesAuditTable;
use WhoIsWho\Tests\Arch\Rules\OnlyStateMachineWritesPubState;
use WhoIsWho\Tests\Arch\Support\Rule;
use WhoIsWho\Tests\Arch\Support\SourceSet;
use WhoIsWho\Tests\TestCase;

final class ModuleBoundariesTest extends TestCase {

	/**
	 * The real plugin source, parsed once.
	 *
	 * @var SourceSet|null
	 */
	private static ?SourceSet $real = null;

	private function real(): SourceSet {
		if ( null === self::$real ) {
			self::$real = SourceSet::from_directory( dirname( __DIR__, 2 ) . '/wp-content/plugins/whoiswho-core/src' );
		}
		return self::$real;
	}

	private function assertHolds( Rule $rule ): void {
		$this->assertGreaterThan( 20, $this->real()->count(), 'the rule must read the real source' );
		$this->assertSame( array(), $rule->violations( $this->real() ) );
	}

	/**
	 * Assert a rule reports at least one violation for fixture code.
	 *
	 * @param Rule                  $rule Rule.
	 * @param array<string, string> $code Relative path => code.
	 */
	private function assertBroken( Rule $rule, array $code ): void {
		$this->assertNotSame( array(), $rule->violations( SourceSet::from_strings( $code ) ) );
	}

	/**
	 * Assert a rule accepts fixture code.
	 *
	 * @param Rule                  $rule Rule.
	 * @param array<string, string> $code Relative path => code.
	 */
	private function assertAccepted( Rule $rule, array $code ): void {
		$this->assertSame( array(), $rule->violations( SourceSet::from_strings( $code ) ) );
	}

	public function test_REQ_199_awards_never_references_commerce(): void {
		$this->assertHolds( new AwardsNeverReferencesCommerce() );
	}

	public function test_REQ_199_a_fixture_where_awards_uses_commerce_fails_the_rule(): void {
		$rule = new AwardsNeverReferencesCommerce();
		$this->assertBroken( $rule, array( 'Awards/Bad.php' => '<?php namespace WhoIsWho\Core\Awards; use WhoIsWho\Core\Commerce\Ledger; class Bad {}' ) );
		$this->assertBroken( $rule, array( 'Awards/Bad.php' => '<?php namespace WhoIsWho\Core\Awards; class Bad { function f() { return \WhoIsWho\Core\Commerce\Ledger::total(); } }' ) );
		$this->assertBroken( $rule, array( 'Awards/Bad.php' => '<?php namespace WhoIsWho\Core\Awards; class Bad { function f( $db ) { return $db->get_results( "SELECT * FROM wp_wiw_nominations n JOIN wp_wiw_arrangements a ON a.id = n.id" ); } }' ) );
		$this->assertAccepted( $rule, array( 'Awards/Good.php' => '<?php namespace WhoIsWho\Core\Awards; class Good { function f( $db ) { return $db->get_results( "SELECT * FROM wp_wiw_nominations" ); } }' ) );
		$this->assertAccepted( $rule, array( 'Commerce/Fine.php' => '<?php namespace WhoIsWho\Core\Commerce; use WhoIsWho\Core\Commerce\Ledger; class Fine {}' ) );
	}

	public function test_REQ_184_commerce_never_writes_claimed_or_featured(): void {
		$this->assertHolds( new CommerceNeverWritesClaimedOrFeatured() );
	}

	public function test_REQ_184_a_fixture_where_commerce_writes_a_trust_mark_fails_the_rule(): void {
		$rule = new CommerceNeverWritesClaimedOrFeatured();
		$this->assertBroken( $rule, array( 'Commerce/Bad.php' => '<?php namespace WhoIsWho\Core\Commerce; class Bad { function f( $db ) { $db->update( "t", array( "featured" => 1 ), array( "id" => 1 ) ); } }' ) );
		$this->assertBroken( $rule, array( 'Commerce/Bad.php' => '<?php namespace WhoIsWho\Core\Commerce; class Bad { function f( $db ) { $db->query( "UPDATE wp_wiw_profile_state SET claimed = 1" ); } }' ) );
		$this->assertBroken( $rule, array( 'Commerce/Bad.php' => '<?php namespace WhoIsWho\Core\Commerce; class Bad { function f() { stick_post( 5 ); } }' ) );
		$this->assertBroken( $rule, array( 'Content/SponsoredLabel.php' => '<?php namespace WhoIsWho\Core\Content; class SponsoredLabel { function f( $db ) { $db->update( "t", array( "trust" => "verified" ), array() ); } }' ) );
		$this->assertAccepted( $rule, array( 'Commerce/Good.php' => '<?php namespace WhoIsWho\Core\Commerce; class Good { function f( $db ) { $db->update( "t", array( "sponsored_until" => "2027-01-01" ), array() ); } }' ) );
	}

	public function test_REQ_210_only_state_machine_writes_pub_state(): void {
		$this->assertHolds( new OnlyStateMachineWritesPubState() );
	}

	public function test_REQ_210_a_fixture_that_writes_pub_state_outside_the_state_machine_fails_the_rule(): void {
		$rule = new OnlyStateMachineWritesPubState();
		$this->assertBroken( $rule, array( 'Import/Bad.php' => '<?php namespace WhoIsWho\Core\Import; class Bad { function f( $db ) { $db->update( "t", array( "pub_state" => "published_reviewed" ), array() ); } }' ) );
		$this->assertBroken( $rule, array( 'Import/Bad.php' => '<?php namespace WhoIsWho\Core\Import; class Bad { function f( $db ) { $db->query( "UPDATE t SET pub_state = %s" ); } }' ) );
		$this->assertBroken( $rule, array( 'Import/Bad.php' => '<?php namespace WhoIsWho\Core\Import; class Bad { function f() { update_post_meta( 1, "pub_state", "x" ); } }' ) );
		$this->assertBroken( $rule, array( 'Import/Bad.php' => '<?php namespace WhoIsWho\Core\Import; class Bad { function f() { wp_update_post( array( "ID" => 1, "post_status" => "publish", "post_type" => "wiw_profile" ) ); } }' ) );
		$this->assertAccepted( $rule, array( 'Review/StateMachine.php' => '<?php namespace WhoIsWho\Core\Review; class StateMachine { function f( $db ) { $db->update( "t", array( "pub_state" => "x" ), array() ); } }' ) );
		$this->assertAccepted( $rule, array( 'Content/Stories.php' => '<?php namespace WhoIsWho\Core\Content; class Stories { function f() { wp_update_post( array( "ID" => 1, "post_status" => "publish", "post_type" => "post" ) ); } }' ) );
		$this->assertAccepted( $rule, array( 'Directory/Read.php' => '<?php namespace WhoIsWho\Core\Directory; class Read { function f( $row ) { return $row["pub_state"]; } }' ) );
	}

	public function test_REQ_051_only_audit_logger_writes_audit_table(): void {
		$this->assertHolds( new OnlyAuditLoggerWritesAuditTable() );
	}

	public function test_REQ_051_a_fixture_that_updates_the_audit_table_outside_audit_logger_fails_the_rule(): void {
		$rule = new OnlyAuditLoggerWritesAuditTable();
		$this->assertBroken( $rule, array( 'Review/Bad.php' => '<?php namespace WhoIsWho\Core\Review; class Bad { function f( $db ) { $db->query( "UPDATE wp_wiw_audit_log SET action = %s" ); } }' ) );
		$this->assertBroken( $rule, array( 'Import/Bad.php' => '<?php namespace WhoIsWho\Core\Import; class Bad { function f( $db ) { $db->insert( $db->prefix . "wiw_audit_log", array() ); } }' ) );
		$this->assertAccepted( $rule, array( 'Platform/Audit/AuditLogger.php' => '<?php namespace WhoIsWho\Core\Platform\Audit; class AuditLogger { function f( $db ) { $db->query( "INSERT INTO wiw_audit_log" ); } }' ) );
		$this->assertAccepted( $rule, array( 'Platform/Pii/PiiCatalogue.php' => '<?php namespace WhoIsWho\Core\Platform\Pii; class PiiCatalogue { const X = array( "wiw_audit_log.action" => "no person" ); }' ) );
	}
}
