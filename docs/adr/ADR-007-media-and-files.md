# ADR-007 Media and file storage

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-042, 080, 127, 162, 203

**Context.** Images need a CDN; claim documents are sensitive; backups need an off-host copy.

**Decision.** Public images in Cloudflare R2 (re-encoded variants, licence and attribution stored). Claim documents on a private disk outside the web root, encrypted, retention-limited. Backups encrypted to a separate R2 bucket.

**Why.** Cheap, S3-compatible, no egress fee, aligned with Cloudflare.

**Rejected.** S3 (egress cost). Local disk only (no CDN, no off-host backup).

**Consequences.** Two buckets and tokens to manage.
