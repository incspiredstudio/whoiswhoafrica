# ADR-012 CI/CD and deployment

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-056, 095

**Context.** One human supervisor, many agents, shared hosting.

**Decision.** GitHub Actions runs all gates on every PR, builds an artifact, auto-deploys staging, and deploys production after manual approval. Release directories with an atomic symlink switch; additive migrations; automatic pre-migration dump. Fallback: cPanel Git deploy with .cpanel.yml.

**Why.** Fast rollback and no build tools needed on the host.

**Rejected.** Manual FTP (error-prone). Docker on cPanel (unsupported).

**Consequences.** Needs SSH on the host (checked in spike S1).
