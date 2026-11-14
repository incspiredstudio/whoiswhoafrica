# Sponsored stories

A sponsored story is the only thing money can buy on WHOISWHO. A sponsor pays offline for a story to sit in a labelled slot on Home, Stories or both, for set dates. The **Commercial > Sponsored stories** screen is the deal tracker: it holds the whole deal from agreement to a live slot. The site records the deal and the payment. It does not process payments.

## Who does what

| Work | Capability | Default roles |
|---|---|---|
| Add a deal, change the agreed amount, record the payment, keep customers | `wiw_manage_register` (fixed) | Administrator |
| Choose the story, schedule or end the slot | `wiw_set_sponsored` | Editor, Administrator |
| Revenue and target tiles on the dashboard | `wiw_view_dashboard_commercial` | Administrator |
| Export deals | `wiw_export_data` | Administrator |

An Editor can use the screen but never sees an amount or a payment field. Each deal is one sponsor, one story and one paid slot.

## The four steps

| Step | What staff do | Who |
|---|---|---|
| 1. Deal agreed | Add the deal: pick the sponsor customer or add one on the spot, enter the agreed amount, currency and a note | Administrator |
| 2. Payment received | Record the method, reference, date received and the named Administrator who confirmed it | Administrator |
| 3. Story ready | Choose an existing story, or use "Create the story" to open the Stories screen and link the new story to the deal. A draft story can be chosen but must be published before step 4. | Editor, Administrator |
| 4. Placement | Choose Home, Stories or Both, and a start and end date | Editor, Administrator |

The New sponsored story button covers steps 1 and 2 on one page so an Administrator can record a paid deal in one go.

## Status

Status is worked out from the deal and never typed.

| Status | Meaning |
|---|---|
| Needs payment | The deal is agreed but the payment record is incomplete |
| Needs story | Payment is recorded but there is no published story |
| Ready to schedule | A published story is chosen and no slot is set |
| Scheduled | A slot exists and starts in the future |
| Live | The slot is running and the story carries the label |
| Ended | The slot has ended or was ended early |
| Cancelled | The deal was cancelled and its slot ended |

A scheduled or live deal with no payment record shows "Payment not recorded". Payment does not block scheduling, but a deal counts toward the targets only when payment is recorded.

## Run a deal

1. An Administrator adds the deal and, once the money arrives, records the payment.
2. An Editor or Administrator chooses or creates the story and publishes it.
3. Choose where the slot appears and for which dates, then save.
4. On the start date, the story fills one labelled slot and shows "Sponsored by (name)" on its card, page and share preview. Only one slot is allowed on a page for the same dates.
5. To stop early choose End now. To book another run choose Schedule again.
6. When the slot ends, the label and slot go automatically. Nothing is deleted.

## What the slot never does

- It never reorders unsponsored stories, search results, profiles, nominations or winners.
- It never changes a profile's Claimed mark or a story's Featured flag.
- Events and profiles cannot be Sponsored.

## What counts toward the targets

A deal counts when it is agreed, not cancelled, and its payment record has all of: amount and currency, payment method and reference, date received, and a named confirming Administrator. Remove any one and it stops counting. The screen shows what is missing.

## Sprint tracking and thresholds

The Sprint thresholds screen holds two targets the Administrator dashboard measures against: the number of counted paid deals and the confirmed revenue. Either one is enough. Only the Administrator sees or changes them, and each change is audited.

Sprint tracking can be switched off in the theme settings. Turning it off hides the sprint card on the dashboard and the Sprint thresholds screen. The thresholds are kept, and the sponsored stories screen and revenue records are not affected. See [Theme settings](/theme/theme-settings).

## Customers

A customer record holds a name, segment and contact, with a stable external ID. When a deal is agreed, an Administrator creates the sponsor as a customer, either on **Audience > Customers** or from New sponsored story.

## Changes and cancellations

Changes to the amount, the payment record and a cancellation are audited. Refunds are handled offline and recorded by an Administrator as a note. A dashboard list shows slots ending in the next 30 days. The system takes no automatic action against a sponsor.

## Payment details

Method and reference are stored as text only. A guard rejects anything that looks like a card number. The site holds no card or bank data.
