# Commerce

The module that records the business side without ever touching what the public sees: customers, sponsored story deals, the counting rule and the paid story slot.

## Responsibility

- Keep customers and sponsored story deals (what was agreed, paid and by whom) as records.
- Move each deal through four steps: deal agreed, payment recorded, story ready, placement (REQ-269).
- Compute whether a deal counts toward the launch targets.
- Hold sponsor records and sponsored placements, and apply the Sponsored label and a labelled slot to stories (REQ-263).
- Show figures to Administrators only.

The site processes no payments. The sponsor pays offline, and an Administrator records the payment (method and reference text). Nothing here changes a profile's position, look, label, nomination or award. The only thing money buys is a labelled story placement, which never reorders unsponsored stories or search.

## Content types, taxonomies and tables

| Item | Notes |
|---|---|
| `wiw_customers` | Name, segment, contact (personal data), external id |
| `wiw_arrangements` | One row per sponsored story deal. Type is always `sponsored_post`. Customer, story, amount in integer minor units, currency, method, reference text, date received, confirmed by, agreed and cancelled flags, notes, external id. Status is computed, never stored as typed |
| `wiw_placements` | One row per slot: deal, story, sponsor name, slot (Home, Stories or Both), start and end dates, ended flag. Status (scheduled, active, expired) is computed from the dates |
| `wiw_sponsor` | Custom post type: name, logo, link. Shown as "Supported by" on the Awards page |
| Story sponsor fields | A sponsor name and period on a story, written only by this module's label service |

There is one deal type, the sponsored post. There is no sponsored profile type, because profiles are never Sponsored.

## Admin screens

| Screen | What it does |
|---|---|
| Customers | Customer records and the link issue for profile invitations. **Administrator only**: it needs `wiw_manage_register` (fixed), so Editors and support staff do not see it |
| Sponsored stories | The deal list with progress dots, status, next step and a status filter; the deal page with the four steps; New sponsored story; the Supported by records |
| Dashboard (commercial tiles) | Counted deals against the threshold, revenue against the target, slots ending in 30 days |
| Sprint thresholds | The two sprint targets. The only settings screen |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `wiw_set_sponsored` (steps 3 and 4) | no | yes | yes |
| `wiw_manage_register` (fixed; steps 1 and 2, money fields, the Customers screen) | no | no | yes |
| `wiw_view_dashboard_commercial` | no | no | yes |
| `wiw_export_data` | no | no | yes |

Fixed means only the Administrator can hold it. It is enforced by a capability filter, not only by hiding the screen. An Editor opening a deal sees no money field, and the handlers refuse a money change from an Editor.

## Public read models and endpoints

| Item | Behaviour |
|---|---|
| `POST /subscribe` | The "Get listed" enquiry form. Creates an Enquiries item of type Enquiry (Compliance), emails the Editors and acknowledges the sender |
| Sponsored label and slot | A story's page, card and share preview show "Sponsored by [name]" while a placement is active. The slot sits on Home, Stories or Both and never reorders other stories or search |
| Sponsors | Shown on the awards pages where attached |

## Jobs, hooks and events

| Trigger | Action |
|---|---|
| Scheduled | Move each placement from scheduled to active to expired by its start and end dates, adding and removing the label and slot |
| Scheduled | List live slots ending in 30 days for the dashboard |
| Deal saved | Recompute status and the counting result; audit the change without personal data |
| Placement ended, expired or deal cancelled | Remove the label and slot |
| Story created from a deal | Link the new story to the deal |

## Business rules

- **Status is computed, not typed.** Cancelled; otherwise by the deal's placement (live, scheduled, ended); otherwise needs payment, needs story or ready to schedule.
- **A deal counts** when it is agreed, not cancelled, and its payment record is complete: amount, currency, method, reference, date received and confirmed by. Removing a required field stops it counting.
- **Payment is not a gate for scheduling.** An unpaid live or scheduled deal shows "Payment not recorded".
- **A slot needs a published story** and cannot overlap another slot on the same page and dates.
- **The thresholds are settings.** Ten paid deals and NGN 5,000,000 by default, edited on the Sprint thresholds screen.
- **No card data.** A digit-pattern guard rejects card-like numbers in the reference field.
- **The label is for stories only.** It is applied between dates and written only to the story's sponsor fields. It cannot write anything on a profile, and an event cannot be chosen.
- **A sponsor does not write the story** and has no influence over any profile, label or award.
- **A pending-review profile cannot be linked to a deal.**
- **Never joined to Awards.** No query joins deals to nominations or winners.
- **Enquiries are not newsletter subscribers.** Story newsletter sign-ups belong to the consent record (see [Content](/plugins/content)).
- **Exports are Administrator-only and logged.**

## Tests that must exist

- `test_REQ_179_only_sponsored_post_deal_type_exists`
- `test_REQ_269_deal_status_follows_steps`
- `test_REQ_022_incomplete_payment_record_does_not_count`
- `test_REQ_022_removing_required_field_stops_deal_counting`
- `test_REQ_269_editor_sees_no_money_fields_and_cannot_record_payment`
- `test_REQ_263_slot_requires_published_story`
- `test_REQ_263_overlapping_slot_on_same_page_is_refused`
- `test_REQ_263_unpaid_deal_can_be_scheduled_with_warning`
- `test_REQ_184_commercial_change_never_alters_a_profile`
- `test_REQ_182_label_applies_only_between_start_and_end`
- `test_REQ_182_label_service_cannot_write_profile_columns`
- `test_REQ_199_awards_module_has_no_reference_to_commerce` (architecture test)
- `test_REQ_179_card_like_number_in_reference_is_rejected`
- `test_REQ_087_deal_export_requires_capability_and_is_logged`
- `test_REQ_226_pending_profile_cannot_be_linked_to_deal`

## Depends on

Platform (capabilities, audit, jobs, mail), Content (stories that carry the label), Compliance (consents for profile invitations).

## Requirement ids covered

REQ-021, 022, 069, 077, 086, 087, 112, 115, 116, 177 to 182, 184, 186, 188, 189, 205, 226, 263, 269.
