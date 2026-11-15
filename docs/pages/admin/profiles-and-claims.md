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
| Claim and profile owner | Claim status, and the profile owner, with transfer and remove | `wiw_review_claims` |
| Arrangements | Linked commercial records, read-only | view |
| Contact and enquiries | What contact details show publicly, and whether enquiries are allowed | `edit_wiw_profiles` |
| Public URL | Change the slug; the old URL redirects | `edit_wiw_profiles` |
| Source sync | A changed source is flagged for review, never overwritten (REQ-161) | view |
| Revision history and internal notes | Who changed what; notes are never public (REQ-187) | view |

Contact details are private by default. A person without a Profile owner account asks through the correction form and staff set the contact switches on request (REQ-018, REQ-060).

## Editing a profile directly

1. Open the profile and use Edit this profile.
2. Change the fields. If you are resolving an open correction, pick it in "This resolves the open item".
3. Enter the reason or request reference. It is required.
4. Save. If the profile is published, the public page changes at once.

The edit is stored as a revision with author and time, written to the audit log, and closes the linked request (REQ-125, REQ-232). Editing a published profile does not wait for a second person today; the exception is a claimed profile edited by its Profile owner, described below.

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
| 4 | Reviewer of claims | Chooses Approve and create account, Approve, or Reject. Rejecting records a reason and emails the claimant. |
| 5 | System | On approval, sets the profile Claimed and emails the claimant. |
| 6 | System | With Approve and create account, creates the Profile owner account (or reuses the one for that email), links the profile and emails a "Set your password" link. |

Who decides: a user with `wiw_review_claims`, which is Editor and Administrator by default and can be granted to any role, including Reviewer. A profile that is still pending review cannot be claimed until it has been reviewed (REQ-226).

### Deadline and reminders

Every claim must be decided within 5 days (REQ-256). A scheduled job emails every holder of `wiw_review_claims` on day 3 and on day 4, then once a day while the claim is overdue. The Claims screen shows the decide-by date and marks an overdue claim; the dashboard is meant to show open, due-soon and overdue counts. Deciding the claim stops the reminders, and each reminder is recorded in the email log. The prototype shows the decide-by date and a reminder status on each claim; the reminder job itself is a requirement for the build.

The public promise is a reply within five days, and the FAQ says so. Evidence is checked offline (D-060, Q92): the website only accepts the claim, shows staff what the claimant entered, and records the decision.

## The profile owner

- A claimed profile can have one owner: a Profile owner account on the public site, never a wp-admin user (D-071, REQ-253, REQ-274). One owner can own several profiles.
- The role is `wiw_profile_owner`, with `read` and `wiw_own_profiles` only. Any `/wp-admin/` request redirects to `/account/`, and the admin bar is hidden.
- There is no public sign-up. A user with `wiw_review_claims` creates the account with **Approve and create account**, and can transfer or remove ownership. Every action is audited.
- The owner can edit only the profiles they own, and cannot change status, slug, Claimed, labels or arrangements.
- **Every change they make is saved as a pending revision.** It goes public only when a Reviewer or Administrator approves it, and the person who made the change cannot approve it. The previous version is kept.

## How a claimant edits their profile (REQ-261, REQ-274)

1. Staff approve the claim with **Approve and create account**. The evidence was checked offline.
2. The site creates the Profile owner account, or reuses the existing one for that email, links the profile, and emails a "Set your password" link. This is audited.
3. The owner signs in at `/account/` from the footer link "Profile owner sign in" (it reads "My account" when signed in; it is never in the header). Sign-in has the login lockout, Turnstile when configured, and generic errors. Two-factor is optional.
4. **My profiles** lists their profiles. Each shows its status: Live, or Changes waiting for review.
5. **Edit profile** changes descriptive text, links, contact details and the photograph. Saving submits the change for review. The live profile does not change.
6. A Reviewer or Administrator, never the author, approves it or returns it with a note. The owner is emailed either way.
7. **Account details** changes the owner's name and password. Staff change the email in Users. **Sign out** ends the session.

Account pages are noindex, not in the sitemap and never page-cached. The old wp-admin My profiles screen and the `wiw_edit_assigned_profiles` capability are removed.

The prototype predates D-071: it lets staff assign any existing staff user and has no account pages, so this flow is described here and in REQ-261 and REQ-274, not drawn.

## Customer-created profiles

Staff can create a profile on behalf of a customer, or invite the customer to a single-use link to fill it in. A complimentary listing is excluded from paid counts and needs recorded customer consent before it is published (REQ-175, REQ-023). These screens are marked illustration only in the prototype.

## WordPress native or custom

| Part | Status |
|---|---|
| Profile post type, editing, revisions, redirects | Native with plugin fields |
| Claims screen, deadline, reminders, Claimed mark | Custom plugin |
| Profile owner accounts, `/account/` pages and pending revision approval | Custom plugin and theme (REQ-253, REQ-261, REQ-274) |
