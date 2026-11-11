# Requests and reports

Removal requests, profile reports and data-rights requests share one list called Requests and reports. This page explains how to work each kind, and how the permanent suppression list fits in.

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Work data requests, removals and reports | Requests and reports | `wiw_handle_requests` | Editor, Administrator |
| Decide removals | Requests and reports | `wiw_handle_disputes` | Editor, Administrator |
| View the suppression list | Suppression list | `wiw_view_suppression` | Reviewer, Editor, Administrator |
| Change the suppression list | Suppression list | `wiw_edit_suppression` | Administrator |

## The list

The Kind column says which it is. Filter by kind and status. Each row shows reference, kind, the profile or person, received date, due date and status.

| Kind | Comes from | Staff actions |
|---|---|---|
| Data request | The data-rights form: access, correction of your own data, erasure, objection, portability | Close once fulfilled |
| Removal | "Request removal" on a profile | Valid: hide and suppress, or Not valid with a reason |
| Report | "Report" on a profile | Hide the profile now, dismiss, or resolve |

## Handle a removal

1. Open the request. A due-date countdown shows the two-business-day target.
2. Check it. If it is valid, choose "Valid: hide and suppress". If not, choose "Not valid" and give a reason.
3. The profile now answers "gone" with a noindex instruction, leaves the sitemap and search, and a cache purge is queued.
4. The person or organisation is added to the suppression list so no import can bring it back.
5. The requester is emailed.

The two-day target applies to a valid request from the subject or their representative. Pressing "Request removal" on a profile creates a Removal item, never a data request. A person may file both.

## Handle a report

1. Open the report.
2. If the claim looks serious, hide the profile at once.
3. Investigate, then resolve or dismiss the report.

## Handle a data request

1. Open the request. It has a due date.
2. Fulfil it using the WordPress Privacy tools for access and erasure, or by hand for the other rights.
3. Close it once fulfilled.

When a subject asks for their profile status, view count, sources or enquiries, staff send it from data they already see, and each such request is logged.

## The suppression list

The list holds people and organisations who asked to be removed, and nominees whose removal request was upheld. It is permanent. There is no remove button on purpose. A valid removal adds an entry automatically, and only the Administrator edits the list by hand.

## Bulk unpublish

To take down many profiles at once, see [Profile review and publication states](/staff/profile-review#bulk-unpublish).
