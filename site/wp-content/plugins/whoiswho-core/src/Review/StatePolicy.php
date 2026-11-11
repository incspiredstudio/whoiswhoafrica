<?php
/**
 * The one table that maps a publication state to what every surface may do with a profile (REQ-208, REQ-209, REQ-217,
 * REQ-218): the HTTP status, the robots rule, and whether it may appear in lists, search, the sitemap, feeds, structured data
 * and share cards. PublicProfile, list queries, the guard and the sitemap all ask this class, so no surface keeps its own rule.
 * Pure logic with no WordPress calls, so tests and callers can use it anywhere. Callers pass the state read from the database
 * on that request, never a cached copy.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Review;

use WhoIsWho\Core\Directory\State\PubState;

/**
 * Immutable answer for one state.
 */
final class StatePolicy {

	/**
	 * The label shown on a pending profile (REQ-216). The wording is configuration in the settings service; this is the default.
	 */
	public const PENDING_LABEL = 'Compiled automatically from public sources. Human review pending.';

	/**
	 * HTTP status: 200, 404 or 410.
	 *
	 * @var int
	 */
	public int $status;

	/**
	 * Send X-Robots-Tag noindex and a robots meta tag.
	 *
	 * @var bool
	 */
	public bool $noindex;

	/**
	 * May appear in lists, the home page and industry pages.
	 *
	 * @var bool
	 */
	public bool $in_lists;

	/**
	 * May appear in on-site search.
	 *
	 * @var bool
	 */
	public bool $in_search;

	/**
	 * May appear in the sitemap and feeds.
	 *
	 * @var bool
	 */
	public bool $in_sitemap;

	/**
	 * May carry structured data.
	 *
	 * @var bool
	 */
	public bool $has_schema;

	/**
	 * May produce a share card.
	 *
	 * @var bool
	 */
	public bool $share_card;

	/**
	 * Show the pending-review label (page, cards, share preview).
	 *
	 * @var bool
	 */
	public bool $label;

	/**
	 * Constructor.
	 *
	 * @param int  $status     HTTP status.
	 * @param bool $noindex    Noindex.
	 * @param bool $in_lists   Lists.
	 * @param bool $in_search  Search.
	 * @param bool $in_sitemap Sitemap and feeds.
	 * @param bool $has_schema Structured data.
	 * @param bool $share_card Share card.
	 * @param bool $label      Pending label.
	 */
	private function __construct( int $status, bool $noindex, bool $in_lists, bool $in_search, bool $in_sitemap, bool $has_schema, bool $share_card, bool $label ) {
		$this->status     = $status;
		$this->noindex    = $noindex;
		$this->in_lists   = $in_lists;
		$this->in_search  = $in_search;
		$this->in_sitemap = $in_sitemap;
		$this->has_schema = $has_schema;
		$this->share_card = $share_card;
		$this->label      = $label;
	}

	/**
	 * The policy for a state. An unknown or missing state gets the most closed answer (404, nothing allowed).
	 *
	 * @param string|null $state A PubState value read from the database.
	 */
	public static function for_state( ?string $state ): self {
		switch ( $state ) {
			case PubState::PUBLISHED_REVIEWED:
				return new self( 200, false, true, true, true, true, true, false );
			case PubState::PUBLISHED_PENDING:
				return new self( 200, true, false, true, false, false, true, true );
			case PubState::UNPUBLISHED:
			case PubState::REMOVED_SUPPRESSED:
				return new self( 410, true, false, false, false, false, false, false );
			default:
				// Rejected, imported not public, no state, anything unknown.
				return new self( 404, true, false, false, false, false, false, false );
		}
	}

	/**
	 * Whether a visitor sees the page at all (HTTP 200).
	 */
	public function is_visible(): bool {
		return 200 === $this->status;
	}
}
