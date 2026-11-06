# ADR-002 Database: MySQL 8 / MariaDB

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-079, 112, 049

**Context.** Needs relational data, FULLTEXT for 10k+ profiles, money as integers, daily backups.

**Decision.** Use whichever of MySQL 8 or MariaDB 10.6+ cPanel provides, InnoDB, utf8mb4. Avoid vendor-specific SQL.

**Why.** Native to cPanel, no extra cost, adequate for the scale.

**Rejected.** PostgreSQL (better features, not guaranteed on cPanel, extra bill). SQLite (concurrency).

**Consequences.** A move to managed MySQL or Postgres later is a dump and a config change.
