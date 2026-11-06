# ADR-003 Hosting: existing cPanel behind Cloudflare, VPS fallback

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-042, 045, 046, 081, 204, 209

**Context.** cPanel and wiwi.africa already exist (D-020). Uptime, CDN, WAF and instant purge are required. Account capabilities are unverified.

**Decision.** Host the app on the existing cPanel account. Put Cloudflare (free) in front for DNS, CDN, WAF, Turnstile and cache purge. Run spike S1 first; if it fails, deploy the same release to a small VPS.

**Why.** No extra monthly cost and one fewer processor. Cloudflare covers CDN, bot challenge and purge.

**Rejected.** Render, Fly or Railway (new monthly bill). Laravel Cloud or Forge plus VPS (about US$20+/month, kept as the fallback).

**Consequences.** Shared hosting cannot run daemons or give strong uptime guarantees; see ADR-011 and risk 1 in architecture.md.
