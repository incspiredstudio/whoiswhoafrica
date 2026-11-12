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

## Live-host check results

Filled in at M5 from the checks listed in Deployment (Q87 still open).

| Check | Result | Date |
|---|---|---|
| PHP version and extensions | TODO | |
| Memory limit | TODO | |
| Cron and scheduled jobs | TODO | |
| Inodes and disk | TODO | |
| Outgoing mail limits | TODO | |

## PHP version header (REQ-046, #594)

The guard calls `header_remove('X-Powered-By')` on every response. As a second layer, set `expose_php = Off` in the live host's php.ini (cPanel: MultiPHP INI Editor) and check with `curl -sI https://<site> | grep -i x-powered-by`, which must print nothing. Record the result in the check table above.

## Who receives alerts

TODO. Owner: M5 (monitoring and launch).
