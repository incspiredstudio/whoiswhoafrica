# Trust

The module that handles claims: a person or organisation asks to claim a profile, staff decide, and an approved claim marks the profile Claimed and can assign someone to keep it up to date.

## Responsibility

- Take claim requests from the public form.
- Give reviewers every detail of a claim and a decide-by date.
- Remind reviewers before the deadline.
- Set the Claimed mark and the assigned user.
- Hide the Claim button on a claimed profile.

There are no Verified profiles, and profiles are never Sponsored or Featured. The only mark on a profile is Claimed, and it is for staff. Featured exists only for stories, through WordPress's native sticky flag (see [Content](/plugins/content)).

## Content types, taxonomies and tables

| Item | Notes |
|---|---|
| `wiw_profile_claims` (proposed name) | One row per claim: reference (CLM-0001 style), profile, relationship, name, role, work email, phone, evidence, consent, status (new, approved, rejected), received date, decide-by date, last reminder, decided by, decided at, reason. Name, email, phone and evidence are personal data and go in the PII catalogue |
| Profile state row | Gains `claimed`, `claimed_at` and `manager_user_id` (owned here, stored in Directory's table) |

The name avoids a clash with the pipeline's `wiw_claims` table, which holds fact claims (see [Import](/plugins/import)).

## Admin screens

| Screen | What it does |
|---|---|
| Claims | One card per claim showing every field the claimant entered, the received date and the decide-by date, with overdue and reminder status. Approve or reject |
| Profile screen, "Claim and profile manager" card | Shows claim status and date. For a claimed profile, lets a holder of the capability choose the assigned user |
| Profiles list | A checkmark badge, like the tick on social platforms, on claimed profiles. Staff only |
| Dashboard | Open, due-soon and overdue claim counts |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `wiw_review_claims` | no | yes | yes (grantable to any role) |

The withdrawn trust-label capability no longer exists.

## Public read models and endpoints

| Item | Behaviour |
|---|---|
| `/claim/{slug}` | The claim form. Fields: relationship (the person, or an authorised representative of the organisation), name, role, work email, phone (optional), how we can check it, consent to be contacted. Bot check and rate limit, no account |
| `POST /claim/{slug}` | Creates the claim, sends the claimant an acknowledgement, notifies reviewers |
| Claimed profile | `/claim/{slug}` shows "Already claimed" with a link to suggest a correction. The profile page does not show the Claim button |
| Claimed badge | Never public |

## Jobs, hooks and events

| Event | Behaviour |
|---|---|
| Claim submitted | Set the decide-by date to 5 days after receipt; email an acknowledgement; email reviewers |
| Reminder job | Emails every holder of `wiw_review_claims` on day 3 and day 4, then once a day while the claim is overdue. Each email is written to the email log |
| Claim decided | Stop reminders; email the claimant (approval, or rejection with the reason); write an audit row |
| Approval | Set claimed, claimed date; record the decision; offer the assign step |
| Assign | Set `manager_user_id`; audit |

The same reminder job can serve other deadlines (corrections, removals, data requests). See [Compliance](/plugins/compliance).

## Business rules

- **Five days to decide.** The decide-by date is received plus 5 days. Acknowledge within one business day.
- **Reject needs a reason.** Approval and rejection are audited. Neither can be triggered by a payment or an arrangement.
- **A pending-review profile cannot be claimed** until it has been reviewed.
- **Claimed is a staff mark.** The public sees nothing except that the Claim button is gone.
- **The assigned user is an ordinary staff user.** The Administrator creates whatever role fits, such as a limited "Profile manager", and grants capabilities. No fourth default role ships.
- **The assigned user edits only profiles assigned to them.** A capability check on the profile (a `map_meta_cap` rule) enforces it, not only the menu.
- **Their edits are approved.** Every change to a claimed profile made by the assigned user goes to the review queue and is public only after a Reviewer or Administrator approves it. They cannot approve it themselves. They cannot change status, slug or arrangements.
- **Claim evidence standard.** What counts as enough evidence, and who approves, is an open question (see [open questions](/open-questions)). Suggested default: a page on the official website naming the claimant, a registration number or a signed letter, approved by an Editor, with two people for a high-profile organisation.

## Tests that must exist

- `test_REQ_251_claim_form_creates_claim_without_account`
- `test_REQ_252_claims_screen_shows_every_form_field`
- `test_REQ_252_approval_sets_claimed_and_hides_public_claim_button`
- `test_REQ_252_rejection_requires_reason`
- `test_REQ_226_pending_profile_cannot_be_claimed`
- `test_REQ_256_reminders_sent_day_3_day_4_and_daily_when_overdue`
- `test_REQ_256_decision_stops_reminders`
- `test_REQ_253_assigned_user_can_edit_only_assigned_profiles`
- `test_REQ_184_payment_never_sets_claimed`
- `test_REQ_150_claimed_badge_not_in_public_output`

## Depends on

Directory (profile and state row), Review (approval of edits to claimed profiles), Platform (jobs, mail and email log, audit, capabilities), Compliance (acknowledgement email consent).

## Requirement ids covered

REQ-150, 183, 184, 226, 251 to 253, 256.
