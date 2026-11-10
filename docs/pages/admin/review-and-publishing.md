# Review and publishing

How a record gets from the acquisition pipeline to a public page: import, review, approval, and the screens that support it. Nothing about a person is public until a human has approved it; organisations can be live earlier, with a visible label.

## Who does what

| Step | Screen | Capability | Default roles |
|---|---|---|---|
| Run an import | Import | `wiw_run_imports` | Administrator |
| Review and approve | Review queue | `wiw_review_records` | Reviewer, Editor, Administrator |
| Resolve duplicates | Duplicates | `wiw_resolve_duplicates` | Reviewer, Editor, Administrator |
| See where records came from | Sources (read-only) | `wiw_view_sources` | Reviewer, Editor, Administrator |
| Edit text in a record | Profile screen | `edit_wiw_profiles` | Editor, Administrator |

## Import, step by step

The import is the first acquisition task (REQ-237). The website and the acquisition pipeline meet only through the [import contract](/contracts/import-format).

1. The pipeline writes a file to a private staging database that no public page reads (REQ-211).
2. A named Administrator uploads the file in wp-admin, or runs it from the command line.
3. They run the **dry run** first. The file is checked against the contract: required fields, country codes, terms that already exist as taxonomy terms, the suppression list, duplicates and free-text patterns. They see a diff before anything is written.
4. They import. Records are created or updated by stable ID, so importing the same file twice changes nothing.
5. WordPress recomputes the content hash and refuses a record whose hash does not match what was drafted (REQ-221).
6. Each record is tagged with its file, batch and importer, and the import is written to the audit log with counts and the states set (REQ-228).

A source field and an import batch field are stored on every profile (REQ-242). Re-importing never overwrites a field a person has already reviewed (REQ-161).

## What state a record lands in

| Record | State after import | Public? |
|---|---|---|
| Organisation without a duplicate flag | Published, review pending | Yes, with a label, not indexed |
| Individual or entrepreneur | Imported, not public | No |
| Flagged duplicate, or "import all as not public" | Imported, not public | No |

The label on a pending organisation reads "Compiled automatically from public sources. Human review pending." It links to a correct or remove form and is not a trust status (REQ-216). A pending page answers 200 with a noindex header and no sitemap or structured-data entry (REQ-217). A pending profile cannot be linked to an arrangement or appear on an awards page (REQ-226).

The prototype shows this label only as a preview on the review screen. The publish-first states are not fully drawn in the wireframe.

## Review queue, step by step

1. Open the Review queue. Items are new profiles, or edits to published ones. The default order is pilot first, then organisations, then individuals (Q31); claims and corrections rise to the top (REQ-222).
2. Open an item. You see a field-level diff, each fact with its source URL, retrieval date, confidence and method, and flags from the free-text scan.
3. Fix or remove any fact the queue flags. A fact with low confidence or no source cannot be published (REQ-143). A record that fails the free-text scan stays blocked until it is cleared (REQ-141).
4. Check for a possible duplicate. Resolve it in Duplicates first.
5. Approve or reject with a reason.

The checklist in the prototype is five points: no allegations, litigation or rumours; no opinion or praise; no personal life, family, health or finances; every fact dated and sourced; professional facts only.

## Safeguards

- **The approver is not the importer or the drafter.** The prototype blocks it on screen and tells the user another reviewer must approve (REQ-148).
- **Sources are read-only here.** Approved sources are managed in the acquisition pipeline, not in this screen (D-038).
- **Every approval and rejection is audited**, and review throughput, queue age and rejection reasons are tracked (REQ-149).

## What approval and rejection do to the public page

| Action | Result |
|---|---|
| Approve | The profile becomes Published, reviewed with the date. An individual becomes public. Noindex is lifted, the sitemap entry and structured data are added, and the cache is purged in the same step (REQ-222, REQ-217). |
| Reject | The record is not public. If it was live, it answers 410 (REQ-222, REQ-209). |

Listing pages such as the home page, discover and industry pages show reviewed profiles only, so a pending name cannot be indexed through a list (REQ-218).

## Duplicates

Matching uses registration number, Wikidata ID, and a normalised name plus organisation plus location. A staff member merges. **Individuals are never merged automatically** (REQ-145, REQ-146).

## Sources screen

A read-only table: one row per source, with its profile count, last import date, import errors and removals, and a link to the Profiles list filtered by that source (REQ-243). It has no add, edit, approve, reject or publish. In the prototype the import error figures are sample numbers.

## WordPress native or custom

| Part | Status |
|---|---|
| Review queue, diff, state machine, import with dry run, duplicates | Custom plugin |
| Profiles list with filters and groups by source and batch | Customised native list table |
| Revisions of every edit | Native (REQ-125) |
