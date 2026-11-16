<?php
/**
 * The Profiles list as a shared staff list table (REQ-091, REQ-208, REQ-242, REQ-275). Rows are the filtered profiles; each shows
 * what visitors see now. Row actions: Edit (edit_wiw_profiles), View (the public page, when it answers 200) and Unpublish (the
 * existing single action, wiw_unpublish_profiles, when the profile is public). One bulk action, Unpublish selected, repeats that
 * single action for each selected profile through the same review service (destructive: the browser asks, or a confirmation
 * step without JavaScript); the state machine writes the audit row per profile. The filters and the bulk-unpublish-by-filter
 * panel stay on the screen as they were.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Platform\Admin\ActionGuard;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Admin\Ui\ListTable;
use WhoIsWho\Core\Review\Admin\BulkUnpublishPanel;

/**
 * Profiles table.
 */
final class ProfilesTable extends ListTable {

	public const BULK = 'wiw_profiles_bulk';

	/**
	 * The sanitised filters (ProfilesQuery::filters_from).
	 *
	 * @var array<string, string>
	 */
	private array $filters;

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $filters Filters.
	 */
	public function __construct( array $filters = array() ) {
		$this->filters = $filters;
		parent::__construct( 'profile', 'profiles' );
	}

	/**
	 * {@inheritDoc}
	 */
	public static function bulk_action(): string {
		return self::BULK;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function capability(): string {
		return ProfilesScreen::CAPABILITY;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function screen_slug(): string {
		return ProfilesScreen::SCREEN;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function columns(): array {
		return array(
			'name'    => __( 'Name', 'whoiswho' ),
			'type'    => __( 'Type', 'whoiswho' ),
			'state'   => __( 'State', 'whoiswho' ),
			'visitor' => __( 'Visitors see', 'whoiswho' ),
			'origin'  => __( 'Origin', 'whoiswho' ),
			'source'  => __( 'Source', 'whoiswho' ),
			'batch'   => __( 'Batch', 'whoiswho' ),
			'claimed' => __( 'Claimed', 'whoiswho' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function primary_column(): string {
		return 'name';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $page     Page, from 1.
	 * @param int $per_page Rows per page.
	 */
	protected function fetch( int $page, int $per_page ): array {
		$result = ( new ProfilesQuery() )->run( $this->filters, $page, $per_page );
		return array(
			'items' => $result['rows'],
			'total' => $result['total'],
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_id( array $item ): int {
		return (int) $item['id'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function item_name( array $item ): string {
		return (string) $item['name'];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function empty_message(): string {
		return __( 'No profiles match.', 'whoiswho' );
	}

	/**
	 * Keep the filters and the page when coming back.
	 *
	 * @param Request $request Request.
	 * @return array<string, string|int>
	 */
	protected function screen_args( Request $request ): array {
		return array_merge( $this->filters, parent::screen_args( $request ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item   Item.
	 * @param string               $column Column key.
	 */
	protected function column_html( array $item, string $column ): string {
		$id = (int) $item['id'];
		switch ( $column ) {
			case 'name':
				$name = esc_html( (string) $item['name'] );
				return current_user_can( ProfileEditScreen::CAPABILITY ) ? '<a class="row-title" href="' . esc_url( ProfileEditScreen::url( $id ) ) . '">' . $name . '</a>' : '<strong>' . $name . '</strong>'; // phpcs:ignore WordPress.WP.Capabilities.Unknown -- generated for the profile type, listed in config/capabilities.php.
			case 'state':
				$state = (string) $item['state'];
				$tone  = 0 === strpos( $state, 'published_' ) ? 'success' : ( in_array( $state, array( 'unpublished', 'rejected', 'removed_suppressed' ), true ) ? 'danger' : 'neutral' );
				return self::pill( $state, $tone );
			case 'visitor':
				$http = (int) $item['visitor_http'];
				return self::pill( (string) $item['visitor'] . ' (' . $http . ')', 200 === $http ? 'success' : ( 410 === $http ? 'danger' : 'neutral' ) );
			case 'claimed':
				return $item['claimed'] ? '<span aria-hidden="true">&#10003;</span><span class="screen-reader-text">' . esc_html__( 'Claimed', 'whoiswho' ) . '</span>' : '';
			case 'type':
			case 'origin':
			case 'source':
			case 'batch':
				return esc_html( (string) $item[ $column ] );
		}
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $item Item.
	 */
	protected function row_actions_for( array $item ): array {
		$id      = (int) $item['id'];
		$public  = 200 === (int) $item['visitor_http'];
		$actions = array(
			'edit' => array(
				'label'      => __( 'Edit', 'whoiswho' ),
				'url'        => ProfileEditScreen::url( $id ),
				'capability' => ProfileEditScreen::CAPABILITY,
			),
		);
		if ( $public ) {
			$actions['view'] = array(
				'label'      => __( 'View', 'whoiswho' ),
				'url'        => (string) get_permalink( $id ),
				'capability' => ProfilesScreen::CAPABILITY,
			);
		}
		if ( $public && has_action( 'admin_post_' . ProfilesScreen::UNPUBLISH_ACT ) ) {
			$actions['unpublish'] = array(
				'label'       => __( 'Unpublish', 'whoiswho' ),
				'url'         => add_query_arg(
					array(
						'action'     => ProfilesScreen::UNPUBLISH_ACT,
						'profile_id' => $id,
						'_wpnonce'   => ActionGuard::nonce_for( ProfilesScreen::UNPUBLISH_ACT ),
					),
					admin_url( 'admin-post.php' )
				),
				'capability'  => ProfilesScreen::UNPUBLISH_CAP,
				'destructive' => true,
			);
		}
		return $actions;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function bulk_action_definitions(): array {
		return array(
			'unpublish' => array(
				'label'       => __( 'Unpublish selected', 'whoiswho' ),
				'capability'  => ProfilesScreen::UNPUBLISH_CAP,
				'destructive' => true,
				/* translators: %d: number of profiles unpublished. */
				'done'        => __( '%d unpublished', 'whoiswho' ),
				'handler'     => static fn ( int $id ) => BulkUnpublishPanel::unpublish_id( $id, get_current_user_id() ),
			),
		);
	}
}
