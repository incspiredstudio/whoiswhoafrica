# Trust and independence

Commercial relationships and editorial judgement are kept apart on purpose. This page lists every label in the product, explains who sees each, and states the rule that payment never buys recognition.

## The rule

Payment never affects nominations, judging, winners, profile ranking, Featured, Claimed or any label. The only thing money can buy is a labelled placement slot for a story, for set dates, always marked "Sponsored by (name)". A sponsored slot never reorders the unsponsored stories or search results.

| Money cannot | Why it matters |
|---|---|
| Create a nomination, or change its stage or status | Nominations are decided offline by judges |
| Appear on any Awards screen or export | Awards screens never read commercial data |
| Set Claimed on a profile or Featured on a story | Marks are editorial or evidence-based |
| Buy a paid tier | There are no tiers, packages or entitlements. Every listing has the same format. |
| Make a profile Sponsored | Only stories can be Sponsored |

## The labels

| Label | Applies to | Public? | Can it be bought? |
|---|---|---|---|
| Sponsored | Stories only | Yes, always visible with the sponsor's name | The placement is paid for. It changes nothing else. |
| Featured | Stories only | Yes, as editorial placement | No |
| Claimed | Profiles | No. Staff only, shown as a checkmark | No |
| Compiled automatically, human review pending | Imported organisation profiles | Yes, until reviewed | No |

Profiles are never Sponsored and never Featured. There are no Verified profiles.

### Sponsored

A sponsored story shows the label and the sponsor's name on its card, page and share preview. The sponsor does not write the story and has no influence over any label, profile or award. The label can be set by hand or applied automatically between the start and end dates of a deal. It is styled so it cannot be mistaken for any other status.

### Featured

Featured is an editorial choice using the sticky flag on a story. Featured stories come first on Stories and Home.

### Claimed

Claimed means a person or organisation proved who they are and staff approved the claim. Staff see a checkmark. The public sees nothing, and the Claim button disappears. Edits made by the assigned user of a claimed profile wait for approval from a Reviewer or Administrator.

### Review pending

An imported organisation that no person has reviewed shows "Compiled automatically from public sources. Human review pending." It is not a trust status and is never styled like one. It disappears on approval. The wording is a single setting.

## What the site records about money

There is no payment processing: no gateway, no checkout, no card data. Payment happens offline, and an Administrator records it on the sponsored story deal.

| Item | How it is stored |
|---|---|
| Amount | A whole number of the smallest currency unit plus a currency code |
| Method and reference | Text only, with a guard against card-number patterns |
| Who confirmed it | A named Administrator |
| Dates | Received, start and end |
| Refunds and cancellations | Handled offline; the deal holds a status and a note |

Only the Administrator can add a deal, see an amount or confirm a payment. This permission is fixed and cannot be granted to others.

## How the separation is enforced

1. **No data path.** The awards code may not reference the commercial code, so no query leads from payment to a nomination or winner.
2. **Single writers.** Commercial code and the Sponsored label never write Claimed or Featured. Only the review state machine writes publication state. Only the audit logger writes the audit log.
3. **Offline judging.** The site stores no judge, conflict or score data.
4. **Automatic tests.** Architecture tests run on every change to check these boundaries.
5. **Disclosed sponsors.** A supporter of the awards (name, logo, link) is shown as "Supported by" on the Awards page. This is disclosure, not influence.

## What visitors are told

The FAQ and the Get listed page say the same thing: being listed is free, paid options such as sponsored stories are agreed with the team and never on the site, and none of them affects who can be nominated or who wins.
