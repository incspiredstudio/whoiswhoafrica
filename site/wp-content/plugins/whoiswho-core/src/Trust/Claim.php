<?php
/**
 * One profile claim as an immutable value (REQ-251, REQ-252, REQ-256). Built by ClaimRepository from a stored row.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Trust;

/**
 * Claim value object.
 */
final class Claim {

	public const NEW      = 'new';
	public const APPROVED = 'approved';
	public const REJECTED = 'rejected';

	public const RELATIONSHIPS = array( 'self', 'representative' );

	/**
	 * Row id.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Reference, for example CLM-0001.
	 *
	 * @var string
	 */
	public string $reference;

	/**
	 * Profile post id.
	 *
	 * @var int
	 */
	public int $profile_id;

	/**
	 * Relationship: self or representative.
	 *
	 * @var string
	 */
	public string $relationship;

	/**
	 * Claimant name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Claimant role (optional).
	 *
	 * @var string
	 */
	public string $role;

	/**
	 * Work email.
	 *
	 * @var string
	 */
	public string $work_email;

	/**
	 * Phone (optional).
	 *
	 * @var string
	 */
	public string $phone;

	/**
	 * How staff can check the claim.
	 *
	 * @var string
	 */
	public string $evidence;

	/**
	 * When the consent was given (UTC), or null.
	 *
	 * @var string|null
	 */
	public ?string $consent_at;

	/**
	 * Status: new, approved or rejected.
	 *
	 * @var string
	 */
	public string $status;

	/**
	 * Received (UTC).
	 *
	 * @var string
	 */
	public string $received_at;

	/**
	 * Decide-by (UTC): received plus five days.
	 *
	 * @var string
	 */
	public string $decide_by_at;

	/**
	 * Last reminder sent (UTC), or null.
	 *
	 * @var string|null
	 */
	public ?string $last_reminder_at;

	/**
	 * Deciding user.
	 *
	 * @var int|null
	 */
	public ?int $decided_by;

	/**
	 * Decided (UTC).
	 *
	 * @var string|null
	 */
	public ?string $decided_at;

	/**
	 * Decision reason.
	 *
	 * @var string
	 */
	public string $reason;

	/**
	 * Build from a database row.
	 *
	 * @param array<string, mixed> $row Row.
	 */
	public static function from_row( array $row ): self {
		$claim                   = new self();
		$claim->id               = (int) $row['id'];
		$claim->reference        = (string) $row['reference'];
		$claim->profile_id       = (int) $row['profile_id'];
		$claim->relationship     = (string) $row['relationship'];
		$claim->name             = (string) $row['name'];
		$claim->role             = (string) $row['role'];
		$claim->work_email       = (string) $row['work_email'];
		$claim->phone            = (string) $row['phone'];
		$claim->evidence         = (string) $row['evidence'];
		$claim->consent_at       = null === $row['consent_at'] ? null : (string) $row['consent_at'];
		$claim->status           = (string) $row['status'];
		$claim->received_at      = (string) $row['received_at'];
		$claim->decide_by_at     = (string) $row['decide_by_at'];
		$claim->last_reminder_at = null === $row['last_reminder_at'] ? null : (string) $row['last_reminder_at'];
		$claim->decided_by       = null === $row['decided_by'] ? null : (int) $row['decided_by'];
		$claim->decided_at       = null === $row['decided_at'] ? null : (string) $row['decided_at'];
		$claim->reason           = (string) $row['reason'];
		return $claim;
	}

	/**
	 * Whether the claim is still waiting for a decision.
	 */
	public function is_open(): bool {
		return self::NEW === $this->status;
	}
}
