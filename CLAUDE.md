# CLAUDE.md

## Session
- Start: read STATUS.md and your folder's status file; run `gh issue list --state open`; read only the docs needed.
- End: the shared-docs owner updates STATUS.md; any other session updates its folder's status file (`prototype/STATUS.md` or `acquisition/STATUS.md`). Then commit.

## Repo layout and ownership
- `docs/` = shared product docs. `prototype/` = the wireframe. `acquisition/` = data acquisition (`docs/`, `pipeline/`, git-ignored `data/`, `exports/` with no personal data).
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
Stack: Laravel (PHP 8.3), MySQL/MariaDB, Filament, Blade + Tailwind + Alpine, Vite. See docs/architecture.md (proposed until Gate 2 is approved).
- Install: `composer install` and `npm ci`; copy `.env.example` to `.env` yourself (agents never read `.env*`)
- Run: `php artisan serve` and `npm run dev`; queue: `php artisan queue:work --stop-when-empty`
- Test: `./vendor/bin/pest` (one test: `./vendor/bin/pest --filter "REQ-208"`)
- Style: `./vendor/bin/pint` (CI uses `--test`)
- Static analysis: `./vendor/bin/phpstan analyse` (Larastan, level 6 or higher)
- Build assets: `npm run build`
- E2E and accessibility: `npx playwright test`
- Dependency audit: `composer audit` and `npm audit --audit-level=high`
- Migrate: `php artisan migrate` (local); production uses `--force` only through the deploy script
- Deploy: through GitHub Actions only (staging on merge to main; production after manual approval)

## Conventions
- Areas and ownership (edit only your area; cross-area needs become an issue): backend = app/, database/, routes/, config/ (not theme), tests/Feature|Unit|Arch; frontend = resources/views, resources/css, resources/js, config/theme.php, public/; infra = .github/, deploy/, scripts/, docs/runbook.md, .env.example; qa = tests/e2e, tests/Visibility, tests/Security, docs/traceability.md, docs/verify-*.md.
- Domain code lives in app/Domain/{Directory,Acquisition,Review,Trust,Commerce,Content,Awards,Compliance,Platform}. Controllers and Filament resources stay thin and call domain services.
- Boundaries (enforced by arch tests): Awards never references Commerce; Commerce never writes Trust fields; only Review\PublishRevision sets profiles.published_revision_id; public pages read only through PublicProfile / PublicArticle read models.
- Test names cite REQs, e.g. `it('REQ-208 hides unreviewed profiles from the sitemap')`. Write the test first.
- Branches: `area/issue-N-short-slug`. Commits: imperative, cite REQ. PR body: `Closes #N`, REQ list, test evidence.
- Migrations: forward-only, additive first (expand/contract); only the backend area writes them; money is integer kobo plus currency.
- Validation in Form Requests; authorisation in Policies; every staff action on profiles, register, labels, flags, notes goes through AuditLogger; feature flags through Flags::on().
- No brand values (colours, fonts, logo) outside config/theme.php. No `env()` outside config/. No `{!! !!}` on user data. No raw SQL with user input.
- No PII in logs, Sentry or error messages; new PII columns must be added to config/pii.php.
- No payment processing, card data or gateway code anywhere (D-005).
- Never read or write real secrets; use `.env.example` names only.
