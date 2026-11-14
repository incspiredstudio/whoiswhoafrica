# Import format: the contract between acquisition and the website

Status: **v1.1, 2026-11-14.** The importer accepts `"1.0"` and `"1.1"`; the pipeline exports `"1.1"` (`acquisition/pipeline/export_bundle.py`). v1.1 adds the narrative sections, person `lifespan` and `field`, open-licence images and the zip form, and keeps the hash rule. REQs: REQ-142, 143, 145, 146, 162, 203, 207, 210–212, 214, 221, 237. Decision: D-033 as amended.

**This is the only interface between acquisition and the website.** The pipeline never writes to WordPress, its database or its files. The website never reads `acquisition/data/`. Each side works only from what this document defines. Words such as MUST and MUST NOT are requirements on the side named.

## 1. Overview

| Direction | What | Section |
|---|---|---|
| Acquisition → website | An export bundle of records | 2–10 |
| Website → acquisition | A suppression snapshot, used before each run | 11 |
| Website → acquisition | An import result, one per import | 12 |

**The pipeline does not choose a record's state and cannot publish.** A named staff member starts each import in WordPress, and WordPress sets the state (section 9).

**Export bundles with real records contain personal data.** They belong in `acquisition/data/exports/`, which is git-ignored, never in `acquisition/exports/`, which holds only schemas and samples with no personal data.

## 2. Export bundle

```
export-<run_id>-<batch_id>/
  manifest.json
  records.jsonl     one record per line, UTF-8, Unicode NFC
  images/           only when a record carries images (section 10)
```

JSON Lines is the normative form, because per-field provenance is nested. A REST payload, if chosen, carries the same record object. A CSV form would be a new contract version (see U2).

**Two equal forms (v1.1):** a bundle is either that directory or a `.zip` of it, named `export-<run_id>-<batch_id>.zip`. In the zip, `manifest.json`, `records.jsonl` and `images/` sit at the root of the archive or inside one top-level folder named like the directory; nothing else is in it. Paths use `/`, are relative, and never contain `..`, a drive letter or a leading `/`; the archive holds no symlinks. The website accepts a zip upload on the Import screen, extracts it to a private folder with a random name, checks the paths and a size cap before extracting, and deletes the folder after the import. A large run is split into several bundles (for example 1,000 records each), each with its own `batch_id`.

### 2.1 `manifest.json`

| Field | Type | Rule |
|---|---|---|
| `contract_version` | string | `"1.1"` for this version. The importer also accepts `"1.0"`. |
| `run_id` | string | The pipeline run |
| `batch_id` | string | Import batch, unique across runs (REQ-237) |
| `generated_at` | string | ISO 8601 UTC |
| `pipeline_version` | string | Code version |
| `source_list_version` | string | The approved source list the run used |
| `sources` | array | One entry per source used: `source_id` and display `name`. The website's read-only Sources screen shows these names (Q74, default accepted; the acquisition side confirms) |
| `suppression_snapshot_id` | string | The snapshot the run used (section 11) |
| `record_count` | integer | Lines in `records.jsonl` |
| `counts` | object | `{"organisation": n, "individual": n, "entrepreneur": n}` |
| `records_sha256` | string | `sha256:` + hex digest of the bytes of `records.jsonl` |

The manifest MUST NOT name any person. Records that failed verification are not exported, and the manifest gives no details of them.

## 3. Record

