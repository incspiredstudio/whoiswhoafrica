<?php
/**
 * The staff form kit (REQ-275): each field has a label that points at it, help text below it, an inline error with aria-invalid
 * and aria-describedby, and its kept value, all escaped; the toggle is a switch; the textarea counter matches char-count.js; the
 * kit's styles load on WHOISWHO screens only. Needs the WordPress test suite.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Tests\Integration\Platform;

use WhoIsWho\Core\Platform\Admin\Ui\Form;
use WhoIsWho\Core\Platform\Admin\Ui\StaffAssets;
use WhoIsWho\Tests\WpTestCase;

final class StaffFormTest extends WpTestCase {

	public function test_REQ_275_text_field_has_label_help_error_and_kept_value(): void {
		$html = Form::text(
			'f_name',
			'Name <b>',
			'Ada "Q" <x>',
			array(
				'help'     => 'As on the source.',
				'error'    => 'This is required.',
				'required' => true,
			)
		);
		$this->assertStringContainsString( '<label for="wiw-f-f_name">Name &lt;b&gt;', $html );
		$this->assertStringContainsString( 'value="Ada &quot;Q&quot; &lt;x&gt;"', $html );
		$this->assertStringContainsString( 'aria-describedby="wiw-f-f_name-help wiw-f-f_name-error"', $html );
		$this->assertStringContainsString( 'aria-invalid="true"', $html );
		$this->assertStringContainsString( 'required="required"', $html );
		$this->assertStringContainsString( '<p class="wiw-error" id="wiw-f-f_name-error" role="alert">This is required.</p>', $html );
		$this->assertStringContainsString( 'wiw-field--invalid', $html );

		$clean = Form::email( 'contact', 'Email', 'a@example.test' );
		$this->assertStringContainsString( 'type="email"', $clean );
		$this->assertStringNotContainsString( 'aria-invalid', $clean );
		$this->assertStringNotContainsString( 'aria-describedby', $clean );
	}

	public function test_REQ_275_textarea_counter_matches_char_count_script(): void {
		$html = Form::textarea(
			'f_bio',
			'Bio',
			"Line one\n\nLine two",
			array(
				'maxlength' => 6000,
				'counter'   => true,
				'rows'      => 10,
			)
		);
		$this->assertMatchesRegularExpression( '#<textarea[^>]*name="f_bio"[^>]*maxlength="6000"[^>]*data-wiw-count="wiw-count-f_bio"#', $html );
		$this->assertMatchesRegularExpression( '#id="wiw-count-f_bio"[^>]*>18 / 6000 characters<#', $html );
		$this->assertStringContainsString( "Line one\n\nLine two</textarea>", $html );
	}

	public function test_REQ_275_toggle_select_radio_cards_section_and_actions(): void {
		$toggle = Form::toggle( 'public', 'Show publicly', true );
		$this->assertStringContainsString( 'role="switch"', $toggle );
		$this->assertStringContainsString( 'checked="checked"', $toggle );
		$this->assertStringContainsString( '<label class="wiw-toggle__label" for="wiw-f-public">', $toggle );

		$select = Form::searchable_select(
			'country',
			'Country',
			array(
				'1' => 'Nigeria',
				'2' => 'Ghana <b>',
			),
			'2',
			array( 'placeholder' => 'Choose' )
		);
		$this->assertStringContainsString( 'data-wiw-search="1"', $select );
		$this->assertStringContainsString( '<option value="2" selected=\'selected\'>Ghana &lt;b&gt;</option>', $select );
		$this->assertStringContainsString( '<option value="">Choose</option>', $select );

		$radios = Form::radio_cards(
			'kind',
			'Kind',
			array(
				'a' => array(
					'label'       => 'Person',
					'description' => 'A named individual.',
				),
				'b' => 'Organisation',
			),
			'b'
		);
		$this->assertStringContainsString( '<legend class="wiw-label">Kind</legend>', $radios );
		$this->assertMatchesRegularExpression( '#value="b" checked=\'checked\'#', $radios );
		$this->assertStringContainsString( 'A named individual.', $radios );

		$section = Form::section(
			'Photo',
			'<p>x</p>',
			array(
				'description' => 'Open licence only.',
				'id'          => 'wiw-image',
			)
		);
		$this->assertStringContainsString( '<section class="wiw-card" id="wiw-image" aria-labelledby="wiw-image-title">', $section, 'the id is on the section, so #wiw-image links land on the card' );
		$this->assertStringContainsString( '<h2 id="wiw-image-title" class="wiw-card__title">Photo</h2>', $section, 'the heading keeps an id of its own' );
		$this->assertSame( 1, substr_count( $section, 'id="wiw-image"' ), 'no duplicate id' );

		$this->assertStringContainsString( 'wiw-actions--sticky', Form::actions( '<button>Save</button>', array( 'sticky' => true ) ) );
		$this->assertStringContainsString( 'data-wiw-unsaved="1"', Form::open( '', array( 'unsaved' => true ) ) );
	}

	public function test_REQ_275_open_takes_an_enctype_and_extra_attributes(): void {
		$open = Form::open(
			'<input type="hidden" name="action" value="x" />',
			array(
				'class'   => 'wiw-upload',
				'enctype' => 'multipart/form-data',
				'attrs'   => array(
					'data-wiw-import'    => 'upload',
					'data-personal-data' => 'none',
					'novalidate'         => true,
					'data-skip'          => false,
					'onclick"x'          => 'y',
				),
			)
		);
		$this->assertStringStartsWith( '<form method="post" action="', $open );
		$this->assertStringContainsString( ' class="wiw-form wiw-upload"', $open );
		$this->assertStringContainsString( ' enctype="multipart/form-data"', $open );
		$this->assertStringContainsString( ' data-wiw-import="upload"', $open );
		$this->assertStringContainsString( ' data-personal-data="none"', $open );
		$this->assertStringContainsString( ' novalidate="novalidate"', $open );
		$this->assertStringNotContainsString( 'data-skip', $open, 'false leaves the attribute out' );
		$this->assertStringNotContainsString( 'onclick"', $open, 'attribute names are cleaned' );
		$this->assertStringEndsWith( '><input type="hidden" name="action" value="x" />', $open );

		$this->assertStringNotContainsString( 'enctype', Form::open( '', array( 'enctype' => 'text/evil' ) ), 'only the known encodings' );
		$this->assertStringContainsString( 'enctype="application/x-www-form-urlencoded"', Form::open( '', array( 'enctype' => 'application/x-www-form-urlencoded' ) ) );
		$this->assertStringNotContainsString( 'enctype', Form::open( '' ), 'none by default' );
	}

	public function test_REQ_275_row_marks_a_ready_made_control_invalid(): void {
		$html = Form::row( 'wiw-f-image_id', 'Image', '<input type="number" id="wiw-f-image_id" name="image_id" aria-describedby="wiw-image-name" />', array( 'error' => 'Choose an image.' ) );
		$this->assertStringContainsString( 'aria-describedby="wiw-image-name wiw-f-image_id-error" aria-invalid="true" />', $html );
		$this->assertStringContainsString( '<label for="wiw-f-image_id">Image</label>', $html );
	}

	public function test_REQ_275_kit_assets_load_on_whoiswho_screens_only(): void {
		$this->assertTrue( StaffAssets::is_staff_screen( 'review_page_wiw-review' ) );
		$this->assertTrue( StaffAssets::is_staff_screen( 'admin_page_wiw-profile-edit' ) );
		$this->assertTrue( StaffAssets::is_staff_screen( 'toplevel_page_wiw' ) );
		$this->assertFalse( StaffAssets::is_staff_screen( 'edit.php' ) );
		$this->assertFalse( StaffAssets::is_staff_screen( 'settings_page_wiwx' ) );
		$this->assertFalse( StaffAssets::is_staff_screen( 'toplevel_page_other-wiw-tool' ) );
	}
}
