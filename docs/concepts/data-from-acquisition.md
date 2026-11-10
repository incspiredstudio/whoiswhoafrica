# Data from acquisition

Most profile data is gathered by a separate pipeline and arrives on the site as an import. This page explains how the two sides meet, what travels each way, and the checks that stop bad data from going public.

Requirements: REQ-142, 143, 145, 146, 161, 207, 211, 212, 214, 221, 237, 242, 243. Decisions: D-033, D-038, D-042. Source of detail: the [import contract](/contracts/import-format) and architecture section 3.4.

## Two sides, one interface

| Side | Does | Never does |
|---|---|---|
| Acquisition pipeline (`acquisition/`) | Gathers data from approved sources, drafts, runs basic verification, stores records in a private staging database, exports a bundle | Publishes, writes to WordPress, holds a website credential |
| Website (WordPress) | Imports a bundle after a named person starts it, sets each record's state, reviews, publishes | Reads `acquisition/data/` |

**The import contract is the only interface.** Neither side reads or writes the other's folders or data. Only the shared-docs owner changes the contract, and the other side confirms.

## What travels each way

| Direction | What |
|---|---|
| Acquisition to website | An export bundle: a manifest and `records.jsonl` (one record per line) |
| Website to acquisition | A suppression snapshot, before each run |
| Website to acquisition | An import result after each import |

The import result lists counts by result code and a code per record ID. It contains no field values and no names.

## What a record carries

- A stable `record_id`, forever the same for one entity, so re-exports update instead of duplicating (REQ-145).
- An entity type: organisation, individual or entrepreneur.
- **Only allowlisted fields** for that type. Anything else is refused. Individuals have no field for addresses, phone numbers, age, family, health, politics and the rest of the list in [Privacy](/concepts/privacy-and-data-rights).
- **Per-field provenance:** source, exact page URL, retrieval date, confidence, method and a hash of the stored page (REQ-142). A field with low confidence or no source cannot be published (REQ-143).
- Claims and the passages that support them, for the reviewer. They are admin-only and never public.
- A verification report and a **content hash**.
- No state, publish or visibility field. The pipeline cannot choose a state.

## The checks

### In the pipeline (basic verification)

Exactly seven checks (REQ-214): the source is on the approved list, the source URL succeeded, every AI-drafted claim has an exact quote found in the stored source text, only allowlisted fields remain, a sensitive-data scan passes, the suppression list is clear, and duplicates are flagged or clear. A record that fails is not exported.

### In WordPress, at import

| Check | Refusal code |
|---|---|
| Known contract version | `CONTRACT_VERSION` |
| Manifest counts and file hash match | `MANIFEST_MISMATCH` |
| Schema and allowlists | `SCHEMA`, `FORBIDDEN_FIELD` |
| Provenance present, confidence not low | `UNSOURCED_FIELD`, `LOW_CONFIDENCE` |
| Verification report passed | `VERIFICATION_FAILED` |
| Content hash recomputed and equal | `HASH_MISMATCH` |
| Not on the **live** suppression list | `SUPPRESSED` |
| No images (until allowed) | `IMAGE_NOT_ALLOWED` |

The content hash proves that what is imported is what was drafted. It uses canonical JSON (RFC 8785) over the fields marked "in hash", so both sides get the same bytes (REQ-221).

## The import, step by step

1. A named Administrator uploads the bundle in wp-admin, or runs it from the command line. The pipeline has no credential.
2. WordPress checks the manifest.
3. **Dry run:** every record is validated and a diff is shown. Nothing is written (REQ-237).
4. After confirmation, records are applied in small chunks, so a time-out on shared hosting resumes where it stopped.
5. Each record gets a result code:

| Situation | Action | Code |
|---|---|---|
| New `record_id` | Create the profile and set its state | `CREATED` |
| Same ID, same hash | Nothing | `UNCHANGED` |
| Hash differs, not yet reviewed | Update as a new version | `UPDATED` |
| Hash differs, already reviewed | **Do not overwrite**; flag for a reviewer | `FLAGGED_CHANGE` |

6. A bad record is refused and the rest continue. A bad manifest refuses the whole bundle.

A reviewed field is never overwritten by a re-import (REQ-161). The change is flagged on the review queue instead.

## Batches and sources

Every record belongs to an import **batch**, and each field to a **source**. Staff can filter and group the profile list by source and batch, and bulk unpublish by the same filters (REQ-193, 242). **Undo a batch** removes records from it that are still unreviewed and reverts unreviewed updates. Reviewed records are kept.

The Sources screen is **read-only**: one row per source with profile count, last import, errors and removals. Approving sources happens in the pipeline, not on the site (D-038, D-042, REQ-243).

## State on import

WordPress, not the pipeline, sets the state. Organisations can go live straight away with the pending label and noindex, but individuals and entrepreneurs wait for a person ([Publication states](/concepts/publication-states)).

## Suppression travels the other way

The website owns the suppression list. The pipeline gets a snapshot before each run and drops matches early. WordPress checks again at import against the live list, which is authoritative. Reason text never crosses the interface (REQ-207).

## Open points

The contract is a draft (v1.0-draft). Open items include where the source list lives long term, how bundles move between machines, images, and what undo does once some of a batch is reviewed. They are listed as U1 to U14 in the contract.
