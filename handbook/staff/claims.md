# Claims

A claim is a request from a person or an organisation's representative to take care of a profile. Staff decide each claim within five days. Approving a claim sets the Claimed mark, which only staff see.

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Review claims, set Claimed, assign a user | Claims | `wiw_review_claims` | Editor, Administrator |
| Approve edits made by an assigned user | Review queue | `wiw_review_records` | Reviewer, Editor, Administrator |

The Administrator can grant `wiw_review_claims` to any role, including a Reviewer.

## Decide a claim

1. Open **Review > Claims**. Each claim shows every field the claimant entered, the received date and the decide-by date, five days after receipt.
2. Check the evidence offline. The site only records what the claimant entered and your decision.
3. Choose Approve or Reject. A rejection needs a reason and the claimant is emailed.
4. On approval the profile is set to Claimed, a checkmark badge appears in staff lists and on the profile screen, and the claimant is emailed.
5. Optionally assign a user to keep the profile up to date.

A profile that is still pending review cannot be claimed until it has been reviewed.

## Deadlines and reminders

A scheduled job emails every holder of the claims capability on day 3 and day 4, then once a day while the claim is overdue. The Claims screen marks an overdue claim. Deciding the claim stops the reminders. Each reminder is recorded in the email log.

## What Claimed means

| Question | Answer |
|---|---|
| What it means | A person or organisation proved who they are and staff approved the claim |
| Who sees it | Staff only, as a checkmark |
| What the public sees | Nothing. The Claim button is hidden, and the claim address shows "Already claimed" with a link to the correction form. |
| What it is not | A fact-check, a trust score or a paid status. Payment never changes it. |

## The assigned user

1. A user with the claims capability assigns one staff user to a claimed profile. The assignment is audited.
2. The assigned user is an ordinary staff user with a limited role that the Administrator creates, for example "Profile manager".
3. They see only their assigned profiles, each with its status: Published or Pending review.
4. They can edit descriptive text, links, contact details and the photograph. They cannot change status, address, Claimed, labels or sponsored story deals.
5. Saving stores a pending revision. The live profile does not change.
6. A Reviewer or Administrator approves the revision or returns it with a note. The author cannot approve their own change. The previous version is kept.
7. The claimant is emailed either way.
