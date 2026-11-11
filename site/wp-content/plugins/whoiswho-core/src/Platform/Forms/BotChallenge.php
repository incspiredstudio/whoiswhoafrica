<?php
/**
 * The bot check a public form runs before validating. Form code depends only on this interface, so the honeypot-only
 * default can be swapped for a Turnstile adapter (when Cloudflare is in front of the site) without touching any form.
 *
 * A Turnstile adapter would implement verify() by posting the "cf-turnstile-response" field to Cloudflare's siteverify
 * endpoint with the secret from the secrets file. It is not enabled without Cloudflare.
 *
 * @package WhoIsWho
 */

declare(strict_types=1);

namespace WhoIsWho\Core\Platform\Forms;

/**
 * Bot challenge.
 */
interface BotChallenge {

	public const OK = 'ok';

	/**
	 * Check a submission.
	 *
	 * @param FormInput $input Submitted values.
	 * @param string    $form  Form type.
	 * @return string "ok", or a reason code (honeypot, too_fast, expired, invalid, challenge_failed).
	 */
	public function verify( FormInput $input, string $form ): string;
}
