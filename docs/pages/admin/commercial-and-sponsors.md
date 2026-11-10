# Commercial and sponsors

How the team records customers, payments and sponsors, and why none of it can touch rankings, labels, nominations or winners. Payment is recorded in the register; there is no payment processing on the site (D-005).

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Customers, arrangements, confirming payments | Customers, Register | `wiw_manage_register` (fixed) | Administrator |
| Story sponsors and the Sponsored label | Story sponsors | `wiw_set_sponsored` | Editor, Administrator |
| Revenue and sprint tiles | Dashboard | `wiw_view_dashboard_commercial` | Administrator |
| Set the sprint targets | Sprint thresholds | `wiw_manage_register` (fixed) | Administrator |
| Export the register | WordPress list-table export (not drawn in the prototype) | `wiw_export_data` | Administrator |

The register capability is fixed to the Administrator (Q72). The prototype's own Customers and Register screens are marked custom; Customers and Sponsors are marked "illustration only" because they are close to native WordPress list screens.

## Sprint thresholds

The only settings screen in the product. It holds the two sprint targets the dashboard measures against (REQ-181): the number of Confirmed paid subscriptions (default 10) and the confirmed revenue (default NGN 5,000,000). Either one is enough; no single gate is hard-coded. Only the Administrator sees or changes it, and every change is audited with the old and new value.

Nothing else is configured here. Each arrangement type maps to a fixed revenue stream in code (subscription, media or editorial, sponsorship), and response targets and the pending-label wording are fixed (REQ-158, REQ-050). The Nominations open or closed switch lives on the Nominations screen, not here.

## Customers

A customer record holds an external ID, name, segment and contact. Enquiries arrive in the Support inbox as type Enquiry; when a deal is agreed, staff create the customer directly. Staff can send a customer a single-use invitation link to fill in their own profile (REQ-013). Customers have stable external IDs and change timestamps so a CaromHQ link can be added later; CSV import and export serve until then (REQ-205).

## The register and arrangements

An **arrangement** is one commercial deal with a customer. The register lists them (REQ-179).

| Field group | Fields |
|---|---|
| What | Type: sponsored post, media or editorial package, awards sponsorship, other |
| Who | Customer, with segment |
| Money | Amount, currency, payment method, payment reference, date received |
| Control | Confirmed-by (a named admin), onboarding started, complimentary flag |
| Dates | Start date, end date |
| Notes | Internal notes, never public (REQ-187) |

There is no "sponsored profile" type. Profiles are never Sponsored (D-059, D-055).

### Status is computed, not typed

Status follows from the fields: proposed, awaiting payment, confirmed, ended or cancelled (REQ-022). In the prototype, "awaiting payment (incomplete)" shows when some payment fields are present but not all.

### The counting rule

A paid subscription counts toward the sprint target only when the arrangement is **Confirmed**. Confirmed needs amount, currency, payment method and reference, the date received, a named confirmed-by admin, and onboarding started. Complimentary arrangements never count, and are reported separately (REQ-022, REQ-023). The thresholds (10 paid subscriptions and NGN 5 million) are configuration, not code (REQ-181).

The register screen explains the rule on screen, and an arrangement edit shows what is missing before it can count.

### Changing an arrangement

Editing, extending, ending or marking an arrangement complimentary needs a reason and is audited (REQ-086, REQ-188). Refunds and cancellations are handled offline and recorded as a note and a status only. Admins see reminders for upcoming expiries; the system takes no automatic action against a customer (REQ-189).

## Story sponsors and the Sponsored label

Only **stories** can be Sponsored (REQ-182). A sponsor can pay for a story to be placed in Stories (REQ-263, D-062). Events cannot be Sponsored (D-063).

The screen lists placements with these fields:

| Field | Meaning |
|---|---|
| Sponsor | Name, with logo and link if wanted |
| Story | Any published story. Events are not offered |
| Placement | Home, Stories or Both |
| Start and end | The dates the placement is active |
| Status | Scheduled, Active or Expired, set by a job from the dates |
| Arrangement | Optional link to the register entry that records the payment |

1. A user with `wiw_set_sponsored` creates the placement. Every change is audited.
2. While it is Active, the story fills a labelled slot on the chosen surface and its page, card and share preview show "Sponsored by [name]".
3. When it expires, the label and slot are removed automatically. Nothing is deleted (REQ-116).

The sponsor does not write the story and has no influence over labels or awards. Sponsor records (name, logo, link) can also be attached to awards and pages as a disclosure only (REQ-186).

## Independence from rankings and awards

These are rules the build must test:

- **No paid position except the labelled story slot.** Search, directory lists and the order of unsponsored stories are never affected by payment (REQ-183, REQ-263).
- **No commercial change to profiles.** Changing an arrangement or payment never changes a profile's Claimed mark or a story's Featured flag, and the reverse (REQ-184).
- **No path into awards.** Payment, subscription or sponsorship never creates a nomination or affects a nomination's stage, status or winners. Awards screens never show commercial data (REQ-199, REQ-183). The nomination screens say so on the page.
- **A pending profile cannot be linked to an arrangement** (REQ-226).
- **Commercial figures** (sprint count, revenue per stream, arrangement tiles) are visible to the Administrator only (REQ-239).

## WordPress native or custom

| Part | Status |
|---|---|
| Register, arrangements, computed status, counting rule | Custom plugin |
| Customers, sponsors | Customised native list screens |
| Sponsored placement and label on a story | Native post with plugin fields and a placement record |
