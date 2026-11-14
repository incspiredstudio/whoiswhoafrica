# CLAUDE.md

## Session
- Start: read STATUS.md and your folder's status file; run `gh issue list --state open`; read only the docs needed.
- End: the shared-docs owner updates STATUS.md; any other session updates its folder's status file (`prototype/STATUS.md` or `acquisition/STATUS.md`). Then commit.

## Repo layout and ownership
- `docs/` = shared product docs. `prototype/` = the wireframe. `acquisition/` = data acquisition (`docs/`, `pipeline/`, git-ignored `data/`, `exports/` with no personal data). `site/` = the WordPress website code (plugins, theme, tests, deploy), created at M0 and written only by the website build sessions (Q80).
- **Sessions write only inside their own folder:** the wireframe session in `prototype/`, the acquisition session in `acquisition/`.
- **Shared docs are edited by one session at a time, the owner named in STATUS.md.** Shared docs are everything outside those two folders: `docs/`, `CLAUDE.md`, `STATUS.md`, `scripts/`, `.github/`, `.gitignore`. To hand over, the owner commits and changes the owner line in STATUS.md.
- A session that is not the owner never edits shared docs. It lists what it needs under "For shared docs" in its folder's status file; the owner applies it.
- **One exception:** the wireframe session may regenerate `docs/wireframe-inventory.md` with `scripts/build-inventory.js`, and edit `docs/wireframe-findings.md`, the build script and `scripts/smoke-prototype.js`.
- **`docs/contracts/import-format.md` is the only interface between acquisition and the website.** Neither side reads or writes the other's folders or data. Only the shared-docs owner changes it, and the other side confirms.
- Sessions running at the same time use separate git worktrees, so one session's branch switch cannot change another's files.
- Every REQ, D, C and Q has a Scope: SITE, ACQUISITION or BOTH. Never renumber.

| Stage | Shared-docs owner |
|---|---|
| Restructure, until `chore/repo-restructure` is merged | Restructure session |
| Pending content amendments and wireframe review | **Wireframe session** |
| Gate 2: architecture revision for WordPress, and settling the contract's unknowns | Website session |
| Gate 3: `/plan`, then the website build | Website session |
| Acquisition runs | Stays with the website session. The acquisition session writes only in `acquisition/`, and asks for any change to shared docs. |

## Pipeline
review-spec -> architect -> plan -> issues -> build waves -> verify -> ship.
Human gates after: the question list, the architecture, the plan.

## Rules
- Every requirement is REQ-### in docs/requirements.md; every issue, PR and test cites REQs.
- No scope creep: discoveries become new issues labelled `type:discovered`.
- Never guess on blocking unknowns: write them to docs/questions.md (or, if you are not the shared-docs owner, under "For shared docs" in your folder's status file) and stop.
- Only a separate reviewer step marks work done. Tests first.
- Never commit secrets, never read `.env*`, never force-push. Never commit personal data: anything fetched, drafted or staged stays in `acquisition/data/`.
- One branch + PR per issue ("Closes #N").
- Speed: finish as early as possible; deadlines in the brief are ceilings, not targets; parallelise; only external lead times and human approvals are real constraints.
- Terms and ID codes (REQ, C, Q, D, N, M, ADR) are explained in docs/GUIDE.md; use them consistently.

## Commands
Stack: WordPress (current stable) built locally and migrated to the live cPanel host (Cloudflare optional later); email through cPanel SMTP; media in the WordPress media library; custom plugin `whoiswho-core`, must-use plugin `whoiswho-guard`, parent theme `whoiswho` plus child theme `whoiswho-child` (the child is the active theme; brand values and site-specific overrides live in the child); MariaDB/MySQL; server-rendered PHP templates. See docs/architecture.md (proposed v2 until Gate 2 is approved). Website code lives in `site/`. The script names below are the plan; the M0 scaffold creates them (verify).
- Install (in `site/`): `composer install` and `npm ci`; copy `.env.example` to the secrets file yourself (agents never read `.env*`)
- Local environment: `npx wp-env start` (needs Docker; verify) or an equivalent local PHP and MariaDB
- Test: `composer test` (PHPUnit on the WordPress test suite; one test: `composer test -- --filter REQ_208`); architecture tests: `composer test -- --testsuite Arch`
- Style: `composer lint` (PHPCS with the WordPress coding standards; CI runs the same)
- Static analysis: `composer analyse` (PHPStan with a WordPress extension; level agreed at M0)
- Build assets: `npm run build`
- E2E and accessibility: `npx playwright test`
- Dependency audit: `composer audit` and `npm audit --audit-level=high`
- Migrate: `php bin/wiw.php migrate` (local); production runs it through the deploy script. On a live site the Administrator can also apply pending migrations from the Setup screen's guarded "Update the database" button, after a backup
- Run queued jobs locally: `php bin/wiw.php jobs:run`
- Go live: migrate the finished local site to the live host with the checklist in docs/architecture.md section 8, after your approval

## Conventions
- Areas and ownership of the website code (edit only your area; cross-area needs become an issue): plugin = `site/wp-content/plugins/whoiswho-core/**`, `site/wp-content/mu-plugins/**`, `site/bin/`, tests/Unit|Integration|Arch; theme = `site/wp-content/themes/whoiswho/**` (parent) and `site/wp-content/themes/whoiswho-child/**` (child), tokens file, assets, UI specs in tests/e2e; infra = .github/, deploy/, scripts/, site/composer.json and lockfile, the wp-config template, docs/runbook.md, .env.example; qa = tests/e2e, tests/Visibility, tests/Security, docs/traceability.md, docs/verify-*.md.
- Domain code lives in the plugin under `src/{Directory,Review,Import,Trust,Commerce,Content,Awards,Compliance,Platform}`. Admin screens and handlers stay thin and call domain services.
- Boundaries (enforced by architecture tests): Awards never references Commerce; Commerce and the Sponsored label never write Verified or Featured; only `Review\StateMachine` writes a profile's `pub_state` and publish status; only `Platform\AuditLogger` writes the audit log; public templates read profiles and stories only through the `PublicProfile` and `PublicStory` read models, never `get_post_meta` or `WP_Query` on the profile type.
- Test names cite REQs, e.g. `test_REQ_208_hides_unreviewed_profiles_from_sitemap`. Write the test first.
- Branches: `area/issue-N-short-slug`. Commits: imperative, cite REQ. PR body: `Closes #N`, REQ list, test evidence.
- Migrations: forward-only, additive first (expand/contract); only the plugin area writes them; money is integer kobo plus currency.
- WordPress rules: escape all output (`esc_*`, `wp_kses` with an allowlist), verify nonces on admin actions, check a named `wiw_*` capability on every admin screen and action (deny by default), use `$wpdb->prepare` for any variable in SQL, validate input with one validator per form. No `eval`, no raw `$_GET`/`$_POST` use outside the validators.
- Only the Administrator changes roles or capabilities; roles are default bundles of capabilities and menus follow effective capabilities. Add a new capability to the matrix in docs/architecture.md and to its test.
- Every staff action on profiles, labels, the register, imports, bulk unpublish, suppression, the Nominations flag and capability grants goes through `AuditLogger`. The only feature flag is Nominations, read through the settings service.
- No brand values (colours, fonts, logo) outside the theme tokens file. No secrets in code: `wp-config.php` is a template that loads the secrets file outside the web root; `.env.example` lists names only.
- No PII in logs or error messages; new PII columns or meta keys must be added to the PII catalogue (`whoiswho-core/config/pii.php`).
- No payment processing, card data or gateway code anywhere (D-005).
- Never read or write real secrets; use `.env.example` names only.
