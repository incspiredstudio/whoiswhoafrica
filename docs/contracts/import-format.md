# Import format: the contract between acquisition and the website

Status: **v1.0-draft, 2026-11-08. Proposed; nothing implements it yet.** REQs: REQ-142, 143, 145, 146, 162, 203, 207, 210–212, 214, 221, 237. Decision: D-033 as amended.

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
  images/           only if images are allowed (section 10)
```

JSON Lines is the normative form, because per-field provenance is nested. A REST payload, if chosen, carries the same record object. A CSV form would be a new contract version (see U2).

### 2.1 `manifest.json`

| Field | Type | Rule |
|---|---|---|
| `contract_version` | string | For this draft, `"1.0"` |
| `run_id` | string | The pipeline run |
| `batch_id` | string | Import batch, unique across runs (REQ-237) |
| `generated_at` | string | ISO 8601 UTC |
| `pipeline_version` | string | Code version |
| `source_list_version` | string | The approved source list the run used |
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
| `inclusion` | object | Yes | Yes | `criterion_code` (O1–O7, E1–E2, I1–I6), `evidence_url` |
| `fields` | object | Yes | Yes | Section 4, each with provenance (section 5) |
| `claims` | array | Yes | Yes | Section 6; empty for T1 |
| `passages` | array | Yes | Yes | Section 6; empty for T1 |
| `images` | array | Yes | Yes | Section 10; empty unless images are allowed |
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

**No organisation field may name a person** (REQ-214). That means no leadership, founder or board field in this version, even though REQ-004 lists leadership for customer profiles.

### 4.2 Individual and entrepreneur (REQ-139)

| Key | Value | Required | Note |
|---|---|---|---|
| `name` | string | Yes | |
| `current_title` | string | Yes | |
| `current_organisation` | string | Yes | |
| `current_organisation_ref` | string | No | `record_id` of the organisation, if exported |
| `industry` | string | No | Controlled list |
| `public_record_roles` | array of string | No | Board or office roles; a founder role for E1 |
| `published_awards` | array of string | No | |
| `official_website` | URL | No | Official or organisational only |

**Never exported (REQ-140, 067):** home address, phone, personal email, family, date of birth or age, health, religion, politics including party affiliation, ethnicity, finances, allegations, personal social handles.

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
| `passage_id` | string | No | Required when `method` is `ai` |

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

**Default for this version: `images` MUST be empty, and WordPress refuses a non-empty array,** until Q34 is decided (`IMAGE_NOT_ALLOWED`). The shape below is reserved so that allowing images needs no breaking change. Only open-licence images are ever allowed (REQ-162, 203, D-024).

| `images[]` key | Rule |
|---|---|
| `image_id` | Unique within the record |
| `file` | Path in the bundle, `images/<sha256>.<ext>` |
| `sha256` | Of the file |
| `mime_type`, `width`, `height` | From the file |
| `kind` | `logo` or `photo` |
| `source_url` | The page it came from |
| `retrieved_at` | ISO 8601 UTC |
| `creator` | As the source gives it |
| `licence` | SPDX-style identifier, for example `CC-BY-4.0` |
| `licence_url` | The licence text |
| `attribution_text` | Exactly what the page must show |

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

**Refusal codes:** `CONTRACT_VERSION`, `MANIFEST_MISMATCH`, `SCHEMA`, `HASH_MISMATCH`, `VERIFICATION_FAILED`, `SUPPRESSED`, `UNSOURCED_FIELD`, `LOW_CONFIDENCE`, `FORBIDDEN_FIELD`, `IMAGE_NOT_ALLOWED`. A bad manifest refuses the whole bundle.

**Import result, website → acquisition:** `batch_id`, the person's role (not their name), a timestamp, counts by code, and `[record_id, code]` for each record. It contains no field values.

**Undo:** a whole `batch_id` can be undone (REQ-237). Reviewed records are not removed by an undo.

## 13. Versioning

- `contract_version` is `MAJOR.MINOR`. This draft becomes `1.0` when Gate 2 settles the unknowns that affect it.
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
| U12 | Image storage, if ever allowed | Section 10 | Q34, REQ-080 |
| U13 | **The under-18 guard in REQ-212 has nothing to check,** because no age or date of birth may be stored (REQ-140) | At present only the seed rule protects against this (plan 1.3) | REQ-068, 212 |
| U14 | What undo does to a batch once some records in it are reviewed | Section 12 | REQ-237 |

## 15. Worked example (fictional; no personal data)

This T1 record's `content_hash` was computed with the rule in section 8, so it can serve as a test vector.

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

## 16. Change log

| Version | Date | Change |
|---|---|---|
| 1.0-draft | 2026-11-08 | First draft |