| Field | Type | Req. | In hash | Rule |
|---|---|---|---|---|
| `contract_version` | string | Yes | No | Same as the manifest |
| `record_id` | string | Yes | Yes | Stable forever for one entity (REQ-145), for example `rec_org_01H…`. Reused on re-export. |
| `record_version` | integer | Yes | No | Goes up by 1 when the content hash changes |
| `run_id`, `batch_id` | string | Yes | No | As in the manifest |
| `entity_type` | enum | Yes | Yes | `organisation`, `individual`, `entrepreneur` |
| `tier` | enum | Yes | Yes | `T1`, `T2`, `T3` |
| `country` | string | Yes | Yes | ISO 3166-1 alpha-2, or `PAN` for pan-African bodies |
| `external_ids` | object | Yes | Yes | Any of `wikidata_qid`, `rc_number`, `licence_number`, `lei`, `official_domain`. May be empty for people. |
| `inclusion` | object | Yes | Yes | `criterion_code` (O1–O7, E1–E2, I1–I6; from v1.1 also `HIST`, a historical figure outside the plan criteria, and `LEAD`, a living person whose E or I criterion is not yet shown), `evidence_url`. A reviewer confirms `HIST` and `LEAD` records before they go public; rule 3 of section 9 already keeps every person not public. |
| `fields` | object | Yes | Yes | Section 4, each with provenance (section 5) |
| `claims` | array | Yes | Yes | Section 6; empty for T1 |
| `passages` | array | Yes | Yes | Section 6; empty for T1 |
| `images` | array | Yes | Yes | Section 10; may be empty. Open-licence images only (v1.1). |
| `ai` | object | Yes | Yes | `ai_assisted` (boolean), `model`, `prompt_version`; the last two null if not assisted (REQ-168). Internal; never public. |
| `flags` | object | Yes | No | `probable_duplicate` (boolean), `duplicate_of` (array of `record_id`), `flag_reasons` (array) |
| `verification` | object | Yes | No | Section 7 |
| `content_hash` | string | Yes | — | Section 8 |

**A record MUST NOT contain a state, publish, visibility or status field.** Unknown fields anywhere are refused (`SCHEMA`).

## 4. Fields by entity type

These are allowlists. The pipeline MUST NOT export any other field, and WordPress MUST refuse one.

### 4.1 Organisation (REQ-004, 005)

| Key | Value | Required | Note |
|---|---|---|---|
| `name` | string | Yes | |
| `org_type` | string | Yes | Controlled list (REQ-009; U5) |
| `industry` | string | No | Controlled list |
| `sector` | string | No | Controlled list |
| `licence_category` | string | No | T1 |
| `regulator` | string | No | T1 |
| `mandate` | string | No | Institutions (REQ-005) |
| `hq_city` | string | No | Controlled list |
| `website` | URL | No | |
| `year_founded` | integer | No | |
| `summary` | string | T2 | AI-drafted; each sentence backed by a claim. Whether it shows before review is Q46. |
| `bio`, `achievement`, `influence`, `legacy` | string | No | Narrative sections, v1.1 (section 4.3) |

**No organisation field may name a person** (REQ-214). That means no leadership, founder or board field in this version, even though REQ-004 lists leadership for customer profiles. The narrative sections follow the same rule for organisations.

The same allowlist applies to an institution, which the website stores as an organisation profile.

### 4.2 Individual and entrepreneur (REQ-139)

| Key | Value | Required | Note |
|---|---|---|---|
| `name` | string | Yes | The only required person field from v1.1 |
| `current_title` | string | No | Required in v1.0; optional from v1.1 |
| `current_organisation` | string | No | Required in v1.0; optional from v1.1 |
| `current_organisation_ref` | string | No | `record_id` of the organisation, if exported |
| `industry` | string | No | Controlled list |
| `public_record_roles` | array of string | No | Board or office roles; a founder role for E1 |
| `published_awards` | array of string | No | |
| `official_website` | URL | No | Official or organisational only |
| `lifespan` | string | No | v1.1. Only for people who have died, for example `1924–2013` (years joined by an en dash). Never a full date of birth. |
| `field` | string | No | v1.1. The person's field or profession, for example `Music` |
| `bio`, `achievement`, `influence`, `legacy` | string | No | Narrative sections, v1.1 (section 4.3) |

The pipeline's kinds map to entity types like this: `person` → `individual`; `deceased` → `individual` with `lifespan`; `entrepreneur` → `entrepreneur`; `organisation` → `organisation`.

**Never exported (REQ-140, 067):** home address, phone, personal email, family, date of birth or age, health, religion, politics including party affiliation, ethnicity, finances, allegations, personal social handles.

### 4.3 Narrative sections (v1.1, every entity type)

| Key | Shown as | Rule |
|---|---|---|
| `bio` | Bio | Who or what this is |
| `achievement` | Achievement | What they did or built |
| `influence` | Influence | Whom or what it affected |
| `legacy` | Legacy | What lasts |

- Each is optional, a plain-text string of at most 6,000 characters, with no HTML or Markdown. Paragraphs are separated by one blank line (`\n\n`).
- Each has the usual `{value, provenance[]}` shape (section 5). When the text was AI-drafted, the provenance `method` is `ai`, `source_url` is the page the drafted facts came from, and `confidence` is that of those facts. For a narrative section reworded from stored facts, which has no claims, `passage_id` may be omitted.
- `ai.ai_assisted` is `true` for a record with any AI-drafted section. The text is reviewed before a person's profile goes public (section 9, rule 3).
- The REQ-140 list above applies to the text as well as to the fields: the sensitive scan (section 7) runs over it.

