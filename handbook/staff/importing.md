# Import data from the acquisition team

The acquisition team researches people and organisations and sends them to the website as **bundles**: zip files in an agreed format. An Administrator uploads each bundle on the **Import** screen, reads the **dry run**, then confirms. Nothing is written until you confirm, and a whole batch can be undone.

## Who can do it

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Upload, dry run, confirm and undo | Imports > Import | `wiw_run_imports` | Administrator |
| See where records came from | Imports > Sources | `wiw_view_sources` | Reviewer, Editor, Administrator |
| Review imported records | Review > Review queue | `wiw_review_records` | Reviewer, Editor, Administrator |

## What a bundle is

A bundle is one `.zip` file that holds:

| Inside the zip | What it is |
|---|---|
| `manifest.json` | The label: format version, how many records, a fingerprint of the records |
| `records.jsonl` | The records, one per line, each fact with its source |
| `images/` | Openly licensed photos and logos, if any |

You never open or change the zip. If anything in it has been altered, the import refuses it. The format is agreed between the two teams and is version 1.1; the site also accepts the older 1.0.

## Before you import

1. Take a backup: a database backup from cPanel is enough. See [Backups](/operations/backups).
2. One bundle can be up to 512 MB. The Import screen sends large files in small pieces, so the host's upload limit does not matter. If an upload still fails, use **Or import from the server** (below).
3. Decide on the switch **Import everything as not public** on the Import screen, below the upload boxes. Press **Turn on** or **Turn off**:
   - **On**: every imported profile arrives as Imported, not public, even organisations. Use this for a first large import, so nothing appears until the team has reviewed it.
   - **Off**: individuals and entrepreneurs still arrive not public, and organisations arrive as Published, review pending, with the "Human review pending" label.

## Import one bundle

1. Open **Imports > Import**.
2. Under **Upload a bundle**, choose the zip and press **Upload and run the dry run**.
3. Wait. A bar shows "Uploading", then "Checking records: N of M". The message "Dry run done. Nothing has been written yet. Read the result below, then confirm." appears.
4. Read the dry run:
   - The counts at the top show how many records would be created, updated, left unchanged, flagged or refused. Click a count to see only those records.
   - The table shows each record's result, any flags, and the changes field by field.
5. **Map new terms.** If records name a type, industry, sector or place the site does not know, the counts show **New list values needed (N)** instead of a code, each such record reads "New list value needed", and a heading says how many values are not in the site's lists yet. This is not an error in the bundle. Press **Add all as new list entries** to add them all at once, or use each "Map ... to" box: choose the existing entry it means and press **Save mapping** (choose **Skip this value** in the list to skip it). The dry run then runs again; read it again.
6. If the result looks right, press **Confirm and import**.
7. The message "Import started. It runs in the background and resumes where it stopped." appears, with "Importing records: N of M". It runs in small steps. You can leave the page, but keeping it open makes it faster. If the connection drops, the screen says so: press **Continue** and it carries on where it stopped, never importing a record twice.
8. When the status says it is applied, the records are in the Review queue.

### Or import from the server

For very large bundles, or if the upload fails, put the zip on the server instead. The Import screen's **Or import from the server** box names the folder (inside the site's uploads folder, called `wiw-import`). Upload the zip into that folder with cPanel File Manager, reload the Import screen, choose it under **Bundle on the server** and press **Import this file**. The dry run then starts as above. Each file is removed from the folder once it is read.

## What the results mean

| Result | Meaning |
|---|---|
| `CREATED` | A new profile was made |
| `UPDATED` | An existing, not yet reviewed profile was updated |
| `UNCHANGED` | Nothing changed since the last import. Importing the same bundle twice is safe. |
| `FLAGGED_CHANGE` | The source changed a profile someone already reviewed. The reviewed text is **not** overwritten; a reviewer sees the change in the Review queue. |
| `SUPPRESSED` | The person or organisation asked to be removed. No profile is made. |
| `IMAGE_NOT_ALLOWED` | A photo's licence is not an open one. The photo is skipped and the rest of the record imports. |
| `UNSOURCED_FIELD`, `LOW_CONFIDENCE` | A fact without a source, or with low confidence, is refused |
| New list values needed | The records name a list value (type, industry, sector, place) the site does not have yet. Add the values or map them (step 5), and the dry run runs again |
| `SCHEMA`, `HASH_MISMATCH`, `VERIFICATION_FAILED`, `FORBIDDEN_FIELD` | The record is malformed, altered or carries a field the site does not accept. It is refused; the rest continue. |
| `CONTRACT_VERSION`, `MANIFEST_MISMATCH` | The whole bundle is refused. Ask the acquisition team for a new one. |

Send the acquisition team the batch number and the counts if anything was refused. The result never contains names, so it is safe to share.

## Undo a batch

1. Open **Imports > Import** and find the batch under **History**.
2. Press **Undo this batch**.
3. The message "Batch undone. Reviewed profiles were kept." appears. For a large batch the message is "Undo started. It carries on in steps below." and a bar shows "Undoing the batch..." until it is done.

Undo takes down the profiles that batch created and nobody has reviewed: they move to **Rejected** and are no longer public anywhere. Profiles the batch only updated go back to their earlier text. Profiles someone has already reviewed are kept, and listed. The images the batch added are removed, unless a later batch uses them too. The undo is written to the audit log.

### Import an undone batch again

If you undid a batch by mistake, or the acquisition team fixed it and sent the same batch again, just import it again as above. The site recognises the batch, takes back the profiles the undo set aside (they return to Imported, not public) and fills them in again, so no duplicates are made. The dry run counts them as `CREATED`. This is written to the audit log as well.

## The full import (about 13,000 records)

The acquisition team's full set is about 13,400 records. It comes as several zip files of about 1,000 records each.

1. Take a full cPanel backup first.
2. Turn **Import everything as not public** on (press **Turn on**).
3. Import the bundles one at a time, in the order the acquisition team gives. For each: upload, read the dry run, confirm, and wait for it to finish before uploading the next.
4. It runs in small steps; keeping the tab open makes it faster. How long each bundle takes depends on the host and on how many photos it carries. If a bundle stops, open it from **History** and press **Continue**.
5. When every bundle is in, check **Imports > Sources** for the totals and errors.
6. Leave the switch on until the team has worked through the Review queue, then turn it off for later imports.

Do the full import outside busy hours, and tell the team before you start, because the Review queue will fill up.

## After importing

Reviewers work through the records in the [Review queue](/staff/profile-review#review-a-record). Nothing about an individual is public until a reviewer approves it.
