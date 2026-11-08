# ADR-008 Email provider: Brevo SMTP through wp_mail

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-061, 075, 094, 204, 233

**Context.** Transactional and opt-in email with good deliverability on wiwi.africa: signed links, support acknowledgements, nominee invitations, expiry reminders to admins.

**Decision.** Send through Brevo SMTP configured on `wp_mail` by the plugin (credentials from the secrets file). SPF, DKIM and DMARC on wiwi.africa. A bounce and complaint webhook (shared secret, idempotent) fills `wiw_email_events`. Marketing mail needs separate consent and one-click unsubscribe. Signed-link emails never contain personal data in the URL.

**Why.** The free tier covers launch (verify the daily cap); standard SMTP so it is replaceable.

**Rejected.** cPanel SMTP (deliverability). SES (setup effort). Postmark (cost). A WordPress SMTP plugin (one more dependency).

**Consequences.** Bulk nominee mail needs batching and warm-up (spike S7).
