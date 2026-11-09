# WordPress fit report (2026-11-09, D-053)

Scope: `docs/requirements.md`, `decisions.md`, `questions.md`, `GUIDE.md`, `wireframe-inventory.md`. `acquisition/` was skipped. Nothing was renumbered.

**Rule (D-053).** Every REQ, decision and question has one WP type: Native, Native+config, Custom plugin, Theme or Process. Native is preferred; custom code only where behaviour is genuinely custom. Permissions are capabilities mapped to the four roles, in one place (requirements.md, "Capabilities and default role mapping"), and can be changed in wp-admin without code (D-046).

## 1. Counts by WP type

| WP type | REQs (active) | Decisions | Questions and contradictions |
|---|---|---|---|
| Native | 2 | 3 | 5 |
| Native+config | 49 | 9 | 15 |
| Custom plugin | 97 | 17 | 25 |
| Theme | 16 | 3 | 9 |
| Process | 39 | 20 | 50 |
| **Total** | **203** | **52** | **104** |

A further 42 REQs are withdrawn (all earlier rounds plus this one), keeping a type for their original topic only. About 70% of active REQs are custom plugin work; the main mass is the review/publish gate, labels, import, audit, commerce register and awards.

Wireframe (122 entries): 18 native, 42 customised-native, 58 custom, 4 wireframe tools (n/a).

## 2. Requirements withdrawn or merged (native WordPress)

| REQ | Result | WordPress provides |
|---|---|---|
| REQ-083 | Withdrawn, merged into REQ-235 | wp-admin, users, roles |
| REQ-108 | Withdrawn, merged into REQ-047 | Login, reset, sessions (a reset never bypasses two-factor) |
| REQ-109 | Withdrawn, merged into REQ-235 and REQ-244 | Role checks |
| REQ-129 | Withdrawn | Old-slug redirects |
| REQ-245 | Kept as "Native: no build" | One role per user; the plugin only blocks a second role |

Kept on purpose because each adds a product rule on top of core: REQ-125 (revisions), REQ-121 (sitemap and robots), REQ-079 (search), REQ-027 (stories).
Rewritten as capabilities: REQ-235, 244, 245, 240, 193, 174 in full; about 19 more edited (listed in the requirements section).

## 3. Questions

- **Closed:** Q78 (hierarchical taxonomies are core).
- **Rewritten for WordPress, status unchanged:** Q23 (view counter needs a plugin), Q67 (one import screen under Tools, or WP-CLI), Q72 (core already restricts users, settings and plugins to the Administrator).
- **New, Open, with recommended defaults (Q81–Q91):** plugin rule (Q81), 10k+ listing performance and indexing (Q82), backups (Q83), update policy (Q84), security hardening (Q85), role-editor plugin or small custom screen (Q86), cPanel limits checked at import (Q87), no page builder (Q88), single site (Q89), local-to-live move (Q90), search (Q91).
- **Status now:** 61 answered, 24 default accepted, 8 deferred, 11 open (104 items).

## 4. Decisions amended (D-053 marker, substance kept)

D-006, D-019, D-028, D-033, D-034, D-035, D-037, D-039, D-040, D-041, D-042, D-043, D-044, D-045, D-046, D-048. Common change: screens are native list tables or core screens with plugin columns and actions; roles and sign-in are WordPress's own; the Nominations flag is an option; taxonomies are core.

## 5. Architecture alignment

The capability matrix in architecture.md section 4.3 is replaced by a pointer to requirements.md. Differences found: profile and story editing, term management, user management and plugin install use core capabilities; `wiw_view_profiles` becomes `read_private_wiw_profiles`; `wiw_receive_enquiries` is new (Q62). The `wiw_redirects` table is dropped (core handles it).

## 6. Wireframe

Recommended removals: A01 sign-in, A24 articles list/editor, A34 users and roles, A57 taxonomy terms. Flagged: A54 and A60 (capability screens, see Q86), A58 (fold into A06), A35 (core Privacy Tools may cover part). Findings 70–76.

## 7. Needs your ruling

1. **Q86:** a maintained role-editor plugin (less to build, plugin dependency) or a small custom capability screen (A54, A60). WordPress has no core screen for per-user grants. Recommended: a maintained plugin, within the plugin cap.
2. **Q81–Q91:** eleven new open questions; each has a recommended default. Say "accept defaults" to close them.
3. **Profile capability type:** the profile post type needs its own capability type so `edit_wiw_profiles` and friends exist (architecture detail, confirm).
4. **Monitoring and backups** (REQ-045, 049, 050) are typed Native+config (host settings or plugins), not Process.
5. **Editor and bulk unpublish:** by default an Editor cannot bulk unpublish (matches D-045) but single-profile unpublish needs `wiw_unpublish_profiles`. Confirm.
6. **Mixed rows:** each row has one primary type; mixed items (such as D-033, D-040) are tagged Custom plugin.
7. **Public pages in the wireframe:** tagged by how WordPress would build them (plain content native, templates over profiles customised-native, forms and signed-link pages custom).
