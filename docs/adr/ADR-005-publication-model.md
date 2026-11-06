# ADR-005 Publication model: immutable revisions and one published pointer

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-014, 015, 125, 148, 208

**Context.** Nothing may be public before human approval, across every surface, and edits must not leak.

**Decision.** Content lives in append-only profile_revisions. profiles.published_revision_id is set only by Review PublishRevision. All public read models derive from that revision.

**Why.** One choke point is testable and auditable; versions give history for free.

**Rejected.** A boolean is_published on a mutable row (drafts can leak, no history).

**Consequences.** Some duplication (snapshot plus search table). Worth it.
