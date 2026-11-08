# ADR-001 Application framework: WordPress with a custom plugin, a guard plugin and a theme

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-040 to 044, 046, 052, 190, 229 · Replaces the Laravel decision of 2026-11-06 (D-031)

**Context.** We need indexable pages, forms, a staff-only admin, roles, jobs and a review workflow, built by agents, hosted on the cPanel account we already own. You decided on WordPress (D-031). WordPress's defaults are weak on the rules that matter most here: nothing public before the rules allow it, and instant removal.

**Decision.** Use current stable WordPress (WordPress's requirements page read 2026-11-06 lists PHP 8.3 recommended, MariaDB 10.11+ or MySQL 8.0+; verify at build). Put product logic in a custom plugin `whoiswho-core` (modules Directory, Review, Import, Trust, Commerce, Content, Awards, Compliance, Platform) and the leak-closing and hardening code in a must-use plugin `whoiswho-guard`, so an Administrator cannot deactivate it. Use one custom theme `whoiswho`: server-rendered PHP templates, design tokens in one file (REQ-052, 190), no page builder, no SEO plugin. Public templates read through read models only. Third-party plugins are few, pinned and justified one by one.

**Why.** Native to cPanel; editors know it; posts, taxonomies, media and the admin shell come free; custom code in a plugin is testable by agents; server rendering gives SEO and low LCP.

**Rejected.** Laravel + Filament (the earlier proposal; stronger on the gating rules, but not the decision). Headless CMS options (second codebase, Node host). Page builders and block-heavy themes (large, uncontrolled output surface).

**Consequences.** WordPress's large plugin surface is the main risk, so the visibility matrix test (architecture.md 3.2) runs on every plugin added. Domain logic sits in plain classes so it does not depend on WordPress templates.
