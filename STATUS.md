# STATUS

**Shared-docs owner: the website session** (Gate 2 and Gate 3 are done; stages are in `CLAUDE.md`). To hand over, commit and change this line.

**Updated 2026-11-14** after the no-code production build (integration rounds 9 and 10 and the release-blocker PR #825) was merged into `chore/structured-build-setup`. Release **0.2.0** (build ff6f79d) is in the local `dist/` folder (git-ignored).

**Repo layout:** `docs/` shared (a VitePress site); `site/` the WordPress code; `prototype/` the wireframe; `acquisition/` data acquisition; `handbook/` the staff handbook (VitePress). Interface between acquisition and the website: `docs/contracts/import-format.md` (**v1.1**). Other sessions keep their own status in `prototype/STATUS.md` or `acquisition/STATUS.md`.

## Where the project is

The site installs and runs with **no code and no command line**. You upload the zips in wp-admin, the Setup screen walks you through the rest, and demo content is optional (you choose the parts). This was verified on fresh production-style WordPress 7.1.4 sites on MariaDB:
- The environment was left at production and only the `dist/` zips were used.
- Email was real SMTP through FluentSMTP to mail.wiwi.africa. The site reported "delivered".
- An upgrade from 0.1.0 to 0.2.0 lost nothing.
- axe found 0 violations on 38 public pages at 360 and 1280.
- The full acquisition import (13,407 records, 5,286 open-licence images) ran through the admin Import screen with 0 failures.

The production-level test results are summarised in `docs/gaps.md` (sources section).

## What is built (in `site/`)

- **Foundations to Recognition (M0 to M4):** as before. These cover the profile record and review state machine, imports, suppression, corrections, search, stories, newsletter capture, claims, enquiries, sponsored stories, data rights, awards and nominations, events, comments and pages. See `docs/plugins/*` and `docs/pages/*`.
- **No-code setup:**
  - Site keys are generated automatically.
  - Mail goes through FluentSMTP, or the SMTP constants, or plain `wp_mail`; FluentSMTP's email logging is forced off.
  - Turnstile keys are entered on the Setup screen.
  - The Setup checklist (11 lines) has a guarded **Update the database** button for live updates, after a backup.
  - The Required plugins screen installs and activates FluentSMTP and wpDiscuz in one click.
  - Two-factor is available and recommended, not enforced (D-070).
  - Plain-language messages throughout; MySQL or MariaDB only (no SQLite or WordPress Studio).
- **Profiles:**
  - Bio, Achievement, Influence and Legacy sections on every type, with lifespan and field for people.
  - Open-licence photos and logos, with a credit line.
  - Edit profile has paragraph boxes with counters and a photo panel; Directory > Add profile (`wiw_add_profile`) has a duplicate check, and new profiles enter the review queue.
  - Non-Latin profile URLs work. List fields keep accents and quotes, and the database update repairs earlier damage.
- **Import (contract v1.1):**
  - Bundles come in by zip upload (sent in 1 MB pieces) or by "Import from the server" (`wp-content/uploads/wiw-import/`).
  - Dry run, "Add all as new list entries", chunked and resumable apply, and undo, then re-import.
  - The exporter is `acquisition/pipeline/export_bundle.py` (full export or `--sample 50`).
- **Demo content:**
  - A chooser on the Setup screen (profiles, stories, comments, events, awards, curation, pages, settings), with per-part removal that keeps anything staff edited.
  - The 50 real sample profiles ship only inside the plugin zip (`SAMPLE_BUNDLE`, never committed); demo awards use invented stand-ins.
- **Theme:**
  - Matches the prototype pages, using only the four approved Adinkra marks.
  - Three.js is replaced by a small WebGL helper; CSS went from 179 KB to 104 KB; story CLS is 0.
  - wpDiscuz is styled with tokens.
- **Security:** the review is in `docs/security-review.md` (no Critical or High findings; SEC-01 to SEC-06 fixed). The guard's HTTPS redirect waits for an https site address. Theme and plugin zip updates work with the guard active (update the guard first).
- **Docs:**
  - The staff handbook in `handbook/` (getting started, staff tasks, operations, troubleshooting).
  - `site/deploy/install-bundle/INSTALL.md`.
  - The gaps record in `docs/gaps.md`.

## What remains

- **Owner decisions:** #813 to #821 (G1 to G9 in `docs/gaps.md`). First is #813, the guard's DISALLOW_FILE_MODS/EDIT notice, which shows on every no-code install. Then #814 two-factor before go-live, and #815 cleaning up the 2,437 raw list values created by the full import before profiles go public.
- **Follow-ups:** #822 (gaps G10 to G53, each with a recommended option).
- **Human-only issues from earlier:** licences (#8, #31), host check (#37), mailbox and DNS (#1, #38), data-rights period (#544), legal pages and backups (deferred), the live migration (#128).
- Run `gh issue list --state open` for the full list.

## How to run it

- **No-code install:** follow `dist/INSTALL.md` (or `site/deploy/install-bundle/INSTALL.md`) and the handbook's Getting started section.
- **Build the bundle:** in `site/`, `composer install && npm ci`, then `SAMPLE_BUNDLE=<path to sample-50.zip> npm run package`. The parts must share one version.
- **Developers:** in `site/`, `composer test`, `composer lint`, `composer analyse`, `npm run build`, `npx playwright test`. Set `git config core.autocrlf false` in worktrees; `.gitattributes` forces LF.

## CI gates (every pull request)

Eleven checks in `.github/workflows/ci.yml`: lint, analyse, test, visibility, build, e2e, audit, plugins, brand, secrets, pr-body.

## Decisions and questions

See `docs/decisions.md` (D-070: two-factor optional) and `docs/questions.md`, which records the owner answers of 2026-11-13 and 2026-11-14 (no-code setup, MySQL only, the Adinkra symbols, the guard kept as is, real sample profiles only in the bundle).

## Next up

- Owner: decide #813 to #821; check the test inboxes (+prodtest, +clean, +final, +upgrade) for delivery.
- Website session: the #822 follow-ups. Before going live, measure directory and search with published profiles, and add a cPanel cron.
- Acquisition session: a curated term map (G3), provenance labels (G10), and the README update for the exporter (G15).
