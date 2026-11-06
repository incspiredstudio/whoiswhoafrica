# ADR-011 Queues and scheduling on shared hosting

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-045, 075, 160, 209

**Context.** No persistent workers on cPanel.

**Decision.** Database queue. A one-minute cron runs schedule:run, which starts a worker with stop-when-empty and max-time 55 s. Purge and email jobs use a high-priority queue.

**Why.** Works on any cPanel; no extra services.

**Rejected.** Redis plus Horizon (not available on shared hosting).

**Consequences.** Job latency up to about one minute; moving to Supervisor on a VPS needs no code change.
