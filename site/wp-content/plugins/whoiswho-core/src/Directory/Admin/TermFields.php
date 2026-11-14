<?php
/**
 * Extra fields and rules on the native term screens of the classification lists (REQ-009):
 * - Places: kind (Country, City or Pan-African body), a two-letter country code (required and unique for countries) and a parent
 *   country (required for cities).
 * - Industries: top-level terms are sectors; an industry needs a sector as its parent (never a third level). "Also known as" names
 *   are kept in term meta wiw_alias and used by import matching.
 * - Organisation types: "Also known as".
 * - The "unclassified" industry cannot be deleted or renamed.
 * - A slug change keeps the old slug in term meta wiw_old_slugs, so old directory addresses redirect (see OldTermSlugRedirect).
 * - Clear admin columns: Kind, Code and Country for places, Sector and Also known as for industries.
 *
 * The term screens need the taxonomy's manage_terms (Editors and Administrators). Saving the extra fields checks this class's nonce
 * and edit_term on the term; each save is audited through AuditLogger with the term id and the values (no personal data).
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Directory\Admin;

use WhoIsWho\Core\Directory\Classification;
use WhoIsWho\Core\Directory\Edit\ClassificationInput;
use WhoIsWho\Core\Directory\Taxonomies;
use WhoIsWho\Core\Platform\Admin\Request;
use WhoIsWho\Core\Platform\Audit\AuditLogger;

/**
 * Term screen fields and rules.
 */
final class TermFields {

	public const NONCE          = 'wiw_term_fields';
	public const NONCE_FIELD    = 'wiw_term_nonce';
	public const META_ALIAS     = Taxonomies::META_ALIAS;
	public const META_OLD_SLUGS = 'wiw_old_slugs';
	public const ERROR_ARG      = 'wiw_term_error';

	/**
	 * Longest alias list and alias.
	 */
	private const MAX_ALIASES = 50;
	private const ALIAS_MAX   = 100;

	/**
	 * Slugs of terms whose slug is about to change, captured before the update: term id => old slug.
	 *
	 * @var array<int, string>
	 */
	private static array $old_slugs = array();

	/**
	 * Register the hooks.
	 */
	public static function hooks(): void {
		$self = new self();
		foreach ( array( Taxonomies::PLACE, Taxonomies::INDUSTRY, Taxonomies::ORG_TYPE ) as $taxonomy ) {
			add_action( $taxonomy . '_add_form_fields', array( $self, 'add_fields' ) );
			add_action( $taxonomy . '_edit_form_fields', array( $self, 'edit_fields' ), 10, 2 );
			add_action( 'created_' . $taxonomy, array( $self, 'saved' ) );
			add_action( 'edited_' . $taxonomy, array( $self, 'saved' ) );
			add_filter( 'manage_edit-' . $taxonomy . '_columns', static fn ( $columns ): array => $self->columns( (array) $columns, $taxonomy ) );
			add_filter( 'manage_' . $taxonomy . '_custom_column', array( $self, 'column' ), 10, 3 );
		}
		add_filter( 'pre_insert_term', array( $self, 'check_insert' ), 10, 3 );
		add_action( 'load-edit-tags.php', array( $self, 'check_update' ) );
		add_action( 'admin_notices', array( $self, 'notice' ) );
		add_action( 'edit_terms', array( $self, 'remember_slug' ), 10, 2 );
		add_action( 'edited_term', array( $self, 'record_slug' ), 10, 3 );
		add_filter( 'wp_update_term_data', array( $self, 'lock_unclassified' ), 10, 3 );
		add_filter( 'map_meta_cap', array( $self, 'no_delete_unclassified' ), 10, 4 );
		add_filter( Taxonomies::INDUSTRY . '_row_actions', array( $self, 'row_actions' ), 10, 2 );
	}

