<!-- verify-after-round-9 -->

# Security basics

The site starts from a small attack surface: no public accounts, no public API and no payment data. This page lists the main threats, the controls that answer them, and the rules for sign-in, secrets, validation and logging.

## Threats and controls

| Threat | Control |
|---|---|
| A non-public profile leaks through a feed, API, sitemap, search, preview or attachment page | The publication state machine, the guard plugin that closes core surfaces, no SEO plugin, and a visibility test matrix |
| Removed content stays visible | A "gone" response from database state, cache purge, a two-minute worst case at the edge, restore by batch |
| Wrong or defamatory facts about a real person | Sources on every field, review, removal targets, bulk unpublish, permanent suppression |
| A tampered import | Contract checks, a recomputed hash, a suppression check, allowed fields only, undo by batch, audit |
| Staff account takeover | Two-factor sign-in with recovery codes (recommended for all staff), strong passwords, lockout, session revocation, idle timeout |
| Insider misuse and data export | Least-privilege capabilities, Administrator-only logged exports, audit of every grant |
| Abuse of bulk unpublish | A limited capability, a reason, a count confirmation, audit, restore by batch |
| Money influencing recognition | Automated tests on module boundaries |
| Spam, rival-driven removal requests, enumeration | Bot challenge, throttles, duplicate detection, email verification, staff validity check, uniform responses |
| Signed-link theft or scanning | Single use, expiry, hashed storage, rate limit, revocation |
| Cross-site scripting and injection | Escaping on output, allowlists for story bodies, prepared statements, nonces on admin actions, a content security policy |
| Malicious uploads | Allowed image types re-encoded on the server. Evidence files limited to JPG, PNG and PDF, stored outside the web root and never served inline. |
| Plugin and theme supply chain | Few plugins, only from wordpress.org or the WHOISWHO bundle, a backup before every update, a weekly check of Dashboard > Updates |
| Cache deception or poisoning | Only anonymous page views on named public paths are cached. Staff cookies bypass the cache. |

## Sign-in

1. Only staff sign in, with a strong password. Two-factor sign-in is available and recommended for every staff member but not enforced; the Setup checklist shows who has it on. Recovery codes cover a lost phone, and repeated failures lock the sign-in.
2. Registration is off, there is no default role, no public login link, no application passwords and no XML-RPC.
3. The admin session times out when idle.
4. Authorisation is deny by default, and a capability is checked on every admin action.

## Hardening

- The theme and plugin file editor in wp-admin is switched off.
- Automatic plugin updates are off, so every update follows a backup ([Updates](/operations/updates)).
- Directory listing is off, and PHP cannot run in the uploads folder.
- There is no public REST route. Core REST is limited to signed-in staff.
- Headers: HSTS, a nonce-based content security policy, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` and frame-ancestors none.

## Secrets

1. The keys that sign single-use links and form tokens are created by the site itself on first use and stored in the database. Nobody needs to type or see them.
2. The mail password is entered once in FluentSMTP and the Turnstile secret once in the WHOISWHO Settings menu. Neither is shown again in the admin.
3. Keep every password (cPanel, wp-admin, mail account, Cloudflare) in the team's password manager, never in this handbook, email or chat.
4. Change the mail password and the Turnstile secret when someone who knew them leaves, and after any suspected break-in.
5. A developer may instead keep these values in a secrets file outside the web root. If one is set there, it wins over the admin setting.

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

## Everyday security for staff

1. Use a long, unique password for wp-admin, kept in the password manager.
2. Switch on two-factor sign-in ([how](/staff/users-and-two-factor#two-factor-sign-in)).
3. Sign out on shared computers.
4. Never share an account. Each person has their own, so the audit log shows who did what.
5. Deactivate leavers on their last day ([how](/staff/users-and-two-factor#when-someone-leaves)).
6. Be wary of emails asking you to sign in through a link. Type the wp-admin address yourself.
7. Report anything odd (an unknown user, a change nobody made, a plugin nobody installed) to the Administrator the same day.
