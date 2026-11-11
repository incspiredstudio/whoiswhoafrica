# Deployment

The site is built and tested locally and then moved to the live cPanel host when finished. This page covers the environments, the go-live migration, the checks on the live host, and rollback.

Decisions: D-020, D-050. Records: ADR-003, ADR-011, ADR-012. Architecture: section 8.

## Environments

| Environment | Purpose |
|---|---|
| Local | WordPress via `wp-env` or an equivalent. The main build and test place |
| Live | wiwi.africa on the existing cPanel account |
| Staging | Optional. If a staging site is public it follows D-029: real data only after the import safeguards pass (Q64) |

## Accounts

| Needed now | Later, optional |
|---|---|
| GitHub; cPanel (PHP version selector, a database and user, a mail account for SMTP) | Cloudflare, Brevo, uptime monitor, Sentry, Google Search Console |

Object storage is not planned. There is no AI provider account on the site side.

## Building a release

A release contains both themes: the parent `whoiswho` and the child `whoiswho-child`. The child is the active theme on the live site, and the deploy script checks that the child is active and that the parent is present (REQ-273).

For go-live, build an artifact containing:

- WordPress core, pinned
- plugins and the theme, from the lockfile (third-party plugins pinned from the plugin directory; verify the mechanism)
- the production Composer autoloader
- built assets and a version stamp

## The go-live migration

The finished local site is moved to the live host after the owner's approval.

1. Copy the code (SSH, rsync or the cPanel file manager).
2. Import the database and the uploads.
3. Set `wp-config.php` and the secrets file outside the web root.
4. Run the plugin's forward-only migrations.
5. Run smoke checks.

Later improvement: releases in `releases/<timestamp>/` with a `current` symlink, which makes rollback instant. This is optional.

Production runs migrations only through the deploy script, never by hand. Locally, migrations run with `php bin/wiw.php migrate`.

## Migrations

Migrations are **forward-only** and **expand then contract**: add first, remove later, so the previous release still runs against the new schema. A pre-migration database dump is taken automatically in production. Only the plugin area writes migrations.

## Checks on the live host

These run once, at import time. None of them blocks building. Each result is written to the runbook, and a failure leads to a fix or the VPS fallback.

| Check | Why |
|---|---|
| PHP version, memory and time limits | Imports and the plugin need them |
| A database and user | Required |
| SMTP sends, plus SPF, DKIM and DMARC | Deliverability |
| The uploads folder is writable | Media |
| Cron or WP-Cron runs jobs | Purge, email, imports, reminders |
| A 1,000-record import in chunks | Throughput on shared hosting |
| 410 behaviour, and purge by URL if a CDN is added | Immediate unpublish |
| TRIGGER privilege for audit-log triggers | Immutability |
| A restore test | Backups work |

## Jobs

WP-Cron with Action Scheduler runs the jobs: purge, email, import chunks, bulk unpublish chunks, sponsored apply and expiry, claim reminders, retention and backups. If the host allows it, a cPanel cron every minute can drive it instead (`DISABLE_WP_CRON`). Job timing depends on site traffic unless a real cron is added. Moving to a supervised worker on a VPS needs no code change.

## Rollback

| Layer | Rollback |
|---|---|
| Code | Switch `current` to the previous release (when release directories are used), or redeploy the previous artifact |
| Database | Restore the pre-deploy dump. Expand-then-contract migrations mean most releases need no database rollback |
| A bad import | Undo the batch |
| A bad publication | Bulk unpublish by filter, then restore by batch |

## Monitoring and runbook

Failed-job alerts and a backup check appear in wp-admin. An uptime monitor is a later, optional addition (REQ-045). The runbook, `docs/runbook.md`, covers deploy, rollback, restore, incidents, bulk unpublish, key rotation and moving to a VPS (REQ-095).

## Fallback hosting

If the live-host check fails, the same site moves to one small VPS (2 vCPU, 4 GB, in an EU or Africa region) with Nginx or Caddy, PHP-FPM, MariaDB and a supervised worker. The same release script and CI apply.

## Why not manual FTP or wp-admin updates

Manual FTP is error-prone, and updating plugins from wp-admin puts unreviewed code in production. Everything ships from the pipeline, and file modification is disabled on the live site.

## Risks to watch

| Risk | Note |
|---|---|
| Shared-host uptime against the 99.5% target (REQ-045) | The weakest link; CDN helps if added |
| Email volume | Hosts cap messages per hour; batch bulk nominee mail |
| Immediate unpublish at the edge | Origin returns 410 at once; if a CDN is added, test purge and record time to 410 |
