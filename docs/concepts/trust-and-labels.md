# Trust and labels

This page lists every label in the product, says which are public and which are staff-only, and explains why profiles are never "Verified".

Requirements: REQ-016, 150, 155, 182, 183, 184, 216, 251, 252, 253. Decisions: D-036, D-054, D-055, D-057, D-059.

## The short version

| Label | Applies to | Public? | Can it be bought? |
|---|---|---|---|
| Sponsored | Stories only | Yes, always visible | It marks paid content, so yes, but it never changes anything else |
| Featured | Stories only | Yes, as editorial placement | No |
| Claimed | Profiles | **No. Staff only**, shown as a checkmark | No |
| Compiled automatically, human review pending | Imported organisation profiles | Yes, until reviewed | No |

Profiles are **never Sponsored and never Featured** (D-055). A paid listing is a subscription, not a label.

## Sponsored: stories only

The brief limits the label to paid content, such as sponsored interviews, articles and features. A story is marked Sponsored with the sponsor's name, and the story page, the card and the share preview all show it (REQ-182).

- The sponsor does not write the story and has no influence over any label, profile or award.
- The label can be set by hand or applied automatically between the start and end dates of a linked arrangement.
- It is styled so it can never be mistaken for any other status.
- It writes only its own fields. It cannot touch any other label, and an architecture test checks this.

## Featured: editorial, stories only

Featured is an editorial choice. In the WordPress build it is WordPress's own sticky flag on a story, controlled with the core `edit_others_posts` capability. The plugin adds no label capability for it (REQ-183). The stories page and the home page put Featured stories first.

## Claimed: a staff mark

A person or an organisation's representative can ask to claim a profile with a form. No account is needed (REQ-251).

1. The claim arrives in the Claims screen with every field the claimant entered, and a decide-by date five days later.
2. A user with the claims capability approves or rejects it. Rejecting records a reason and emails the claimant.
3. Approving sets the profile to **Claimed**. Staff see a checkmark badge, like the verified tick on social platforms, on profile lists and the profile screen.
4. **The public never sees it.** Once a profile is claimed, the Claim button is hidden on its public page.

A claimed profile can have one assigned backend user to keep it up to date. That user is an ordinary staff user with a role the Administrator creates. **Every edit they make is held for approval by a Reviewer or Administrator before it is public**, and the person who made the edit cannot approve it (REQ-253).

A pending-review profile cannot be claimed until it has been reviewed.

## Why nothing is "Verified"

Earlier versions had a staff-only Verified label set from a checklist. It was retired on 2026-11-10 (D-059):

- The ruling was that a claimed profile is simply a claimed profile, and that the platform does not really have verified profiles.
- Public pages never showed Verified anyway (D-054).
- Approving a claim had been setting both Claimed and Verified (D-057). Approval now sets Claimed only.
- The checkmark staff already knew from social platforms is kept, and now means Claimed.

REQ-025 and REQ-153 (the Verified checklist) are withdrawn. The staff trust-label capability is withdrawn too.

Facts are still individually protected: each field shows its source and retrieval date, and review approves them against those sources. See [Publication states](/concepts/publication-states).

## The review-state label

An imported organisation profile that no person has reviewed shows: "Compiled automatically from public sources. Human review pending." It is not a trust status and is never styled like one (REQ-216). It disappears on approval.

## History, in one table

| Date | Decision | Effect on labels |
|---|---|---|
| 2026-11-08 | D-036 | Claiming removed; Verified, Featured and Sponsored remain |
| 2026-11-09 | D-054 | Public pages show no trust status; Verified and Featured become staff labels |
| 2026-11-09 | D-055 | Sponsored and Featured are for stories only |
| 2026-11-09 | D-057 | Claiming returns; approval set Claimed and Verified |
| 2026-11-10 | D-059 | Verified retired; the staff checkmark means Claimed |

## Rules that hold across all labels

- No label is set from a payment field or an arrangement (REQ-183, 184). See [Independence and money](/concepts/independence-and-money).
- Changes to labels are written to the audit log with actor, time and old and new values (REQ-188).

## Where older documents disagree

The architecture document and the GUIDE still describe Verified and Featured on profiles, and a Trust module that owns them. They predate D-054, D-055 and D-059. Follow the decisions.
