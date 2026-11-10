# Support and requests

How staff handle what the public sends in: enquiries, corrections, removal requests, reports and data requests, and how to take a profile down quickly, one at a time or in bulk. None of this needs a public account.

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Work the inbox | Support inbox | `wiw_support_inbox` | Editor, Administrator |
| Decide corrections and removals | Support inbox | `wiw_handle_disputes` | Editor, Administrator |
| Unpublish one profile | Profile screen | `wiw_unpublish_profiles` | Editor, Administrator |
| Bulk unpublish and restore | Profiles | `wiw_bulk_unpublish` | Reviewer, Administrator |
| View the suppression list | Suppression list | `wiw_view_suppression` | Reviewer, Editor, Administrator |
| Change the suppression list | Suppression list | `wiw_edit_suppression` | Administrator |
| Data requests | Data requests | data-request screens | Administrator |

An unresolved dispute goes to the Administrator (REQ-174). The Administrator can grant `wiw_handle_disputes` to others, for example a Reviewer.

## The Support inbox

Every public form lands here as a typed item with a status, an age and, for requests, a due-date countdown (REQ-089, REQ-232).

| Type | Comes from | Staff actions in the prototype |
|---|---|---|
| Enquiry | Contact form, or the enquiry form on a profile | Mark read |
| Correction | "Suggest a correction" on a profile | Edit the profile, mark resolved, decline |
| Removal | "Request removal" on a profile | Valid: hide and suppress, or decline |
| Report | "Report" on a profile | Hide the profile now, dismiss, resolve |

Claims have their own screen. See [Profiles and claims](./profiles-and-claims).

### Response targets

These are the public promises (REQ-158). Breaches are flagged in the Due column.

| Request | Target |
|---|---|
| Any correction or removal | Acknowledge within 1 business day |
| Correction | Resolve within 7 days |
| Valid removal from the subject or their representative | Hide the profile within 2 business days |

### Corrections, step by step

1. The requester fills in the form with their name, relationship, email, what to change and evidence. They confirm their email with a single-use link; until then the item shows "email not confirmed" (REQ-233).
2. Staff open the item and the profile. Staff do not edit a separate draft: they edit the profile directly and enter the request reference as the reason.
3. Choose "This resolves the open item" in the edit form, or mark the item resolved, or decline it.
4. The edit, the closure and the decline are all written to the audit log. The requester is emailed when the request is resolved (REQ-094).

### Removals, step by step

1. A removal request arrives with a due-date countdown.
2. A user with `wiw_handle_disputes` checks it. If it is valid, choose "Valid: hide and suppress".
3. The profile answers 410 Gone with a noindex header, leaves the sitemap and search, and a cache purge is queued (REQ-209).
4. The person or organisation is added to the suppression list, so no import can bring it back (REQ-147).
5. The requester is emailed.

The two-business-day target applies to a valid request from the subject or their representative (REQ-158).

### Reports

A report is an abuse flag from any visitor (REQ-090). A reviewer can hide the profile at once while they look into it, then resolve or dismiss the report.

### Information on request

When a subject asks for their profile status, view count, sources used or enquiries, staff send it through the inbox from data they already see, and each such request is logged (REQ-236).

## Suppression list

Permanent. It covers removed individuals and organisations and declined nominees (REQ-207). **There is no remove button on purpose.** Editing it needs `wiw_edit_suppression`, which stays with the Administrator by default. An Editor's valid removal adds an entry automatically.

## Bulk unpublish and restore

This replaced the earlier global kill switch (D-043, REQ-193).

1. On the Profiles list, filter by source, import batch, type, state, origin or name. The live count shows how many published profiles match.
2. Choose bulk unpublish. A reason is required.
3. A confirmation shows the count and the first five names.
4. Each profile becomes 410 with noindex, leaves the sitemap and search, and gets a purge-log entry. The audit log gets one row per profile and one for the batch.
5. To undo it, open "Recent bulk actions" and choose **Restore this batch**. Restoring returns the profiles to 200.

It acts only on published profiles. By default the Reviewer and the Administrator can do this and the Editor cannot. If Legal objects to publishing, this is the planned answer (REQ-072).

## Data requests

Access, correction, erasure, objection and portability requests are tracked with a due date (REQ-059). WordPress's own Privacy tools take, confirm and fulfil access and erasure requests; the due dates and the other right types are the custom part. The prototype's screen is marked illustration only.

## WordPress native or custom

| Part | Status |
|---|---|
| Inbox, typed items, due dates | Custom plugin |
| Direct profile editing with audit and revisions | Native with plugin audit |
| Single-use signed links | Custom plugin |
| Access and erasure tools | Native Privacy tools, plus custom due dates |