## 5. Per-field provenance

Each entry in `fields` has this shape (REQ-142):

```json
{ "value": "…", "provenance": [ { … } ] }
```

| Provenance key | Type | Req. | Rule |
|---|---|---|---|
| `source_id` | string | Yes | From the approved source list |
| `source_url` | URL | Yes | The exact page |
| `retrieved_at` | string | Yes | ISO 8601 UTC. Retrieval date per REQ-142. |
| `confidence` | enum | Yes | `high`, `medium`, `low` |
| `method` | enum | Yes | `api`, `import`, `manual`, `ai` |
| `source_hash` | string | Yes | `sha256:` of the stored page |
| `passage_id` | string | No | Required when `method` is `ai`, except for a narrative section reworded from stored facts (section 4.3) |

A field MUST have at least one provenance entry. A field whose confidence is `low` or that has no source cannot be published (REQ-143): the pipeline should not export it, and WordPress refuses the record if it does (`LOW_CONFIDENCE`, `UNSOURCED_FIELD`).

## 6. Claims and passages

These let the reviewer see each claim beside its source (REQ-168). They are admin-only and never public.

| `claims[]` key | Rule |
|---|---|
| `claim_id` | Unique within the record |
| `field` | The key in `fields` it supports |
| `text` | The claim |
| `passage_id` | Must exist in `passages` |
| `quote` | Must be found exactly in that passage's text (REQ-166). Claims that fail are dropped before export, and only counted (section 7). |

| `passages[]` key | Rule |
|---|---|
| `passage_id` | Unique within the record |
| `source_id`, `source_url`, `retrieved_at`, `source_hash` | As in section 5 |
| `text` | Only the passage needed for the claim, never the whole page |

## 7. Verification report

Exactly the seven checks of REQ-214.

| `verification` key | Value |
|---|---|
| `run_id` | The run |
| `verified_at` | ISO 8601 UTC |
| `checks.approved_source` | `pass` required |
| `checks.source_url_ok` | `pass` required |
| `checks.exact_quotes` | `pass` required, after unsupported claims are dropped |
| `checks.allowlisted_fields` | `pass` required, after other fields are dropped |
| `checks.sensitive_scan` | `pass` required |
| `checks.suppression` | `pass` required |
| `checks.duplicates` | `clear` or `flagged` |
| `claims_kept`, `claims_dropped` | Integers |
| `fields_dropped` | Array of field names, never their values |

A record with anything other than the required results MUST NOT be exported, and WordPress refuses it (`VERIFICATION_FAILED`). The report is shown to the reviewer and is never public.

## 8. Content hash

The hash proves that what is imported is what was drafted (REQ-221).

1. Take only the fields marked "In hash" in section 3.
2. Normalise all strings to Unicode NFC.
3. Serialise with the JSON Canonicalization Scheme (RFC 8785): sorted keys, no whitespace.
4. `content_hash` = `sha256:` + lowercase hex SHA-256 of the UTF-8 bytes.

WordPress MUST recompute it and refuse a mismatch (`HASH_MISMATCH`). It stores the hash as received. Later human edits are recorded in version history (REQ-125); they do not change the stored hash.

## 9. State on import

**WordPress decides, in this order. First match wins.** These rules are D-033 as amended, and you may change them; they are settings, not contract fields, so changing them needs no new contract version.

| # | Condition | State (REQ-210) | Public? |
|---|---|---|---|
| 1 | The import-all-as-not-public setting is on | Imported – not public | No |
| 2 | `flags.probable_duplicate` is true | Imported – not public | No |
| 3 | `entity_type` is `individual` or `entrepreneur` | Imported – not public | No |
| 4 | `entity_type` is `organisation` | Published – review pending: label "Compiled automatically from public sources. Human review pending.", noindex | Yes |

After a human approves, the state is Published – reviewed, shown as "Reviewed on <date>". The safeguards in `acquisition/docs/acquisition-plan.md` section 5.4 must pass before rule 1 is turned off.

## 10. Images

**From v1.1, open-licence images are allowed** (REQ-162, 203, D-024). In a v1.0 bundle `images` MUST still be empty.

