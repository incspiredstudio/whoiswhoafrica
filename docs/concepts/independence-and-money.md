# Independence and money

Commercial relationships and editorial judgement are kept apart on purpose. This page explains the rule, what it covers, and how the build enforces and tests it.

Requirements: REQ-021 to 023, 112, 116, 182 to 184, 199, 263. Awards decisions are offline (D-064). Decisions: D-005, D-028, D-055, D-059, D-062. Architecture: section 3.5 and 3.6 of the architecture.

## The rule

**Payment never affects nominations, judging, winners, profile ranking, Featured, Claimed or any label. The only thing money can buy is a labelled placement slot for a story, for set dates, always marked "Sponsored by [name]". A sponsored slot never reorders the unsponsored stories or search results.** Payment also never creates a nomination or affects screening, shortlists or winners.

| What money cannot do | Requirement |
|---|---|
| Create a nomination or affect a stage or status | REQ-199 |
| Appear on any Awards screen or export | REQ-183, 199 |
| Set or change a staff mark on a profile (Claimed) or a story's Featured flag | REQ-183, 184 |
| Buy a paid placement tier. There are no tiers, packages or entitlements; every listing has the same format | D-028, REQ-016 |
| Make a profile Sponsored. Only stories can be Sponsored | D-055 |

What money can do is one narrow, visible thing: a sponsor can pay for a story to sit in a labelled placement slot (Home, Stories or Both) for set dates (REQ-263, D-062). The story is marked "Sponsored by [name]" on its card, page and share preview. The slot never moves the unsponsored stories, search results, profiles, nominations or winners.

## What the platform records about money

The site has no payment processing at all: no gateway, no checkout, no card data (D-005). Payment happens offline. An Administrator records it on the sponsored story deal.

| Item | How it is stored |
|---|---|
| Amount | Integer minor units plus a currency code (REQ-112) |
| Method and reference | Text only. A digit-pattern guard rejects anything that looks like a card number |
| Who confirmed it | A named Administrator |
| Dates | Received, start and end |
| Refunds and cancellations | Handled offline; the deal holds a status and a note |

Only the Administrator can add a deal, see an amount or confirm a payment. The capability is fixed to the Administrator and cannot be granted to others ([build: roles in code](/build/roles-in-code)).

## The counting rule

The sprint target counts paid sponsored story deals. A deal counts only when it is agreed, not cancelled, and its payment record is complete, and that is computed, never typed (REQ-022). It needs all of:

1. amount and currency
2. payment method and reference
3. date received
4. a named confirming Administrator

Property tests cover this: removing any required field makes a deal stop counting.

## How the separation is enforced

The rule is built into the code structure, not left to good behaviour.

- **No data path.** The `Awards` module may not reference the `Commerce` module. No code or query leads from commercial data to nominations, stages, status or winners.
- **Single writers.** Commercial code and the Sponsored label do not write any profile mark. The label writes only its own fields.
- **Awards decisions are made offline.** Judging and scoring happen outside the site (D-064), and the site stores no judge, conflict or score data. Payment never affects nominations or winners.
- **Screens are built without commercial fields.** Awards screens never query commercial tables.
- **Sponsors are records.** A sponsor (name, logo, link) can be attached to awards, categories and pages, which is visible sponsorship, not influence (REQ-186).

## How it is tested

| Check | What it proves |
|---|---|
| Architecture test: Awards does not reference Commerce | No path from payment to nomination or winner |
| Architecture test: Commerce and the Sponsored label never write Claimed or Featured | A paid arrangement cannot change a mark |
| Property tests on the counting rule | Status follows the fields and the slot |
| Screen tests on the Awards screens | No commercial data is displayed or exported |
| Change test: edit an arrangement, then check profile marks | Commercial changes leave marks unchanged (REQ-184) |

The architecture tests run on every pull request ([build: testing and CI](/build/testing-and-ci)).

## What the visitor is told

The FAQ and the get-listed page say the same thing in plain words: being listed is free, paid options such as sponsored stories are agreed with the team and never on the site, and none of them affects who can be nominated or who wins.

## Settled and open

- Settled: the Administrator alone records and confirms payments (D-045, Q70).
- The sprint thresholds (10 subscriptions, NGN 5,000,000) are settings on the Sprint thresholds screen, not code (REQ-181).
- Old wording in a few requirements (for example REQ-188) still names Verified. Read it as the Claimed mark (D-059).
