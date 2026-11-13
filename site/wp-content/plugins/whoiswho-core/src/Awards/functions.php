<?php
/**
 * The theme-facing helpers of the Awards module: the one feature flag, the public stage, the winners data and the categories
 * (REQ-259, REQ-246, REQ-250). Templates ask these functions and never read an option or a table.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

if ( ! function_exists( 'wiw_nominations_open' ) ) {
	/**
	 * Whether the public nomination form is open. Closed on a clean install, and closed if the settings service cannot answer.
	 */
	function wiw_nominations_open(): bool {
		try {
			return \WhoIsWho\Core\Platform\Settings\SettingsService::for_site()->nominations_enabled();
		} catch ( \Throwable $e ) {
			unset( $e );
			return false;
		}
	}
}

if ( ! function_exists( 'wiw_awards_stage' ) ) {
	/**
	 * The public Awards stage for buttons, menus and pages (REQ-259): stage, status label, main button label and link, the
	 * navigation label, the Nominate link (null while closed) and the home page button (null before anything is public).
	 *
	 * @return array{stage: string, status: string, label: string, url: string, nav_label: string, nominate_url: string|null, home_button: array<string, string>|null, open: bool}
	 */
	function wiw_awards_stage(): array {
		$stage = \WhoIsWho\Core\Awards\AwardsStage::for_site();
		return array_merge(
			$stage->button(),
			array(
				'nav_label'    => $stage->nav_label(),
				'nominate_url' => $stage->nominate_url(),
				'home_button'  => $stage->home_button(),
				'open'         => $stage->nominations_open(),
			)
		);
	}
}

if ( ! function_exists( 'wiw_awards_winners' ) ) {
	/**
	 * The winners page data for the current address (REQ-246): filters, search, sort and page come from the query string through
	 * the request layer, and the result carries the page of winners, the year groups, the facet lists and the clean parameters.
	 *
	 * @return array<string, mixed>
	 */
	function wiw_awards_winners(): array {
		return ( new \WhoIsWho\Core\Awards\WinnersQuery() )->run( \WhoIsWho\Core\Awards\WinnersQuery::from_request() );
	}
}

if ( ! function_exists( 'wiw_awards_winners_url' ) ) {
	/**
	 * The winners page address for a set of filters; defaults stay out of the address (REQ-246).
	 *
	 * @param array<string, mixed> $params Filter values.
	 */
	function wiw_awards_winners_url( array $params = array() ): string {
		$query = \WhoIsWho\Core\Awards\WinnersQuery::query_string( $params );
		return home_url( '/awards/winners/' ) . ( '' === $query ? '' : '?' . $query );
	}
}

if ( ! function_exists( 'wiw_awards_categories' ) ) {
	/**
	 * The current award categories by group, for the Nominate page guidance and the form's category select (REQ-250).
	 *
	 * @return array<string, array{label: string, categories: array<int, array<string, mixed>>}>
	 */
	function wiw_awards_categories(): array {
		return \WhoIsWho\Core\Awards\CategoryService::for_site()->grouped();
	}
}
