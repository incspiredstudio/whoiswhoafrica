<?php
/**
 * The staff Profiles screen (REQ-091, REQ-242, REQ-208, REQ-244): profiles in every state with filters and group-by, each row
 * showing what visitors see now. Needs read_private_wiw_profiles; the registry denies by default and the screen checks again.
 * The filter state lives in the address. The unpublish row action appears once its handler exists (M1-REV-03) and only for a
 * user who may unpublish; the bulk unpublish interface (M1-REV-10) plugs into the wiw_profiles_bulk_host action, which receives
 * the same sanitised filter set the list shows.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Directory\State\PubState;
use WhoIsWho\Core\Directory\State\StateRepository;
use WhoIsWho\Core\Platform\Admin\ActionGuard;
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
		$page    = max( 1, $request->int( 'paged' ) );
		$result  = ( new ProfilesQuery() )->run( $filters, $page );

		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Profiles', 'whoiswho' ) . '</h1>' . ( current_user_can( AddProfileScreen::CAPABILITY ) ? ' <a class="page-title-action" href="' . esc_url( AddProfileScreen::url() ) . '">' . esc_html__( 'Add profile', 'whoiswho' ) . '</a>' : '' ) . '<hr class="wp-header-end" />'; // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
		$this->filter_form( $filters );
		/* translators: %d: number of profiles. */
		echo '<p>' . esc_html( sprintf( _n( '%d profile', '%d profiles', $result['total'], 'whoiswho' ), $result['total'] ) ) . '</p>';
		if ( array() !== $result['groups'] ) {
			$this->groups( $result['groups'], $filters );
		}
		if ( current_user_can( 'wiw_unpublish_profiles' ) || current_user_can( 'wiw_bulk_unpublish' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
			do_action( 'wiw_profiles_bulk_host', $filters );
		}
		$this->table( $result['rows'] );
		$this->pager( $result['pages'], max( 1, min( $page, $result['pages'] ) ), $filters );
		echo '</div>';
	}

	/**
	 * Filter form (GET, so the filter state is in the address).
	 *
	 * @param array<string, string> $filters Filters.
	 */
	private function filter_form( array $filters ): void {
		echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '"><input type="hidden" name="page" value="' . esc_attr( self::SCREEN ) . '" />';
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

	/**
	 * The rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows from ProfilesQuery.
	 */
	private function table( array $rows ): void {
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Name', 'Type', 'State', 'Visitors see', 'Origin', 'Source', 'Batch', 'Claimed', '' ) as $heading ) {
			echo '<th scope="col">' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		if ( array() === $rows ) {
			echo '<tr><td colspan="9">' . esc_html__( 'No profiles match.', 'whoiswho' ) . '</td></tr>';
		}
		$can_unpublish = current_user_can( self::UNPUBLISH_CAP ) && has_action( 'admin_post_' . self::UNPUBLISH_ACT ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
		foreach ( $rows as $row ) {
			$edit = get_edit_post_link( (int) $row['id'] );
			echo '<tr><td>' . ( $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( (string) $row['name'] ) . '</a>' : esc_html( (string) $row['name'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['type'] ) . '</td><td>' . esc_html( (string) $row['state'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['visitor'] ) . ' (' . esc_html( (string) $row['visitor_http'] ) . ')</td>';
			echo '<td>' . esc_html( (string) $row['origin'] ) . '</td><td>' . esc_html( (string) $row['source'] ) . '</td><td>' . esc_html( (string) $row['batch'] ) . '</td>';
			echo '<td>' . ( $row['claimed'] ? '<span aria-label="' . esc_attr__( 'Claimed', 'whoiswho' ) . '">&#10003;</span>' : '' ) . '</td><td>';
			if ( $can_unpublish && 200 === $row['visitor_http'] ) {
				$url = add_query_arg(
					array(
						'action'     => self::UNPUBLISH_ACT,
						'profile_id' => (int) $row['id'],
						'_wpnonce'   => ActionGuard::nonce_for( self::UNPUBLISH_ACT ),
					),
					admin_url( 'admin-post.php' )
				);
				echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Unpublish', 'whoiswho' ) . '</a>';
			}
			if ( current_user_can( ProfileEditScreen::CAPABILITY ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
				echo ' <a href="' . esc_url( ProfileEditScreen::url( (int) $row['id'] ) ) . '">' . esc_html__( 'Edit fields', 'whoiswho' ) . '</a>';
			}
			echo '</td></tr>';
		}//end foreach
		echo '</tbody></table>';
	}

	/**
	 * Page links.
	 *
	 * @param int                   $pages   Page count.
	 * @param int                   $page    Current page.
	 * @param array<string, string> $filters Filters.
	 */
	private function pager( int $pages, int $page, array $filters ): void {
		if ( $pages < 2 ) {
			return;
		}
		echo '<p class="wiw-pager">';
		for ( $i = 1; $i <= $pages; $i++ ) {
			$url = add_query_arg(
				array_merge(
					$filters,
					array(
						'page'  => self::SCREEN,
						'paged' => $i,
					)
				),
				admin_url( 'admin.php' )
			);
			echo $i === $page ? '<strong>' . esc_html( (string) $i ) . '</strong> ' : '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a> ';
		}
		echo '</p>';
	}
}
