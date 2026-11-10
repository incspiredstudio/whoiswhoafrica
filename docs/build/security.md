# Security

The main threats, the controls that answer them, and the rules for secrets, validation, rate limits and logging. Security here starts from a small attack surface: no public accounts, no public API, no payment data.

Requirements: REQ-046, 047, 049, 051, 066, 069, 081, 122, 127, 229, 233. Architecture: section 6. Records: ADR-004, ADR-007, ADR-013.

## Threats and controls

| Threat | Control |
|---|---|
| A profile that should not be public leaks through a feed, REST, sitemap, oEmbed, search, preview or attachment page | The state machine, the guard plugin that closes core surfaces, no SEO plugin, and the visibility test matrix |
| Removed content stays visible | 410 from database state, cache purge, a two-minute worst case at the edge, restore by batch |
| Wrong or defamatory facts about a real person | Per-field provenance, source beside each claim in review, removal service level, bulk unpublish, permanent suppression |
| Poisoned or tampered import | Contract checks, recomputed hash, live suppression check, allowlisted fields, undo a batch, audit |
| Staff account takeover | Enforced two-factor with recovery codes, strong passwords, lockout, session revocation, idle timeout |
| Insider misuse and data export | Least-privilege capabilities, Administrator-only logged exports, audit of every grant |
| Abuse of bulk unpublish | Limited capability, mandatory reason, count confirmation, audit, restore by batch |
| Pay-to-win leakage | Architecture tests on module boundaries |
| Spam, rival-driven removal requests, enumeration | Bot challenge, throttles, duplicate detection, email verification, staff validity check, uniform responses |
| Signed-link theft or scanning | Single use consumed on `POST`, expiry, hashed storage, rate limit, revocation |
| XSS and injection | Escape on output, `wp_kses` allowlists for story bodies, prepared statements only, nonces on admin actions, a Content-Security-Policy |
| Malicious uploads | Allowlisted image types re-encoded server-side, evidence files limited to JPG, PNG and PDF, stored outside the web root, never served inline |
| Plugin and theme supply chain | Few plugins, pinned by lockfile, file modification disabled in production, weekly vulnerability scan |
| Cache deception or poisoning | Cache only anonymous GET pages for named public paths; bypass on staff cookies |

## Authentication

- **Staff only.** Password plus two-factor, enforced before first use of wp-admin, with recovery codes and lockout after repeated failures (REQ-047). Idle timeout is 60 minutes in wp-admin (verify).
- **Closed registration.** `users_can_register` is off, there is no default role, no front-end login link, no application passwords and no XML-RPC. User enumeration is blocked (author query, users REST route, user sitemaps, login error messages).
- **No other accounts.** Visitors, owners and nominators use forms and signed links. See [No accounts](/concepts/no-accounts-model).
- **Authorisation** is deny by default, and a capability is checked on every admin action. See [Roles in code](/build/roles-in-code).

## Hardening

Done in the guard plugin and the server configuration:

- file editing and file modification disabled in production
- automatic plugin updates off; updates ship through the pipeline
- `wp-config.php` outside the web root, loading the secrets file
- directory listing off; PHP execution blocked in uploads
- no public REST route; core REST limited to logged-in staff
- headers: HSTS, a nonce-based CSP, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and frame-ancestors none

## Secrets

- Real values exist only in a `.env`-style file **outside the web root** (mode 600, created by the owner on the host), and in GitHub Environment secrets if automated deploys are added later.
- `wp-config.php` is committed as a template with no secrets and loads that file.
- `.env.example` lists **names only**. Agents never read `.env*`.
- A secret scanner runs in CI and as a pre-commit hook.
- Keys include SMTP credentials, the signed-link and form-token signing keys, the backup passphrase and the WordPress salts. Rotation is written in the runbook.

## Validation and rate limits

- Validation is **server-side only**, with length limits and Unicode-safe names. One validator class per form. No raw request data outside validators.
- Each public form is rate-limited per IP and per email or link token, and protected by a bot challenge (REQ-081, 122).
- Forms use a time-based token instead of a nonce, because public pages may be cached.
- Responses to public forms are uniform so they do not reveal what exists.

## Personal data and payments

- No card or bank data is handled. The register stores method and reference text, with a digit-pattern guard.
- Personal-data columns are catalogued and kept out of logs and error reports. Sensitive evidence is encrypted at rest where possible. See [Privacy](/concepts/privacy-and-data-rights).
- No PII in logs or error messages. Add any new personal-data column to the catalogue.

## Dependencies and logging

- Dependabot runs weekly. `composer audit` and `npm audit` fail CI on high or critical findings.
- Lockfiles are committed. Packages are kept to a minimum.
- Logs are structured, with IDs rather than names or emails, a request ID, and 30-day retention. The audit log is separate and kept.

## Backups

Nightly database dump and private-file archive from cPanel backups, with a plugin or export if needed. Target: RPO 24 hours, RTO 8 hours. A **restore test** into a local copy runs before launch and monthly (REQ-049). Images and backups live on the host, so keep an off-server copy by downloading them.

## Audit-log immutability on shared hosting

The log is an append-only writer with a hash chain, plus database triggers blocking UPDATE and DELETE where the account has the privilege (checked on the live host). A periodic copy is kept off the server, so tampering is detectable even without database-level immutability (ADR-013).

## Legal exposure

Publishing personal data without consent is the largest exposure (defamation, privacy, false light). The design limits it: individuals never go public before review, organisations carry a label and noindex until reviewed, every field has a source, removal has a two-day service level, suppression is permanent, and bulk unpublish with a 410 can take down a batch quickly. Legal's written position is outstanding and deferred (D-051).

## Added by D-059

| Item | Security note |
|---|---|
| Story comments | Held for moderation; honeypot, rate limits and a spam filter; Gravatar off; comments are personal data |
| Claims | Every field is stored privately; reminders go only to holders of the claims capability |
| Claimed profile edits | Held for approval; the editor cannot approve their own change |
