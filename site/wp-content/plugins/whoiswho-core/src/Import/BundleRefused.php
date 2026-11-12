<?php
/**
 * A whole bundle was refused. Carries the contract code (CONTRACT_VERSION or MANIFEST_MISMATCH) and a plain reason that never
 * names a person.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Import;

/**
 * Bundle refusal.
 */
final class BundleRefused extends \RuntimeException {

	/**
	 * Contract refusal code.
	 *
	 * @var string
	 */
	private string $refusal_code;

	/**
	 * Constructor.
	 *
	 * @param string $code   Contract code.
	 * @param string $reason Plain reason.
	 */
	public function __construct( string $code, string $reason ) {
		parent::__construct( $reason );
		$this->refusal_code = $code;
	}

	/**
	 * The contract code.
	 */
	public function refusal_code(): string {
		return $this->refusal_code;
	}
}