| `images[]` key | Rule |
|---|---|
| `image_id` | Unique within the record |
| `file` | Path in the bundle, `images/<sha256>.<ext>`, where `<ext>` is `jpg`, `png` or `webp` |
| `sha256` | Lowercase hex SHA-256 of the file bytes (no prefix); WordPress recomputes it |
| `mime_type` | `image/jpeg`, `image/png` or `image/webp`, from the file's bytes, not its name |
| `width`, `height` | Integers, in pixels, from the file |
| `kind` | `photo` or `logo`. At most one of each per record. |
| `source_url` | The page it came from, for example the Wikimedia Commons file page |
| `retrieved_at` | ISO 8601 UTC |
| `creator` | As the source gives it; may be empty only for `CC0-1.0` and `PDM-1.0` |
| `licence` | One identifier from the allowlist below |
| `licence_url` | The licence text, for example `https://creativecommons.org/licenses/by-sa/4.0/` |
| `attribution_text` | Exactly what the page must show next to the image. If the file was changed (for example the background removed), it says so. |

**Licence allowlist:** `CC0-1.0`, `PDM-1.0` (public domain), `CC-BY-2.0`, `CC-BY-2.5`, `CC-BY-3.0`, `CC-BY-4.0`, `CC-BY-SA-2.0`, `CC-BY-SA-2.5`, `CC-BY-SA-3.0`, `CC-BY-SA-4.0`. Ported licences (for example CC BY-SA 3.0 DE), GFDL, "attribution only" and any other licence are not on the list; the pipeline should not export such images.

**Limits:** at most 5 MB per file; JPEG, PNG or WebP only (no SVG, GIF or ICO).

**On a failed image check** (licence not on the list, wrong type, too large, or a sha256, size or file mismatch), WordPress refuses **that image** with `IMAGE_NOT_ALLOWED` in the import result and imports the rest of the record without it. The record's content hash still covers the `images` array as exported.

WordPress stores `licence`, `licence_url`, `creator`, `attribution_text` and `source_url` with the media item and shows the credit wherever the image is shown. Undoing a batch removes the media items it created.

## 11. Suppression

**The website owns the suppression list (REQ-207).** The pipeline only receives a snapshot.

**Snapshot, website → acquisition, before each run.** It is personal data, so it is stored in `acquisition/data/` only.

| Field | Rule |
|---|---|
| `snapshot_id`, `generated_at` | Identify the snapshot |
| `entries[].suppression_id` | Stable |
| `entries[].entity_type` | As in section 3 |
| `entries[].name` | Retained under D-023 |
| `entries[].external_ids` | Where held |

**Checks (the three in plan 6.3):**

| # | Where | On a match |
|---|---|---|
| 1 | Pipeline, seed list | Candidate dropped; nothing further is fetched |
| 2 | Pipeline, basic verification | Record not exported |
| 3 | WordPress, at import, against the **live** list | Record refused (`SUPPRESSED`); it creates no profile |

**Matching:** an exact match on any external ID, or on normalised name plus entity type. A name match with a different external ID is treated as a match until a person decides. The import result gives only the `record_id` and the code, never a name. Check 3 is authoritative, since the list may have changed since the snapshot. Reason text for a suppression MUST NOT cross this interface.

## 12. What WordPress does with a bundle

1. Check the manifest: known contract version, counts and `records_sha256`.
2. **Dry run** (REQ-237): validate every record and show a diff. Nothing is written.
3. On a named staff member's confirmation, apply each record:

| Situation | Action | Result code |
|---|---|---|
| New `record_id` | Create the profile; set the state (section 9) | `CREATED` |
| Same `record_id`, same hash | Nothing | `UNCHANGED` |
| Hash differs; not yet reviewed | Update as a new version; re-set the state | `UPDATED` |
| Hash differs; already reviewed | **Do not overwrite** (REQ-161, 221); flag for a reviewer | `FLAGGED_CHANGE` |
| Failed a check | Refuse that record; continue with the rest | See below |

**Refusal codes:** `CONTRACT_VERSION`, `MANIFEST_MISMATCH`, `SCHEMA`, `HASH_MISMATCH`, `VERIFICATION_FAILED`, `SUPPRESSED`, `UNSOURCED_FIELD`, `LOW_CONFIDENCE`, `FORBIDDEN_FIELD`, `IMAGE_NOT_ALLOWED`. A bad manifest refuses the whole bundle. From v1.1, `IMAGE_NOT_ALLOWED` refuses only the image, not the record (section 10); the result lists it as `[record_id, "IMAGE_NOT_ALLOWED"]` beside the record's own code.

