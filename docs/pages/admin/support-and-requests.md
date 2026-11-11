# Enquiries, corrections and requests

How staff handle what the public sends in, and how to take a profile down quickly, one at a time or in bulk. It is three screens, so each kind of item has its own list: **Enquiries**, **Corrections**, and **Requests and reports**. None of this needs a public account (D-069).

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Work the enquiries | Enquiries | `wiw_support_inbox` | Editor, Administrator |
| Work the corrections | Corrections | `wiw_support_inbox` | Editor, Administrator |
| Decide corrections and removals | Corrections, Requests and reports | `wiw_handle_disputes` | Editor, Administrator |
| Work data requests, removals and reports | Requests and reports | `wiw_handle_requests` | Editor, Administrator |
| Unpublish one profile | Profile screen | `wiw_unpublish_profiles` | Editor, Administrator |
| Bulk unpublish and restore | Profiles | `wiw_bulk_unpublish` | Reviewer, Administrator |
| View the suppression list | Suppression list | `wiw_view_suppression` | Reviewer, Editor, Administrator |
| Change the suppression list | Suppression list | `wiw_edit_suppression` | Administrator |

An unresolved dispute goes to the Administrator (REQ-174). The Administrator can grant `wiw_handle_disputes` to others, for example a Reviewer. The prototype calls the requests capability `data_requests`; the docs name is `wiw_handle_requests`. Before D-069 data requests were Administrator only; they are now an Editor capability by default because removals and reports, which Editors already handled, share the screen.

Claims have their own screen. See [Profiles and claims](./profiles-and-claims).

## Enquiries

The Get listed form (listing, sponsored story, media work, awards sponsorship), the contact form and the enquiry form on a profile land here as items of type Enquiry, with a status and an age (REQ-089, REQ-177). Staff mark an item read, set an owner and, when a deal is agreed, create the customer or open a sponsored story deal (see [Commercial and sponsors](./commercial-and-sponsors)). Enquiries carry no due date.

## Corrections

Public "Suggest a correction" submissions land on the **Corrections** screen (REQ-157, REQ-158, REQ-271). The list shows reference, profile, the suggestion, received date, due date and status, with a filter by status. Opening an item shows everything the requester sent, including who they say they are, their evidence and their contact (staff only).

1. The requester fills in the form with their name, relationship, email, what to change and evidence. They confirm their email with a single-use link; until then the item shows "email not confirmed" (REQ-233).
2. Staff open the item and choose **Apply**: this opens the profile for a direct edit with a required reason, and the request reference is entered as the reason. Staff do not edit a separate draft.
3. If the profile is claimed, the edit does not go public straight away: it waits for a Reviewer or Administrator to approve it (REQ-253). The correction screen shows this hint.
4. Or choose **Mark resolved**, or **Decline with a reason**. The reason is kept with the request and is never public.
5. The edit, the closure and the decline are written to the audit log. The requester is emailed when the request is resolved (REQ-094).

| Target | Time |
|---|---|
| Acknowledge | 1 business day |
| Resolve | 7 days |

Breaches of these public promises (REQ-158) are flagged in the Due column.

## Requests and reports

Data-rights requests, removal requests and profile reports are one list on the **Requests and reports** screen (REQ-059, REQ-090, REQ-158, REQ-270). The Kind column says which it is. The list filters by kind and by status, and shows reference, kind, profile or person, received date, due date and status. Opening an item shows its full detail and its actions.

| Kind | Comes from | Staff actions |
|---|---|---|
| Data request | The data-rights form: access, correction of your own data, erasure, objection, portability | Close once fulfilled |
| Removal | "Request removal" on a profile | Valid: hide and suppress, or Not valid with a reason |
| Report | "Report" on a profile | Hide the profile now, dismiss, resolve |

### Removals, step by step

1. A removal request arrives with a due-date countdown.
2. A user with `wiw_handle_disputes` checks it. If it is valid, choose "Valid: hide and suppress". If not, choose "Not valid" and give a reason.
3. The profile answers 410 Gone with a noindex header, leaves the sitemap and search, and a cache purge is queued (REQ-209).
4. The person or organisation is added to the suppression list, so no import can bring it back (REQ-147).
5. The requester is emailed.

The two-business-day target applies to a valid request from the subject or their representative (REQ-158). Pressing "Request removal" on a profile never creates a data request. It creates a Removal item here. A person can file both.

### Reports

A report is an abuse flag from any visitor (REQ-090). A reviewer can hide the profile at once while they look into it, then resolve or dismiss the report.

### Data requests

Access, correction, erasure, objection and portability requests are tracked with a due date (REQ-059). WordPress's own Privacy tools take, confirm and fulfil access and erasure requests; the due dates, the other right types and the combined list are the custom part.

### Information on request

When a subject asks for their profile status, view count, sources used or enquiries, staff send it from data they already see, and each such request is logged (REQ-236).

## Suppression list

Permanent. It covers removed individuals and organisations and nominees whose removal request was upheld (REQ-207). **There is no remove button on purpose.** Editing it needs `wiw_edit_suppression`, which stays with the Administrator by default. A valid removal adds an entry automatically.

## Bulk unpublish and restore

This replaced the earlier global kill switch (D-043, REQ-193).

1. On the Profiles list, filter by source, import batch, type, state, origin or name. The live count shows how many published profiles match.
2. Choose bulk unpublish. A reason is required.
3. A confirmation shows the count and the first five names.
4. Each profile becomes 410 with noindex, leaves the sitemap and search, and gets a purge-log entry. The audit log gets one row per profile and one for the batch.
5. To undo it, open "Recent bulk actions" and choose **Restore this batch**. Restoring returns the profiles to 200.

It acts only on published profiles. By default the Reviewer and the Administrator can do this and the Editor cannot. If Legal objects to publishing, this is the planned answer (REQ-072).

## WordPress native or custom

| Part | Status |
|---|---|
| Enquiries, Corrections and Requests and reports lists, typed items, due dates | Custom plugin |
| Direct profile editing with audit and revisions | Native with plugin audit |
| Single-use signed links | Custom plugin |
| Access and erasure tools | Native Privacy tools, plus custom due dates |
