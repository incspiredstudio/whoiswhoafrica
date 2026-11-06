# ADR-008 Email provider: Brevo SMTP

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-061, 075, 094, 204

**Context.** Transactional and opt-in email with good deliverability on wiwi.africa.

**Decision.** Brevo SMTP through the Laravel mailer. SPF, DKIM and DMARC on wiwi.africa. Bounce webhook to email_events. Marketing mail needs separate consent and one-click unsubscribe.

**Why.** Free tier covers launch; standard SMTP so it is replaceable.

**Rejected.** cPanel SMTP (deliverability). SES (setup effort). Postmark (cost).

**Consequences.** The free-tier daily cap (300) needs batching for bulk nominee mail.
