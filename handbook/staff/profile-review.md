# Profile review and publication states

A profile moves from an imported or hand-added record to a public page through review. Nothing about an individual is public until a person has approved it. This page covers the review queue, duplicates, the six publication states, editing, and how to take profiles down.

## Who does what

| Step | Screen | Capability | Default roles |
|---|---|---|---|
| Run an import | Imports > Import | `wiw_run_imports` | Administrator |
| Add a profile by hand | Directory > Add profile | `wiw_add_profile` | Editor, Administrator |
| Review and approve | Review > Review queue | `wiw_review_records` | Reviewer, Editor, Administrator |
| Resolve duplicates | Directory > Duplicates | `wiw_resolve_duplicates` | Reviewer, Editor, Administrator |
| See where records came from | Imports > Sources (read-only) | `wiw_view_sources` | Reviewer, Editor, Administrator |
| Edit a profile | Directory > Edit profile | `edit_wiw_profiles` | Editor, Administrator |
| Unpublish one profile | Directory > Profiles | `wiw_unpublish_profiles` | Editor, Administrator |
| Bulk unpublish and restore | Directory > Profiles | `wiw_bulk_unpublish` | Reviewer, Administrator |

## Where profiles come from

| Route | Page |
|---|---|
| An import bundle from the acquisition team | [Import data](/staff/importing) |
| Added by hand by an Editor or Administrator | [Add a profile by hand](/staff/add-profile) |
| A listing agreed through Get listed, by invitation or consent link | [Enquiries](/staff/enquiries) |

Whatever the route, an individual's profile is not public until a reviewer approves it, and the reviewer cannot be the person who imported or added it. A re-import never overwrites a field a person has already reviewed; a changed source is flagged for review instead.

## The six publication states

Every profile has exactly one state. The state is shown in the State column of **Directory > Profiles**.

| State | Public? | Who sees it | In plain words |
|---|---|---|---|
| Imported, not public | No | Staff only | Waiting for review. Every new individual starts here. |
| Published, review pending | Yes, with a label | Everyone, but not indexed | An organisation that is visible with the "Human review pending" label while it waits for review |
| Published, reviewed | Yes | Everyone, indexed | Approved by a person. This is the normal live state. |
| Unpublished | No ("gone", 410) | Staff only | Taken down for now. It can be restored. |
| Rejected | No ("not found", 404) | Staff only | A reviewer decided it should not be published |
| Removed and suppressed | No ("gone", 410) | Staff only | Removed at the person's request and on the suppression list, so no import can bring it back |

After an import, the first matching rule sets the state.

| Condition | State |
|---|---|
| The "import everything as not public" setting is on | Imported, not public |
| The record is a probable duplicate | Imported, not public |
| The record is an individual or entrepreneur | Imported, not public |
| The record is an organisation | Published, review pending |

A Published, review pending profile shows the label "Compiled automatically from public sources. Human review pending." on its page, cards and share preview. It returns 200 with a noindex instruction, has no sitemap or structured-data entry, and is left off lists. It cannot be claimed, linked to a sponsored story deal or put on an awards page until it is reviewed.

## Review a record

1. Open **Review > Review queue**. Claims and corrections rise to the top. Otherwise organisations come before individuals.
2. Open an item. You see the difference field by field, each fact with its source address, retrieval date, confidence and method, and any flags from the free-text scan. Read the Bio, Achievement, Influence and Legacy sections against the sources, especially text marked with the method "ai". Check that any photo has an open licence and a credit ([Photos and credits](/staff/photos-and-credits)).
3. Fix or remove any flagged fact. A fact with low confidence or no source cannot be published. A record that fails the **Free-text scan** stays blocked until someone fills in **Why the flags do not apply** and presses **Clear the block**.
4. If it may be a duplicate, resolve that first in **Directory > Duplicates**.
5. Tick the **Checklist**: no allegations, litigation or rumours; no opinion or praise; no personal life, family, health or finances; every fact is dated and sourced; professional facts only.
6. Press **Approve**, or **Reject** with a reason.

If you imported or added the record yourself, the screen says "You imported this record, so another reviewer must approve it."

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

1. Open **Directory > Profiles**, find the profile and open **Edit profile**.
2. Change the fields under **Edit fields**. If you are resolving an open correction, pick it in "This resolves the open item".
3. Fill in **Reason or request reference (required)**.
4. Press **Save changes**. If the profile is published, the public page changes at once.

The four narrative sections and the photo have their own pages: [Profile sections](/staff/profile-sections) and [Photos and credits](/staff/photos-and-credits).

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
