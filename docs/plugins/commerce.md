# Commerce

The module that records the business side without ever touching what the public sees: customers, arrangements, the counting rule and story sponsors.

## Responsibility

- Keep customers and arrangements (what was agreed, paid and by whom) as records.
- Compute whether an arrangement counts toward the launch targets.
- Hold sponsors and sponsored placements, and apply the Sponsored label and a labelled slot to stories (REQ-263).
- Show figures to Administrators only.

The site processes no payments. Customers pay offline, and an Administrator records the payment (method and reference text). Nothing here changes a profile's position, look, label, nomination or award. The only thing money buys is a labelled story placement, which never reorders unsponsored stories or search.

## Content types, taxonomies and tables

| Item | Notes |
|---|---|
| `wiw_leads`, `wiw_customers` | Name, organisation, interest, contact (personal data), owner, status, external id |
| `wiw_arrangements`, `wiw_arrangement_links` | Customer, type, amount in integer minor units, currency, method, reference text, date received, confirmed by, status (computed), start and end dates, complimentary flag, stream, notes, external id. Links tie an arrangement to a story or an award sponsorship |
| `wiw_sponsor` | Custom post type: name, logo, link. Attached to awards, categories, pages and stories |
| Story sponsor fields | A sponsor name and period on a story, written only by this module's label service |

Arrangement types: sponsored post, media or editorial package, awards sponsorship, other. There is no sponsored profile type, because profiles are never Sponsored.

## Admin screens

| Screen | What it does |
|---|---|
| Customers | Customer records and the link issue for invitations and consent |
| Commercial register | All arrangements, with the computed status and why it does or does not count |
| Arrangement edit | Create and edit an arrangement; confirm a payment; end it |
| Story sponsors | Sponsors and the stories they are attached to |
| Dashboard (commercial tiles) | Paid subscriptions against the threshold, revenue against the target, revenue per stream |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `wiw_set_sponsored` | no | yes | yes |
| `wiw_manage_register` (fixed) | no | no | yes |
| `wiw_view_dashboard_commercial` | no | no | yes |
| `wiw_export_data` | no | no | yes |

Fixed means only the Administrator can hold it. It is enforced by a capability filter, not only by hiding the screen.

## Public read models and endpoints

| Item | Behaviour |
|---|---|
| `POST /subscribe` | The "Get listed" enquiry form (name, organisation, interest, contact, message). Creates a Support inbox item of type Enquiry (Compliance), emails the Editors and acknowledges the sender |
| Sponsored label and slot | A story's page, card and share preview show "Sponsored by [name]" while a placement is active. The slot sits on Home, Stories or Both and never reorders other stories or search |
| Sponsors | Shown on the awards pages where attached |

## Jobs, hooks and events

| Trigger | Action |
|---|---|
| Scheduled | Move each sponsored placement from scheduled to active to expired by its start and end dates, adding and removing the label and slot |
| Scheduled | Expiry reminders before an arrangement ends |
| Arrangement saved | Recompute status; audit the change with the before and after |
| Placement expired or arrangement ended | Remove the label and slot |

## Business rules

- **Status is computed, not typed.** An arrangement is Confirmed only when amount, currency, method, reference, date received, confirmed by and onboarding started are all present and it is not complimentary or cancelled. Removing a required field flips it back.
- **Complimentary never counts.**
- **The thresholds are settings.** Ten paid subscriptions and NGN 5,000,000 by default, edited on the Sprint thresholds screen. Each arrangement type maps to a fixed revenue stream in code.
- **No card data.** A digit-pattern guard rejects card-like numbers in the reference field.
- **The label is for stories only.** It is set from an arrangement link or by hand, applied between dates, and written only to the story's sponsor fields. It cannot write anything on a profile.
- **A sponsor does not write the story** and has no influence over any profile, label or award.
- **A pending-review profile cannot be linked to an arrangement.**
- **Never joined to Awards.** No query joins arrangements to nominations or winners.
- **Enquiries are not newsletter subscribers.** Story newsletter sign-ups belong to the consent record (see [Content](/plugins/content)).
- **Exports are Administrator-only and logged.**

## Tests that must exist

- `test_REQ_022_complimentary_arrangement_never_counts`
- `test_REQ_022_removing_required_field_unconfirms_arrangement`
- `test_REQ_184_commercial_change_never_alters_a_profile`
- `test_REQ_182_label_applies_only_between_start_and_end`
- `test_REQ_182_label_service_cannot_write_profile_columns`
- `test_REQ_199_awards_module_has_no_reference_to_commerce` (architecture test)
- `test_REQ_179_card_like_number_in_reference_is_rejected`
- `test_REQ_087_register_export_requires_capability_and_is_logged`
- `test_REQ_226_pending_profile_cannot_be_linked_to_arrangement`

## Depends on

Platform (capabilities, audit, jobs, mail), Content (stories that carry the label), Compliance (consents for invitations).

## Requirement ids covered

REQ-021 to 023, 069, 077, 086, 087, 112, 115, 116, 177 to 182, 184, 186, 188, 189, 205, 226.
