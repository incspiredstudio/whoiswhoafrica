# ADR-011 Jobs and scheduling on shared hosting: system cron driving a queue library

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-045, 075, 160, 193, 209, 237

**Context.** No persistent workers on cPanel. WP-Cron runs only when a page is requested, and with edge caching origin requests are sparse. Jobs: cache purge, email, import chunks, bulk unpublish chunks, sponsored apply and expiry, expiry reminders, stale checks, retention, backups.

**Decision.** Disable WP-Cron (`DISABLE_WP_CRON`). A cPanel cron every minute runs the job runner with a time budget under the host's limit. Use Action Scheduler as a library inside the plugin (verify licence, tables and host fit; Q79), with a high-priority group for purge and email; fall back to a small custom job table if it does not fit. Imports are chunked and resumable.

**Why.** Works on any cPanel; retries and logs come with the library; no extra services.

**Rejected.** WP-Cron alone (unreliable timing). Redis workers (not on shared hosting).

**Consequences.** Job latency up to about one minute. Moving to a supervised worker on a VPS needs no code change.
