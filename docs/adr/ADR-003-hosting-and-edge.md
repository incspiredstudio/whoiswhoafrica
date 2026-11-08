# ADR-003 Hosting: existing cPanel behind Cloudflare, VPS fallback

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-042, 045, 046, 081, 204, 209 · Decision kept from 2026-11-06

**Context.** cPanel and wiwi.africa already exist (D-020). Uptime, CDN, WAF and instant purge are required. Account capabilities are unverified (Q26).

**Decision.** Host WordPress on the existing cPanel account. Put Cloudflare (free) in front for DNS, CDN, WAF, Turnstile and cache purge. Run spike S1 first (PHP version, SSH, cron every minute, MariaDB version, TRIGGER privilege, symlinked document root, limits). If it fails, deploy the same release to a small VPS.

**Why.** No extra monthly cost and one fewer processor. Cloudflare covers CDN, bot challenge and purge. WordPress is the default workload on cPanel.

**Rejected.** Managed WordPress hosts (a new monthly bill, kept as the fallback). Render, Fly or Railway.

**Consequences.** Shared hosting cannot run daemons or give strong uptime guarantees; see ADR-011 and risk 1 in architecture.md. The release script must work on both targets.
