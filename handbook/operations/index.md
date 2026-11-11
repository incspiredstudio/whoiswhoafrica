# Operations

This section is for whoever keeps the site running: launching, backing up, updating, securing and recovering it. WordPress is built and tested locally and then moved to the live cPanel host. Everything that ships goes through a release, and file editing is switched off on the live site.

## Pages in this section

| Page | Covers |
|---|---|
| [Launch checklist](/operations/launch-checklist) | The items that must be true before and at go-live, and how to move the site |
| [Backups](/operations/backups) | What is backed up, how often, and how to test a restore |
| [Updates](/operations/updates) | How core, plugin and theme updates are applied |
| [Security basics](/operations/security) | Sign-in, hardening, secrets, validation and logging |
| [Runbook outline](/operations/runbook) | The procedures the runbook holds |

## The stack in brief

| Layer | Choice |
|---|---|
| CMS | WordPress, current stable, with PHP 8.3 recommended |
| Front end | Parent theme `whoiswho` and active child theme `whoiswho-child`, server-rendered PHP templates |
| Admin | wp-admin with custom roles and capabilities |
| Database | MariaDB 10.11 or newer, or MySQL 8.0 or newer |
| Staff sign-in | Closed registration and enforced two-factor authentication |
| Email | cPanel SMTP through WordPress, with SPF, DKIM and DMARC on the domain |
| Media | The WordPress media library. Private files live outside the web root. |
| Search | A flattened full-text table built from public states only |
| Jobs | WP-Cron with Action Scheduler |
| Payments | None. The site never processes payment. |
| Analytics | Cookieless, first-party counters |

There is no SEO plugin. The guard plugin writes all search markup, so nothing can add a sitemap, feed or structured-data entry for a profile that is not public.

## Environments

| Environment | Purpose |
|---|---|
| Local | WordPress via `wp-env` or an equivalent. The main build and test place. |
| Live | The production site on the cPanel account |
| Staging | Optional. A public staging copy follows the same rules as live. |

## Scheduled jobs

WP-Cron with Action Scheduler runs these jobs: cache purge, email, import chunks, bulk unpublish chunks, sponsored slot start and end, claim reminders, data retention and backups. If the host allows it, a cPanel cron every minute can drive WordPress's scheduler instead, which makes timing independent of site traffic.
