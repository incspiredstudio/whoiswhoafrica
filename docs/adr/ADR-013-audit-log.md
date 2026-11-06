# ADR-013 Audit log: append-only, hash-chained, copied off-host

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-051, 188

**Context.** Staff actions on profiles, register, labels, flags and notes must be tamper-evident, on a host where we may not control DB grants.

**Decision.** An append-only table; each row stores the previous row hash. The model blocks update and delete; DB triggers block them where privileges allow. Nightly export to R2.

**Why.** Tampering is detectable even without DB-level immutability.

**Rejected.** A separate immutable ledger service (cost, complexity).

**Consequences.** A verification command checks the chain; breaks raise an alert.
