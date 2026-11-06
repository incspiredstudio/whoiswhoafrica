# ADR-010 Search: MySQL FULLTEXT first

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-007, 008, 079

**Context.** Search for at least 10k profiles, ranked, with filters.

**Decision.** A flattened profile_search table with FULLTEXT and indexed filter columns, built from published revisions only. The Laravel Scout interface is kept for a later Meilisearch swap.

**Why.** No extra service or cost; adequate scale.

**Rejected.** Meilisearch, Algolia or Typesense now (cost, operations).

**Consequences.** Relevance tuning is limited; revisit if search quality complaints appear.
