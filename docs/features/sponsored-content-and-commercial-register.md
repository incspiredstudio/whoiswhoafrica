# Sponsored content and the commercial register

Staff record customers, arrangements and payments in a register, and place paid stories in a labelled slot. Payment never affects nominations, judging, winners, profile ranking or any label; a sponsored slot never reorders other stories or search.

## What it does

- Records every commercial deal as an arrangement with customer, type, amount, dates and payment details.
- Computes each arrangement's status from its fields, and applies the counting rule for the sprint target.
- Labels sponsored stories visibly, and removes the label automatically when the arrangement ends.
- Reports revenue by arrangement type and by stream, to the Administrator only.
- Exports the register to CSV with the counting-rule status of each row.

## Who uses it and how

- As an Administrator, I can create and edit arrangements, confirm payments and see sprint and revenue tiles.
- As an Editor, I can set a story's sponsor.
- As a visitor, I can always tell a sponsored story from other stories, and I know the sponsor has no say over labels or awards.

## The pieces

### Theme

- Sponsored badge on story cards and story pages, the in-story notice, and the share-preview label.
- Awards sponsors block ("Supported by") with the note that sponsors do not choose, score or influence nominees or winners.

### Plugin

- `Commerce` module: customers, arrangements, links to stories and sponsors, computed status, counting rule, revenue streams, expiry reminders (REQ-116, 189).
- Sponsor records (name, logo, link) attachable to awards, categories and pages (REQ-186).
- Dashboard tiles for sprint count, revenue per stream and arrangements, visible to the Administrator only (REQ-181, 239).
- CSV export of the register and customers, logged (REQ-077, 087).
- Internal notes on profiles, customers and nominations (REQ-187).
- Capabilities `wiw_manage_register` (fixed to Administrator), `wiw_set_sponsored`, `wiw_view_dashboard_commercial`, `wiw_export_data`.
- Architecture test that Commerce never writes a profile's mark and Awards never references Commerce.

### Pages and screens

- Staff: Customers, Register, Arrangement edit, counting-rule explainer, Story sponsors, Dashboard commercial tiles, Exports.
- Public: Sponsored badge and notice on stories only.

### Third-party plugins

None. There is no payment gateway or card data anywhere (REQ-073 withdrawn).

### Data

- Customers, arrangements and links tables. Money is integer minor units plus a currency code, default NGN (REQ-112).
- Staff: Sprint thresholds (10 paid subscriptions, NGN 5m); the only settings screen.

## Business rules

- Payment is offline. Customers pay outside the site; an Administrator records and confirms the payment (REQ-086).
- Arrangement types: sponsored post, media or editorial package, awards sponsorship, other. There is no sponsored-profile type (REQ-179).
- Fields: type, customer (with segment), amount, currency, payment method and reference, date received, confirmed-by, onboarding started, status, start and end dates, complimentary flag, notes.
- Status is computed: proposed, awaiting payment, confirmed, ended or cancelled. It is not typed in.
- Counting rule: an arrangement counts as a paid subscription only when Confirmed, which needs amount, currency, method and reference, date received, a named confirmed-by admin and onboarding started. Complimentary arrangements never count and are reported separately (REQ-022, 023).
- Editing, extending, ending or marking complimentary needs a reason and is audited (REQ-086, 188). Refunds and cancellations are handled offline and recorded as note and status only (REQ-115).
- Revenue is reported by type and by stream (subscription, media or editorial, sponsorship); each type maps to a fixed stream in code (REQ-180).
- Only stories can be Sponsored. A sponsored placement has a sponsor, a story, a placement (Home, Stories or Both), start and end dates; it is active only between the dates, and the label and slot go when it ends (REQ-155, 182, 263).
- Independence: no paid position in search, directory lists or the unsponsored story order; the only paid slot is a labelled story placement; changing commercial data never changes Claimed or Featured; payment never creates a nomination or affects screening, judging or winners; judges never see commercial data; a pending-review profile cannot be linked to an arrangement (REQ-183, 184, 199, 226, 263).
- Commercial figures are visible only to the Administrator (REQ-239).
- No public page states prices, packages or commercial terms (REQ-120).

## Acceptance criteria

- [ ] An arrangement cannot be saved without a customer, type and currency; amounts are stored as integers.
- [ ] An arrangement's status is derived from its fields; no screen lets staff type a status directly.
- [ ] An arrangement missing any of amount, currency, method, reference, date received, confirmed-by or onboarding does not count as a paid subscription, and the screen shows what is missing.
- [ ] A complimentary arrangement is excluded from paid counts and revenue and appears in a separate report.
- [ ] The Administrator's dashboard shows the count of Confirmed arrangements, confirmed revenue total and revenue per stream; an Editor and a Reviewer do not see them.
- [ ] Editing an arrangement requires a reason and writes an audit row with old and new values.
- [ ] A sponsored story shows the Sponsored badge and sponsor name on its card, page and share preview between the arrangement's dates, and none after.
- [ ] Setting Sponsored by hand requires `wiw_set_sponsored`.
- [ ] No profile page, card or share preview shows Sponsored.
- [ ] Creating, editing or ending any arrangement leaves every profile's Claimed mark, every story's Featured flag, every search order and every nomination unchanged (automated test).
- [ ] No awards screen or export contains a commercial field (automated test).
- [ ] A Published (review pending) profile cannot be linked to an arrangement.
- [ ] The register export needs `wiw_export_data`, includes counting-rule status, and writes an audit row.
- [ ] Expiry reminders appear for upcoming end dates and no automatic action is taken against a customer.

## Depends on

- Stories (Sponsored label).
- Get listed and enquiries (an agreed enquiry becomes a customer).
- Roles, audit and admin.
- Awards and nominations (the separation boundary).

## Out of scope or later

- Payment processing, checkout, invoices or card data.
- Packages, tiers or entitlements (every listing has the same format).
- Profile analytics reports (REQ-020).
- A live CaromHQ integration.

## Requirement ids covered

REQ-016, 021, 022, 023, 026, 077, 086, 087, 112, 115, 116, 155, 178, 179, 180, 181, 182, 183, 184, 186, 187, 189, 199, 239. Arrangements recorded and weekly reporting live by 20 Dec 2026 (REQ-101, 102); sprint target by 31 Jan 2027 (REQ-105).
