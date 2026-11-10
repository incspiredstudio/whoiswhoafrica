# Stack

The technology choices for each layer, why each fits, and what was rejected. The choices keep the site small, cheap to host and easy for agents to test.

Records: [ADR-001 to ADR-013](/build/). Decisions: D-031, D-049, D-050, D-053. Full detail: section 2 of the architecture.

## Shape

WordPress, built and tested locally, then migrated to the existing cPanel account. Product rules sit in one plugin, one guard plugin and one theme. The database is the MariaDB or MySQL that cPanel provides. WordPress tables hold content and users. Custom tables hold what WordPress does poorly.

## Layer by layer

| Layer | Choice | Why | Rejected |
|---|---|---|---|
| CMS | WordPress, current stable, PHP 8.3 recommended (verify at build) | Your decision (D-031); native to cPanel; editors know it | Laravel and Filament (the earlier draft); headless CMS options |
| Public front end | Server-rendered PHP templates, CSS variables from one tokens file, small JavaScript only where needed | Indexable, fast on 4G, works at 360 px | Page builders for output; a single-page app |
| Admin | wp-admin with custom roles, capabilities and list tables | No admin framework to buy or learn | A separate admin app |
| Database | MariaDB 10.11+ or MySQL 8.0+ through `$wpdb`, InnoDB, utf8mb4 | Native to cPanel; enough for 10k to 100k profiles with FULLTEXT | PostgreSQL (not on cPanel); SQLite |
| Auth | Staff only, enforced two-factor plugin, closed registration | There is no visitor login to attack | Third-party identity; front-end login |
| Hosting | Local build, then cPanel; one small VPS as fallback | No extra cost | Managed WordPress hosts (kept as the fallback bill) |
| Payments | None | No payment processing on the site (D-005) | Any gateway |
| Email | cPanel SMTP through `wp_mail`; SPF, DKIM and DMARC on the domain | No new account | Brevo, SES, Postmark (later, optional) |
| Storage | WordPress media library; private files outside the web root | WordPress already handles images | Object storage and offload |
| Search | A flattened FULLTEXT table built from public states only | It cannot return a hidden profile | Hosted search; core search |
| Jobs | WP-Cron with Action Scheduler | Works on any cPanel; retries and logs included | A custom job table; Redis workers |
| AI | None on the site | Drafting belongs to the acquisition pipeline | AI inside WordPress |
| Observability | Logs with IDs only; first-party counters; uptime monitor and Sentry later | Cookieless, so no consent banner | GA4 |
| CI/CD | GitHub Actions | Matches the repo and PR workflow | Manual FTP |
| Bots | Per-IP and per-email throttles; Cloudflare Turnstile and WAF when Cloudflare is added | Rate limits and bot challenge | reCAPTCHA |

## Cloudflare is optional

Cloudflare in front of the host is a go-live option, not a dependency. If added, it gives CDN, a web application firewall, rate limits, bot challenge and cache purge. Without it there is no edge cache, and the 410 response from the origin is already the live answer.

## Plugins and dependencies

Few plugins, all pinned by a lockfile and updated through the pipeline. File modification is disabled in production, so plugins are never updated from wp-admin. Third-party plugins chosen so far in the docs:

| Purpose | Plugin | Status |
|---|---|---|
| Staff two-factor | A maintained plugin, enforced for every staff role | Decided (D-050, Q76) |
| Background jobs | Action Scheduler, used as a library inside the plugin | Decided (Q79) |
| Story comments | wpDiscuz | Decided (D-059, REQ-258) |
| Per-user capability screen | A maintained role-editor plugin | Decided (D-060, Q86) |
| Page editor | The native block editor with theme patterns | Decided (D-060, Q98) |
| Caching | A cache plugin | Decided (D-060, Q82) |

There is **no SEO plugin**. The guard plugin emits all SEO markup so nothing can add a sitemap, feed or structured data entry for a profile that is not public.

## Cost

Now: about US$0 to 5 a month, because the host and the domain already exist. At ten times today's traffic, estimates are about US$80 to 200 a month, driven by hosting and a mail provider. The biggest cost is people, not hosting. These are estimates to verify at purchase.

## Fallback

If the live-host check fails, the same site moves to a small VPS (2 vCPU, 4 GB) with Nginx or Caddy, PHP-FPM, MariaDB and a supervised worker. Same code, same CI.

## Known stack risks

| Risk | Response |
|---|---|
| The cPanel account may not meet needs (PHP version, limits, cron, TRIGGER privilege, uptime) | Checked on the live host at import time; fix or move to a VPS |
| A WordPress surface leaks a non-public profile | The visibility test matrix, rerun for every plugin added |
| Import throughput on shared hosting | Chunked, resumable imports tested at 1,000 records |
| Email volume and deliverability | Batch sends; add a provider only if hourly caps bite |
