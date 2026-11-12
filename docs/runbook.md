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

### Personal-data breach procedure (M3-PRV-18, REQ-066)

This is an offline procedure. There is no breach-log screen and no automatic regulator notification (D-067). A breach is any loss, theft, unauthorised access, disclosure or alteration of personal data held by the site: staff or enquirer details, claim and correction records, newsletter subscribers, the audit log, backups, or a leaked secret that gives access to them. Treat a suspected breach as a breach until it is ruled out.

**Clock.** Write down the time you became aware (UTC). The regulator and affected-people notices are due within the legal deadline for the data subjects' country; the usual outer limit is 72 hours from awareness (Nigeria Data Protection Act, GDPR). The Administrator confirms the exact rule with Legal at the first step and records it in the incident note. Do not wait for certainty before starting the clock.

**1. Detect and report.** Anyone who sees a sign (unexpected admin login, audit-log gap, alert from a failed job, a host or plugin security notice, a report from a user, a lost laptop or backup) tells the Administrator immediately. Sources to check: the Audit log screen and its verify action (`php bin/wiw.php audit:verify` once M0-PLG-07 is merged), the host's access logs, failed-login alerts.

**2. Contain** (first hour; the person on call does these without waiting for approval):

- [ ] Take the site to maintenance or switch off the affected form or screen (the Nominations flag, a plugin) if data is still leaking.
- [ ] Revoke the access that was used: disable the staff account, rotate the secret involved (see Key rotation), rotate the WordPress salts to end all sessions, rotate the database password and SMTP credentials if the host is suspect.
- [ ] Preserve evidence before changing anything: copy the host logs and a database dump (encrypted with `BACKUP_PASSPHRASE`) to the restricted incident folder.
- [ ] Do not delete data and do not restore a backup over the evidence until it is copied.

**3. Assess** (within 24 hours):

- [ ] What kinds of data (categories only, for example "email addresses of enquirers"), how many people, which countries.
- [ ] How it happened, when it started, whether it has stopped.
- [ ] Likely harm to the people: low, or likely to cause risk to their rights; high if contact details with context, or anything that could expose someone to harm.
- [ ] Whether a processor (host, email provider) is involved; ask them for their own report.
- [ ] Decision: notify the regulator? notify the people? Record who decided and why. A breach unlikely to risk people's rights may need only an internal record; when in doubt, notify.

**4. Notify** (inside the legal deadline):

- [ ] Regulator: the Administrator sends the notice (what happened, categories and approximate numbers, likely consequences, measures taken, contact point). A first notice may be incomplete; send it on time and follow up.
- [ ] Affected people, when the risk to them is high: plain language, what happened, what it means for them, what we have done, what they should do, who to contact. Send from the support address, not through bulk newsletter tooling.
- [ ] Processors and partners who need to act.

**5. Recover and learn:**

- [ ] Fix the cause through the normal pipeline (issue, tests, pull request); a discovered weakness becomes an issue labelled `type:discovered`.
- [ ] Restore service; run the smoke check (`php deploy/smoke-check.php`) and the visibility matrix.
- [ ] Hold a short review within two weeks; update this procedure if it failed.

**Incident log.** Each incident is one dated entry kept in this section (or in the restricted incident folder when it must name data locations). Entries never contain personal data: no names, emails, profile text from a request, or request bodies; refer to people by count and category. Template:

```
### Incident YYYY-MM-DD-NN
- Aware at (UTC):            Reported by (role):
- Contained at (UTC):        Deadline for notices (UTC):
- What happened (no personal data):
- Data categories and approximate number of people:
- Countries of the people:   Processor involved:
- Likely harm:               Decision on regulator / people, by whom, why:
- Regulator notified (date, reference):   People notified (date, how):
- Cause and fix (issue and PR numbers):
- Review held (date) and changes made:
```

No incident has been recorded yet.

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