**Import result, website → acquisition:** `batch_id`, the person's role (not their name), a timestamp, counts by code, and `[record_id, code]` for each record. It contains no field values.

**Undo:** a whole `batch_id` can be undone (REQ-237). Reviewed records are not removed by an undo.

## 13. Versioning

- `contract_version` is `MAJOR.MINOR`. The current version is `1.1`; the importer accepts `1.0` and `1.1`.
- **MAJOR:** anything an existing importer would misread or refuse: a removed or renamed field, a changed meaning or hash rule.
- **MINOR:** an added optional field, enum value or refusal code. Because unknown fields are refused, the importer must be updated first.
- WordPress lists the versions it accepts and refuses others (`CONTRACT_VERSION`).
- `record_id` values, and the check names in section 7, are never reused with a new meaning.
- **Changing this file:** only the session that owns the shared docs edits it (see `CLAUDE.md`). Each change gets a line in section 16, and the other side confirms before the version is used.
- Changing the hash rule (section 8) makes every stored hash incomparable. It requires a new MAJOR version and a full re-export.

## 14. Unknowns the WordPress architecture must settle (Gate 2)

| # | Unknown | Why it matters here | Ref |
|---|---|---|---|
| U1 | Profile post type, and where provenance, claims, passages and reports are stored (custom tables or post meta) | Must be queryable; meta may not scale | T-3 |
| U2 | Transport: file upload, WP-CLI or REST; maximum sizes. This contract assumes JSON Lines, while REQ-237 says "CSV or REST". | The nested form does not fit one CSV | Q67, REQ-237 |
| U3 | How 1,000 records import on cPanel within time limits: chunking, resuming, partial failure | Section 12 assumes record-level results | Q26 |
| U4 | Which WordPress takes the import, and how the setting in rule 1 is controlled | Section 9 | Q64, D-029 |
| U5 | Controlled lists (industry, sector, org type, city): who owns the values, and how the pipeline gets them | Unknown values would be refused or need mapping | REQ-009 |
| U6 | Slugs and the map from `record_id` to post ID | The pipeline does not generate URLs | REQ-006, 129 |
| U7 | Linking a person to an organisation when one is not public | `current_organisation_ref` must not leak an unapproved person | REQ-208 |
| U8 | Who holds the approved source list: this repo until the WordPress registry exists, then which is authoritative? `source_id` values must not change. | Two possible sources of truth | REQ-136, T-1 |
| U9 | How the suppression snapshot and export bundles travel between machines, encrypted and logged, and how long copies are kept | Both hold personal data | REQ-063, 207 |
| U10 | Whether AI summaries can be hidden until review | Decides how `summary` is displayed | Q46 |
| U11 | How long passages are kept in WordPress, and how they stay admin-only | Copyright and retention | REQ-063, 208 |
| U12 | Image storage. **Settled in v1.1:** the WordPress media library, with the licence, creator, attribution and source as attachment meta (section 10) | Section 10 | Q34, REQ-080 |
| U13 | **The under-18 guard in REQ-212 has nothing to check,** because no age or date of birth may be stored (REQ-140) | At present only the seed rule protects against this (plan 1.3) | REQ-068, 212 |
| U14 | What undo does to a batch once some records in it are reviewed | Section 12 | REQ-237 |

## 15. Worked examples (fictional; no personal data)

Both `content_hash` values were computed with the rule in section 8, by the pipeline (Python) and by the website's `ContentHasher` (PHP), with the same result, so they can serve as test vectors.

### 15.1 Organisation, v1.0

This T1 record is a valid v1.0 record; the importer still accepts it.

