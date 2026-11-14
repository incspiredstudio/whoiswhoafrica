# Directory

The module that owns profiles: what a profile is, which fields it may hold, how profiles are found, and the one read model every public page uses.

## Responsibility

- Define the profile type for individuals, businesses and institutions, and the fields each may store.
- Own the controlled lists: the classification tree (sectors, industries, Unclassified), profession, organisation type and place.
- Keep a version history of every edit and a source for every field.
- Build and query the search index, for profiles and, through the Content module, stories.
- Expose `PublicProfile`, the only way a template or form can read a profile.

## Content types, taxonomies and tables

| Item | Kind | Notes |
|---|---|---|
| `wiw_profile` | Custom post type | No archive, no feed, no REST, excluded from core search. URLs are served by the plugin's own rewrite and handler. The post holds name and slug. Fields are post meta registered from an allowlist per entity type |
| `wiw_industry` | Taxonomy, hierarchical | The classification tree (REQ-009 as amended 2026-11-14): sectors at the top, their industries under them, and a top-level `unclassified` term hidden from public navigation. Seeded from `config/classification.php` (slugs never change; staff may rename labels). Industry terms carry the aliases in term meta `wiw_alias` (array of strings). Shared with acquisition as `docs/contracts/classification-tree.json`, regenerated with `php site/bin/classification-tree.php`; a unit test keeps the two in step |
| `wiw_org_type` | Taxonomy, flat | The curated organisation types from the same config, with `wiw_alias`. Exactly one per organisation or institution; an unknown value becomes `other` |
| `wiw_place` | Taxonomy, hierarchical | Country, then city. Term meta `wiw_iso_code` (ISO alpha-2, or `PAN`) and `wiw_place_kind` (`country`, `city`, `body`). Seeded with the African countries and `pan-african` (kind body, code PAN) |
| `wiw_profession` | Taxonomy | Controlled list. Editors and Administrators add terms in WordPress's native term screens. Reviewers only assign existing terms |
| `wiw_sector` | Taxonomy, retired | Registered without any screen so the upgrade can read it; migration 2026112103 empties it. Nothing public uses it |
| Profile classification | Post meta | `_wiw_primary_industry` (term id of a leaf or Unclassified), `_wiw_primary_country` (term id of a country or the Pan-African body), `_wiw_classification_provenance` (JSON: method, model, prompt_version, confidence; internal). Written only by `Directory\ProfileClassifier` (called by the importer, the editor, the upgrade and the demo), never by templates. The profile also carries the matching terms |
| `wiw_place` | Hierarchical taxonomy | Countries at the top (Nigeria first, then other African countries and pan-African bodies), cities beneath, ISO code as term meta |
| `wiw_profile_state` | Table | One row per profile: publication state, entity type, origin (import, customer, complimentary, nominee, manual), source, import batch, content hash, reviewer and review date, claimed flag and date, assigned user. Indexed for the source, batch, type and state filters |
| `wiw_profile_versions` | Table | Append-only snapshots with author (empty for import), how it was created, reason and time |
| `wiw_field_provenance` | Table | For every field: source URL, retrieval date, confidence, method, source hash |
| `wiw_profile_search` | Table | Flattened FULLTEXT index built from public states only |
| `wiw_sources` | Table | Read-only snapshot of approved sources, fed by the import |

The state table in the earlier design also held trust and sponsored columns. They are dropped: profiles are never Sponsored or Featured, and there is no Verified. Claimed lives on the state row and is owned by the Trust module.

## Admin screens