	/**
	 * Why a term's values are refused, or null when they are acceptable.
	 *
	 * @param string               $taxonomy Taxonomy.
	 * @param array<string, mixed> $data     name, slug, parent, kind, code.
	 * @param int                  $term_id  The term being edited, or 0 for a new one.
	 * @return string|null An error code (see message()).
	 */
	public function refusal( string $taxonomy, array $data, int $term_id = 0 ): ?string {
		$parent = (int) ( $data['parent'] ?? 0 );
		if ( Taxonomies::PLACE === $taxonomy ) {
			$kind = (string) ( $data['kind'] ?? '' );
			$code = strtoupper( trim( (string) ( $data['code'] ?? '' ) ) );
			if ( ! in_array( $kind, array( Taxonomies::KIND_COUNTRY, Taxonomies::KIND_CITY, Taxonomies::KIND_BODY ), true ) ) {
				return 'kind';
			}
			if ( Taxonomies::KIND_COUNTRY === $kind ) {
				if ( 1 !== preg_match( '/^[A-Z]{2}$/', $code ) ) {
					return 'code';
				}
				if ( $this->code_taken( $code, $term_id ) ) {
					return 'code_taken';
				}
				return $parent > 0 ? 'country_parent' : null;
			}
			if ( Taxonomies::KIND_BODY === $kind ) {
				if ( '' !== $code && 1 !== preg_match( '/^([A-Z]{2}|PAN)$/', $code ) ) {
					return 'code';
				}
				return $parent > 0 ? 'country_parent' : null;
			}
			$country = $parent > 0 ? get_term( $parent, Taxonomies::PLACE ) : null;
			return $country instanceof \WP_Term && Taxonomies::KIND_COUNTRY === ClassificationInput::place_kind( $parent ) ? null : 'city_parent';
		}//end if
		if ( Taxonomies::INDUSTRY === $taxonomy ) {
			$term = $term_id > 0 ? get_term( $term_id, Taxonomies::INDUSTRY ) : null;
			if ( $term instanceof \WP_Term && Classification::unclassified_slug() === $term->slug ) {
				$slug = (string) ( $data['slug'] ?? $term->slug );
				$name = (string) ( $data['name'] ?? $term->name );
				return ( $parent > 0 || ( '' !== $slug && $slug !== $term->slug ) || $name !== $term->name ) ? 'unclassified' : null;
			}
			if ( 0 === $parent ) {
				return null;
			}
			$sector = get_term( $parent, Taxonomies::INDUSTRY );
			if ( ! $sector instanceof \WP_Term || 0 !== (int) $sector->parent || Classification::unclassified_slug() === $sector->slug ) {
				return 'sector_parent';
			}
			if ( $term_id > 0 ) {
				$children = get_terms(
					array(
						'taxonomy'   => Taxonomies::INDUSTRY,
						'parent'     => $term_id,
						'hide_empty' => false,
						'fields'     => 'ids',
						'number'     => 1,
					)
				);
				if ( is_array( $children ) && array() !== $children ) {
					return 'sector_moved';
				}
			}
		}//end if
		return null;
	}

	/**
	 * A refusal code in words.
	 *
	 * @param string $code Code.
	 */
	public static function message( string $code ): string {
		$words = array(
			'kind'           => __( 'Choose what kind of place this is: Country, City or Pan-African body.', 'whoiswho' ),
			'code'           => __( 'A country needs its two-letter code, for example NG for Nigeria. A Pan-African body may use PAN.', 'whoiswho' ),
			'code_taken'     => __( 'Another country already has this code. Each country code is used once.', 'whoiswho' ),
			'country_parent' => __( 'A country or Pan-African body is at the top of the list: leave Parent empty.', 'whoiswho' ),
			'city_parent'    => __( 'A city needs its country as Parent.', 'whoiswho' ),
			'sector_parent'  => __( 'An industry\'s Parent must be a sector (a top-level industry term). Leave Parent empty to add a sector.', 'whoiswho' ),
			'sector_moved'   => __( 'This sector has industries under it, so it cannot be moved under another sector.', 'whoiswho' ),
			'unclassified'   => __( 'Unclassified holds the profiles that still need an industry. It cannot be renamed, moved or deleted.', 'whoiswho' ),
		);
		return $words[ $code ] ?? __( 'Check the values and try again.', 'whoiswho' );
	}

	/**
	 * Refuse a new term from the term screen that breaks a rule. Programmatic inserts (seeding, the import) are not affected.
	 *
	 * @param string|\WP_Error $term     Term name, or an earlier error.
	 * @param string           $taxonomy Taxonomy.
	 * @param mixed            $args     Insert arguments (an array).
	 * @return string|\WP_Error
	 */
	public function check_insert( $term, $taxonomy, $args = array() ) {
		if ( is_wp_error( $term ) || ! $this->from_screen() || ! in_array( $taxonomy, array( Taxonomies::PLACE, Taxonomies::INDUSTRY ), true ) ) {
			return $term;
		}
		$request = Request::from_globals();
		$code    = $this->refusal(
			(string) $taxonomy,
			array(
				'name'   => (string) $term,
				'parent' => (int) ( is_array( $args ) ? ( $args['parent'] ?? 0 ) : 0 ),
				'kind'   => $request->string( 'wiw_place_kind' ),
				'code'   => $request->string( 'wiw_iso_code' ),
			)
		);
		return null === $code ? $term : new \WP_Error( 'wiw_term_' . $code, self::message( $code ) );
	}

