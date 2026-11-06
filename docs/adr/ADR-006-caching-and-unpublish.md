# ADR-006 Edge caching and immediate unpublish

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-041, 193, 209

**Context.** Pages must be fast from Nigeria yet removable immediately, including indexed pages.

**Decision.** Cache public HTML at Cloudflare for at most 120 s (60 s for listings). The origin returns 410 plus noindex from DB state. A high-priority job purges by URL; the kill switch purges everything and also hides rows in the query scope.

**Why.** Staleness is bounded even if a purge fails; the edge cache gives LCP.

**Rejected.** No HTML caching (slower). Long TTL with tag purge (tag purge is not on the free plan).

**Consequences.** Search engines may still hold URLs; N21 covers manual removal requests.