| Screen | What it does |
|---|---|
| Profiles list | Filter and group by source, import batch, type, state, origin and name; shows what visitors currently see for each row (page, gone, not public). View links **All** and **Needs classification** (`classification=needs`: main industry missing or Unclassified, or no main country), each with its count (REQ-009). Hosts the bulk unpublish action owned by Review |
| Profile screen | Status and marks, direct edit with a required reason, the **Classification** block (see below), "Where each fact comes from" (sources, read-only), claim and assigned user (Trust), contact and enquiry switches, public URL, "Changes from the source since review" (shown only when a re-import change is pending), history, internal notes. The old "Lists" multi-selects, the text inputs for industry, sector, organisation type, head office city and a person's field, and the empty Arrangements panel are gone; those text fields stay in `config/profile-fields.php` as import inputs only |
| Classification block (Edit profile and Add profile) | `Directory\Admin\ClassificationPicker` renders, `Directory\Edit\ClassificationInput` reads and checks the term ids, and hands the result to `Directory\ProfileClassifier::assign()`, the only writer. **Industry**: the main industry, a select grouped by sector with a filter box (an industry under a sector, or Unclassified); **Other industries**: the profile's own extras as chips (untick to remove) and a select to add one; **Organisation type**: one, for organisations and institutions; **Country**: a country or the Pan-African body; **City**: optional, only cities of the chosen country. Without JavaScript every control still works (`assets/admin/directory/classification.js` adds the filter, chips and city narrowing). A save goes through `ProfileEditor` (version key `classification`, audit `profile.edit`) and, through `ProfileClassifier::assign()`, sets `_wiw_primary_industry`, `_wiw_primary_country`, the terms (industry = main plus extras, place = country plus city, one org type) and `_wiw_classification_provenance` (method `manual`, confidence `medium`). An empty main industry is stored as Unclassified and an organisation with no type as Other, as assign() does. It needs `assign_terms`; an assigned manager with limited rights cannot change it; an approved pending revision applies it. A staff field edit also writes a provenance row (method `manual`, source "Staff edit", confidence `medium`) so the edited fact keeps showing publicly |
| Sources | Read-only list of sources with profile count, last import, errors and removals, linking to the Profiles list filtered by that source. No add, edit, approve or reject exists |
| Add profile | Directory > Add profile (`wiw_add_profile`). Staff add a profile by hand: entity type first, then that type's fields from the allowlist, country, the classification block (main and other industries, organisation type, which is required for organisations and institutions, country, which is required, and city), the four sections (Bio, Achievement, Influence, Legacy), an optional photo or logo from the media library with an open licence, credit and source, and at least one source URL. Possible duplicates (same type and a close normalised name, or the same Wikidata id or registration number) are listed before saving, with a "create anyway" confirmation; a suppressed name is refused. The profile starts Imported not public (origin `manual`, the creator recorded as importer so they cannot approve it), each field gets a provenance row with method `manual`, and the creation is audited (`profile.create`). Opened from the Nominations "Create profile" shortcut, it returns there after saving. Field labels come from the `label` key in `config/profile-fields.php`, and every paragraph box has a live character counter, as on Edit profile. **Profiles > Add New is removed from the menu, and its address (`post-new.php?post_type=wiw_profile`) redirects a person who may add profiles to Directory > Add profile**; anyone else gets WordPress's own capability answer |
| Edit profile photo panel | On the Edit profile screen, a staff member with `edit_wiw_profiles` picks a JPEG, PNG or WebP from the media library, gives its open licence (the import image allowlist only), the creator or credit and the source page, or removes the image. Form action `wiw_profile_image` (nonce and capability through `ActionGuard`), domain service `Directory\Edit\ProfileImageEditor`. A reason is required; it writes a version row with the old and new values, an audit row `profile.image_change` and a page purge. The credit is the side field `image_credit` (with the licence and source, stored by `ImageMeta::save()`); an edit by the assigned manager of a claimed profile becomes a pending revision. The media library item is never deleted. Long texts (summary, mandate and the four sections) are paragraph boxes with a counter |
| Hidden detail screens | Edit profile and Review item are **detail screens**: registered with `ScreenRegistry` as hidden, so they have no menu item and are reached from their lists. They are guarded the same way as menu screens; the registry sets their page title and keeps their group's menu open |
| Create profile for a customer | Starts a profile for a customer or complimentary listing. It enters review and is not public until approved |
| Taxonomy term screens | Native WordPress, with extra fields from `Directory\Admin\TermFields` (REQ-009). **Places**: Kind (Country, City, Pan-African body), Country code (two letters, required and unique for a country; PAN allowed for a body), a city needs a country as Parent, a country has no Parent. **Industries**: top-level terms are sectors; an industry needs a sector as Parent (no third level, a sector with industries cannot be moved); "Also known as" (term meta `wiw_alias`, an array, used by import matching). **Organisation types**: "Also known as". The `unclassified` industry cannot be deleted (`delete_term` is denied), renamed, moved or quick-edited. A screen submission that breaks a rule is refused with a plain message (`pre_insert_term` on add, `load-edit-tags.php` on edit); code inserts such as seeding and the import are not checked. A slug change keeps the old slug in term meta `wiw_old_slugs` (one row per old slug) and `Directory\Url\OldTermSlugRedirect` answers a front-end address whose `industry=` or `country=` names an old slug with a 301 to the current one. Columns: Kind, Country code, Country (parent); Sector (parent), Also known as; the count column is called Profiles. Saves of the extra fields need the form's nonce and `edit_term`, and are audited (`term.save`, `term.slug_change`) |

## Capabilities

