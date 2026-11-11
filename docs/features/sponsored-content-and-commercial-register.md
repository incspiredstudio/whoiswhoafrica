# Sponsored stories and deals

Staff record a sponsored story deal that was agreed and paid offline, and place the story in a labelled slot. Payment never affects nominations, judging, winners, profile ranking or any label; a sponsored slot never reorders other stories or search.

## What it does

- Records every sponsored story deal with sponsor, story, agreed amount, payment details and the slot dates. The only deal type for now is the sponsored post (REQ-179).
- Walks the deal through four steps on one screen: deal agreed, payment recorded, story ready, placement (REQ-269).
- Computes each deal's status from its fields, and applies the counting rule for the sprint target.
- Labels sponsored stories visibly while the slot is live, and removes the label and slot automatically when it ends.
- Shows sprint count and revenue to the Administrator only.

## Who uses it and how

- As an Administrator, I can add a deal, record the agreed amount and the payment, and see the sprint and revenue tiles.
- As an Editor, I can choose or create the story for a deal and schedule or end its slot, without seeing any amounts.
- As a visitor, I can always tell a sponsored story from other stories, and I know the sponsor has no say over labels or awards.

## The pieces

### Theme

- Sponsored badge on story cards and story pages, the in-story notice, and the share-preview label.
- The labelled slot card on Home and the Stories page.
- Awards sponsors block ("Supported by") with the note that sponsors do not choose or influence nominees or winners.

### Plugin

- `Commerce` module: customers, deals, the placement record, computed status, counting rule, slots-ending reminders (REQ-116, 189).
- Sponsor records (name, logo, link) attachable to awards and pages as a disclosure (REQ-186).
- Dashboard tiles for the sprint count and revenue, visible to the Administrator only (REQ-181, 239).
- Internal notes on deals and customers (REQ-187).
- Capabilities `wiw_manage_register` (fixed to Administrator), `wiw_set_sponsored`, `wiw_view_dashboard_commercial`, `wiw_export_data`.
- Architecture test that Commerce never writes a profile's mark and Awards never references Commerce.

### Pages and screens

- Staff: Sponsored stories (list, deal page with the four steps, New sponsored story), Customers, Dashboard tiles and Sprint thresholds.
- Public: the Sponsored badge, notice and slot on stories only.

### Third-party plugins

None. There is no payment gateway or card data anywhere (REQ-073 withdrawn).

### Data

- Customers, deals and placement tables. Money is integer minor units plus a currency code, default NGN (REQ-112).
- Staff: Sprint thresholds (10 paid sponsored stories, NGN 5m); the only settings screen.

## Business rules

- Payment is offline. The deal is agreed and paid outside the site; an Administrator records the payment (REQ-086).
- One deal type: sponsored post. There is no sponsored-profile type, and no media package, awards sponsorship or other type (REQ-179).
- Fields: customer (with segment), story, amount, currency, payment method and reference, date received, confirmed-by, status, notes; the placement holds slot, start and end dates.
- Status is computed: needs payment, needs story, ready to schedule, scheduled, live, ended or cancelled. It is not typed in.
- Counting rule: a deal counts as a paid sponsored story when it is agreed, not cancelled, and its payment record is complete: amount, currency, method, reference, date received and a named confirmed-by admin (REQ-022). Payment is not a gate for scheduling the slot, but an unpaid live deal shows "Payment not recorded".
- Changing the amount, recording a payment, scheduling, ending and cancelling are audited (REQ-086, 188). Refunds are handled offline and recorded as a note (REQ-115).
- Only stories can be Sponsored. A placement has a sponsor, a story, a slot (Home, Stories or Both), start and end dates; it is active only between the dates, and the label and slot go when it ends (REQ-155, 182, 263).
- Independence: no paid position in search, directory lists or the unsponsored story order; the only paid slot is a labelled story placement; changing commercial data never changes Claimed or Featured; payment never creates a nomination or affects winners; a pending-review profile cannot be linked to a deal (REQ-183, 184, 199, 226, 263).
- Commercial figures are visible only to the Administrator (REQ-239). An Editor never sees an amount or payment field.
- No public page states prices, packages or commercial terms (REQ-120).

## Acceptance criteria

- [ ] The only deal type that can be created is the sponsored post; the form has no type choice.
- [ ] A new deal needs a customer and an agreed amount; amounts are stored as integers.
- [ ] A deal's status is derived from its fields and slot; no screen lets staff type a status.
- [ ] A deal missing any of amount, currency, method, reference, date received or confirmed-by does not count toward the sprint target, and the deal page shows what is missing.
- [ ] The list shows each deal's progress as four steps and its next step, and filters by status.
- [ ] A slot can be scheduled for a deal with no payment record, and the deal page shows "Payment not recorded".
- [ ] A draft story can be chosen for a deal, but no slot can be scheduled until it is published.
- [ ] "Create the story" opens the Stories screen with the deal noted, and the saved story is linked to the deal.
- [ ] An Editor can do steps 3 and 4 but sees no amount, currency, method, reference or confirmed-by, and cannot record a payment or create a deal.
- [ ] A second slot on the same page and dates is refused.
- [ ] The Administrator's dashboard shows the count of counted deals and confirmed revenue; an Editor and a Reviewer do not see them.
- [ ] A sponsored story shows the Sponsored badge and sponsor name on its card, page and share preview between the slot's dates, and none after.
- [ ] No profile page, card or share preview shows Sponsored, and no event can be sponsored.
- [ ] Every step (create, payment, story, schedule, end, cancel) writes an audit row without personal data.
- [ ] Creating, editing, ending or cancelling any deal leaves every profile's Claimed mark, every story's Featured flag, every search order and every nomination unchanged (automated test).
- [ ] No awards screen or export contains a commercial field (automated test).
- [ ] A pending-review profile cannot be linked to a deal.
- [ ] A deal export needs `wiw_export_data`, includes counting status, and writes an audit row.
- [ ] A dashboard list shows slots ending in 30 days and no automatic action is taken against a sponsor.

## Depends on

- Stories (the Sponsored label and slot).
- Get listed and enquiries (an agreed enquiry becomes a customer).
- Roles, audit and admin.
- Awards and nominations (the separation boundary).

## Out of scope or later

- Payment processing, checkout, invoices or card data.
- Other deal types: media packages, awards sponsorships, complimentary listings as deals.
- Packages, tiers or entitlements (every listing has the same format).
- Profile analytics reports (REQ-020).
- A live CaromHQ integration.

## Requirement ids covered

REQ-016, 021, 022, 026, 077, 086, 087, 112, 115, 116, 155, 178, 179, 180, 181, 182, 183, 184, 186, 187, 189, 199, 239, 263, 269. Deals recorded and weekly reporting live by 20 Dec 2026 (REQ-101, 102); sprint target by 31 Jan 2027 (REQ-105).
