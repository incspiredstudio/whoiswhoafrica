# ADR-008 Email provider: cPanel mail SMTP through wp_mail

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08, amended 2026-11-09 (D-050) · REQs: REQ-061, 075, 094, 204, 233

**Context.** Transactional and opt-in email with good deliverability on wiwi.africa: signed links, support acknowledgements, nominee invitations, expiry reminders to admins.

**Decision.** Send through the SMTP of a cPanel mail account, configured on `wp_mail` by the plugin (credentials from the secrets file). SPF, DKIM and DMARC on wiwi.africa. Bounces are read from the mailbox by staff until a provider with webhooks is added; `wiw_email_events` records sends and failures. Marketing mail needs separate consent and one-click unsubscribe. Signed-link emails never contain personal data in the URL.

**Why.** No new account or cost. Standard SMTP, so Brevo or another provider can replace it later without code change.

**Rejected.** Brevo, SES and Postmark for now (later, optional). A WordPress SMTP plugin (one more dependency).

**Consequences.** Hosts cap messages per hour, so bulk nominee mail needs batching. This is a risk to check at go-live; move to a provider only if the cap bites.
