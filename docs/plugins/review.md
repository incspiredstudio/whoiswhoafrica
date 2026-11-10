# Review

The module that decides what is public. It owns the publication state machine, the review queue, duplicate handling, bulk unpublish, and the approval of edits to claimed profiles.

## Responsibility

- Be the only code that changes a profile's publication state.
- Give reviewers a queue where every claim is shown beside its source.
- Take profiles down fast and bring them back safely.
- Hold edits to claimed profiles until a Reviewer or Administrator approves them.

## Content types, taxonomies and tables

| Item | Notes |
|---|---|
| `wiw_profile_state.pub_state` | Written only by `Review\StateMachine`. Six states: Imported, not public; Published, review pending; Published, reviewed; Unpublished; Rejected; Removed and suppressed |
| Custom post status `wiw_hidden` | Registered non-public. Every state except the two Published ones uses it, so core WordPress never treats the post as public |
| `wiw_duplicates` | Pairs of probable duplicates with a score and status |
| `wiw_bulk_actions` | One row per bulk unpublish: filter, reason, count, actor, time, restored time |
| Pending revisions | An edit to a claimed profile by its assigned user is saved as a pending revision (a row in `wiw_profile_versions` with an approval status) and is not public until approved |

## Admin screens

| Screen | What it does |
|---|---|
| Dashboard (review tiles) | Counts by state: imported, pending review, reviewed, published |
| Review queue | Items to review, including customer submissions marked "submitted" and pending revisions on claimed profiles |
| Review item | Field-level diff, each claim beside its source and retrieval date, the verification report, a neutral checklist, approve, edit or reject with a reason |
| Duplicates | Side-by-side human merge review. Individuals are never auto-merged |
| Bulk unpublish | On the Profiles list: filter, live match count, mandatory reason, confirmation showing the count and first names, and a recent-actions list with restore |
| Purge log | Cache purges and unpublish events (shared with Platform) |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `wiw_view_dashboard_review` | yes | yes | yes |
| `wiw_review_records` (approve or reject) | yes | yes | yes |
| `wiw_resolve_duplicates` | yes | yes | yes |
| `wiw_bulk_unpublish` | yes | no | yes (grantable) |
| `wiw_unpublish_profiles` (single profile) | no | yes | yes |

A Reviewer can unpublish a single profile through the bulk capability, so there is always someone able to act in an emergency without edit rights.

## Public read models and endpoints

None of its own. The module decides what `PublicProfile` may return. Its visible effects are:

- 200 with a pending label and noindex for Published, review pending.
- 200 with a "Reviewed on" date for Published, reviewed.
- **410 Gone with noindex** for removed, suppressed and unpublished profiles, answered from the database and not from a cache.
- 404 for Rejected and Imported, not public.

## Jobs, hooks and events

| Trigger | Action |
|---|---|
| `wp_insert_post_data` | Refuse any write that changes post status or `pub_state` outside the `StateMachine`; log the refusal |
| State change | One transaction: set state and post status, delete or add the search row, write audit rows |
| Bulk unpublish | One `wiw_bulk_actions` row, then chunked jobs of a few hundred profiles, one audit row per profile plus one for the batch |
| After commit | High-priority cache purge jobs (by URL: page, list pages, sitemap, share image). A broad purge above a threshold |
| Approving a pending revision | Publish the revision, purge, notify the assigned user |

## Business rules

- **Public only after review, except labelled organisations.** An organisation may go live on import as Published, review pending, labelled and noindex. Individuals and entrepreneurs are not public until reviewed.
- **The importer cannot approve.** The reviewer approving a record must not be the person who imported it (REQ-148).
- **Approve is one flow.** Set reviewed, remove noindex, add the sitemap and schema entries, purge the cache. Reject makes it not public (410 if it was live).
- **A pending-review profile cannot be claimed or linked to an arrangement** until a human has reviewed it (REQ-226).
- **Edits to a claimed profile are approved.** When the assigned user edits a claimed profile, the change waits for a Reviewer or Administrator holding `wiw_review_records`. The person who made the change cannot approve it. The previous version is kept (REQ-253).
- **Staff edits on unclaimed profiles go live at once**, with a required reason, a version and an audit row.
- **Bulk unpublish includes only published profiles.** Restore reverses one batch, only for profiles still unpublished by that batch.
- **Reason required.** Every unpublish, reject and bulk action needs one.

## Tests that must exist

- `test_REQ_210_only_state_machine_changes_pub_state`
- `test_REQ_208_state_matrix_on_every_surface`
- `test_REQ_148_importer_cannot_approve_own_import`
- `test_REQ_209_unpublish_returns_410_with_noindex`
- `test_REQ_193_bulk_unpublish_writes_one_audit_row_per_profile`
- `test_REQ_193_restore_batch_only_restores_profiles_unpublished_by_it`
- `test_REQ_226_pending_profile_cannot_be_claimed`
- `test_REQ_253_claimed_profile_edit_waits_for_approval`
- `test_REQ_253_editor_cannot_approve_own_change`

## Depends on

Directory (the profile and state row), Platform (audit, capabilities, cache purge, jobs), Trust (claimed flag and assigned user), Compliance (removal flow calls the state machine).

## Requirement ids covered

REQ-084, 091, 123, 146 to 149, 160, 163, 193, 208 to 210, 214, 216, 217, 221, 222, 226, 239, 241, 253.
