# ADR-010 Search: FULLTEXT on a public-only table

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-007, 008, 079, 218

**Context.** Search for at least 10k profiles, ranked, with filters. WordPress's built-in search is weak and can reach content that should not be public.

**Decision.** A flattened `wiw_profile_search` table with InnoDB FULLTEXT and indexed filter columns (type, industry, sector, profession, place, organisation type), maintained in the same transaction as every state change and edit. It contains only profiles in the two Published states, with a `reviewed` flag: lists and industry pages use `reviewed = 1`; on-site search and the "include profiles pending review" view include pending profiles, always with the label (REQ-218). Core search is disabled for the profile type. The search service sits behind one interface.

**Why.** No extra service or cost; adequate scale; it cannot return a hidden profile because hidden profiles have no row.

**Rejected.** Hosted search services now (cost, operations). Core WordPress search.

**Consequences.** Relevance tuning is limited; revisit if search quality complaints appear.
