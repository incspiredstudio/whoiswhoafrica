# ADR-003 Hosting: build locally, live on the existing cPanel account, VPS fallback

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08, amended 2026-11-09 (D-050) · REQs: REQ-042, 045, 046, 081, 204, 209 · Decision kept from 2026-11-06

**Context.** cPanel and wiwi.africa already exist (D-020). Uptime, CDN, WAF and instant purge are required. The site is built locally and moved to the live host when finished (D-050). Account capabilities are checked then (Q26).

**Decision.** Build and test WordPress locally. Host it on the existing cPanel account by migrating the finished site. Cloudflare (free) in front for DNS, CDN, WAF, Turnstile and cache purge is an option at go-live, not a dependency. On the live host, at import time, check PHP version, limits, cron, MariaDB version and TRIGGER privilege. If the host fails, move the same site to a small VPS.

**Why.** No extra monthly cost and one fewer processor. If Cloudflare is added it covers CDN, bot challenge and purge. WordPress is the default workload on cPanel.

**Rejected.** Managed WordPress hosts (a new monthly bill, kept as the fallback). Render, Fly or Railway.

**Consequences.** Shared hosting cannot run daemons or give strong uptime guarantees; see ADR-011 and risk 1 in architecture.md. The go-live migration steps must work on both targets.
