# Privacy and data rights

How the platform collects, shows, keeps and deletes personal data, and how people exercise their rights without an account.

## What it does

- Shows a privacy notice at every point where data is collected, and stores consent with the policy version.
- Takes data requests (access, correction, erasure, objection, portability) through a public page and tracks each with a due date.
- Loads non-essential cookies and analytics only after consent, through a small cookie card.
- Keeps a processor register, a lawful-basis register and a retention schedule as documents, and a breach-response procedure in the runbook. There is no Compliance screen (D-067).
- Deletes or anonymises data when its retention period ends.

## Who uses it and how

- As a visitor, I can accept or decline non-essential cookies, and reopen my choice from the footer.
- As a person, I can ask to see, correct, erase, object to or port my data on the data-rights page.
- As a subscriber, I can stop marketing email with one click.
- As an Administrator, I can track data requests, and read the processor register, the breach procedure and the retention schedule in the docs.

## The pieces

### Theme

- Cookie notice: a small card that appears after the visitor has scrolled a little, or at once on a short page, with Decline and Accept; footer "Cookie preferences" reopens it (REQ-248).
- Pages: Privacy policy, Terms, Cookie policy, Your data rights (`/data-rights`), Complaints and takedown, Unsubscribe.
- A privacy notice and consent tick on every form that collects personal data.

### Plugin

- `Compliance` module: data-request intake and due dates, consent records and the retention job. The registers are documents.
- PII catalogue file listing every personal-data column and meta key; it drives retention, export logging, log scrubbing and the data-request export. A test fails if a new personal-data column is not in it.
- Export and erase handlers for every table that holds personal data, including wpDiscuz comments.
- Log scrubbing so logs hold IDs only.

### Pages and screens

- Public: `/privacy`, `/terms`, `/cookies`, `/data-rights`, `/complaints`, `/unsubscribe`, cookie card.
- Staff: Data requests. Exports are provided by WordPress and a plugin. The registers are documents, not screens.

### Third-party plugins

WordPress core Privacy tools take, confirm and fulfil access and erasure requests; the plugin adds due dates and the other right types. Analytics, if used, is cookieless and first-party.

### Data

- Consent records (who, what, policy version, time), data requests with due dates.

## Business rules

- Lawful basis is recorded per category of person. Public figures published without consent rely on legitimate interests with a right to object (REQ-057).
- A privacy notice appears at every collection point; consent is stored with timestamp and policy version (REQ-058).
- Data-subject requests are tracked with due dates (REQ-059). No account is needed; email ownership is confirmed by a single-use link.
- Marketing email needs a separate opt-in and one-click unsubscribe (REQ-061).
- Non-essential cookies and analytics load only after consent (REQ-062).
- Retention defaults (to be confirmed by Legal): subscriber data 6 years after the last payment; nominations 2 years, including the staff-only Contact email; suppression records kept indefinitely, name only; signed links expire after 14 days (48 hours for nominator and requester checks); logs 30 days with IDs only, except the audit log which is kept (REQ-063).
- Nominator details are never public (REQ-064).
- Sensitive personal data (health, religion, politics, ethnicity, biometrics, criminal records, sexual life, trade-union membership) is never collected in profiles, stubs or nominations, and free text is scanned (REQ-067).
- Nobody under 18 holds a profile (REQ-068).
- Bulk export of personal data is Administrator-only and logged. Reviewers cannot export (REQ-069).
- Every processor is listed in the docs with its location and a data-processing agreement; an offline breach procedure covers detection, logging and notice within the legal deadline (REQ-065, 066).
- Removal: acknowledge within 1 business day; hide within 2 business days of a valid request from the subject (REQ-158).
- No payment or card data is collected anywhere (REQ-070 withdrawn).

## Acceptance criteria

- [ ] No non-essential cookie or analytics script loads before Accept; Decline keeps them off.
- [ ] The cookie card appears after a small scroll, or immediately on a short page, with Decline and Accept; the footer link reopens it.
- [ ] Every form that collects personal data shows a privacy notice link, and consent forms store a timestamp and policy version.
- [ ] Submitting a data request on `/data-rights` creates a request with a due date and a confirmation email.
- [ ] A data export for one email address includes enquiry, nomination, support, newsletter and comment records for it; an erase request removes or anonymises them.
- [ ] A test fails when a table or meta key holding personal data is missing from the PII catalogue.
- [ ] The retention job deletes expired signed links and anonymises data past its retention date, and logs what it did without personal data.
- [ ] Logs and error messages contain IDs but no names or emails.
- [ ] A Reviewer cannot download personal-data exports; an Administrator's export is audited.
- [ ] The processor register in the docs lists hosting, mail and each added service with location and DPA status.
- [ ] Free text containing an age, phone number or sensitive keyword is blocked on intake and nomination forms.
- [ ] Terms and Privacy Policy are published before any customer data is collected.

## Depends on

- Roles, audit and admin (audit log, exports).
- Email and notifications (consent and unsubscribe).
- Comments, Newsletter, Awards, Get listed (data they collect).

## Out of scope or later

- Legal's written position and final wording (supplied by Legal; the build does not wait for it).
- A DPIA document and data-protection contact (a process task, run in parallel).
- Automatic regulator notification (the breach screen logs and shows a checklist only).

## Requirement ids covered

REQ-057, 058, 059, 061, 062, 063, 064, 065, 066, 067, 068, 069, 071, 072, 100, 120, 158, 248. Terms and Privacy published by 21 Nov 2026 (REQ-100).
