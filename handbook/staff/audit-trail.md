# Audit trail

The audit trail is the set of logs and exports that show what staff did, what the system sent and what is due. An Administrator uses them to answer "who changed this", "what did we email" and "what is due".

## Who does what

| Screen | What it holds | Capability | Default roles |
|---|---|---|---|
| Audit log | Every staff action that matters | `wiw_view_audit` | Administrator |
| Purge log | Each unpublish, removal and decline and its cache purge | `wiw_view_audit` | Administrator |
| Email log | Every notification the system sends | `wiw_view_audit` | Administrator |
| Exports | CSV downloads from the list screens | `wiw_export_data` | Administrator |
| Requests and reports | Data requests, removals and reports in one list | `wiw_handle_requests` | Editor, Administrator |

## Audit log

An append-only record of staff and system actions.

| Recorded | Details |
|---|---|
| What | Actions on profiles, customers and reviews, imports, changes to sponsored story deals, bulk unpublish, suppression changes, capability grants and revocations, the Nominations switch, category changes, nomination stage and status changes, nominee and winner publishing, and exports |
| Each row | The actor, the time, the action, the target, and the old and new values or a reason |

Rules:

1. Only the audit service writes to the log. Screens never write to it directly.
2. A reason is required for edits to published profiles, bulk unpublish and deal changes.
3. Each row stores the hash of the row before it. A button on the screen verifies the chain, so any change to history is detectable.
4. The log is copied off the server regularly.
5. The screen has no edit or delete control for audit rows.

## Purge log

When a profile is unpublished, removed or declined, the site already answers "gone". The purge log lists each one with its cache purge so staff can confirm the cached copy is gone.

## Email log

Every notification is listed: acknowledgements, verification links, claim decisions and reminders, correction and removal confirmations, and nomination and winner emails (without the address). Failures and bounces staff find are recorded here. Email is sent through a cPanel mail account with SPF, DKIM and DMARC set up.

## Exports

1. Open a list screen: sponsored story deals, customers, nominations, profiles or support items.
2. Use the export action. Only the Administrator has the capability.
3. The export is written to the audit log with who, when, what and how many rows.

Nomination exports never include the Contact email. Core Tools > Export is separate and also needs the core export capability. Reviewers cannot export.

## Data requests

The Requests and reports screen lists access, correction, erasure, objection and portability requests with a due date. See [Requests and reports](/staff/requests-and-reports).

## Rules for staff

- No personal data in logs, reasons or error messages.
- Internal notes are never public.
- Any new column or field that holds personal data is added to the personal-data catalogue the same day.
