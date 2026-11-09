# ADR-011 Jobs and scheduling: WP-Cron with Action Scheduler

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08, amended 2026-11-09 (D-050) · REQs: REQ-045, 075, 160, 193, 209, 237

**Context.** No persistent workers on cPanel. WP-Cron runs when a page is requested; if an edge cache is added later, requests become sparse and a real cron can drive it. Jobs: cache purge, email, import chunks, bulk unpublish chunks, sponsored apply and expiry, expiry reminders, stale checks, retention, backups.

**Decision.** Use WP-Cron with Action Scheduler as a library inside the plugin (Q79 answered, no spike), with a high-priority group for purge and email and a time budget per run. If the live host allows it, a cPanel cron every minute may drive it instead (`DISABLE_WP_CRON`). Imports are chunked and resumable.

**Why.** Works on any cPanel; retries and logs come with the library; no extra services.

**Rejected.** A custom job table (not needed). Redis workers (not on shared hosting).

**Consequences.** Job timing depends on site traffic unless a real cron is added. Moving to a supervised worker on a VPS needs no code change.
