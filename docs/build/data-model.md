# Data model

What WordPress stores natively, what the plugin adds, and the rules for personal data. WordPress holds content, terms and users. Custom tables hold the things WordPress does poorly.

Records: ADR-002, ADR-005, ADR-010. Architecture: section 4. Decisions: D-033, D-053, D-059.

## Conventions

- Custom tables use the `wiw_` prefix after the WordPress table prefix.
- Money is integer minor units plus a currency code (kobo for NGN) (REQ-112).
- Migrations are forward-only and additive first (expand, then contract), and only the plugin area writes them.
- Personal-data columns are marked in the PII catalogue, `whoiswho-core/config/pii.php`. A test fails if a personal-data column is not in it.

## WordPress content types and taxonomies

| Item | Kind | Notes |
|---|---|---|
| `wiw_profile` | Custom post type | Individual, organisation, institution. No archive, no feed, no REST, excluded from core search. URLs are served by the plugin's own rewrite and handler. Fields are post meta registered from a per-type allowlist, so no field exists for forbidden attributes |
| `wiw_story` | Custom post type | Interview, feature, spotlight, opinion, announcement, news. Block editor, scheduled publishing, Sponsored link |
| `wiw_sponsor` | Custom post type | Name, logo, link, attached to awards, categories and pages |
| Pages | Core `page` | About, FAQ, rules, legal pages, contact |
| `wiw_industry`, `wiw_sector`, `wiw_profession`, `wiw_org_type` | Custom taxonomies | Controlled lists. Editors and Administrators add terms in the native term screens. Reviewers can only assign existing terms |
| `wiw_place` | Hierarchical taxonomy | Countries at the top (Nigeria first, then other African countries and pan-African bodies), cities beneath, ISO code as term meta |

The architecture proposes `wiw_story` as its own type. D-059 describes stories as native posts with Featured as the sticky flag. The two need reconciling when the story type is built. Either way, stories must be read through the public story read model.

## Custom tables (proposed)

| Table | Holds |
|---|---|
| `wiw_profile_state` | One row per profile: `pub_state`, entity type, tier, origin, source, batch, content hash, importer, reviewer and review time. Indexed for source, batch, type and state filters |
| `wiw_profile_versions` | Append-only version snapshots: author, time, reason, old and new values |
| `wiw_field_provenance` | Per field: source, URL, retrieval date, confidence, method, source hash |
| Import claims, passages, verification | Admin-only, never public, retention applies |
| `wiw_profile_search` | Flattened FULLTEXT table built from public states only, covering profiles and stories |
| `wiw_import_batches`, `wiw_import_results` | Batch, file hash, status, cursor; per-record result codes with no field values |
| `wiw_sources` | Read-only snapshot of source names for the Sources screen |
| `wiw_duplicates` | Probable duplicate pairs for human review |
| `wiw_suppression` | Permanent list of removed or declined people and organisations |
| `wiw_bulk_actions` | Filter, reason, count and actor for each bulk unpublish |
| `wiw_support_items` | Enquiry, correction, removal, report, complaint, data request: requester, message, status, owner, due date |
| `wiw_customers` | Customers: a stable `external_id` (UUID), name, segment, contact email and phone, notes, and an optional link to the enquiry it came from. A customer can be sent a single-use profile invitation (signed link, 14 days, purpose `profile_invitation`, Administrator only through `wiw_manage_register`); the submission creates a profile in the review queue with origin customer, not public |
| `wiw_arrangements`, `wiw_placements` | Sponsored story deals (type is always sponsored post; money in integer kobo plus currency) and their placements, one row per slot for a deal and a story. The docs earlier named the second table `wiw_arrangement_links`; the built name is `wiw_placements`. Never joined to awards tables |
| `wiw_editions`, `wiw_award_categories`, `wiw_nominations`, `wiw_nomination_changes` | Editions, categories (under five award groups), nominations and a stage, status and removal history. Nomination columns: `category_id`, optional `profile_id`, `nominee_name`, `nominee_org`, `nominator_name`, `nominator_email`, `nominator_verified_at`, `contact_email`, `reference` (unique, at most 24 characters), `relationship_declared` and `relationship_note`, `evidence`, `citation`, `notes`, `stage` (nominated or winner), `status` (live or not live), `source` (public form or staff), and who sent the nomination and winner emails and when. Not live nominations are private. Names, emails and notes are personal data in the PII catalogue. No Awards table has a key to a commercial table |
| Request and enquiry references | Support items (`wiw_support_items`) carry a reference shown on the received page and in email, in the form `WIW-` plus eight characters from an alphabet without look-alike letters (for example `WIW-7K3Q9XPD`); nominations carry their own `reference` |
| `wiw_signed_links` | Hashed token, purpose, subject, expiry, use and revocation |
| `wiw_notes` | Internal notes, never public |
| `wiw_consents` | Who agreed to what, with policy version and time |
| `wiw_email_events` | Sends, bounces, complaints |
| `wiw_view_counts_daily` | Daily counts for the staff report only; bots filtered, no IPs stored |
| `wiw_audit_log` | Append-only, hash-chained (ADR-013) |
| `wiw_processors` | The processor register |

Settings (the sprint thresholds, the import-all-as-not-public setting, the Nominations flag) live in WordPress options with autoload off. Brand values do not: they live in the theme tokens file.

Old-slug redirects are core WordPress behaviour, so there is no redirects table (D-053).

## One state, one place

A profile's publication state is the most guarded field in the system. The `StateMachine` writes it and maps it to WordPress post status in one transaction. See [Publication states](/concepts/publication-states) and [Roles in code](/build/roles-in-code).

**Open:** D-053 says to prefer native features and add a lookup table only if a measured list query is slow (Q82). The table above is the architecture's proposal. Settle it with a timed test at 10,000 profiles before committing to it.

## Read models

Public templates never call `get_post_meta` or `WP_Query` on the profile type. They read through `PublicProfile` and `PublicStory`. These classes return data only when the state allows it and expose only allowlisted fields. They also produce the pending label, the noindex header and the home page eligibility rules.

## What D-059 adds

| Need | Storage |
|---|---|
| Claim requests | Every form field, a received date and a decide-by date (received plus 5 days), a status and the deciding user. The architecture's `wiw_claims` name already means import claims, so the profile claim table needs another name |
| Claimed flag | A field on the profile; the public template hides the Claim button when set |
| Assigned user | A user ID on the claimed profile |
| Pending revision of a claimed profile | Held until a Reviewer or Administrator approves; the editor cannot approve their own change |
| Home page curation | One setting per block: mode, item count, pinned item IDs, in options. Reads go through the read models and the daily random seed is cached |
| Story comments | wpDiscuz's own tables. Add them to the PII catalogue, the data export and erase, and the processor register |

## Personal-data classes

| Mark | Meaning | Examples |
|---|---|---|
| PII | Personal data | Requester email, nominator details, enquiry contact |
| SENS | High sensitivity | Two-factor secrets, evidence uploads |
| FIN | Commercial or financial | Amounts, payment references |
| PUB | May appear publicly after approval | Profile fields |

The catalogue drives retention, export logging, log scrubbing and the data-request export ([Privacy](/concepts/privacy-and-data-rights)).
