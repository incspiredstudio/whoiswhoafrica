# Directory

The module that owns profiles: what a profile is, which fields it may hold, how profiles are found, and the one read model every public page uses.

## Responsibility

- Define the profile type for individuals, businesses and institutions, and the fields each may store.
- Own the controlled lists (industry, sector, profession, organisation type, place).
- Keep a version history of every edit and a source for every field.
- Build and query the search index, for profiles and, through the Content module, stories.
- Expose `PublicProfile`, the only way a template or form can read a profile.

## Content types, taxonomies and tables

| Item | Kind | Notes |
|---|---|---|
| `wiw_profile` | Custom post type | No archive, no feed, no REST, excluded from core search. URLs are served by the plugin's own rewrite and handler. The post holds name and slug. Fields are post meta registered from an allowlist per entity type |
| `wiw_industry`, `wiw_sector`, `wiw_profession`, `wiw_org_type` | Taxonomies | Controlled lists. Editors and Administrators add terms in WordPress's native term screens. Reviewers only assign existing terms |
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
| Profiles list | Filter and group by source, import batch, type, state, origin and name; shows what visitors currently see for each row (page, gone, not public). Hosts the bulk unpublish action owned by Review |
| Profile screen | Status and marks, direct edit with a required reason, assign terms, fields with their sources, claim and assigned user (Trust), arrangements (read-only), contact and enquiry switches, public URL, source sync, history, internal notes |
| Sources | Read-only list of sources with profile count, last import, errors and removals, linking to the Profiles list filtered by that source. No add, edit, approve or reject exists |
| Add profile | Directory > Add profile (`wiw_add_profile`). Staff add a profile by hand: entity type first, then that type's fields from the allowlist, country, the four sections (Bio, Achievement, Influence, Legacy), an optional photo or logo from the media library with an open licence, credit and source, and at least one source URL. Possible duplicates (same type and a close normalised name, or the same Wikidata id or registration number) are listed before saving, with a "create anyway" confirmation; a suppressed name is refused. The profile starts Imported not public (origin `manual`, the creator recorded as importer so they cannot approve it), each field gets a provenance row with method `manual`, and the creation is audited (`profile.create`). Opened from the Nominations "Create profile" shortcut, it returns there after saving. Field labels come from the `label` key in `config/profile-fields.php`, and every paragraph box has a live character counter, as on Edit profile. **Profiles > Add New is removed from the menu, and its address (`post-new.php?post_type=wiw_profile`) redirects a person who may add profiles to Directory > Add profile**; anyone else gets WordPress's own capability answer |
| Edit profile photo panel | On the Edit profile screen, a staff member with `edit_wiw_profiles` picks a JPEG, PNG or WebP from the media library, gives its open licence (the import image allowlist only), the creator or credit and the source page, or removes the image. Form action `wiw_profile_image` (nonce and capability through `ActionGuard`), domain service `Directory\Edit\ProfileImageEditor`. A reason is required; it writes a version row with the old and new values, an audit row `profile.image_change` and a page purge. The credit is the side field `image_credit` (with the licence and source, stored by `ImageMeta::save()`); an edit by the assigned manager of a claimed profile becomes a pending revision. The media library item is never deleted. Long texts (summary, mandate and the four sections) are paragraph boxes with a counter |
| Hidden detail screens | Edit profile and Review item are **detail screens**: registered with `ScreenRegistry` as hidden, so they have no menu item and are reached from their lists. They are guarded the same way as menu screens; the registry sets their page title and keeps their group's menu open |
| Create profile for a customer | Starts a profile for a customer or complimentary listing. It enters review and is not public until approved |
| Taxonomy term screens | Native WordPress. Not custom |

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
| List and search queries | Run on `wiw_profile_search`, built only from public states, ordered by relevance or name and never by payment |
| `/profile/{slug}`, `/people`, `/businesses`, `/institutions`, `/industries`, `/industries/{term}`, `/directory`, `/search` | Server-rendered pages. Filters by industry, profession, sector, country, city and organisation type |
| `POST /enquire/{slug}` | Relays an enquiry to the owner without exposing their email. Rate-limited and spam-checked |
| `/sitemap.xml` | Built from the read model on request, cached five minutes, purged on unpublish |

## Jobs, hooks and events

| Trigger | Action |
|---|---|
| A profile changes state | Add or remove its search row; queue cache purge (Platform) |
| A profile is saved | Write a version and an audit row |
| Daily | Roll up view counts for the staff report (no IPs stored, bots filtered) |
| An import applies a record | Write fields, provenance and state (Import) |

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
