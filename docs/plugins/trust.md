# Trust

The module that handles claims: a person or organisation asks to claim a profile, staff decide, and an approved claim marks the profile Claimed and can give the claimant a Profile owner account on the public site to keep it up to date (D-071).

## Responsibility

- Take claim requests from the public form.
- Give reviewers every detail of a claim and a decide-by date.
- Remind reviewers before the deadline.
- Set the Claimed mark.
- Create Profile owner accounts, link them to profiles, and transfer or remove ownership.
- Serve the owner's `/account/` pages and keep owners out of wp-admin.
- Hide the Claim button on a claimed profile.

There are no Verified profiles, and profiles are never Sponsored or Featured. The only mark on a profile is Claimed, and it is for staff. Featured exists only for stories, through WordPress's native sticky flag (see [Content](/plugins/content)).

## Content types, taxonomies and tables

| Item | Notes |
|---|---|
| `wiw_profile_claims` (proposed name) | One row per claim: reference (CLM-0001 style), profile, relationship, name, role, work email, phone, evidence, consent, status (new, approved, rejected), received date, decide-by date, last reminder, decided by, decided at, reason. Name, email, phone and evidence are personal data and go in the PII catalogue |
| Profile state row | Gains `claimed`, `claimed_at` and the owner's user ID (owned here, stored in Directory's table) |
| Role `wiw_profile_owner` | "Profile owner": `read` and `wiw_own_profiles`, no wp-admin capabilities. Owner accounts are WordPress users; name and email are personal data in the PII catalogue and covered by export and erasure |

The name avoids a clash with the pipeline's `wiw_claims` table, which holds fact claims (see [Import](/plugins/import)).

## Admin screens

| Screen | What it does |
|---|---|
| Claims | One card per claim showing every field the claimant entered, the received date and the decide-by date, with overdue and reminder status. Approve and create account, Approve, or Reject |
| Profile screen, claim card | Shows claim status and date and the profile owner. A holder of the capability can transfer or remove ownership |
| Profiles list | A checkmark badge, like the tick on social platforms, on claimed profiles. Staff only |
| Dashboard | Open, due-soon and overdue claim counts |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `wiw_review_claims` | no | yes | yes (grantable to any role) |

Profile owners hold `read` and `wiw_own_profiles` only, through the `wiw_profile_owner` role. The old `wiw_edit_assigned_profiles` capability and the wp-admin My profiles screen are removed.

The withdrawn trust-label capability no longer exists.

## Public read models and endpoints

| Item | Behaviour |
|---|---|
| `/claim/{slug}` | The claim form. Fields: relationship (the person, or an authorised representative of the organisation), name, role, work email, phone (optional), how we can check it, consent to be contacted. Bot check and rate limit, no account |
| `POST /claim/{slug}` | Creates the claim, sends the claimant an acknowledgement, notifies reviewers |
| Claimed profile | `/claim/{slug}` shows "Already claimed" with a link to suggest a correction. The profile page does not show the Claim button |
| Claimed badge | Never public |
| `/account/` | Profile owner sign-in (login lockout, Turnstile when configured, generic errors), lost password, My profiles (Live, or Changes waiting for review), Edit profile, Account details (name and password), Sign out. Noindex, not in the sitemap, never page-cached |
| Footer link | "Profile owner sign in", or "My account" when signed in. Never in the header |
| `/wp-admin/` for an owner | Redirects to `/account/`; no admin bar |

## Jobs, hooks and events

| Event | Behaviour |
|---|---|
| Claim submitted | Set the decide-by date to 5 days after receipt; email an acknowledgement; email reviewers |
| Reminder job | Emails every holder of `wiw_review_claims` on day 3 and day 4, then once a day while the claim is overdue. Each email is written to the email log |
| Claim decided | Stop reminders; email the claimant (approval, or rejection with the reason); write an audit row |
| Approval | Set claimed, claimed date; record the decision |
| Approve and create account | As Approval, then create the Profile owner account (or reuse the one for that email), link the profile, email a set-password link; audit |
| Transfer or remove ownership | Change or clear the profile's owner; audit |
| Owner edit saved | Store a pending revision for Review; the owner is emailed when it is approved or returned |

The same reminder job can serve other deadlines (corrections, removals, data requests). See [Compliance](/plugins/compliance).

## Business rules

- **Five days to decide.** The decide-by date is received plus 5 days. Acknowledge within one business day.
- **Reject needs a reason.** Approval and rejection are audited. Neither can be triggered by a payment or an arrangement.
- **A pending-review profile cannot be claimed** until it has been reviewed.
- **Claimed is a staff mark.** The public sees nothing except that the Claim button is gone.
- **The owner is a Profile owner account on the public site, never a wp-admin user** (D-071, REQ-274). No public sign-up; staff create the account. One owner can own several profiles.
- **The owner edits only the profiles they own.** A capability check on the profile (a `map_meta_cap` rule on `wiw_own_profiles`) enforces it, not only the page.
- **Their edits are approved.** Every change to a claimed profile made by the owner goes to the review queue and is public only after a Reviewer or Administrator approves it. They can change descriptive text, links, contact details and the photo; they cannot change status, slug, Claimed, labels or arrangements. Their email is changed by staff in Users.
- **Claim evidence standard.** What counts as enough evidence, and who approves, is an open question (see [open questions](/open-questions)). Suggested default: a page on the official website naming the claimant, a registration number or a signed letter, approved by an Editor, with two people for a high-profile organisation.

## Tests that must exist

- `test_REQ_251_claim_form_creates_claim_without_account`
- `test_REQ_252_claims_screen_shows_every_form_field`
- `test_REQ_252_approval_sets_claimed_and_hides_public_claim_button`
- `test_REQ_252_rejection_requires_reason`
- `test_REQ_226_pending_profile_cannot_be_claimed`
- `test_REQ_256_reminders_sent_day_3_day_4_and_daily_when_overdue`
- `test_REQ_256_decision_stops_reminders`
- `test_REQ_253_owner_can_edit_only_owned_profiles`
- `test_REQ_274_owner_is_redirected_from_wp_admin`
- `test_REQ_184_payment_never_sets_claimed`
- `test_REQ_150_claimed_badge_not_in_public_output`

## Depends on

Directory (profile and state row), Review (approval of edits to claimed profiles), Platform (jobs, mail and email log, audit, capabilities), Compliance (acknowledgement email consent).

## Requirement ids covered

REQ-150, 183, 184, 226, 251 to 253, 256, 261, 274.
