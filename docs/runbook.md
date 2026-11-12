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

## Performance budget (REQ-041, REQ-042)

Run on a served site: `BASE_URL=http://127.0.0.1:<port> WIW_THEME_READY=1 WIW_PERF=1 npx playwright test` (in `site/`). It loads each page three times cold-cache in Chromium at 412x823 with 4x CPU slowdown, 1.6 Mbps down, 750 kbps up and 150 ms round trip, and takes the median LCP, CLS, script KB and total KB. The numbers it enforces live in `site/tests/e2e/perf/budgets.json`. CI runs it as a non-blocking step of the e2e job.

Baseline on the local PHP built-in server (Windows, no page cache; time to first byte is about 1.1 s there, so LCP here is a worst case, to be re-measured on the staging host):

| Page | LCP ms | CLS | Script KB | Total KB |
|---|---|---|---|---|
| / | 4532 | 0 | 11.3 | 721 |
| /directory/ | 3332 | 0 | 9.9 | 375 |
| /stories/ | 3320 | 0 | 9.9 | 371 |
| /events/ | 3316 | 0 | 9.9 | 371 |
| /contact/ | 2808 | 0 | 9.9 | 372 |

Proposed ceilings (for the owner to agree; docs/theme/theme-acceptance.md still lists the numeric budget as an open point): LCP 6000 ms enforced now with 2500 ms the REQ-041 target reported as a warning, CLS 0.1, scripts 20 KB, page weight 900 KB. Tighten LCP to 2500 ms once measured on the staging host.

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
