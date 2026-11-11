# Audit and compliance

The logs and screens that show what staff did, what the system sent and which data requests are open. An Administrator uses them to answer "who changed this", "what did we email" and "what is due". The registers the organisation must keep (processors, lawful basis, retention) are written down in [Privacy and data rights](/concepts/privacy-and-data-rights); there is no Compliance screen.

## Who does what

| Screen | What it holds | Capability | Default roles |
|---|---|---|---|
| Audit log | Every staff action that matters | `wiw_view_audit` | Administrator |
| Purge log | Each unpublish, removal and decline and its cache purge | `wiw_view_audit` | Administrator |
| Email log | Every notification the system sends | `wiw_view_audit` | Administrator |
| Exports | CSV downloads, provided by WordPress and a plugin (not drawn in the prototype) | `wiw_export_data` | Administrator |
| Requests and reports | Data requests, removals and profile reports in one list (see [Enquiries, corrections and requests](./support-and-requests)) | `wiw_handle_requests` | Editor, Administrator |

In the prototype the audit, purge and email logs share one capability (`audit_log`), and Requests and reports uses `data_requests`.

## Audit log

An append-only record of staff and system actions (REQ-051, REQ-188).

- **What is recorded:** actions on profiles, customer records and reviews, imports, changes to arrangements, sponsors and notes, bulk unpublish, suppression changes, capability grants and revocations, the Nominations toggle (who, when, reason), category changes, nomination stage changes, nominee and winner publishing, and exports.
- **What each row stores:** the actor, the time, the action, the target, and the old and new values or a reason.
- **Tamper evidence:** each row stores the hash of the row before it, so a change to history is detectable. The screen has a button to verify the chain. The audit log is exported nightly off the host.
- **Who can write to it:** only the audit service writes to the log; screens never write to it directly (a repository rule in `CLAUDE.md`).
- **Reasons are required** for edits to published profiles, bulk unpublish and arrangement changes, so the log explains why as well as what.

The screen has no edit or delete control for audit rows.

## Purge log

When a profile is unpublished (single or bulk), removed or declined, the origin already answers 410 with noindex, and a CDN purge is queued. The purge log lists each one so staff can confirm the cached copy is gone (REQ-209).

## Email log

Every notification the system sends is listed here (REQ-094). That includes acknowledgements, claim decisions, correction and removal confirmations, nomination and winner emails (without the address), and the reminders for claims (REQ-256). Bounces that staff find are recorded here (REQ-075). Email goes through the SMTP of a cPanel mail account with SPF, DKIM and DMARC set up (D-049).

## Exports

Exports are provided by WordPress and are not drawn in the prototype. The build gives the Administrator CSV downloads of sponsored story deals with their counting status, customers, nominations (never including the Contact email) and all profiles in an open format, and of support items (REQ-077, REQ-087, REQ-131).

- **How:** a small export action on each list screen, built on the WordPress list tables, plus core Tools > Export for posts and pages. No separate Exports screen is drawn.
- **Administrator only.** The `wiw_export_data` capability is held by the Administrator, and Reviewers cannot export (REQ-069).
- **Every export is logged** in the audit log with who, when, what and how many rows.
- Core Tools > Export is separate and also needs the core `export` capability.

## Data requests

The Requests and reports screen lists access, correction, erasure, objection and portability requests with a due date (REQ-059). Public data-rights requests come in through the data-rights form. Core WordPress Privacy tools take, confirm and fulfil access and erasure requests; the other right types and the due dates are custom. New personal-data columns and meta keys must be added to the PII catalogue (a repository rule in `CLAUDE.md`). Comments and newsletter sign-ups are personal data too, and need to be covered (REQ-258).

## Privacy rules staff must follow

- No personal data in logs or error messages.
- Internal notes are never public (REQ-187).

## WordPress native or custom

| Part | Status |
|---|---|
| Hash-chained audit log, purge log | Custom plugin |
| Email log | Custom plugin over core mail |
| Exports | WordPress list-table actions plus core Tools > Export; the plugin adds the logging and the capability check |
| Access and erasure tools | Native Privacy tools |
| Processor register, lawful basis, retention schedule | Documents, not screens: see [Privacy and data rights](/concepts/privacy-and-data-rights) |
| Breach response | An offline procedure in the runbook (REQ-066); no log screen |
