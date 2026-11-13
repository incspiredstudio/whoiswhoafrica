<?php
/**
 * A personal-data handler (REQ-059): knows which tables it covers, exports what it holds for an email address and erases it.
 * Modules add their own through the wiw_privacy_handlers filter; the registry plugs them into the WordPress privacy tools.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Compliance\Privacy;

/**
 * Privacy handler.
 */
interface PrivacyHandler {

	/**
	 * Id, unique and stable (used as the WordPress exporter key).
	 */
	public function id(): string;

	/**
	 * Friendly name for the privacy tools.
	 */
	public function label(): string;

	/**
	 * Tables covered, without the site prefix.
	 *
	 * @return string[]
	 */
	public function tables(): array;

	/**
	 * Export what is held for an address.
	 *
	 * @param string $email Address.
	 * @return array<int, array{item_id: string, data: array<int, array{name: string, value: string}>}>
	 */
	public function export( string $email ): array;

	/**
	 * Erase (delete or anonymise) what is held for an address.
	 *
	 * @param string $email Address.
	 * @return int Rows deleted or anonymised.
	 */
	public function erase( string $email ): int;
}
