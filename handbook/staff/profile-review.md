# Profile review and publication states

A profile moves from an imported record to a public page through review. Nothing about an individual is public until a person has approved it. This page covers the import, the review queue, duplicates, the six publication states, editing, and how to take profiles down.

## Who does what

| Step | Screen | Capability | Default roles |
|---|---|---|---|
| Run an import | Import | `wiw_run_imports` | Administrator |
| Review and approve | Review queue | `wiw_review_records` | Reviewer, Editor, Administrator |
| Resolve duplicates | Duplicates | `wiw_resolve_duplicates` | Reviewer, Editor, Administrator |
| See where records came from | Sources (read-only) | `wiw_view_sources` | Reviewer, Editor, Administrator |
| Edit a profile | Profile screen | `edit_wiw_profiles` | Editor, Administrator |
| Unpublish one profile | Profile screen | `wiw_unpublish_profiles` | Editor, Administrator |
| Bulk unpublish and restore | Profiles | `wiw_bulk_unpublish` | Reviewer, Administrator |

## Import a file

1. A named Administrator uploads the file in the admin, or runs it from the command line.
2. Run the dry run first. It checks required fields, country codes, terms, the suppression list, duplicates and free-text patterns, and shows a difference before anything is written.
3. Import. Records are created or updated by stable ID, so importing the same file twice changes nothing.
4. The site recomputes each record's content hash and refuses a record whose hash does not match.
5. Each record is tagged with its file, batch and importer, and the import is written to the audit log.

A re-import never overwrites a field a person has already reviewed. A changed source is flagged for review instead.

## The six publication states

Every profile has exactly one state.

| State | Public? | Who sees it |
|---|---|---|
| Imported, not public | No | Staff only |
| Published, review pending | Yes, with a label | Everyone, but not indexed |
| Published, reviewed | Yes | Everyone, indexed |
| Unpublished | No ("gone", 410) | Staff only |
| Rejected | No ("not found", 404) | Staff only |
| Removed and suppressed | No ("gone", 410) | Staff only |

After an import, the first matching rule sets the state.

| Condition | State |
|---|---|
| The "import everything as not public" setting is on | Imported, not public |
| The record is a probable duplicate | Imported, not public |
| The record is an individual or entrepreneur | Imported, not public |
| The record is an organisation | Published, review pending |

A Published, review pending profile shows the label "Compiled automatically from public sources. Human review pending." on its page, cards and share preview. It returns 200 with a noindex instruction, has no sitemap or structured-data entry, and is left off lists. It cannot be claimed, linked to a sponsored story deal or put on an awards page until it is reviewed.

## Review a record

1. Open the Review queue. Claims and corrections rise to the top. Otherwise organisations come before individuals.
2. Open an item. You see the difference field by field, each fact with its source address, retrieval date, confidence and method, and any flags from the free-text scan.
3. Fix or remove any flagged fact. A fact with low confidence or no source cannot be published. A record that fails the free-text scan stays blocked until it is cleared.
4. If it may be a duplicate, resolve that first in Duplicates.
5. Check the five points: no allegations, litigation or rumours; no opinion or praise; no personal life, family, health or finances; every fact dated and sourced; professional facts only.
6. Approve or reject, with a reason.

The approver cannot be the person who imported or drafted the record. Every approval and rejection is audited.

| Action | Result |
|---|---|
| Approve | The profile becomes Published, reviewed. An individual becomes public. The noindex instruction lifts, the sitemap entry and structured data are added and the cache is cleared in the same step. |
| Reject | The record stays non-public. If it was live, it answers "gone". |

## Duplicates

Matching uses registration number, Wikidata ID, and a normalised name plus organisation plus location. A staff member merges records. Individuals are never merged automatically.

## Sources

The Sources screen is read-only. It lists each source with its profile count, last import date, import errors and removals, and a link to the Profiles list filtered by that source. Approved sources are managed outside this screen.

## Edit a profile

1. Open the profile and choose Edit this profile.
2. Change the fields. If you are resolving an open correction, pick it in "This resolves the open item".
3. Enter the reason or request reference. It is required.
4. Save. If the profile is published, the public page changes at once.

Each edit is stored as a revision with author, time, reason and old and new values, and written to the audit log. Contact details are private by default, and staff switch what is public on request. Changing the address keeps a redirect from the old one. Internal notes are never public.

If the profile is claimed and the edit was made by its assigned user, the edit waits for a Reviewer or Administrator. See [Claims](/staff/claims).

## Who moves a profile between states

| Transition | Who |
|---|---|
| Import into a state | The Administrator who runs the import |
| Pending or not public to Reviewed, or to Rejected | A user with the review capability, not the importer |
| Any public state to Unpublished | A user with the single-unpublish capability, or the bulk capability for a filter |
| Any state to Removed and suppressed | The removal flow, which also writes the suppression list |

Any other change is refused and logged.

## Take profiles down

Unpublishing works immediately, even on pages search engines have indexed.

1. Staff set Unpublished or Removed and suppressed. The profile's search entry is deleted.
2. The site answers "gone" with a noindex instruction, reading the state from the database, not from a cache.
3. The sitemap drops the address.
4. If a CDN is in front of the site, a purge clears the cached page. The edge keeps public pages for at most two minutes.
5. Restoring a reversible unpublish returns the page.

### Bulk unpublish

1. On the Profiles list, filter by source, import batch, type, state, origin or name. A live count shows how many published profiles match.
2. Choose bulk unpublish and enter a reason.
3. Check the confirmation, which shows the count and the first five names.
4. Each profile becomes "gone", leaves the sitemap and search, and gets a purge-log entry. The audit log gets one row per profile and one for the batch.
5. To undo it, open Recent bulk actions and choose Restore this batch.

Bulk unpublish acts only on published profiles.
