# Operations

This section is for whoever keeps the site running: updating, backing up, securing and fixing it. Everything here is done in wp-admin and cPanel, without code. Installing the site for the first time is in [Getting started](/start/).

## Pages in this section

| Page | Covers |
|---|---|
| [Launch checklist](/operations/launch-checklist) | The items that must be true before and at go-live |
| [Backups](/operations/backups) | Taking cPanel backups, restoring, and testing a restore |
| [Updates](/operations/updates) | Updating WordPress, plugins and the WHOISWHO bundle |
| [Security basics](/operations/security) | Sign-in, passwords, hardening and everyday security |
| [Troubleshooting](/operations/troubleshooting) | Email not sending, upload too large, white screen, Setup red items, "needs MySQL or MariaDB" |
| [Who to contact](/operations/contacts) | Who handles what, and what to send when you ask for help |
| [Runbook outline](/operations/runbook) | The procedures the runbook holds |

## The stack in brief

| Layer | Choice |
|---|---|
| CMS | WordPress, current stable, on PHP 8.3 or newer |
| Host | A cPanel account |
| Front end | Parent theme `whoiswho` and active child theme `whoiswho-child`, server-rendered PHP templates |
| Admin | wp-admin with custom roles and capabilities |
| Database | MariaDB 10.11 or newer, or MySQL 8.0 or newer. SQLite (WordPress Studio) is not supported. |
| Staff sign-in | Closed registration. Two-factor sign-in available and recommended, not enforced. |
| Email | A cPanel mail account through the FluentSMTP plugin, with SPF, DKIM and DMARC on the domain |
| Comments | wpDiscuz, on stories only, every comment held for moderation |
| Spam protection | Cloudflare Turnstile on every public form |
| Media | The WordPress media library. Private files live outside the web root. |
| Search | A flattened full-text table built from public states only |
| Jobs | WP-Cron with Action Scheduler |
| Payments | None. The site never processes payment. |
| Analytics | Cookieless, first-party counters |

There is no SEO plugin. The guard plugin writes all search markup, so nothing can add a sitemap, feed or structured-data entry for a profile that is not public.

## Environments

| Environment | Purpose |
|---|---|
| Live | The production site on the cPanel account |
| Staging | Optional. A copy on a subdomain for trying updates and restores. It follows the same rules as live and is hidden from search engines. |
| Development | The development team's own machines, where new bundles are built and tested |

## Scheduled jobs

WP-Cron with Action Scheduler runs these jobs: cache purge, email, import chunks, bulk unpublish chunks, sponsored slot start and end, claim reminders and data retention. WP-Cron runs when someone visits the site. If jobs seem slow on a quiet site, ask the development team or the host to set up a cPanel cron job that runs WordPress's scheduler every few minutes.