```json
{
  "contract_version": "1.0",
  "record_id": "rec_org_0001",
  "record_version": 1,
  "run_id": "run_20261110_a",
  "batch_id": "B1",
  "entity_type": "organisation",
  "tier": "T1",
  "country": "NG",
  "external_ids": {
    "licence_number": "SAMPLE-001",
    "official_domain": "sample-bank.example"
  },
  "inclusion": {
    "criterion_code": "O2",
    "evidence_url": "https://regulator.example/licensed-banks"
  },
  "fields": {
    "name": {
      "value": "Sample Bank Ltd",
      "provenance": [
        {
          "source_id": "src_sample_regulator",
          "source_url": "https://regulator.example/licensed-banks",
          "retrieved_at": "2026-11-10T09:00:00Z",
          "confidence": "high",
          "method": "import",
          "source_hash": "sha256:b238cf50f2f70acb914d2c9f213a90e6dba00c96fc6576c97f194f036a889598"
        }
      ]
    },
    "org_type": {
      "value": "commercial-bank",
      "provenance": [
        {
          "source_id": "src_sample_regulator",
          "source_url": "https://regulator.example/licensed-banks",
          "retrieved_at": "2026-11-10T09:00:00Z",
          "confidence": "high",
          "method": "import",
          "source_hash": "sha256:b238cf50f2f70acb914d2c9f213a90e6dba00c96fc6576c97f194f036a889598"
        }
      ]
    },
    "licence_category": {
      "value": "Commercial bank",
      "provenance": [
        {
          "source_id": "src_sample_regulator",
          "source_url": "https://regulator.example/licensed-banks",
          "retrieved_at": "2026-11-10T09:00:00Z",
          "confidence": "high",
          "method": "import",
          "source_hash": "sha256:b238cf50f2f70acb914d2c9f213a90e6dba00c96fc6576c97f194f036a889598"
        }
      ]
    },
    "regulator": {
      "value": "Sample Regulator",
      "provenance": [
        {
          "source_id": "src_sample_regulator",
          "source_url": "https://regulator.example/licensed-banks",
          "retrieved_at": "2026-11-10T09:00:00Z",
          "confidence": "high",
          "method": "import",
          "source_hash": "sha256:b238cf50f2f70acb914d2c9f213a90e6dba00c96fc6576c97f194f036a889598"
        }
      ]
    }
  },
  "claims": [],
  "passages": [],
  "images": [],
  "ai": {
    "ai_assisted": false,
    "model": null,
    "prompt_version": null
  },
  "flags": {
    "probable_duplicate": false,
    "duplicate_of": [],
    "flag_reasons": []
  },
  "verification": {
    "run_id": "run_20261110_a",
    "verified_at": "2026-11-10T10:00:00Z",
    "checks": {
      "approved_source": "pass",
      "source_url_ok": "pass",
      "exact_quotes": "pass",
      "allowlisted_fields": "pass",
      "sensitive_scan": "pass",
      "suppression": "pass",
      "duplicates": "clear"
    },
    "claims_kept": 0,
    "claims_dropped": 0,
    "fields_dropped": []
  },
  "content_hash": "sha256:90f04fe6e1d2e6956fcabd6dff4980e7ad1f517d8687713ec31fefa5582f6e01"
}
```

### 15.2 Deceased individual with narrative sections and an image, v1.1

The new fields hash like any other: they sit inside `fields` (and `images`), which are "In hash", so they go through the same NFC, RFC 8785 and SHA-256 steps. Points to check in an implementation:

