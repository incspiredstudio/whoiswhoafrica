# Plugins

How the platform's code is divided between a custom plugin, a must-use guard plugin, the theme and a short list of third-party plugins.

## The four parts

| Part | Where it lives | Job |
|---|---|---|
| `whoiswho-core` (plugin) | `site/wp-content/plugins/whoiswho-core/` | All product rules: profiles, review and publishing, import, claims, sponsored story deals, awards, support and privacy, audit, settings |
| `whoiswho-guard` (must-use plugin) | `site/wp-content/mu-plugins/` | Keeps non-public content out of every surface WordPress adds, locks core REST to staff, emits all SEO markup, hardens the install. It cannot be switched off from wp-admin |
| `whoiswho` (theme) | `site/wp-content/themes/whoiswho/` | Templates, components, tokens, motion. No product logic: it reads data only through the plugin's read models |
| Third-party plugins | Installed and pinned through the build | Things WordPress or the community already does well: two-factor sign-in, comments, jobs library, spam checks |

The rule of thumb: if WordPress already does it, use WordPress. The plugin adds only what the product needs and WordPress does poorly: a publication state machine, field-level provenance, an audit chain and the counting rule.

## Module layout

Product code lives in domain modules under `whoiswho-core/src/`. Admin screens and request handlers stay thin and call a module's service.

| Module | Owns | Page |
|---|---|---|
| `Directory` | Profile type and fields, taxonomies, search, the `PublicProfile` read model, versions, the Sources view | [Directory](/plugins/directory) |
| `Review` | The `StateMachine`, review queue, duplicates, bulk unpublish, approval of edits to claimed profiles | [Review](/plugins/review) |
| `Import` | Bundle validation, dry run, chunked apply, undo, hash check, results, suppression snapshot | [Import](/plugins/import) |
| `Trust` | Claims, the Claimed mark, the assigned user, claim reminders | [Trust](/plugins/trust) |
| `Commerce` | Customers, sponsored story deals, the counting rule, the paid story slot | [Commerce](/plugins/commerce) |
| `Content` | Stories support, events, home and stories curation, newsletter, comment settings, pages | [Content](/plugins/content) |
| `Awards` | Editions, categories, criteria, nominations (stage and status), winners | [Awards](/plugins/awards) |
| `Compliance` | Enquiries, corrections, requests and reports, suppression list, consents, retention, processors | [Compliance](/plugins/compliance) |
| `Platform` | Capabilities and roles, audit logger, settings, the Nominations switch, signed links, mail, jobs, health, cache purge | [Platform](/plugins/platform) |

The guard and the third-party choices have their own pages: [Guard](/plugins/guard) and [Third-party plugins](/plugins/third-party).

## Boundaries enforced by tests

These are not conventions. Architecture tests fail the build if they are broken.

| Rule | Why |
|---|---|
| `Awards` never references `Commerce`. No code path or join leads from commercial data to nominations, stages, status or winners | Money must not influence awards (REQ-199, 183) |
| `Commerce` and the story Sponsored label never write the Claimed mark, and no commercial change alters a profile or the order of unsponsored stories | Payment must not change a profile or the editorial order (REQ-184, 263) |
| Only `Review\StateMachine` writes a profile's publication state or lets its post status become published | One choke point for what is public (REQ-210) |
| Public templates read profiles and stories only through `PublicProfile` and `PublicStory` | A template cannot show a hidden record |
| Only `Platform\AuditLogger` writes the audit log | The log stays append-only and hash-chained |
| Every admin screen and action checks a named `wiw_*` or core capability; deny by default | No screen is open by accident |
| A new personal-data column or meta key must be listed in the PII catalogue (`config/pii.php`) | Retention, export and erase depend on it |
| No payment, card or gateway code anywhere | The site takes no payments (D-005) |

## Conventions every page follows

Each module page uses the same headings so an issue can be cut from any section:

1. Responsibility
2. Content types, taxonomies and tables
3. Admin screens
4. Capabilities
5. Public read models and endpoints
6. Jobs, hooks and events
7. Business rules
8. Tests that must exist
9. Depends on
10. Requirement ids covered

Capabilities named `wiw_*` belong to the plugin. Names without that prefix are core WordPress capabilities, including the ones WordPress generates for the profile type. The Administrator can grant or revoke any capability for a user except the few marked fixed.

## Build order

The plugin is built in the order the dependencies run: platform core first (roles, audit, settings), then directory and the state machine with the visibility matrix test, then import, review, trust, compliance, commerce and awards. The guard is built alongside the state machine because the matrix test proves both.

## What is not built

| Not built | Because |
|---|---|
| A public JSON API | There is none. Core REST is locked to staff |
| Visitor, owner, nominator or judge accounts | No public accounts exist (REQ-229) |
| Payment processing | Customers pay offline; staff record it |
| AI drafting on the site | It belongs to the acquisition pipeline |
| An SEO plugin | The guard emits SEO markup so nothing can leak |
| Articles, users, roles, sign-in and taxonomy term screens | WordPress already provides them |
