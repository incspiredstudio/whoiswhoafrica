# Claims

A person or organisation can ask to claim their profile without an account. Staff decide within five days and, once approved, the profile is marked Claimed for staff only.

## What it does

- Adds a "Claim this profile" form to every public profile.
- Puts each claim in a Claims screen showing everything the claimant entered, with a decide-by date.
- Reminds claim reviewers before the five days are up.
- On approval, marks the profile Claimed (a checkmark for staff), hides the Claim button publicly, and lets staff assign one user to keep the profile up to date.
- Holds every edit that user makes to a claimed profile until a Reviewer or Administrator approves it.

## Who uses it and how

- As a person named on a profile, I can send a claim with my name, role, work email and how staff can check it.
- As an authorised representative of an organisation, I can claim the organisation's profile.
- As a claim reviewer, I can see every field of a claim, approve it or reject it with a reason, and be reminded before it is overdue.
- As an Administrator, I can assign a staff user to a claimed profile, or create a limited role for it.
- As the assigned user, I can sign in, see My profiles, and edit only the profiles assigned to me; my changes wait for approval and I am emailed the outcome (REQ-261).
- As a claim reviewer, I check the evidence offline; the website only records the claim and my decision.

## The pieces

### Theme

- `/claim/:slug` form page, the acknowledgement page, and the "Already claimed" page shown when a claimed profile's claim URL is opened.
- The "Claim this profile" button in the profile's right rail, hidden when the profile is claimed.

### Plugin

- `Trust` module: claim record and state (new, approved, rejected), with received date, decide-by date (received plus 5 days), deciding user and reason.
- Claimed flag and claimed date on the profile; assigned user ID.
- Scheduled reminder job: one email on day 3, one on day 4, then one a day while overdue, to every holder of `wiw_review_claims`. Deciding the claim stops them (REQ-256).
- Pending revision mechanism: edits by an assigned user are saved as a pending revision and published only when a Reviewer or Administrator other than the author approves it.
- Capability `wiw_review_claims` (Editor and Administrator by default).

### Pages and screens

- Public: `/claim/:slug`, done page.
- Staff: Claims screen (one card per claim with every field, status, decide-by date), Profile screen (Claim and profile manager card with the assign control), Review queue (pending edits to claimed profiles), dashboard counts of open, due soon and overdue claims.

### Third-party plugins

None. Reminders run on the background job runner already chosen for the plugin.

### Data

- Claim table: id, profile, relationship, name, role, work email, phone, evidence, consent, status, received, decide-by, decided-by, reason. The name must not clash with the import-claims table.
- Profile fields: claimed, claimed date, assigned user.

## Business rules

- No account is needed to claim. The form needs a relationship (the person, or an authorised representative), name, work email, evidence, and a consent tick; role and phone are optional (REQ-251).
- A profile in Published (review pending) cannot be claimed until it has been reviewed (REQ-226).
- Approving a claim sets Claimed only. There is no Verified state. Claimed is never shown publicly; once claimed, the Claim button is hidden (REQ-150, 252).
- Payment never changes Claimed, and Claimed never changes ranking (REQ-184).
- The assigned user is an ordinary staff user, not a public account. The Administrator can create a limited role and grant capabilities (REQ-244, 253).
- The assigned user edits only profiles assigned to them. They cannot change status, slug or arrangements.
- Every edit to a claimed profile by that user needs approval from a Reviewer or Administrator. The author cannot approve their own change, and the previous version is kept.
- Approval, rejection and assignment are audited.

## Acceptance criteria

- [ ] The claim form rejects a submission with no relationship, name, valid email, evidence or consent.
- [ ] A submitted claim appears in the Claims screen with every field entered and a decide-by date five days after receipt.
- [ ] The claimant receives an acknowledgement email.
- [ ] Approving a claim sets the profile Claimed, shows the checkmark to staff in the Profiles list and profile screen, and does not set any trust label.
- [ ] After approval, the public profile page has no Claim button, and `/claim/:slug` shows "Already claimed".
- [ ] The public site never shows the Claimed mark on any page, card, share preview or feed.
- [ ] Rejecting a claim needs a reason, emails the claimant, and leaves the Claim button visible.
- [ ] A claim on a pending-review profile is refused with a message to review first.
- [ ] Reminder emails go out on day 3 and day 4 after receipt to every holder of `wiw_review_claims`, then daily while overdue, and stop once the claim is decided.
- [ ] The dashboard shows counts for open, due soon and overdue claims.
- [ ] An assigned user can open and edit only their assigned profiles; editing another profile is refused (403).
- [ ] An edit by the assigned user is not public until a Reviewer or Administrator approves it, and the approver cannot be the author.
- [ ] Approve, reject, assign and approve-edit each write an audit row with actor and reason.

## Depends on

- Directory and profiles (the profile and its states).
- Roles, audit and admin (capabilities, review queue, audit log).
- Email and notifications.
- Corrections, removals and reports (shares the Support inbox patterns and signed-link and bot protections).

## Out of scope or later

- A public owner login or self-edit (no public accounts).
- Evidence upload by the claimant. Today the claimant describes how staff can check; the evidence standard is still open.
- A fourth default role. The Administrator creates extra roles when needed.

## Requirement ids covered

REQ-150, 156, 226, 251, 252, 253, 256; also REQ-184, 244. Claims are part of the profile experience due in production by 31 Jan 2027 (REQ-103).
