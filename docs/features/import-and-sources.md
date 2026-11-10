# Import and sources

Most profile data is gathered by a separate acquisition pipeline and arrives on the site as an import. The site checks every record, sets its state, and keeps a source behind every fact.

## What it does

- Loads an export bundle (a manifest and `records.jsonl`) into WordPress, by file upload in wp-admin or by command line.
- Runs a dry run first that validates each record and shows a diff without writing anything.
- Creates or updates profiles by stable ID, so importing the same file twice changes nothing.
- Sets each profile's publication state, tags it with source and batch, and records provenance for every field.
- Shows read-only Sources and Duplicates screens, and lets staff undo a whole batch.

## Who uses it and how

- As an Administrator, I can upload a bundle, run the dry run, read the diff and then import.
- As a Reviewer, I can see where each fact came from, with its source URL, retrieval date, confidence and method.
- As a Reviewer, I can resolve a probable duplicate by merging it by hand.
- As the acquisition team, I can rely on the import result to see counts by result code without any names.

## The pieces

### Theme

- Source link and retrieval date beside key facts on the profile page.
- The pending-review label (see Directory and profiles).

### Plugin

- `Import` module: bundle checker, dry-run engine, chunked applier that resumes after a time-out, result writer (REQ-237).
- Checks at import: contract version, manifest counts and file hash, schema and allowlists, provenance present and confidence not low, verification report passed, content hash recomputed and equal, not on the live suppression list, no images until allowed (REQ-212, 221).
- Result codes per record: created, unchanged, updated, flagged change, or a refusal code.
- State assignment on import (see Business rules).
- `Review` module: review queue with field-level diff and duplicates matcher.
- Batch undo, source and batch fields on every profile, bulk actions on the filtered set.
- Capability `wiw_run_imports` (Administrator only by default).

### Pages and screens

- Staff: Import (upload, dry run, diff, result), Review queue, Duplicates, Sources (read-only), Profiles list with source and batch filters.
- The acquisition side is out of this site; it meets the website only through the import contract.

### Third-party plugins

- A background job runner (Action Scheduler is the chosen library) for chunked imports and scheduled jobs.

### Data

- Import batches and results tables, field provenance table, version snapshots, duplicates table, a read-only snapshot of source names.
- Import claims, passages and verification reports: admin-only, never public, subject to retention.

## Business rules

- WordPress, not the pipeline, sets state. The pipeline has no credential that can publish and the export carries no state or visibility field.
- State on import, first match wins: the "import everything as not public" setting is on, or the record is a probable duplicate, or the record is an individual or entrepreneur, then Imported (not public); otherwise an organisation becomes Published (review pending) with the label and noindex (REQ-210, 216).
- A named staff member starts every import. The approver of a record is not the person who imported it (REQ-148).
- A record's content hash must match what was drafted; a mismatch is refused. Re-importing never overwrites a field a person has reviewed; the change is flagged on the review queue (REQ-161, 221).
- Every field has a source URL, retrieval date, confidence (high, medium, low) and method. Low-confidence or unsourced fields cannot be published (REQ-142, 143).
- Free-text fields are scanned for phones, emails, addresses, ages and sensitive terms; a failing record is blocked until cleared (REQ-141).
- Matching uses registration number, Wikidata ID, and a normalised name plus organisation plus location. Individuals are never merged automatically (REQ-145, 146).
- The Sources screen has no add, edit, approve, reject or publish control. Source approval happens in the pipeline (REQ-243).
- Each import is audited with person, batch, file hash, record counts and states set (REQ-228).
- Suppressed names are skipped on import; the live suppression list is authoritative (REQ-207).

## Acceptance criteria

- [ ] A bundle with a bad manifest hash or unknown contract version is refused whole, with a reason.
- [ ] The dry run writes nothing and shows, per record, the result code and a field-level diff.
- [ ] Importing the same bundle twice leaves all records unchanged (second run all `UNCHANGED`).
- [ ] A record with a forbidden field, a low-confidence or unsourced field, a failed verification report or a hash mismatch is refused and the rest still import.
- [ ] A suppressed name is refused with a suppressed result and no profile is created.
- [ ] An organisation lands as Published (review pending), with the label and a noindex header; an individual lands as Imported (not public).
- [ ] With "import everything as not public" on, no record becomes public.
- [ ] Re-importing a changed record that has already been reviewed does not change the reviewed fields and raises a flagged change on the review queue.
- [ ] A time-out part-way through resumes from the last chunk without duplicating records.
- [ ] A batch can be undone for records not yet reviewed.
- [ ] The Profiles list filters and groups by source, batch, type and state, and a bulk action acts on the filtered set.
- [ ] The Sources screen shows profile count, last import, errors and removals per source, with a link to the filtered list, and has no edit controls.
- [ ] The review queue shows each claim with its source URL and retrieval date, and the approver cannot be the importer.
- [ ] Every import writes an audit row with person, batch, file hash, counts and states.
- [ ] 1,000 records import within the host's limits.

## Depends on

- Directory and profiles (state machine, read models).
- Corrections, removals and reports (suppression list).
- Roles, audit and admin.
- Privacy and data rights (sensitive-data rules).

## Out of scope or later

- Gathering, drafting and verifying data (acquisition pipeline, separate).
- Continuous AI monitoring and AI takedown (a future item).
- A REST import path (it would need a service account, a fourth role).
- Importing images before the contract allows them.
- A source-registry editor in the website admin.

## Requirement ids covered

REQ-084, 092, 123, 132, 134, 135, 141, 142, 143, 145, 146, 148, 149, 160, 161, 171, 206, 211, 212, 214, 221, 222, 228, 237, 241, 242, 243, 255. The directory holds reviewed profiles by 31 Jan 2027 (REQ-104).
