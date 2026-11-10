# Concepts

These pages explain the ideas the whole product depends on. Read them before the requirements: they say why the rules exist, so the individual requirements make sense.

Each page links to the requirements (REQ), decisions (D), questions (Q) and architecture decision records (ADR) behind it. If a concept page and a requirement ever disagree, the requirements and decisions win, and the concept page needs fixing.

## The pages

| Page | What it answers |
|---|---|
| [Publication states](/concepts/publication-states) | When is a profile public, and what happens on every surface when it stops being public? |
| [Trust and labels](/concepts/trust-and-labels) | Which labels exist, which are public, which are staff-only, and why nothing is "Verified". |
| [Independence and money](/concepts/independence-and-money) | Why payment can never change nominations, winners, rankings or labels, and the one thing it can buy: a labelled story slot. |
| [The no-accounts model](/concepts/no-accounts-model) | Why visitors, owners, nominators and judges have no accounts, and how forms and single-use links replace them. |
| [Privacy and data rights](/concepts/privacy-and-data-rights) | What personal data is held, how long, how people ask to see or remove it, and which cookies load. |
| [Data from acquisition](/concepts/data-from-acquisition) | How profile data arrives from the separate acquisition pipeline, and what each side may and may not touch. |

## The five rules behind everything

1. **Nothing is public until the rules allow it.** Individuals are public only after a person approves them. Organisations may go live on import, but only with a visible label and a noindex instruction.
2. **Every fact has a source.** Each field carries where it came from and when it was retrieved.
3. **Money never buys recognition.** Payment never affects nominations, judging, winners, profile ranking, Featured, Claimed or any label. It can only buy a labelled placement slot for a story.
4. **Removal is fast and permanent.** A valid removal request hides the profile within two business days, and the person is kept on a suppression list so the profile is not added again.
5. **Only staff have accounts.** Everyone else uses forms and single-use links.

## Where the rules live in the build

| Rule | Enforced by |
|---|---|
| Public only when allowed | One state machine, one guard on post status, read models for public templates ([build: data model](/build/data-model)) |
| Money never buys recognition |  Module boundaries checked by architecture tests ([build: roles in code](/build/roles-in-code)) |
| Staff-only accounts | Closed registration, enforced two-factor ([build: security](/build/security)) |
| Fast removal | 410 from database state plus cache purge ([Publication states](/concepts/publication-states)) |

## How these pages are kept honest

The labels and examples on these pages follow the decisions up to D-059 (2026-11-10). Where the older architecture document still uses earlier wording, such as a Verified label on profiles, these pages follow the later decision and say so.

Open points that affect a concept are marked "Open" and point to the question that will settle them.

## Suggested reading order

1. [Publication states](/concepts/publication-states), because every other rule depends on what "public" means.
2. [Trust and labels](/concepts/trust-and-labels), to see what visitors can and cannot be told about a profile.
3. [Independence and money](/concepts/independence-and-money), for the rule that protects the awards and the directory.
4. [The no-accounts model](/concepts/no-accounts-model) and [Privacy and data rights](/concepts/privacy-and-data-rights), for how people interact with the platform and what happens to their data.
5. [Data from acquisition](/concepts/data-from-acquisition), for how the content gets onto the site.

## Terms used on these pages

| Term | Meaning |
|---|---|
| Profile | A page about a person, business or institution |
| Story | An article: interview, feature, spotlight, opinion, announcement or news |
| Claimed | A staff-only mark showing a profile's representative has been confirmed |
| Sponsored | A public label on a paid story |
| Suppression list | People and organisations who asked to be removed, so they are never republished |
| Bulk unpublish | Taking down every profile matching a filter at once, with a reason, reversible by batch |
| Read model | The code a public page must use to read a profile or story, which enforces visibility |
| Capability | A named permission. Roles are default bundles of capabilities |

For the build side of these ideas, see the [build guide](/build/).
