<?php
/**
 * The staff Profiles screen (REQ-091, REQ-242, REQ-208, REQ-244): profiles in every state with filters and group-by, each row
 * showing what visitors see now. Needs read_private_wiw_profiles; the registry denies by default and the screen checks again.
 * The filter state lives in the address. The unpublish row action appears once its handler exists (M1-REV-03) and only for a
 * user who may unpublish; the bulk unpublish interface (M1-REV-10) plugs into the wiw_profiles_bulk_host action, which receives
 * the same sanitised filter set the list shows. View links above the filters switch between all profiles and those that need
 * classification (REQ-009), each with its count. The rows are a shared staff list table (ProfilesTable, REQ-275) with row quick
 * actions and the bulk action Unpublish selected.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\ScreenRegistry;

/**
 * Profiles screen.
 */
final class ProfilesScreen {

	public const SCREEN        = 'wiw-profiles';
	public const CAPABILITY    = 'read_private_wiw_profiles';
	public const UNPUBLISH_CAP = 'wiw_unpublish_profiles';
	public const UNPUBLISH_ACT = 'wiw_unpublish_profile';

	/**
	 * Register the screen, in the admin only.
	 *
	 * @param ScreenRegistry|null $registry Registry; the shared one by default.
	 */
	public static function register( ?ScreenRegistry $registry = null ): void {
		$registry = $registry ?? ScreenRegistry::shared();
		$registry->register(
			self::SCREEN,
			__( 'Profiles', 'whoiswho' ),
			self::CAPABILITY,
			static function (): void {
				( new self() )->render();
			},
			false,
			'directory'
		);
	}

	/**
	 * Hook registration into the admin.
	 */
	public static function hooks(): void {
		add_action(
			'init',
			static function (): void {
				if ( ! is_admin() ) {
					return;
				}
				self::register();
				ScreenRegistry::shared()->hook();
				ProfilesTable::register_bulk_handler();
			},
			1
		);
	}

	/**
	 * Print the screen.
	 */
	public function render(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			wp_die( esc_html__( 'You are not allowed to see this page.', 'whoiswho' ), '', array( 'response' => 403 ) );
		}
		$request = Request::from_globals();
		$filters = ProfilesQuery::filters_from( $request );
		$result  = ( new ProfilesQuery() )->run( $filters, 1, 1 );

