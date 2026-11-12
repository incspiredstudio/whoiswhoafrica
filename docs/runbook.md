# Operations runbook

Skeleton created at M0. Each section names the issue that completes it; "TODO" marks only what a later issue fills. No secret values belong here. Context: [Deployment](/build/deployment), [Security](/build/security), [Launch checklist](/build/launch-checklist).

## Local environment

- WordPress Studio is the main path: `powershell -File deploy/local/setup.ps1` in `site/`. Optional: `npx wp-env start` with Docker. See `site/README.md`.
- Studio uses SQLite; PHPUnit integration tests needing MariaDB wait on the test-environment issue (#366). Mail catcher: #367.
- Owner issue: M0-INF-02 (done).

## Code quality settings

| Tool | Setting | Owner issue |
|---|---|---|
| PHPCS | WordPress-Extra, WordPress-Docs, PHPCompatibilityWP 8.3+, prefixes `wiw`, `WIW`, `WhoIsWho` | M0-INF-03 |
| PHPStan | Level 6 with the WordPress extension, no baseline; raise per module | M0-INF-04 |
| Assets | esbuild, content-hashed output in `dist/` | M0-INF-06 |

## Deploy

TODO. Owner: M0-INF-12 (release artifact script), M0-INF-13 (go-live migration) and M5.

### Smoke check after every deploy (M5-LCH-03)

From the site folder on the host: `php deploy/smoke-check.php --url=https://<site> --gone-path=/profile/<an-unpublished-slug> [--healthz-token=<token>]`. It prints one PASS, FAIL or SKIP line per check and exits non-zero if any check fails (a run with no passes also fails). Staging and live use the same command. Paste the output into the release notes. Add `--no-wp` to run only the HTTP checks from another machine.

## Updates and plugin policy (M5-OPS-08, launch checklist items 8 and 9; REQ-046, REQ-065)

**Rule.** Nothing updates itself on the live site and nothing updates from wp-admin. `AUTOMATIC_UPDATER_DISABLED` and `WP_AUTO_UPDATE_CORE` are off in `wp-config.php`, the guard mu-plugin turns off core, plugin, theme and translation auto-updates, and file editing is disabled. Every update, core included, is tried locally, ships as a release through the pipeline (`deploy/build-release`, then `deploy/migrate-live`) and is applied by the Administrator.

> Reading of the brief: "core minor and security updates are automatic" is met by applying them promptly through the pipeline, not by switching WordPress auto-update on. This keeps one deploy path and means a release is always the thing that was tested. If the owner prefers WordPress's own minor auto-update, change `WP_AUTO_UPDATE_CORE` to `'minor'` in the wp-config template and remove the `auto_update_core` filter in the guard; nothing else depends on it.

| Kind of update | Where tried first | When applied | By |
|---|---|---|---|
| Security fix (core, plugin, theme, dependency) | Locally, with `composer test` and the visibility matrix | Within 48 hours of the advisory | Administrator |
| Core minor release | Locally | Weekly batch, or with the next security fix | Administrator |
| Core major release | A local copy of the live data; full test suite, e2e and accessibility runs | After the local test passes and the owner approves; never in the weekly batch by default | Administrator, owner approves |
| Plugin and theme updates, PHP and JavaScript dependencies | Locally | Weekly batch (Monday, after the Dependabot pull requests arrive) | Administrator |

**How an update ships.**

1. Dependabot opens weekly pull requests (Mondays: Composer and npm in `site/`, GitHub Actions at the root). Auto-merge is not enabled; an Administrator reads each one.
2. Check the changelog for breaking or data-handling changes, run `composer test`, `composer test -- --testsuite Visibility`, `composer analyse`, `composer audit` and `npm audit --audit-level=high`, and let CI run the same gates.
3. Merge, build the release, apply with the go-live script, run the smoke check, record the result in the release notes.
4. A security advisory that arrives outside the weekly batch starts the 48-hour clock when it is published; do not wait for Monday.

**Adding or changing a plugin.** Every plugin is pinned (`composer.lock`) and listed with its reason in `site/config/plugin-allowlist.json`; `node scripts/check-plugins.js` fails the build if the two disagree or a forbidden kind is added (SEO, public login or membership, payment, AI drafting, page builder). The change needs a pull request citing [Third-party plugins](/plugins/third-party), a privacy check, an entry in the processor register when it touches personal data, and a passing visibility matrix: the `visibility` CI job runs the whole matrix on every pull request, so a plugin cannot merge without it. Rerun it locally with `composer test -- --testsuite Visibility` before opening the pull request.

**Weekly custom-plugin review** (scheduled in the Administrator's calendar, launch checklist item 8; D-060). Covers `whoiswho-core`, `whoiswho-guard` and the two themes:

- [ ] List the commits merged since last week (`git log --since="1 week ago" -- site/wp-content`); read the diff of each, not just the titles.
- [ ] Look for the risk patterns: unescaped output, a missing nonce or capability check, raw `$_GET` or `$_POST`, unprepared SQL, `eval`, a new outbound request, a new place that reads profiles outside the `PublicProfile` and `PublicStory` read models, anything that writes `pub_state`, Verified or Featured outside its one owner.
- [ ] Confirm the test and architecture suites are green on the default branch and the visibility matrix covers any new public surface.
- [ ] Check new personal-data fields are in `config/pii.php` and new processors are on the register.
- [ ] Update the dependency register: each package, version, licence, last review date; note anything abandoned or with an open advisory.
- [ ] Look at the failed-job alerts, the audit log verify result and the backup check.
- [ ] Record the date, reviewer and findings (issues opened for anything found) in the release notes.

## Rollback

TODO. Owner: M0-INF-12. Rule: migrations are forward-only; roll back code, not the database (see Deployment).

## Backup restore

TODO. Owner: M5 (restore test). Backups are cPanel daily database and weekly files with an off-server copy (launch checklist items 1 and 2).

## Incidents

TODO. Owner: M5. Who is paged, first checks, how to take the site to maintenance, how to write the incident note.

## Bulk unpublish

TODO. Owner: the bulk unpublish issue in M1. Capability, the audit record it writes, and how to reverse it.

## Key rotation

Keys to rotate (values live only in the secrets file, mode 600, outside the web root):

| Key | Rotate when | Steps | Owner issue |
|---|---|---|---|
| SMTP credentials | Staff change, suspected leak | TODO | M0-EML-01 |
| Signed-link signing key (`SIGNED_LINK_KEY`) | Suspected leak; invalidates outstanding links | TODO | M0-PLG-17 |
| Form-token signing key (`FORM_TOKEN_KEY`) | Suspected leak | TODO | M0-PLG-18 |
| Backup passphrase (`BACKUP_PASSPHRASE`) | Staff change | TODO | M5 |
| WordPress salts | Suspected session theft; logs everyone out | TODO | M0-INF-11 follow-up |
| Health-check token (`HEALTHZ_TOKEN`) | Suspected leak | TODO | M5 |

## Moving to a VPS

TODO. Owner: M5. Fallback hosting is described in Deployment.

## Live-host check results

Filled in at M5 from the checks listed in Deployment (Q87 still open).

| Check | Result | Date |
|---|---|---|
| PHP version and extensions | TODO | |
| Memory limit | TODO | |
| Cron and scheduled jobs | TODO | |
| Inodes and disk | TODO | |
| Outgoing mail limits | TODO | |

## Who receives alerts

TODO. Owner: M5 (monitoring and launch).
