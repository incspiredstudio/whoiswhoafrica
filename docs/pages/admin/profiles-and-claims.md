# Profiles and claims

How staff edit a profile, how the Claimed mark works, and how a claim is decided. There are no Verified profiles and no trust labels on profiles (D-059).

## The profile screen

Each profile has one admin screen. Visibility of its parts follows capabilities.

| Part | What it does | Needs |
|---|---|---|
| Status and marks | Publication state, origin (import or customer), source, import batch, and the Claimed checkmark | `view_profiles` in the prototype (all three roles) |
| Edit this profile | Name, title, organisation, website, industry, country, city, profession or organisation type, biography, achievements. Saves at once and requires a reason | `edit_wiw_profiles` (Editor, Administrator) |
| Assign terms | Reviewers can assign existing terms only; Editors and Administrators can also add terms | `assign_terms` |
| Fields and their sources | Each field's source URL and retrieval date, read-only | view |
| Claim and profile manager | Claim status, and the assigned user | `wiw_review_claims` |
| Arrangements | Linked commercial records, read-only | view |
| Contact and enquiries | What contact details show publicly, and whether enquiries are allowed | `edit_wiw_profiles` |
| Public URL | Change the slug; the old URL redirects | `edit_wiw_profiles` |
| Source sync | A changed source is flagged for review, never overwritten (REQ-161) | view |
| Revision history and internal notes | Who changed what; notes are never public (REQ-187) | view |

Contact details are private by default. Nobody has a login, so a person asks through the correction form and staff set the contact switches on request (REQ-018, REQ-060).

## Editing a profile directly

1. Open the profile and use Edit this profile.
2. Change the fields. If you are resolving an open correction, pick it in "This resolves the open item".
3. Enter the reason or request reference. It is required.
4. Save. If the profile is published, the public page changes at once.

The edit is stored as a revision with author and time, written to the audit log, and closes the linked request (REQ-125, REQ-232). Editing a published profile does not wait for a second person today; the exception is a claimed profile edited by its assigned user, described below.

## Claimed is a staff mark

- **What it means:** a person or organisation has proved who they are, and staff approved the claim (REQ-252).
- **What staff see:** a checkmark badge, like the verified tick on social platforms, on profile lists and the profile screen.
- **What the public sees:** nothing. The mark is never public. When a profile is claimed, the "Claim this profile" button is hidden, and opening the claim URL shows "Already claimed" with a link to suggest a correction (REQ-150).
- **What it is not:** a fact-check, a trust score or a paid status. Payment never changes it (REQ-184).

## The claim flow

| Step | Who | What happens |
|---|---|---|
| 1 | Visitor | Fills in the claim form on the profile: relationship, name, role, work email, phone (optional), how staff can check it, and a consent tick (REQ-251). No account. |
| 2 | System | Creates the claim with a received date and a decide-by date 5 days later, emails an acknowledgement, and tells the Editors. |
| 3 | Reviewer of claims | Opens Claims. Each claim shows every field the claimant entered, the received date and the decide-by date. |
| 4 | Reviewer of claims | Approves or rejects. Rejecting records a reason and emails the claimant. |
| 5 | System | On approval, sets the profile Claimed and emails the claimant. |
| 6 | Reviewer of claims | Optionally assigns a user to keep the profile up to date. |

Who decides: a user with `wiw_review_claims`, which is Editor and Administrator by default and can be granted to any role, including Reviewer. A profile that is still pending review cannot be claimed until it has been reviewed (REQ-226).

### Deadline and reminders

Every claim must be decided within 5 days (REQ-256). A scheduled job emails every holder of `wiw_review_claims` on day 3 and on day 4, then once a day while the claim is overdue. The Claims screen shows the decide-by date and marks an overdue claim; the dashboard is meant to show open, due-soon and overdue counts. Deciding the claim stops the reminders, and each reminder is recorded in the email log. The prototype shows the decide-by date and a reminder status on each claim; the reminder job itself is a requirement for the build.

The public promise is a reply within five days, and the FAQ says so. Evidence is checked offline (D-060, Q92): the website only accepts the claim, shows staff what the claimant entered, and records the decision.

## The assigned user

- A claimed profile can have one assigned staff user (REQ-253). The assignment is made by someone with `wiw_review_claims` and is audited.
- The assigned user is an ordinary staff user. The Administrator creates whatever role fits, for example a limited "Profile manager", and grants its capabilities. No fourth default role ships (Q93 answered).
- That user can edit only the profiles assigned to them, and cannot change status, slug or arrangements.
- **Every change they make to a claimed profile is saved as a pending revision.** It goes public only when a Reviewer or Administrator approves it, and the person who made the change cannot approve it. The previous version is kept.

## How a claimant edits their profile (REQ-261)

1. Staff approve the claim. The evidence was checked offline.
2. A Reviewer or Administrator creates an ordinary wp-admin user for the claimant with the limited role, and assigns the profile. This is audited.
3. The claimant signs in with two-factor and sees a **My profiles** list with only their assigned profiles. Each shows its status: Published, or Pending review.
4. They edit descriptive text, links, contact details and the photograph. They cannot change status, slug, Claimed, arrangements or labels.
5. Saving submits the change for review. The live profile does not change.
6. A Reviewer or Administrator, never the author, approves it or returns it with a note. The claimant is emailed either way.

What is still open: how the account is delivered to the claimant and whether one user may hold several profiles (Q102).

The prototype only lets staff assign any existing staff user, and shows the hint that changes need approval. It has no My profiles screen, so this flow is described here and in REQ-261, not drawn.

## Customer-created profiles

Staff can create a profile on behalf of a customer, or invite the customer to a single-use link to fill it in. A complimentary listing is excluded from paid counts and needs recorded customer consent before it is published (REQ-175, REQ-023). These screens are marked illustration only in the prototype.

## WordPress native or custom

| Part | Status |
|---|---|
| Profile post type, editing, revisions, redirects | Native with plugin fields |
| Claims screen, deadline, reminders, Claimed mark | Custom plugin |
| Assigned user and pending revision approval | Custom plugin, proposed (REQ-253) |