		echo '<div class="wrap wiw-staff"><h1 class="wp-heading-inline">' . esc_html__( 'Profiles', 'whoiswho' ) . '</h1>' . ( current_user_can( AddProfileScreen::CAPABILITY ) ? ' <a class="page-title-action" href="' . esc_url( AddProfileScreen::url() ) . '">' . esc_html__( 'Add profile', 'whoiswho' ) . '</a>' : '' ) . '<hr class="wp-header-end" />'; // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
		$this->views( $filters );
		$this->filter_form( $filters );
		/* translators: %d: number of profiles. */
		echo '<p>' . esc_html( sprintf( _n( '%d profile', '%d profiles', $result['total'], 'whoiswho' ), $result['total'] ) ) . '</p>';
		if ( array() !== $result['groups'] ) {
			$this->groups( $result['groups'], $filters );
		}
		if ( current_user_can( 'wiw_unpublish_profiles' ) || current_user_can( 'wiw_bulk_unpublish' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			do_action( 'wiw_profiles_bulk_host', $filters );
		}
		echo ( new ProfilesTable( $filters ) )->html( $request ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped while the markup is built.
		echo '</div>';
	}

	/**
	 * The view links: All and Needs classification, with counts.
	 *
	 * @param array<string, string> $filters Filters.
	 */
	private function views( array $filters ): void {
		$query = new ProfilesQuery();
		$needs = ProfilesQuery::NEEDS === ( $filters[ ProfilesQuery::CLASSIFICATION ] ?? '' );
		$all   = add_query_arg( 'page', self::SCREEN, admin_url( 'admin.php' ) );
		$views = array(
			array( __( 'All', 'whoiswho' ), $query->count( array() ), $all, ! $needs ),
			array( __( 'Needs classification', 'whoiswho' ), $query->count( array( ProfilesQuery::CLASSIFICATION => ProfilesQuery::NEEDS ) ), add_query_arg( ProfilesQuery::CLASSIFICATION, ProfilesQuery::NEEDS, $all ), $needs ),
		);
		$items = array();
		$last  = count( $views ) - 1;
		foreach ( $views as $i => list( $label, $count, $url, $current ) ) {
			// The separator sits inside the item, as in WordPress's own view links, so the list holds only list items.
			$items[] = '<li><a href="' . esc_url( $url ) . '"' . ( $current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . esc_html( (string) $count ) . ')</span></a>' . ( $i < $last ? ' |' : '' ) . '</li>';
		}
		echo '<ul class="subsubsub wiw-profile-views">' . implode( ' ', $items ) . '</ul><br class="clear" />'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each item is escaped above.
		if ( $needs ) {
			echo '<p class="description">' . esc_html__( 'Profiles whose main industry is missing or Unclassified, or that have no main country. Open one and set its classification.', 'whoiswho' ) . '</p>';
		}
	}

	/**
	 * Filter form (GET, so the filter state is in the address).
	 *
	 * @param array<string, string> $filters Filters.
	 */
	private function filter_form( array $filters ): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '" class="wiw-filters"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
		if ( isset( $filters[ ProfilesQuery::CLASSIFICATION ] ) ) {
			echo '<input type="hidden" name="' . esc_attr( ProfilesQuery::CLASSIFICATION ) . '" value="' . esc_attr( $filters[ ProfilesQuery::CLASSIFICATION ] ) . '" />';
		}
		echo '<label>' . esc_html__( 'Name', 'whoiswho' ) . ' <input type="search" name="name" value="' . esc_attr( $filters['name'] ?? '' ) . '" /></label> ';
		$this->select( 'state', __( 'State', 'whoiswho' ), PubState::all(), $filters );
		$this->select( 'type', __( 'Type', 'whoiswho' ), StateRepository::ENTITY_TYPES, $filters );
		$this->select( 'origin', __( 'Origin', 'whoiswho' ), StateRepository::ORIGINS, $filters );
		echo '<label>' . esc_html__( 'Source', 'whoiswho' ) . ' <input type="text" name="source" value="' . esc_attr( $filters['source'] ?? '' ) . '" /></label> ';
		echo '<label>' . esc_html__( 'Batch', 'whoiswho' ) . ' <input type="text" name="batch" value="' . esc_attr( $filters['batch'] ?? '' ) . '" /></label> ';
		$this->select( 'group', __( 'Group by', 'whoiswho' ), array_keys( ProfilesQuery::FILTERS ), $filters );
		echo '<button type="submit" class="button">' . esc_html__( 'Filter', 'whoiswho' ) . '</button></form>';
	}

	/**
	 * A select with an empty first option.
	 *
	 * @param string                $name    Field name.
	 * @param string                $label   Label.
	 * @param string[]              $options Values.
	 * @param array<string, string> $filters Current filters.
	 */
	private function select( string $name, string $label, array $options, array $filters ): void {
		echo '<label>' . esc_html( $label ) . ' <select name="' . esc_attr( $name ) . '"><option value="">' . esc_html__( 'Any', 'whoiswho' ) . '</option>';
		foreach ( $options as $value ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $filters[ $name ] ?? '', $value, false ) . '>' . esc_html( $value ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		echo '</select></label> ';
	}

	/**
	 * Group counts as links that apply the group value as a filter.
	 *
	 * @param array<int, array{value: string, count: int}> $groups  Groups.
	 * @param array<string, string>                        $filters Filters.
	 */
	private function groups( array $groups, array $filters ): void {
		$by = $filters['group'] ?? '';
		echo '<ul class="wiw-profile-groups">';
		foreach ( $groups as $group ) {
			$args = array_merge(
				$filters,
				array(
					'page'  => self::SCREEN,
					$by     => $group['value'],
					'group' => '',
				)
			);
			echo '<li><a href="' . esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) ) . '">' . esc_html( '' === $group['value'] ? __( '(none)', 'whoiswho' ) : $group['value'] ) . '</a> ' . esc_html( (string) $group['count'] ) . '</li>';
		}
		echo '</ul>';
	}
}
