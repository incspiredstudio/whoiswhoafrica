<?php
/**
 * The public read model of a nominee (REQ-033, REQ-198, REQ-064, REQ-226): the only way a template reads nominations. A nominee
 * exists publicly only while its nomination is Live and its linked profile is reviewed and public; a Not live nomination, one with
 * no linked profile, and one whose profile is pending review, unpublished or gone return nothing. The name, type, facts and
 * link come from the PublicProfile read model, so nominee pages are built from the reviewed profile and its sourced facts.
 * There is no nominator, evidence, Contact email or email-sent property: none exists to leak. The public address uses an opaque
 * reference, not the nomination id.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Awards;

use WhoIsWho\Core\Directory\PublicProfile;
use WhoIsWho\Core\Directory\State\PubState;

/**
 * Public nominee.
 */
final class PublicNominee {

	/**
	 * Opaque public reference.
	 *
	 * @var string
	 */
	public string $ref;

	/**
	 * Public address of the nominee page.
	 *
	 * @var string
	 */
	public string $url;

	/**
	 * Display name, from the reviewed profile.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Category id.
	 *
	 * @var int
	 */
	public int $category_id;

	/**
	 * Category title.
	 *
	 * @var string
	 */
	public string $category;

	/**
	 * Category group key.
	 *
	 * @var string
	 */
	public string $group_key;

	/**
	 * Category group label.
	 *
	 * @var string
	 */
	public string $group;

	/**
	 * Edition year, or the year the nomination was created when it has no edition.
	 *
	 * @var int
	 */
	public int $year;

	/**
	 * The public citation staff wrote for a winner (never the nominator evidence).
	 *
	 * @var string
	 */
	public string $citation = '';

	/**
	 * Whether this nominee is a winner (stage Winner; the nomination is Live).
	 *
	 * @var bool
	 */
	public bool $is_winner;

	/**
	 * Whether the page is a staff preview of a Not live nomination (never true for a visitor).
	 *
	 * @var bool
	 */
	public bool $preview = false;

	/**
	 * The reviewed profile the page is built from.
	 *
	 * @var PublicProfile
	 */
	public PublicProfile $profile;

	/**
	 * Use the find or list methods.
	 *
	 * @param array<string, mixed> $row     Joined nomination and category row.
	 * @param PublicProfile        $profile Profile.
	 */
	private function __construct( array $row, PublicProfile $profile ) {
		$id                = (int) $row['id'];
		$this->ref         = self::ref_for( $id );
		$this->url         = home_url( '/awards/nominee/' . $this->ref . '/' );
		$this->profile     = $profile;
		$this->name        = $profile->name;
		$this->category_id = (int) $row['category_id'];
		$this->category    = (string) $row['category_title'];
		$this->group_key   = (string) $row['group_key'];
		$this->group       = AwardsTables::GROUPS[ $this->group_key ] ?? $this->group_key;
		$this->year        = (int) ( $row['edition_year'] ?? 0 ) > 0 ? (int) $row['edition_year'] : (int) substr( (string) $row['created_at'], 0, 4 );
		$this->is_winner   = 'winner' === $row['stage'];
		$this->citation    = isset( $row['citation'] ) ? (string) $row['citation'] : '';
	}

	/**
	 * The opaque reference of a nomination: its number in base 36 and a short keyed check, so a reference cannot be guessed from
	 * the number and an altered one never resolves.
	 *
	 * @param int $id Nomination id.
	 */
	public static function ref_for( int $id ): string {
		return base_convert( (string) $id, 10, 36 ) . substr( hash_hmac( 'sha256', 'nominee|' . $id, wp_salt( 'auth' ) ), 0, 6 );
	}

	/**
	 * The nomination id a reference stands for, or null when it is not a valid reference.
	 *
	 * @param string $ref Reference from an address.
	 */
	public static function id_for( string $ref ): ?int {
		$ref = strtolower( $ref );
		if ( ! preg_match( '/^([0-9a-z]{1,10})([0-9a-f]{6})$/', $ref, $m ) ) {
			return null;
		}
		$id = (int) base_convert( $m[1], 36, 10 );
		return $id > 0 && hash_equals( self::ref_for( $id ), $ref ) ? $id : null;
	}

