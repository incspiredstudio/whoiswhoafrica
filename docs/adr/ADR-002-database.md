# ADR-002 Database: MariaDB/MySQL through $wpdb, with custom tables for product data

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-079, 112, 049, 125, 142, 237

**Context.** Needs relational data, FULLTEXT for 10k+ profiles, money as integers, per-field provenance that can be queried, version history, and daily backups. WordPress stores content in posts and post meta, which cannot be constrained or queried well.

**Decision.** Use whichever of MariaDB 10.11+ or MySQL 8.0+ cPanel provides (verify), InnoDB, utf8mb4, through `$wpdb`. Keep WordPress content (profile and story post types, terms, users) in core tables. Put publication state, versions, provenance, claims and passages, the register, awards scores, signed links, support items, suppression and the audit log in `wiw_` tables created and versioned by the plugin. Profile fields are post meta registered from an allowlist and never exposed through REST. Avoid vendor-specific SQL. Prepared statements only.

**Why.** Native to cPanel, no extra cost, adequate at this scale; custom tables make filters (source, batch, state, type) cheap and give constraints that post meta cannot.

**Rejected.** PostgreSQL (not on cPanel). Post meta for provenance (cannot be queried at scale; contract unknown U1). SQLite.

**Consequences.** The plugin owns forward-only migrations (expand/contract). A move to managed MySQL later is a dump and a config change.
