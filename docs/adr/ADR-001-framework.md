# ADR-001 Application framework: Laravel (PHP 8.3), server-rendered

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-040 to 044, 046, 107 to 110, 122

**Context.** We need indexable pages, forms, auth, roles, queues and an admin console, built by agents, hosted on the cPanel account we already own.

**Decision.** Use Laravel on PHP 8.3 with Blade, Tailwind and Alpine.js. Livewire only for interactive forms. No SPA.

**Why.** Runs natively on cPanel, ships auth, policies, validation, rate limiting, mail and scheduler, and Claude Code writes it reliably. Server rendering gives SEO and low LCP.

**Rejected.** Next.js on a Node host (no cPanel fit, more services, hand-built admin). Django (no cPanel fit).

**Consequences.** PHP skills are needed to supervise. Domain logic sits in plain services under app/Domain so the framework is replaceable at a cost.
