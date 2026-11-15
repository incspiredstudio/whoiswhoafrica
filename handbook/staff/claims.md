# Claims

A claim is a request from a person or an organisation's representative to take care of a profile. Staff decide each claim within five days. Approving a claim sets the Claimed mark, which only staff see.

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Review claims, set Claimed, create a Profile owner account, transfer or remove ownership | Claims | `wiw_review_claims` | Editor, Administrator |
| Approve edits made by a Profile owner | Review queue | `wiw_review_records` | Reviewer, Editor, Administrator |
| Change a Profile owner's email address | Users | Administrator only | Administrator |

The Administrator can grant `wiw_review_claims` to any role, including a Reviewer.

## Decide a claim

1. Open **Review > Claims**. Each claim shows every field the claimant entered, the received date and the decide-by date, five days after receipt.
2. Check the evidence offline. The site only records what the claimant entered and your decision.
3. Choose **Approve and create account**, **Approve** or **Reject**. A rejection needs a reason and the claimant is emailed.
4. On approval the profile is set to Claimed, a checkmark badge appears in staff lists and on the profile screen, and the claimant is emailed.
5. **Approve and create account** also gives the claimant a Profile owner account on the public site, so they can keep the profile up to date. See [Profile owner accounts](#profile-owner-accounts). Plain **Approve** sets Claimed and creates no account.

A profile that is still pending review cannot be claimed until it has been reviewed.

The list has a checkbox on each open claim and a **Bulk actions** menu: **Approve**, and **Reject** with one reason sent to every claimant listed. Hover over a row, or tab to it, for **View**, **Approve** and **Reject**. **Approve and create account** is only on the claim's own page (open it with **View**), because each account needs its own check.

## Deadlines and reminders

A scheduled job emails every holder of the claims capability on day 3 and day 4, then once a day while the claim is overdue. The Claims screen marks an overdue claim. Deciding the claim stops the reminders. Each reminder is recorded in the email log.

## What Claimed means

| Question | Answer |
|---|---|
| What it means | A person or organisation proved who they are and staff approved the claim |
| Who sees it | Staff only, as a checkmark |
| What the public sees | Nothing. The Claim button is hidden, and the claim address shows "Already claimed" with a link to the correction form. |
| What it is not | A fact-check, a trust score or a paid status. Payment never changes it. |

## Profile owner accounts

A Profile owner is the person behind an approved claim. They have an account on the public site, like a shop customer's "My account". They never use wp-admin (D-071).

1. **Create.** On a claim in **Review > Claims**, press **Approve and create account**. The site creates a Profile owner account for the claimant's email, or reuses the existing one if that email already has an owner account, and links the profile to it. The owner is emailed a "Set your password" link. One owner can own several profiles.
2. **Sign in.** The owner signs in from the "Profile owner sign in" link in the site footer and lands on `/account/`. There is no public sign-up.
3. **What they see.** A My profiles list with each profile's status: Live, or Changes waiting for review.
4. **What they can edit.** Descriptive text, links, contact details and the photograph. They cannot change status, address, Claimed, labels or sponsored story deals.
5. **Review.** Saving stores a pending revision. The live profile does not change. A Reviewer or Administrator approves the revision or returns it with a note. The author cannot approve their own change. The previous version is kept. The owner is emailed either way.
6. **Transfer or remove.** On the profile, staff with the claims capability can move ownership to another owner account or remove it. Every create, link, transfer and removal is written to the audit log.
7. **No wp-admin.** The role `wiw_profile_owner` has no wp-admin capabilities. Any wp-admin address sends the owner back to `/account/`, and they never see the admin bar.
8. **Email changes.** The owner can change their name and password on the account page. A new email address is changed by an Administrator in **Users**.

Two-factor sign-in is optional for owners. The owner's own guide is [Your profile owner account](/visitor/your-account).
