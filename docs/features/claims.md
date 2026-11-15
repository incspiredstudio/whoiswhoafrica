# Claims

A person or organisation can ask to claim their profile without an account. Staff decide within five days and, once approved, the profile is marked Claimed for staff only.

## What it does

- Adds a "Claim this profile" form to every public profile.
- Puts each claim in a Claims screen showing everything the claimant entered, with a decide-by date.
- Reminds claim reviewers before the five days are up.
- On approval, marks the profile Claimed (a checkmark for staff), hides the Claim button publicly, and, with "Approve and create account", gives the claimant a Profile owner account on the public site (D-071, REQ-274).
- Holds every edit the owner makes to a claimed profile until a Reviewer or Administrator approves it.

## Who uses it and how

- As a person named on a profile, I can send a claim with my name, role, work email and how staff can check it.
- As an authorised representative of an organisation, I can claim the organisation's profile.
- As a claim reviewer, I can see every field of a claim, approve it or reject it with a reason, and be reminded before it is overdue.
- As a claim reviewer, I can press "Approve and create account" so the claimant gets a Profile owner account and a "Set your password" email, and I can later transfer or remove ownership.
- As a Profile owner, I can sign in at `/account/` from the footer, see My profiles with each profile's status, and edit only the profiles I own; my changes wait for approval and I am emailed the outcome (REQ-261, 274). I never see wp-admin.
- As a claim reviewer, I check the evidence offline; the website only records the claim and my decision.

## The pieces

### Theme

- `/claim/:slug` form page, the acknowledgement page, and the "Already claimed" page shown when a claimed profile's claim URL is opened.
- The "Claim this profile" button in the profile's right rail, hidden when the profile is claimed.
- `/account/` pages for Profile owners: sign-in, lost password, My profiles, Edit profile, Account details, Sign out. Noindex, out of the sitemap, never cached.
- The footer link "Profile owner sign in" ("My account" when signed in). Never in the header.

### Plugin

- `Trust` module: claim record and state (new, approved, rejected), with received date, decide-by date (received plus 5 days), deciding user and reason.
- Claimed flag and claimed date on the profile; owner user ID.
- Role `wiw_profile_owner` (`read` and `wiw_own_profiles`, no wp-admin capabilities); any wp-admin request redirects to `/account/` and the admin bar is hidden.
- "Approve and create account": creates the owner account or reuses the one for that email, links the profile, emails a set-password link. Transfer and remove ownership. All audited.
- Scheduled reminder job: one email on day 3, one on day 4, then one a day while overdue, to every holder of `wiw_review_claims`. Deciding the claim stops them (REQ-256).
- Pending revision mechanism: edits by a Profile owner are saved as a pending revision and published only when a Reviewer or Administrator other than the author approves it.
- Capability `wiw_review_claims` (Editor and Administrator by default).

### Pages and screens

- Public: `/claim/:slug`, done page.
- Staff: Claims screen (one card per claim with every field, status, decide-by date), Profile screen (claim and profile owner card with transfer and remove), Review queue (pending edits to claimed profiles), dashboard counts of open, due soon and overdue claims.

### Third-party plugins

None. Reminders run on the background job runner already chosen for the plugin.

### Data

- Claim table: id, profile, relationship, name, role, work email, phone, evidence, consent, status, received, decide-by, decided-by, reason. The name must not clash with the import-claims table.
- Profile fields: claimed, claimed date, owner.
- Owner accounts: WordPress users with the `wiw_profile_owner` role, listed in the PII catalogue and covered by personal-data export and erasure.

## Business rules

- No account is needed to claim. The form needs a relationship (the person, or an authorised representative), name, work email, evidence, and a consent tick; role and phone are optional (REQ-251).
- A profile in Published (review pending) cannot be claimed until it has been reviewed (REQ-226).
- Approving a claim sets Claimed only. There is no Verified state. Claimed is never shown publicly; once claimed, the Claim button is hidden (REQ-150, 252).
- Payment never changes Claimed, and Claimed never changes ranking (REQ-184).
- The owner is a Profile owner account on the public site, never a wp-admin user (D-071, REQ-253, 274). There is no public sign-up; staff create the account from an approved claim. One owner can own several profiles.
- The owner edits only the profiles they own: descriptive text, links, contact details and the photo. They cannot change status, slug, Claimed, labels or arrangements, and staff change their email.
- Every edit to a claimed profile by the owner needs approval from a Reviewer or Administrator. The author cannot approve their own change, and the previous version is kept.
- Approval, rejection, account creation, transfer and removal of ownership are audited.

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
- [ ] "Approve and create account" creates a Profile owner account (or reuses the one for that email), links the profile and emails a set-password link.
- [ ] A Profile owner can open and edit only the profiles they own; editing another profile is refused.
- [ ] A Profile owner requesting any `/wp-admin/` address is redirected to `/account/` and sees no admin bar.
- [ ] An edit by the owner is not public until a Reviewer or Administrator approves it, and the approver cannot be the author.
- [ ] Approve, reject, create account, transfer, remove and approve-edit each write an audit row with actor and reason.

## Depends on

- Directory and profiles (the profile and its states).
- Roles, audit and admin (capabilities, review queue, audit log).
- Email and notifications.
- Corrections, removals and reports (shares the typed-item patterns and signed-link and bot protections).

## Out of scope or later

- Evidence upload by the claimant. Today the claimant describes how staff can check; the evidence standard is still open.
- Public sign-up for owners. Staff create every owner account.

## Requirement ids covered

REQ-150, 156, 226, 251, 252, 253, 256, 261, 274; also REQ-184, 244. Claims are part of the profile experience due in production by 31 Jan 2027 (REQ-103).
