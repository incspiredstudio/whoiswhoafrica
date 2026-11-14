<?php
/**
 * The classification controls of Edit profile and Add profile (REQ-009, REQ-125, REQ-209): an Industry picker (the main industry
 * as a select grouped by sector with a filter box, plus the profile's other industries as removable chips and a select to add
 * one), an Organisation type dropdown (organisations and institutions only), a Country dropdown and a City select narrowed to the
 * chosen country. Everything works without JavaScript; classification.js adds the filter box behaviour, the chips and the city
 * narrowing. Markup only: values are read back by from_request() and checked by ClassificationInput.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Directory\Edit\ClassificationInput;
use WhoIsWho\Core\Platform\Admin\Request;

/**
 * Classification picker.
 */
final class ClassificationPicker {

	/**
	 * Field names used on Edit profile.
	 */
	public const EDIT_NAMES = array(
		'present'          => 'cls_present',
		'primary_industry' => 'cls_primary_industry',
		'industries'       => 'cls_industries',
		'org_type'         => 'cls_org_type',
		'country'          => 'cls_country',
		'city'             => 'cls_city',
	);

	/**
	 * Field names used on Add profile (the country keeps its earlier name). The form always carries the picker, so it has no
	 * "present" marker.
	 */
	public const ADD_NAMES = array(
		'present'          => '',
		'primary_industry' => 'primary_industry',
		'industries'       => 'industries',
		'org_type'         => 'org_type',
		'country'          => 'country',
		'city'             => 'city',
	);

	/**
	 * Field names.
	 *
	 * @var array<string, string>
	 */
	private array $names;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $names EDIT_NAMES or ADD_NAMES.
	 */
	public function __construct( array $names = self::EDIT_NAMES ) {
		$this->names = $names;
	}

	/**
	 * Enqueue the script on a screen.
	 */
	public static function enqueue(): void {
		wp_enqueue_script( 'wiw-classification', plugins_url( 'assets/admin/directory/classification.js', WIW_CORE_FILE ), array(), WIW_CORE_VERSION, true );
		wp_add_inline_style( 'common', '.wiw-chips{display:flex;flex-wrap:wrap;gap:.25rem .5rem;margin:0 0 .5rem;padding:0;list-style:none}.wiw-chip{display:inline-flex;align-items:center;gap:.25rem;border:1px solid currentColor;border-radius:1rem;padding:.125rem .625rem}.wiw-filter{display:block;margin:0 0 .25rem}' );
	}

	/**
	 * The classification a request sends, or null when the form had no picker.
	 *
	 * @param Request $request Request.
	 * @return array{primary_industry: int, industries: int[], org_type: int, country: int, city: int}|null
	 */
	public function from_request( Request $request ): ?array {
		if ( '' !== $this->names['present'] && ! $request->has( $this->names['present'] ) ) {
			return null;
		}
		return array(
			'primary_industry' => $request->int( $this->names['primary_industry'] ),
			'industries'       => array_values( array_filter( $request->ints( $this->names['industries'] ), static fn ( int $id ): bool => $id > 0 ) ),
			'org_type'         => $request->int( $this->names['org_type'] ),
			'country'          => $request->int( $this->names['country'] ),
			'city'             => $request->int( $this->names['city'] ),
		);
	}

