# Commercial and sponsors

How the team runs a sponsored story from an offline deal to a live labelled slot, and why none of it can touch rankings, labels, nominations or winners. The deal is agreed and paid offline. The site only records it; there is no payment processing (D-005).

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Add a deal, change the agreed amount, record the payment | Sponsored stories | `wiw_manage_register` (fixed) | Administrator |
| Choose the story, schedule or end the labelled slot | Sponsored stories | `wiw_set_sponsored` | Editor, Administrator |
| Keep sponsor customers | Customers | `wiw_manage_register` (fixed) | Administrator |
| Revenue and sprint tiles | Dashboard | `wiw_view_dashboard_commercial` | Administrator |
| Set the sprint targets | Sprint thresholds | `wiw_manage_register` (fixed) | Administrator |
| Export deals | WordPress list-table export (not drawn in the prototype) | `wiw_export_data` | Administrator |

The money capability is fixed to the Administrator (Q72). An Editor can use the Sponsored stories screen but never sees an amount or a payment field.

## The one deal type: a sponsored story

For now there is exactly one arrangement type, the **sponsored post** (REQ-179, D-068). Media or editorial packages, awards sponsorships and "other" are gone. There is no sponsored-profile type either: profiles are never Sponsored (D-055, D-059). Each deal is one sponsor, one story and one paid slot.

## The Sponsored stories screen (REQ-269)

One screen holds the whole deal. The list shows each deal with its sponsor, story, amount (Administrator only), four progress dots, a status and the next step. It filters by status.

| Status | Meaning |
|---|---|
| Needs payment | The deal is agreed but the payment record is incomplete |
| Needs story | Payment is recorded but there is no published story yet |
| Ready to schedule | A published story is chosen and no slot is set |
| Scheduled | A slot exists and starts in the future |
| Live | The slot is running and the story carries the label |
| Ended | The slot has ended or was ended early |
| Cancelled | The deal was cancelled; its slot is ended |

Status is computed from the deal, never typed. A live or scheduled deal with no payment record shows "Payment not recorded". Payment is not a gate for scheduling, but a deal counts toward the sprint target only when the payment is recorded.

### The four steps

| Step | What staff do | Who |
|---|---|---|
| 1. Deal agreed | Add the deal: pick the sponsor customer (or add a new one on the spot), the agreed amount and currency, and a note | Administrator |
| 2. Payment received | Record the method, reference, date received and the named admin who confirmed it. Optional at creation | Administrator |
| 3. Story ready | Choose an existing story, or use "Create the story", which opens the Stories screen with the deal noted and links the new story to the deal when saved. A draft story can be chosen but must be published before step 4 | Editor, Administrator |
| 4. Placement | Choose Home, Stories or Both, and a start and end date. "End now" stops a slot early; "Schedule again" books a new one after it ended | Editor, Administrator |

A **New sponsored story** button opens one page that covers steps 1 and 2 together, so an Administrator can record a paid deal in one go.

### The slot

- While live, the story fills one labelled slot on the chosen surface, and its page, card and share preview show "Sponsored by [name]" (REQ-263).
- Only a published story can fill a slot. Only one slot is allowed on a page for the same dates.
- It never changes the order of unsponsored stories, search results, profiles, Featured, nominations or winners.
- When it ends, the label and slot go automatically. Nothing is deleted (REQ-116).
- Every step is audited without personal data (REQ-188).

## The counting rule

A deal counts toward the sprint target when it is agreed, not cancelled, and its payment record is complete: amount, currency, payment method, payment reference, date received and a named confirmed-by admin (REQ-022). Removing any of those makes it stop counting. The screen explains the rule and shows what is missing. The thresholds (10 paid sponsored stories and NGN 5 million) are configuration (REQ-181).

## Sprint thresholds

The only settings screen in the product. It is shown only while the **Sprint tracking** theme setting is on (default on; REQ-272, see [Theme settings](/theme/theme-settings)). Turning the setting off hides this screen and the dashboard sprint card without deleting the thresholds. It holds the two sprint targets the dashboard measures against (REQ-181): the number of counted paid deals (default 10) and the confirmed revenue (default NGN 5,000,000). Either one is enough; no single gate is hard-coded. Only the Administrator sees or changes it, and every change is audited with the old and new value. The Nominations open or closed switch lives on the Nominations screen, not here.

## Customers

A customer record holds an external ID, name, segment and contact. Enquiries arrive on the Enquiries screen as type Enquiry; when a deal is agreed, an Administrator creates the sponsor as a customer, either on the Customers screen or from New sponsored story. Customers have stable external IDs and change timestamps so a CaromHQ link can be added later (REQ-205).

## Sponsor records for the Awards page

The Sponsored stories screen also keeps the sponsor name, logo and link shown as "Supported by" on the Awards page. This is a disclosure only and has no effect on nominations or winners (REQ-186, REQ-199).

## Changing or cancelling a deal

Changes to the agreed amount, the payment record and a cancellation are audited (REQ-086, REQ-188). Refunds are handled offline and recorded by an Administrator as a note. Admins see a dashboard list of slots ending in the next 30 days; the system takes no automatic action against a sponsor (REQ-189).

## Independence from rankings and awards

These are rules the build must test:

- **No paid position except the labelled story slot.** Search, directory lists and the order of unsponsored stories are never affected by payment (REQ-183, REQ-263).
- **No commercial change to profiles.** Changing a deal or payment never changes a profile's Claimed mark or a story's Featured flag, and the reverse (REQ-184).
- **No path into awards.** Payment or sponsorship never creates a nomination or affects a nomination's stage, status or winners. Awards screens never show commercial data (REQ-199, REQ-183).
- **Events cannot be Sponsored** (D-063).
- **Commercial figures** (sprint count and revenue) are visible to the Administrator only (REQ-239).

## WordPress native or custom

| Part | Status |
|---|---|
| Deals, computed status, counting rule, the four-step screen | Custom plugin |
| Customers | Customised native list screen |
| Sponsored placement and label on a story | Native post with plugin fields and a placement record |
