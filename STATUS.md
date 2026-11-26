# STATUS

**Shared-docs owner: the website session** (Gate 2 and Gate 3 are done; stages are in `CLAUDE.md`). To hand over, commit and change this line.

**Updated 2026-11-26.** **The site is live at https://wiwi.africa** with all imported profiles (see "Live site" below).
- The latest release is **0.3.4** (PR #878, build 72f0408), in the local `dist/` folder (git-ignored). Earlier builds are kept in `dist-previous-0.3.x/`.
- Since then, #882 (the cache-safe CSP, which closes #719) has been merged. It ships in the next release.

**Repo layout:** `docs/` shared (a VitePress site); `site/` the WordPress code; `prototype/` the wireframe; `acquisition/` data acquisition; `handbook/` the staff handbook (VitePress). Interface between acquisition and the website: `docs/contracts/import-format.md` (**v1.1**). Other sessions keep their own status in `prototype/STATUS.md` or `acquisition/STATUS.md`.

## Live site (2026-11-26)

- **Host:** cPanel with nginx in front; table prefix `wpup_`. The live install runs 0.3.4. The guard sits in `wp-content/mu-plugins`; it had first been extracted into `plugins/` by mistake and was moved.
- **Content:** copied from the finished local test site (MariaDB `wiw_final`, served at 127.0.0.1:8990), not re-imported. Re-importing would have needed a second review of every profile, and the Review queue has no bulk approve.
  - **Database:**
    - A copy of the database had every `127.0.0.1:8990` address changed to `https://wiwi.africa`, with a serialisation-safe replace.
    - Transients, sessions and the local FluentSMTP settings were removed, and the tables were renamed to `wpup_`.
  - **Kept from live:**
    - the live site's own accounts;
    - its email settings (FluentSMTP and the admin email);
    - its email log.
  - **Accounts:** the local `owner` and `editor` accounts were not copied. Their pages now belong to the live Administrator.
  - **Triggers:** the four append-only triggers (audit log and profile versions) went in as a separate file, without a DEFINER.
  - **Photos:** `uploads` (28,755 files, about 600 MB) was copied by zip, without `wiw-private` and `wiw-import`.
  - **Files:** the copy files are in `D:\backups\golive\` on the owner's machine, not in git.
- **Result:**
  - 13,377 profiles are live.
  - 38 profiles held by the free-text scan wait in the Review queue. They must be approved by a different account from the importer.
  - Checked from outside the host:
    - home, Directory, People, search, profile pages and photos load;
    - HTTP redirects to HTTPS;
    - the security headers are present;
    - the sitemap lists the profiles;
    - search engines are allowed.
- **From now on the live database is the master** (Q90). Never copy a local database over live again. Updates go in as zips (the guard first) plus the Setup screen's **Update the database**. New content goes in through the Import screen or wp-admin.
- **#128 (the live migration) stays open.** The content move is done and recorded there, but its launch-checklist evidence (smoke script, firewall decision Q85, 24-hour watch) is still to come.
- **Keep the page cache off on live** until the next release (with #882) is installed and #880 has been checked on the host.
- **Still to do on live:**
  - delete `wp-content/uploads-old` once the site is checked;
  - submit the sitemap to Google Search Console;
  - review the 38 held profiles;
  - add an Editor account;
  - #881 (the CSP blocks Elementor's inline script and its Google Fonts).

## Where the project was on 2026-11-14 (release 0.2.0)

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
  - Matches the prototype pages, using only the four approved brand marks (D-073).
  - Three.js is replaced by a small WebGL helper; CSS went from 179 KB to 104 KB; story CLS is 0.
  - wpDiscuz is styled with tokens.
- **Security:** the review is in `docs/security-review.md` (no Critical or High findings; SEC-01 to SEC-06 fixed). The guard's HTTPS redirect waits for an https site address. Theme and plugin zip updates work with the guard active (update the guard first).
- **Docs:**
  - The staff handbook in `handbook/` (getting started, staff tasks, operations, troubleshooting).
  - `site/deploy/install-bundle/INSTALL.md`.
  - The gaps record in `docs/gaps.md`.

## What remains

- **Go-live follow-ups:**
  - a release with #882;
  - #880: prove the page cache end to end on the host before turning it on;
  - #881: Elementor and the CSP.
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

- Website session: build release 0.3.5 with #882 and install it on live. Upload the guard zip into `mu-plugins` first.
- Owner: decide #813 to #821; check the test inboxes (+prodtest, +clean, +final, +upgrade) for delivery.
- Website session: the #822 follow-ups. Before going live, measure directory and search with published profiles, and add a cPanel cron.
- Acquisition session: a curated term map (G3), provenance labels (G10), and the README update for the exporter (G15).