	/**
	 * Refuse an edit on the term edit screen that breaks a rule: back to the edit form with the reason.
	 */
	public function check_update(): void {
		$request  = Request::from_globals();
		$taxonomy = $request->string( 'taxonomy' );
		if ( 'editedtag' !== $request->string( 'action' ) || ! in_array( $taxonomy, array( Taxonomies::PLACE, Taxonomies::INDUSTRY ), true ) || ! $this->from_screen() ) {
			return;
		}
		$term_id = $request->int( 'tag_ID' );
		$code    = $this->refusal(
			$taxonomy,
			array(
				'name'   => $request->string( 'name' ),
				'slug'   => sanitize_title( $request->string( 'slug' ) ),
				'parent' => max( 0, $request->int( 'parent' ) ),
				'kind'   => $request->string( 'wiw_place_kind' ),
				'code'   => $request->string( 'wiw_iso_code' ),
			),
			$term_id
		);
		if ( null === $code ) {
			return;
		}
		wp_safe_redirect( add_query_arg( self::ERROR_ARG, $code, (string) get_edit_term_link( $term_id, $taxonomy ) ) );
		exit;
	}

	/**
	 * The refusal notice on the term screens.
	 */
	public function notice(): void {
		$code = Request::from_globals()->string( self::ERROR_ARG );
		if ( '' === $code ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html( self::message( $code ) ) . '</p></div>';
	}

	/**
	 * Fields on the "Add new" form.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public function add_fields( $taxonomy ): void {
		$taxonomy = (string) $taxonomy;
		echo '<input type="hidden" name="' . esc_attr( self::NONCE_FIELD ) . '" value="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" />';
		echo '<div class="form-field wiw-term-help"><p>' . esc_html( $this->help( $taxonomy ) ) . '</p></div>';
		if ( Taxonomies::PLACE === $taxonomy ) {
			echo '<div class="form-field"><label for="wiw_place_kind">' . esc_html__( 'Kind', 'whoiswho' ) . '</label>' . $this->kind_select( '' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
			echo '<div class="form-field"><label for="wiw_iso_code">' . esc_html__( 'Country code', 'whoiswho' ) . '</label><input type="text" id="wiw_iso_code" name="wiw_iso_code" maxlength="3" size="4" value="" /><p>' . esc_html__( 'Two letters, for example NG for Nigeria. Required for a country; leave empty for a city.', 'whoiswho' ) . '</p></div>';
			return;
		}
		echo '<div class="form-field"><label for="wiw_alias">' . esc_html__( 'Also known as', 'whoiswho' ) . '</label><input type="text" id="wiw_alias" name="wiw_alias" value="" /><p>' . esc_html__( 'Other names for this entry, separated by commas. The import uses them to match values.', 'whoiswho' ) . '</p></div>';
	}

	/**
	 * Fields on the edit form.
	 *
	 * @param mixed  $term     Term (a WP_Term).
	 * @param string $taxonomy Taxonomy.
	 */
	public function edit_fields( $term, $taxonomy ): void {
		if ( ! $term instanceof \WP_Term ) {
			return;
		}
		$taxonomy = (string) $taxonomy;
		echo '<tr class="form-field wiw-term-help"><td colspan="2"><input type="hidden" name="' . esc_attr( self::NONCE_FIELD ) . '" value="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" /><p class="description">' . esc_html( $this->help( $taxonomy ) ) . '</p></td></tr>';
		if ( Taxonomies::PLACE === $taxonomy ) {
			$kind = (string) get_term_meta( $term->term_id, Taxonomies::META_KIND, true );
			echo '<tr class="form-field"><th scope="row"><label for="wiw_place_kind">' . esc_html__( 'Kind', 'whoiswho' ) . '</label></th><td>' . $this->kind_select( $kind ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built escaped.
			echo '<tr class="form-field"><th scope="row"><label for="wiw_iso_code">' . esc_html__( 'Country code', 'whoiswho' ) . '</label></th><td><input type="text" id="wiw_iso_code" name="wiw_iso_code" maxlength="3" size="4" value="' . esc_attr( (string) get_term_meta( $term->term_id, Taxonomies::META_ISO, true ) ) . '" /><p class="description">' . esc_html__( 'Two letters, for example NG for Nigeria. Required for a country; leave empty for a city.', 'whoiswho' ) . '</p></td></tr>';
			return;
		}
		if ( Taxonomies::INDUSTRY === $taxonomy && Classification::unclassified_slug() === $term->slug ) {
			echo '<tr class="form-field"><td colspan="2"><p class="description">' . esc_html( self::message( 'unclassified' ) ) . '</p></td></tr>';
			return;
		}
		echo '<tr class="form-field"><th scope="row"><label for="wiw_alias">' . esc_html__( 'Also known as', 'whoiswho' ) . '</label></th><td><input type="text" id="wiw_alias" name="wiw_alias" value="' . esc_attr( implode( ', ', self::aliases( $term->term_id ) ) ) . '" /><p class="description">' . esc_html__( 'Other names for this entry, separated by commas. The import uses them to match values.', 'whoiswho' ) . '</p></td></tr>';
	}

	/**
	 * Save the extra fields after a term was created or edited on its screen (nonce and edit_term checked), and audit the save.
	 *
	 * @param int $term_id Term.
	 */
	public function saved( $term_id ): void {
		$term_id = (int) $term_id;
		$term    = get_term( $term_id );
		if ( ! $term instanceof \WP_Term || ! $this->from_screen() || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$request = Request::from_globals();
		$before  = $this->values( $term );
		if ( Taxonomies::PLACE === $term->taxonomy ) {
			$kind = $request->string( 'wiw_place_kind' );
			if ( in_array( $kind, array( Taxonomies::KIND_COUNTRY, Taxonomies::KIND_CITY, Taxonomies::KIND_BODY ), true ) ) {
				update_term_meta( $term_id, Taxonomies::META_KIND, $kind );
			}
			$code = strtoupper( preg_replace( '/[^A-Za-z]/', '', $request->string( 'wiw_iso_code' ) ) ?? '' );
			if ( Taxonomies::KIND_CITY === $kind || '' === $code ) {
				delete_term_meta( $term_id, Taxonomies::META_ISO );
			} else {
				update_term_meta( $term_id, Taxonomies::META_ISO, substr( $code, 0, 3 ) );
			}
		} elseif ( $request->has( 'wiw_alias' ) ) {
			$aliases = self::parse_aliases( $request->string( 'wiw_alias' ) );
			if ( array() === $aliases ) {
				delete_term_meta( $term_id, self::META_ALIAS );
			} else {
				update_term_meta( $term_id, self::META_ALIAS, $aliases );
			}
		}
		$after = $this->values( $term );
		if ( $before !== $after ) {
			AuditLogger::for_site()->log(
				'term.save',
				array(
					'type' => 'term',
					'id'   => $term_id,
				),
				$before,
				$after,
				'Term screen'
			);
		}
	}

	/**
	 * Before a term update: remember its slug.
	 *
	 * @param int    $term_id  Term.
	 * @param string $taxonomy Taxonomy.
	 */
	public function remember_slug( $term_id, $taxonomy ): void {
		if ( ! in_array( $taxonomy, array( Taxonomies::PLACE, Taxonomies::INDUSTRY, Taxonomies::ORG_TYPE ), true ) ) {
			return;
		}
		$term = get_term( (int) $term_id, (string) $taxonomy );
		if ( $term instanceof \WP_Term ) {
			self::$old_slugs[ (int) $term_id ] = $term->slug;
		}
	}

	/**
	 * After a term update: when the slug changed, keep the old one (for the redirect) and audit the change.
	 *
	 * @param int    $term_id  Term.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 */
	public function record_slug( $term_id, $tt_id, $taxonomy ): void {
		$term_id = (int) $term_id;
		unset( $tt_id );
		if ( ! isset( self::$old_slugs[ $term_id ] ) ) {
			return;
		}
		$old = self::$old_slugs[ $term_id ];
		unset( self::$old_slugs[ $term_id ] );
		$term = get_term( $term_id, (string) $taxonomy );
		if ( ! $term instanceof \WP_Term || $term->slug === $old ) {
			return;
		}
		$kept = array_map( 'strval', (array) get_term_meta( $term_id, self::META_OLD_SLUGS, false ) );
		if ( ! in_array( $old, $kept, true ) ) {
			add_term_meta( $term_id, self::META_OLD_SLUGS, $old );
		}
		// A slug used again by its own term is no longer an old one.
		delete_term_meta( $term_id, self::META_OLD_SLUGS, $term->slug );
		AuditLogger::for_site()->log(
			'term.slug_change',
			array(
				'type' => 'term',
				'id'   => $term_id,
			),
			array( 'slug' => $old ),
			array( 'slug' => $term->slug ),
			'Old addresses redirect'
		);
	}

	/**
	 * Keep the unclassified industry's name, slug and place whatever path updates it (the quick edit included).
	 *
	 * @param array<string, mixed> $data     Term data to write (name, slug, term_group).
	 * @param int                  $term_id  Term.
	 * @param string               $taxonomy Taxonomy.
	 * @return array<string, mixed>
	 */
	public function lock_unclassified( $data, $term_id, $taxonomy ): array {
		$data = (array) $data;
		if ( Taxonomies::INDUSTRY !== $taxonomy ) {
			return $data;
		}
		$term = get_term( (int) $term_id, Taxonomies::INDUSTRY );
		if ( $term instanceof \WP_Term && Classification::unclassified_slug() === $term->slug ) {
			$data['name'] = $term->name;
			$data['slug'] = $term->slug;
		}
		return $data;
	}

	/**
	 * Nobody may delete the unclassified industry.
	 *
	 * @param string[]     $caps    Primitive capabilities.
	 * @param string       $cap     Meta capability.
	 * @param int          $user_id User.
	 * @param array<mixed> $args    Arguments (the term id first).
	 * @return string[]
	 */
	public function no_delete_unclassified( $caps, $cap, $user_id, $args ): array {
		unset( $user_id );
		$caps = (array) $caps;
		if ( 'delete_term' !== $cap || ! isset( $args[0] ) ) {
			return $caps;
		}
		$term = get_term( (int) $args[0] );
		if ( $term instanceof \WP_Term && Taxonomies::INDUSTRY === $term->taxonomy && Classification::unclassified_slug() === $term->slug ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * No quick edit on the unclassified industry (its name and slug are fixed).
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param mixed                 $term    Term (a WP_Term).
	 * @return array<string, string>
	 */
	public function row_actions( $actions, $term ): array {
		$actions = (array) $actions;
		if ( $term instanceof \WP_Term && Classification::unclassified_slug() === $term->slug ) {
			unset( $actions['inline hide-if-no-js'], $actions['delete'] );
		}
		return $actions;
	}

	/**
	 * Admin list columns.
	 *
	 * @param array<string, string> $columns  Columns.
	 * @param string                $taxonomy Taxonomy of the list.
	 * @return array<string, string>
	 */
	public function columns( array $columns, string $taxonomy ): array {
		$extra = array();
		if ( Taxonomies::PLACE === $taxonomy ) {
			$extra = array(
				'wiw_kind'    => __( 'Kind', 'whoiswho' ),
				'wiw_code'    => __( 'Country code', 'whoiswho' ),
				'wiw_country' => __( 'Country (parent)', 'whoiswho' ),
			);
		} elseif ( Taxonomies::INDUSTRY === $taxonomy ) {
			$extra = array(
				'wiw_sector' => __( 'Sector (parent)', 'whoiswho' ),
				'wiw_alias'  => __( 'Also known as', 'whoiswho' ),
			);
		} elseif ( Taxonomies::ORG_TYPE === $taxonomy ) {
			$extra = array( 'wiw_alias' => __( 'Also known as', 'whoiswho' ) );
		}
		unset( $columns['description'] );
		$posts = $columns['posts'] ?? null;
		unset( $columns['posts'] );
		$columns += $extra;
		if ( null !== $posts ) {
			$columns['posts'] = __( 'Profiles', 'whoiswho' );
		}
		return $columns;
	}

	/**
	 * One custom column cell.
	 *
	 * @param string $content Content so far.
	 * @param string $column  Column.
	 * @param int    $term_id Term.
	 */
	public function column( $content, $column, $term_id ): string {
		$term_id = (int) $term_id;
		switch ( $column ) {
			case 'wiw_kind':
				$labels = self::kinds();
				return esc_html( $labels[ ClassificationInput::place_kind( $term_id ) ] ?? '' );
			case 'wiw_code':
				return esc_html( (string) get_term_meta( $term_id, Taxonomies::META_ISO, true ) );
			case 'wiw_country':
			case 'wiw_sector':
				$term   = get_term( $term_id );
				$parent = $term instanceof \WP_Term && $term->parent > 0 ? get_term( (int) $term->parent ) : null;
				if ( $parent instanceof \WP_Term ) {
					return esc_html( $parent->name );
				}
				return 'wiw_sector' === $column && $term instanceof \WP_Term && Classification::unclassified_slug() !== $term->slug ? esc_html__( '(a sector)', 'whoiswho' ) : '';
			case 'wiw_alias':
				return esc_html( implode( ', ', self::aliases( $term_id ) ) );
		}
		return (string) $content;
	}

	/**
	 * A term's aliases.
	 *
	 * @param int $term_id Term.
	 * @return string[]
	 */
	public static function aliases( int $term_id ): array {
		return Classification::aliases_of( $term_id );
	}

	/**
	 * Aliases from a comma-separated text: trimmed, unique, at most MAX_ALIASES of at most ALIAS_MAX characters.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	public static function parse_aliases( string $text ): array {
		$out = array();
		foreach ( explode( ',', $text ) as $alias ) {
			$alias = trim( $alias );
			if ( '' === $alias ) {
				continue;
			}
			$alias = function_exists( 'mb_substr' ) ? mb_substr( $alias, 0, self::ALIAS_MAX, 'UTF-8' ) : substr( $alias, 0, self::ALIAS_MAX );
			if ( ! in_array( $alias, $out, true ) ) {
				$out[] = $alias;
			}
		}
		return array_slice( $out, 0, self::MAX_ALIASES );
	}

	/**
	 * The place kinds in words.
	 *
	 * @return array<string, string>
	 */
	public static function kinds(): array {
		return array(
			Taxonomies::KIND_COUNTRY => __( 'Country', 'whoiswho' ),
			Taxonomies::KIND_CITY    => __( 'City', 'whoiswho' ),
			Taxonomies::KIND_BODY    => __( 'Pan-African body', 'whoiswho' ),
		);
	}

	/**
	 * The help line of a list's screen.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	private function help( string $taxonomy ): string {
		if ( Taxonomies::PLACE === $taxonomy ) {
			return __( 'A country needs its two-letter code, for example NG for Nigeria, and no Parent. A city needs its country as Parent. Pan-African bodies use the place Pan-African.', 'whoiswho' );
		}
		if ( Taxonomies::INDUSTRY === $taxonomy ) {
			return __( 'Top-level entries are sectors. To add an industry, choose its sector as Parent; leave Parent empty to add a sector. Unclassified cannot be changed.', 'whoiswho' );
		}
		return __( 'The kind of body, for example Company, University or NGO / non-profit. Each organisation or institution has one.', 'whoiswho' );
	}

	/**
	 * The kind select.
	 *
	 * @param string $current Current kind.
	 */
	private function kind_select( string $current ): string {
		$out = '<select id="wiw_place_kind" name="wiw_place_kind" required="required"><option value="">' . esc_html__( 'Choose', 'whoiswho' ) . '</option>';
		foreach ( self::kinds() as $kind => $label ) {
			$out .= '<option value="' . esc_attr( $kind ) . '"' . selected( $current, $kind, false ) . '>' . esc_html( $label ) . '</option>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a fixed attribute.
		}
		return $out . '</select>';
	}

	/**
	 * The values a save may change, for the audit row.
	 *
	 * @param \WP_Term $term Term.
	 * @return array<string, mixed>
	 */
	private function values( \WP_Term $term ): array {
		if ( Taxonomies::PLACE === $term->taxonomy ) {
			return array(
				'kind' => (string) get_term_meta( $term->term_id, Taxonomies::META_KIND, true ),
				'code' => (string) get_term_meta( $term->term_id, Taxonomies::META_ISO, true ),
			);
		}
		return array( 'aliases' => self::aliases( $term->term_id ) );
	}

	/**
	 * Whether this request is a term screen submission carrying this class's valid nonce.
	 */
	private function from_screen(): bool {
		$nonce = Request::from_globals()->string( self::NONCE_FIELD );
		return '' !== $nonce && false !== wp_verify_nonce( $nonce, self::NONCE );
	}

	/**
	 * Whether another country already uses a code.
	 *
	 * @param string $code    Code.
	 * @param int    $term_id The term being edited (not counted).
	 */
	private function code_taken( string $code, int $term_id ): bool {
		$ids = get_terms(
			array(
				'taxonomy'   => Taxonomies::PLACE,
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_key'   => Taxonomies::META_ISO, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small controlled list.
				'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- as above.
			)
		);
		foreach ( is_array( $ids ) ? $ids : array() as $id ) {
			if ( (int) $id !== $term_id && Taxonomies::KIND_COUNTRY === ClassificationInput::place_kind( (int) $id ) ) {
				return true;
			}
		}
		return false;
	}
}
