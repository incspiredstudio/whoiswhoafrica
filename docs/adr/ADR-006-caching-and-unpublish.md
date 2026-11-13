# ADR-006 Edge caching, 410 from database state, purge and bulk unpublish

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-041, 091, 121, 193, 209, 217

**Context.** Pages must be fast from Nigeria yet removable immediately, including indexed pages. The kill switch is gone (D-043); a bulk unpublish by filter replaces it.

**Decision.** If Cloudflare is added, cache anonymous public HTML there for at most 120 s (60 s for lists and search), bypassing staff cookies and wp-admin. The origin returns **410 plus `X-Robots-Tag: noindex`** for unpublished and removed profiles by reading `wiw_profile_state` (not a cache); rejected and not-public profiles return 404. After commit, high-priority jobs purge by URL (per-call limits: verify) and purge broadly for large batches; failures retry and alert. Bulk unpublish (filter by source, type, import batch, state; Administrator and Reviewer by default) runs in chunked jobs, writes one audit row per profile plus one for the batch, and can be restored per batch. `sitemap.xml` is generated from the read model.

**Why.** Staleness is bounded to two minutes even if a purge fails; the edge cache gives LCP.

**Rejected.** No HTML caching (slower). A long TTL with tag purge (not on the free plan; verify). A page-cache plugin as the primary mechanism (another leak surface). **Update after the build:** the owner approved WP Super Cache 3.1.5 as a swappable page cache, off by default until the CSP-nonce question (issue #719) is settled and the visibility matrix passes with it on. The origin 410 never depends on it.

**Consequences.** Search engines may still hold URLs; N21 covers manual removal requests. The real time to 410 is checked on the live host at import time.