	/**
	 * The rows of the picker: name (for the label's for= and an error key), label and the input markup (escaped).
	 *
	 * @param array{primary_industry: int, industries: int[], org_type: int, country: int, city: int} $current     Current values.
	 * @param string                                                                                  $entity_type Entity type.
	 * @param bool                                                                                    $required    Whether the country and, for organisations, the type are required.
	 * @return array<int, array{name: string, label: string, html: string}>
	 */
	public function rows( array $current, string $entity_type, bool $required = false ): array {
		$tree   = ClassificationInput::industry_tree();
		$places = ClassificationInput::places();
		$rows   = array();
		$rows[] = array(
			'name'  => $this->names['primary_industry'],
			'label' => __( 'Industry', 'whoiswho' ),
			'html'  => ( '' === $this->names['present'] ? '' : '<input type="hidden" name="' . esc_attr( $this->names['present'] ) . '" value="1" />' ) . $this->industry( $tree, $current ),
		);
		$rows[] = array(
			'name'  => $this->names['industries'] . '_add',
			'label' => __( 'Other industries', 'whoiswho' ),
			'html'  => $this->extras( $tree, $current['industries'] ),
		);
		if ( in_array( $entity_type, ClassificationInput::ORG_ENTITY_TYPES, true ) ) {
			$rows[] = array(
				'name'  => $this->names['org_type'],
				'label' => __( 'Organisation type', 'whoiswho' ) . ( $required ? ' *' : '' ),
				'html'  => $this->select( $this->names['org_type'], ClassificationInput::org_types(), $current['org_type'], $required, __( 'Choose a type', 'whoiswho' ) ) . $this->help( __( 'The kind of body, for example Company, University or NGO / non-profit. Add a type on Directory > Organisation types.', 'whoiswho' ) ),
			);
		}
		$rows[] = array(
			'name'  => $this->names['country'],
			'label' => __( 'Country', 'whoiswho' ) . ( $required ? ' *' : '' ),
			'html'  => $this->select( $this->names['country'], $places['countries'], $current['country'], $required, __( 'Choose a country', 'whoiswho' ) ) . $this->help( __( 'The head-office country of an organisation, or the main country of a person. Choose Pan-African for a body that covers the continent.', 'whoiswho' ) ),
		);
		$rows[] = array(
			'name'  => $this->names['city'],
			'label' => __( 'City (optional)', 'whoiswho' ),
			'html'  => $this->cities( $places, $current['city'] ),
		);
		return $rows;
	}

