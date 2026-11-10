# Email and notifications

Every email the site sends, how it is sent, and how staff see what was sent.

## What it does

- Sends acknowledgements, verification links, decisions and reminders to the public.
- Notifies staff when something needs attention.
- Sends through the SMTP of a cPanel mail account on the site's own domain, with SPF, DKIM and DMARC.
- Logs every message so staff can see what was sent and what failed.

## Who uses it and how

- As a visitor, I get an acknowledgement and a reference whenever I send a form.
- As a nominator or requester, I get a link to confirm my email.
- As a claim reviewer, I get reminders before a claim is overdue.
- As an Administrator, I can read the email log and see bounces that staff found.

## The pieces

### Theme

- Email templates in the brand's tone: plain text and simple HTML, one purpose per message, no personal data in links.
- Unsubscribe link block for marketing messages.

### Plugin

- Notification service in the plugin that all features call, with a template per event.
- Email log: every send with time, event, recipient reference and result.
- Bounce and failure recording (REQ-075).
- Scheduled jobs for reminders (claims, expiries) and the retention clean-up of signed links.
- Single-use signed links for the verification emails (REQ-233).

### Pages and screens

- Staff: Email log (Administrator), and the event notices on the Dashboard.
- Public: the "received" pages that quote the reference sent by email.

### Third-party plugins

None decided. A transactional mail provider may replace cPanel SMTP later; if used it must be added to the processor register.

### Data

- Email events table (sends, bounces, complaints). The audit log holds who triggered the send.

## Business rules

- Transactional email goes through the cPanel mail account with SPF, DKIM and DMARC set on the sending domain (REQ-075, 204).
- Marketing email needs a separate opt-in and has one-click unsubscribe (REQ-061).
- Notifications fire for: enquiry received (to staff), profile received, approved, rejected with reason, nomination email and winner email (staff-sent, to the nomination's Contact email), correction, removal or report received, and request resolved. Renewal and expiry reminders go to admins only (REQ-094).
- Public events in the prototype: email verification for corrections, removals and nominations; acknowledgements for corrections, removals, enquiries, claims, data requests, comments and profile submissions; claim decisions; nomination received; nomination email and winner congratulation (staff-sent to the nomination's Contact email, no link); customer invitation and consent request; newsletter confirmation.
- Claim reminder emails: day 3 and day 4 to every holder of `wiw_review_claims`, then daily while overdue (REQ-256).
- Links in email are single-use, expire (default 14 days), are stored hashed, and carry no personal data (REQ-233).
- Logs and error messages hold IDs, not personal data (REQ-050).

## Acceptance criteria

- [ ] A message from the site passes SPF, DKIM and DMARC checks for the sending domain.
- [ ] Submitting each public form sends an acknowledgement with a reference to the submitted email address.
- [ ] A verification email's link works once, then shows the expired-or-used page; it expires after the configured period.
- [ ] Approving or rejecting a customer profile emails the customer; a rejection includes the reason.
- [ ] A new enquiry, claim or review item produces a staff notification.
- [ ] Claim reminders are sent on day 3 and day 4 and then daily while overdue, and stop when the claim is decided.
- [ ] Every send appears in the email log with time, event and result; a failed send shows as failed.
- [ ] Marketing messages carry an unsubscribe link and honour it.
- [ ] No email or log line puts a person's name or email in a URL.
- [ ] Renewal and expiry reminders go only to Administrators and take no action against a customer.

## Depends on

- Roles, audit and admin (who receives staff notices).
- Claims, Corrections, Awards, Get listed, Newsletter, Comments (each triggers messages).
- Privacy and data rights (consent, retention).

## Out of scope or later

- A newsletter composer and sending tool.
- SMS or push notifications.
- A third-party mail provider (planned only if cPanel SMTP is not enough).

## Requirement ids covered

REQ-050, 061, 075, 094, 204, 233, 256. Weekly reporting and the support process work by 20 Dec 2026 (REQ-101, 102).
