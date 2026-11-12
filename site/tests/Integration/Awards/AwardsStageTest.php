<?php
/**
 * The Awards stage read model (REQ-259, REQ-033, REQ-194).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Awards;

use WhoIsWho\Core\Awards\AwardsStage;
use WhoIsWho\Core\Platform\Settings\SettingsService;
use WhoIsWho\Tests\WpTestCase;

final class AwardsStageTest extends WpTestCase {

	private function stage( bool $open, bool $winner, bool $nominee ): AwardsStage {
		return new AwardsStage( static fn (): bool => $open, static fn (): bool => $winner, static fn (): bool => $nominee );
	}

	public function test_REQ_259_stage_read_model_follows_flag_and_live_items(): void {
		$cases = array(
			array( true, true, true, AwardsStage::WINNERS, 'See the winners', '/awards/winners/' ),
			array( false, false, true, AwardsStage::NOMINEES, 'See the nominees', '/awards/nominees/' ),
			array( true, false, false, AwardsStage::OPEN, 'Nominate someone', '/nominate/' ),
			array( false, false, false, AwardsStage::CLOSED, 'Read the awards rules', '/awards/rules/' ),
		);
		foreach ( $cases as $case ) {
			$button = $this->stage( $case[0], $case[1], $case[2] )->button();
			$this->assertSame( $case[3], $button['stage'] );
			$this->assertSame( $case[4], $button['label'] );
			$this->assertSame( home_url( $case[5] ), $button['url'] );
		}
		$this->assertSame( 'Nominations are closed', $this->stage( false, false, false )->button()['status'] );
		$this->assertNull( $this->stage( true, false, false )->home_button(), 'No awards button on the home page before anything is public' );
		$this->assertNotNull( $this->stage( false, false, true )->home_button() );
	}

	public function test_REQ_259_menus_drop_nominate_link_when_closed(): void {
		$items = array(
			(object) array( 'url' => home_url( '/awards/' ) ),
			(object) array( 'url' => home_url( '/nominate/' ) ),
			(object) array( 'url' => home_url( '/awards/winners/' ) ),
		);

		$closed = $this->stage( false, false, false );
		$this->assertSame( 'Nominations closed', $closed->nav_label() );
		$this->assertNull( $closed->nominate_url() );
		$this->assertSame( 'Nominate', $this->stage( true, false, false )->nav_label() );
		$this->assertSame( home_url( '/nominate/' ), $this->stage( true, false, false )->nominate_url() );

		$key = SettingsService::OPTION_PREFIX . SettingsService::NOMINATIONS_ENABLED;
		delete_option( $key );
		$this->assertCount( 2, AwardsStage::filter_menu_items( $items ), 'closed by default drops the Nominate link' );
		update_option( $key, '1' );
		$this->assertCount( 3, AwardsStage::filter_menu_items( $items ), 'open keeps it' );
		delete_option( $key );
	}
}