	/**
	 * The main industry: a filter box and a select grouped by sector, with Unclassified first.
	 *
	 * @param array<int, array{name: string, industries: array<int, string>}>                         $tree    Industry tree.
	 * @param array{primary_industry: int, industries: int[], org_type: int, country: int, city: int} $current Current values.
	 */
	private function industry( array $tree, array $current ): string {
		$name   = $this->names['primary_industry'];
		$filter = 'wiw-f-' . $name . '-filter';
		$out    = '<label class="screen-reader-text" for="' . esc_attr( $filter ) . '">' . esc_html__( 'Filter the industries', 'whoiswho' ) . '</label>';
		$out   .= '<input type="search" id="' . esc_attr( $filter ) . '" class="wiw-filter regular-text" data-wiw-filter-for="wiw-f-' . esc_attr( $name ) . '" placeholder="' . esc_attr__( 'Type to filter the list', 'whoiswho' ) . '" hidden="hidden" />';
		$out   .= '<select id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Not chosen', 'whoiswho' ) . '</option>';
		$unc    = ClassificationInput::unclassified_id();
		if ( $unc > 0 ) {
			$out .= '<option value="' . esc_attr( (string) $unc ) . '"' . selected( $current['primary_industry'], $unc, false ) . '>' . esc_html__( 'Unclassified (needs classification)', 'whoiswho' ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		$out .= $this->grouped_options( $tree, array( $current['primary_industry'] ) ) . '</select>';
		return $out . $this->help( __( 'The main industry decides the profile\'s breadcrumb (Country, Sector, Industry). Add an industry under its sector on Directory > Industries.', 'whoiswho' ) );
	}

	/**
	 * Other industries: the profile's own as chips (untick to remove), and a select to add one.
	 *
	 * @param array<int, array{name: string, industries: array<int, string>}> $tree   Industry tree.
	 * @param int[]                                                           $extras Current extra industry ids.
	 */
	private function extras( array $tree, array $extras ): string {
		$name   = $this->names['industries'];
		$labels = array();
		foreach ( $tree as $sector ) {
			foreach ( $sector['industries'] as $id => $label ) {
				$labels[ $id ] = $label . ' (' . $sector['name'] . ')';
			}
		}
		$out = '<ul class="wiw-chips" data-wiw-chips="' . esc_attr( $name ) . '" aria-label="' . esc_attr__( 'Other industries of this profile', 'whoiswho' ) . '">';
		foreach ( $extras as $id ) {
			$out .= '<li><label class="wiw-chip"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( (string) $id ) . '" checked="checked" /> ' . esc_html( $labels[ $id ] ?? (string) $id ) . '</label></li>';
		}
		$out .= '</ul>';
		$out .= '<select id="wiw-f-' . esc_attr( $name ) . '_add" name="' . esc_attr( $name ) . '[]" data-wiw-chip-add="' . esc_attr( $name ) . '"><option value="0">' . esc_html__( 'Add another industry', 'whoiswho' ) . '</option>' . $this->grouped_options( $tree, array() ) . '</select>';
		// The script swaps the hint as chips are added or unticked, so it never says "None yet" beside a chip.
		$none = __( 'None yet. Extra industries list the profile on those pages too; they do not change its breadcrumb.', 'whoiswho' );
		$some = __( 'Untick an industry to remove it. Extra industries list the profile on those pages too; they do not change its breadcrumb.', 'whoiswho' );
		return $out . '<p class="description" data-wiw-chips-hint="' . esc_attr( $name ) . '" data-hint-none="' . esc_attr( $none ) . '" data-hint-some="' . esc_attr( $some ) . '">' . esc_html( array() === $extras ? $none : $some ) . '</p>';
	}

	/**
	 * Options grouped by sector.
	 *
	 * @param array<int, array{name: string, industries: array<int, string>}> $tree     Industry tree.
	 * @param int[]                                                           $selected Selected ids.
	 */
	private function grouped_options( array $tree, array $selected ): string {
		$out = '';
		foreach ( $tree as $sector ) {
			if ( array() === $sector['industries'] ) {
				continue;
			}
			$out .= '<optgroup label="' . esc_attr( $sector['name'] ) . '">';
			foreach ( $sector['industries'] as $id => $label ) {
				$out .= '<option value="' . esc_attr( (string) $id ) . '"' . selected( in_array( $id, $selected, true ), true, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
			}
			$out .= '</optgroup>';
		}
		return $out;
	}

	/**
	 * Cities grouped by country; the script shows only the chosen country's group.
	 *
	 * @param array{countries: array<int, string>, cities: array<int, array<int, string>>} $places Places.
	 * @param int                                                                          $city   Current city.
	 */
	private function cities( array $places, int $city ): string {
		$name = $this->names['city'];
		$out  = '<select id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" data-wiw-city-for="wiw-f-' . esc_attr( $this->names['country'] ) . '"><option value="0">' . esc_html__( 'No city', 'whoiswho' ) . '</option>';
		foreach ( $places['cities'] as $country_id => $cities ) {
			$label = $places['countries'][ $country_id ] ?? '';
			if ( '' === $label ) {
				continue;
			}
			$out .= '<optgroup label="' . esc_attr( $label ) . '" data-wiw-country="' . esc_attr( (string) $country_id ) . '">';
			foreach ( $cities as $id => $city_name ) {
				$out .= '<option value="' . esc_attr( (string) $id ) . '"' . selected( $city, $id, false ) . '>' . esc_html( $city_name ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
			}
			$out .= '</optgroup>';
		}
		$out .= '</select>';
		return $out . $this->help( __( 'Only cities in the chosen country are offered. Add a city on Directory > Countries & cities, with its country as Parent.', 'whoiswho' ) );
	}

	/**
	 * A plain select with an empty first option.
	 *
	 * @param string             $name        Name and id suffix.
	 * @param array<int, string> $options     Id => label.
	 * @param int                $value       Selected id.
	 * @param bool               $required    Required.
	 * @param string             $placeholder First option text.
	 */
	private function select( string $name, array $options, int $value, bool $required, string $placeholder ): string {
		$out = '<select id="wiw-f-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"' . ( $required ? ' required="required"' : '' ) . '><option value="">' . esc_html( $placeholder ) . '</option>';
		foreach ( $options as $id => $label ) {
			$out .= '<option value="' . esc_attr( (string) $id ) . '"' . selected( $value, $id, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		return $out . '</select>';
	}

	/**
	 * A line of help text.
	 *
	 * @param string $text Text.
	 */
	private function help( string $text ): string {
		return '<p class="description">' . esc_html( $text ) . '</p>';
	}
}