	/**
	 * One nominee by reference. A visitor gets it only while Live and reviewed; a staff member who may manage awards can also
	 * preview a Not live one.
	 *
	 * @param string $ref           Reference.
	 * @param bool   $staff_preview Allow a staff preview.
	 */
	public static function find( string $ref, bool $staff_preview = false ): ?self {
		$id = self::id_for( $ref );
		if ( null === $id ) {
			return null;
		}
		$staff = $staff_preview && current_user_can( NominationService::CAP ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- listed in config/capabilities.php.
		$rows  = self::rows( $staff ? 'n.id = %d' : "n.id = %d AND n.status = 'live'", array( $id ) );
		if ( array() === $rows ) {
			return null;
		}
		$nominee = self::build( $rows[0], $staff );
		if ( null === $nominee ) {
			return null;
		}
		$nominee->preview = 'live' !== $rows[0]['status'];
		return $nominee;
	}

	/**
	 * Live nominees, optionally of one category, in category order then name order.
	 *
	 * @param int $category_id Category, or 0 for all.
	 * @param int $limit       Maximum.
	 * @return PublicNominee[]
	 */
	public static function live( int $category_id = 0, int $limit = 500 ): array {
		return self::collect( "n.status = 'live'" . ( $category_id > 0 ? ' AND n.category_id = %d' : '' ), $category_id > 0 ? array( $category_id ) : array(), $limit );
	}

	/**
	 * Live winners (stage Winner and Live), newest edition first.
	 *
	 * @param int $limit Maximum.
	 * @return PublicNominee[]
	 */
	public static function winners( int $limit = 500 ): array {
		return self::collect( "n.status = 'live' AND n.stage = 'winner'", array(), $limit );
	}

	/**
	 * Build the models for a condition, dropping any whose profile is not reviewed and public.
	 *
	 * @param string            $where  Fixed condition with placeholders.
	 * @param array<int, mixed> $args   Values.
	 * @param int               $limit  Maximum.
	 * @return PublicNominee[]
	 */
	private static function collect( string $where, array $args, int $limit ): array {
		$out = array();
		foreach ( self::rows( $where, $args ) as $row ) {
			$nominee = self::build( $row, false );
			if ( null !== $nominee ) {
				$out[] = $nominee;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Build one model, or null when the profile is not reviewed and public (a staff preview may show a pending profile).
	 *
	 * @param array<string, mixed> $row           Row.
	 * @param bool                 $staff_preview Staff preview.
	 */
	private static function build( array $row, bool $staff_preview ): ?self {
		if ( empty( $row['profile_id'] ) ) {
			return null;
		}
		$profile = PublicProfile::find_by_id( (int) $row['profile_id'], $staff_preview );
		if ( null === $profile ) {
			return null;
		}
		if ( ! $staff_preview && PubState::PUBLISHED_REVIEWED !== $profile->state ) {
			return null;
		}
		return new self( $row, $profile );
	}

	/**
	 * Joined rows.
	 *
	 * @param string            $where Fixed condition with placeholders.
	 * @param array<int, mixed> $args  Values.
	 * @return array<int, array<string, mixed>>
	 */
	private static function rows( string $where, array $args ): array {
		global $wpdb;
		$n   = AwardsTables::table( AwardsTables::NOMINATIONS );
		$c   = AwardsTables::table( AwardsTables::CATEGORIES );
		$e   = AwardsTables::table( AwardsTables::EDITIONS );
		$sql = 'SELECT n.id, n.category_id, n.profile_id, n.stage, n.status, n.created_at, n.citation, c.title AS category_title, c.group_key, c.sort_order, e.year AS edition_year'
			. " FROM {$n} n INNER JOIN {$c} c ON c.id = n.category_id LEFT JOIN {$e} e ON e.id = n.edition_id WHERE {$where}"
			. ' ORDER BY COALESCE(e.year, YEAR(n.created_at)) DESC, c.group_key ASC, c.sort_order ASC, n.id ASC';
		$sql = array() === $args ? $sql : $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- fixed names; values prepared.
		return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB, WordPress.DB.PreparedSQL.NotPrepared -- prepared above.
	}
}