- `lifespan` keeps its en dash as the raw UTF-8 character (RFC 8785 escapes only `"`, `\` and control characters): `{"lifespan":"1924–2013"}`.
- The blank line between paragraphs is escaped as `\n\n`: `{"bio":{"value":"Ama Sample was a fictional singer and bandleader.\n\nShe led a sample orchestra for thirty years.", …}}`.
- `width` and `height` are integers; `external_ids` is an empty object `{}`, not an empty array.
- The image entry is part of the hash, so a record that loses its image at import (refused per image, section 10) still matches the hash as exported. Without the `images` entry, this record would hash to `sha256:7d34c9a8ac3dee2e1ddf3febeba2295b8f8cf97b92395f19972c47f5dffb219b`.

```json
{
  "contract_version": "1.1",
  "record_id": "rec_ind_0002",
  "record_version": 1,
  "run_id": "run_20261114_a",
  "batch_id": "B2",
  "entity_type": "individual",
  "tier": "T3",
  "country": "GH",
  "external_ids": {},
  "inclusion": {
    "criterion_code": "HIST",
    "evidence_url": "https://archive.example/people/ama-sample"
  },
  "fields": {
    "name": {
      "value": "Ama Sample",
      "provenance": [
        {
          "source_id": "src_sample_archive",
          "source_url": "https://archive.example/people/ama-sample",
          "retrieved_at": "2026-11-12T09:00:00Z",
          "confidence": "high",
          "method": "api",
          "source_hash": "sha256:5d41402abc4b2a76b9719d911017c592ae2b9c27a1e3a0f2c1b0c7b1f1e5a6b7"
        }
      ]
    },
    "lifespan": {
      "value": "1924–2013",
      "provenance": [
        {
          "source_id": "src_sample_archive",
          "source_url": "https://archive.example/people/ama-sample",
          "retrieved_at": "2026-11-12T09:00:00Z",
          "confidence": "medium",
          "method": "api",
          "source_hash": "sha256:5d41402abc4b2a76b9719d911017c592ae2b9c27a1e3a0f2c1b0c7b1f1e5a6b7"
        }
      ]
    },
    "field": {
      "value": "Music",
      "provenance": [
        {
          "source_id": "src_sample_archive",
          "source_url": "https://archive.example/people/ama-sample",
          "retrieved_at": "2026-11-12T09:00:00Z",
          "confidence": "medium",
          "method": "api",
          "source_hash": "sha256:5d41402abc4b2a76b9719d911017c592ae2b9c27a1e3a0f2c1b0c7b1f1e5a6b7"
        }
      ]
    },
    "bio": {
      "value": "Ama Sample was a fictional singer and bandleader.\n\nShe led a sample orchestra for thirty years.",
      "provenance": [
        {
          "source_id": "src_sample_archive",
          "source_url": "https://archive.example/people/ama-sample",
          "retrieved_at": "2026-11-12T10:00:00Z",
          "confidence": "medium",
          "method": "ai",
          "source_hash": "sha256:2c26b46b68ffc68ff99b453c1d30413413422d706483bfa0f98a5e886266e7ae"
        }
      ]
    },
    "legacy": {
      "value": "Her recordings remain in the sample archive.",
      "provenance": [
        {
          "source_id": "src_sample_archive",
          "source_url": "https://archive.example/people/ama-sample",
          "retrieved_at": "2026-11-12T10:00:00Z",
          "confidence": "medium",
          "method": "ai",
          "source_hash": "sha256:2c26b46b68ffc68ff99b453c1d30413413422d706483bfa0f98a5e886266e7ae"
        }
      ]
    }
  },
  "claims": [],
  "passages": [],
  "images": [
    {
      "image_id": "photo-1",
      "file": "images/9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08.jpg",
      "sha256": "9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08",
      "mime_type": "image/jpeg",
      "width": 800,
      "height": 1000,
      "kind": "photo",
      "source_url": "https://images.example/file/ama-sample.jpg",
      "retrieved_at": "2026-11-12T09:05:00Z",
      "creator": "Sample Photographer",
      "licence": "CC-BY-SA-4.0",
      "licence_url": "https://creativecommons.org/licenses/by-sa/4.0/",
      "attribution_text": "Sample Photographer, CC BY-SA 4.0"
    }
  ],
  "ai": {
    "ai_assisted": true,
    "model": "sample-model",
    "prompt_version": "reword-v1"
  },
  "flags": {
    "probable_duplicate": false,
    "duplicate_of": [],
    "flag_reasons": []
  },
  "verification": {
    "run_id": "run_20261114_a",
    "verified_at": "2026-11-12T11:00:00Z",
    "checks": {
      "approved_source": "pass",
      "source_url_ok": "pass",
      "exact_quotes": "pass",
      "allowlisted_fields": "pass",
      "sensitive_scan": "pass",
      "suppression": "pass",
      "duplicates": "clear"
    },
    "claims_kept": 0,
    "claims_dropped": 0,
    "fields_dropped": []
  },
  "content_hash": "sha256:54971f5bc3a2905ffbd898f0ff86dd9847e6015a7d6d820096a8e063ee75fe49"
}
```

## 16. Change log

| Version | Date | Change |
|---|---|---|
| 1.0-draft | 2026-11-08 | First draft |
| 1.1 | 2026-11-14 | MINOR, hash rule unchanged. Narrative sections `bio`, `achievement`, `influence`, `legacy` for every entity type (4.3); person `current_title` and `current_organisation` optional, new `lifespan` and `field` (4.2); criterion codes `HIST` and `LEAD` (3); open-licence images with a licence allowlist, refused per image (10); zip form of a bundle (2); worked example 15.2. The importer accepts 1.0 and 1.1. Issue #807. |
