# Security basics

The site starts from a small attack surface: no public accounts, no public API and no payment data. This page lists the main threats, the controls that answer them, and the rules for sign-in, secrets, validation and logging.

## Threats and controls

| Threat | Control |
|---|---|
| A non-public profile leaks through a feed, API, sitemap, search, preview or attachment page | The publication state machine, the guard plugin that closes core surfaces, no SEO plugin, and a visibility test matrix |
| Removed content stays visible | A "gone" response from database state, cache purge, a two-minute worst case at the edge, restore by batch |
| Wrong or defamatory facts about a real person | Sources on every field, review, removal targets, bulk unpublish, permanent suppression |
| A tampered import | Contract checks, a recomputed hash, a suppression check, allowed fields only, undo by batch, audit |
| Staff account takeover | Enforced two-factor with recovery codes, strong passwords, lockout, session revocation, idle timeout |
| Insider misuse and data export | Least-privilege capabilities, Administrator-only logged exports, audit of every grant |
| Abuse of bulk unpublish | A limited capability, a reason, a count confirmation, audit, restore by batch |
| Money influencing recognition | Automated tests on module boundaries |
| Spam, rival-driven removal requests, enumeration | Bot challenge, throttles, duplicate detection, email verification, staff validity check, uniform responses |
| Signed-link theft or scanning | Single use, expiry, hashed storage, rate limit, revocation |
| Cross-site scripting and injection | Escaping on output, allowlists for story bodies, prepared statements, nonces on admin actions, a content security policy |
| Malicious uploads | Allowed image types re-encoded on the server. Evidence files limited to JPG, PNG and PDF, stored outside the web root and never served inline. |
| Plugin and theme supply chain | Few plugins, pinned by lockfile, file modification disabled, weekly vulnerability scan |
| Cache deception or poisoning | Only anonymous page views on named public paths are cached. Staff cookies bypass the cache. |

## Sign-in

1. Only staff sign in. Password plus two-factor, enforced before first use, with recovery codes and lockout after repeated failures.
2. Registration is off, there is no default role, no public login link, no application passwords and no XML-RPC.
3. The admin session times out when idle.
4. Authorisation is deny by default, and a capability is checked on every admin action.

## Hardening

- File editing and file modification are disabled in production.
- Automatic plugin updates are off. Updates ship through the release.
- `wp-config.php` sits outside the web root and loads the secrets file.
- Directory listing is off, and PHP cannot run in the uploads folder.
- There is no public REST route. Core REST is limited to signed-in staff.
- Headers: HSTS, a nonce-based content security policy, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` and frame-ancestors none.

## Secrets

1. Real values live only in a `.env`-style file outside the web root, readable only by the owner.
2. `wp-config.php` is a template with no secrets and loads that file.
3. The example file lists names only.
4. A secret scanner runs on every change.
5. Secrets include SMTP credentials, the signing keys for links and form tokens, the backup passphrase and the WordPress salts. Write the rotation steps in the runbook.

## Validation and rate limits

- Validation is server-side only, with length limits and Unicode-safe names. One validator per form, and no raw request data outside the validators.
- Each public form is rate-limited per address and per email or link token, and protected by a bot challenge.
- Public responses are uniform so they do not reveal what exists.

## Personal data and payments

- No card or bank data is handled. A deal stores a method and reference as text, with a guard against digit patterns.
- Personal-data fields are catalogued and kept out of logs and error reports.
- Logs are structured, hold IDs rather than names or emails, and are kept for 30 days. The audit log is separate and kept.

## Dependencies

Dependency updates are checked weekly. The dependency audit fails a release on any high or critical finding. Lockfiles are committed.