| Capability | Source | Reviewer | Editor | Administrator |
|---|---|---|---|---|
| `read_private_wiw_profiles` | Core, generated for the type | yes | yes | yes |
| `edit_wiw_profiles` family (create, edit others', publish, edit published, delete) | Core, generated | no | yes | yes |
| `assign_terms` | Core | yes | yes | yes |
| `manage_terms`, `edit_terms`, `delete_terms` | Core | no | yes | yes |
| `wiw_view_sources` | Plugin | yes | yes | yes |
| `wiw_add_profile` | Plugin | no | yes | yes (grantable) |
| `wiw_receive_enquiries` | Plugin | no | no | yes (grantable) |

## Public read models and endpoints

| Item | Behaviour |
|---|---|
| `PublicProfile::find(slug)` | Returns an object only when the profile is in a public state, and only allowlisted fields. It also produces the pending-review label, the noindex header and meta, and the share-preview data, so a template cannot forget them |
| `PublicProfile::classification()` | `country` (name, slug, code, url), `sector` and `industry` (name, slug, url; both null when unclassified) and `extra` industries, from the terms. Each url is the directory list filtered: `/directory/?country=<country>`, then `&industry=<sector>` or `&industry=<industry>`. Names are plain text. It drives the breadcrumb `Country › Sector › Industry › Name`. `org_type()` gives the organisation type's name and slug, `place()` the city and country names. Templates, search and SEO read these, never the import text (`wiw_industry`, `wiw_sector`, `wiw_org_type`, `wiw_hq_city` meta) |
| BreadcrumbList | `Seo\StructuredData::breadcrumb_for()` prints a schema.org BreadcrumbList beside the Person or Organization markup: Country, Sector, Industry, Name; Country, Name when unclassified; Directory, Name with no country. None while a profile has no schema (pending review) |
| List and search queries | Run on `wiw_profile_search`, built only from public states, ordered by relevance or name and never by payment |
| `/profile/{slug}`, `/people`, `/businesses`, `/institutions`, `/industries`, `/industries/{term}`, `/directory`, `/search` | Server-rendered pages. Filters by industry, profession, country, city and organisation type. An `industry` filter on a sector takes in all its industries, as a country takes in its cities. The industries index (`ProfileList::industry_counts()`) gives each term its parent and leaves out Unclassified |
| `POST /enquire/{slug}` | Relays an enquiry to the owner without exposing their email. Rate-limited and spam-checked |
| `/sitemap.xml` | Built from the read model on request, cached five minutes, purged on unpublish |

## Jobs, hooks and events

| Trigger | Action |
|---|---|
| A profile changes state | Add or remove its search row; queue cache purge (Platform) |
| A profile is saved | Write a version and an audit row |
| Daily | Roll up view counts for the staff report (no IPs stored, bots filtered) |
| An import applies a record | Write fields, provenance and state (Import), and the classification through `ProfileClassifier`: a contract 1.2 `classification` wins, otherwise the aliases or Unclassified; never a refusal |
| Migration 2026112103 | Seed the tree, the organisation types and the Pan-African place; classify existing profiles from their old terms and text through the aliases (or Unclassified); fold old flat industry and organisation type terms into the tree (merged with their posts, or removed); empty `wiw_sector`. Counts only in the log; safe to run again |

## Business rules

- **Allowlisted fields only.** An individual holds name, current title and organisation, industry, public-record roles, published awards, public bio and sources. Home address, phone, personal email, family, date of birth or age, health, religion, politics, ethnicity, finances and the like have no field to store them in (REQ-139, 140).
- **Every field has a source.** A field with no source, or low confidence, is refused at import (REQ-142, 143).
- **Contact details are private.** Nothing is public beyond the enquiry form unless staff switch a detail on after a request (REQ-060).
- **Profile pages show no cover image.** The only image is the person's photograph or the organisation's logo (REQ-247).
- **One stable URL.** The slug is generated from the name with a collision suffix. Changing it leaves a redirect from the old address.
- **Edits keep history.** Every edit writes a version with author, time and reason. Re-import never overwrites a reviewed field; it flags the change for review (REQ-161).
- **No paid or trust labels on profiles.** Nothing a payment does changes a profile, its position or its look.
- **Claimed is read for one purpose.** Public templates read the claimed flag only to hide the "Claim this profile" button. The badge is staff-only.
- **Unreviewed organisations are labelled.** "Compiled automatically from public sources. Human review pending." appears on the page, cards and share preview, with noindex.

## Tests that must exist

- `test_REQ_208_hides_unreviewed_profiles_from_every_surface` (the visibility matrix, one profile per state, every surface).
- `test_REQ_139_individual_profile_stores_only_allowlisted_fields`
- `test_REQ_140_forbidden_attributes_have_no_storage`
- `test_REQ_142_field_without_source_is_refused`
- `test_REQ_125_edit_writes_version_with_reason`
- `test_REQ_247_profile_page_has_no_cover_image`
- `test_REQ_254_search_returns_only_public_items`
- `test_REQ_060_contact_details_private_by_default`
- Architecture test: no template calls `get_post_meta` or `WP_Query` on the profile type.

## Depends on

Review (`StateMachine` writes the state), Platform (capabilities, audit, cache purge), Content (stories in search), the theme (templates).

## Requirement ids covered

REQ-003 to 009, 014, 015, 018, 060, 091, 123, 125, 139, 140, 142 to 146, 161, 164, 187, 208, 216, 217, 242, 243, 247, 254.
