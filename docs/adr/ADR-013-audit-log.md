# ADR-013 Audit log: append-only, hash-chained, copied off-host

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-051, 188, 193, 228, 244

**Context.** Staff actions on profiles, labels, the register, imports, bulk unpublish, suppression, flags and capability grants must be tamper-evident, on a host where we may not control database grants.

**Decision.** A custom table `wiw_audit_log`; each row stores the previous row's hash. Only `Platform\AuditLogger` writes it, with no update or delete methods; database triggers block UPDATE and DELETE where the privilege exists (checked in S1). A nightly export goes to a separate bucket. A verify command checks the chain and raises an alert on a break. Staff actions reach the logger through services and WordPress hooks (user, role and capability changes, logins), so no action path bypasses it.

**Why.** Tampering is detectable even without database-level immutability.

**Rejected.** An activity-log plugin as the only record (not tamper-evident). A separate immutable ledger service (cost, complexity).

**Consequences.** One audit row per profile in a bulk action plus one per batch; keep rows small. The chain must not break on archive or export.
