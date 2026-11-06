# ADR-004 Admin and judge console: Filament

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-083 to 095, 177 to 189, 201

**Context.** Large admin surface: review queue with diffs, register, leads, flags, awards, audit.

**Decision.** Use Filament with two panels (admin, judge). Business logic stays in app/Domain services; Filament resources call them.

**Why.** Fast to build, role-aware, well documented, fits Laravel.

**Rejected.** Hand-built admin (slower). Nova or Backpack (paid).

**Consequences.** Admin UI is tied to Filament; the domain layer is not.
