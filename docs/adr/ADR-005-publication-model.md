# ADR-005 Publication model: one state machine, mapped status, versions table

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-014, 015, 125, 148, 208, 210, 216 to 218, 222, 226 · Replaces "immutable revisions and one published pointer"

**Context.** Visibility rules changed (D-033 as amended, D-040): imported organisations may be public before review, labelled and noindex; individuals and entrepreneurs only after a human approves; staff edits go live on one person's sign-off; there is no owner edit and no pointer to an approved revision. WordPress core revisions do not cover post meta.

**Decision.** Each profile has exactly one `pub_state` (six states of REQ-210) in `wiw_profile_state`, separate from the trust label and Sponsored. Only the `StateMachine` writes it; it also maps state to `post_status` (the two Published states are `publish`, all others a non-public custom status), in one transaction. A `wp_insert_post_data` guard refuses any other change to a profile's status. Public read models (`PublicProfile`, `wiw_profile_search`, sitemap) include only the two Published states and add the label and noindex (REQ-216, 217). Every write also appends a row to `wiw_profile_versions` (author, time, reason, snapshot) for history and diff (REQ-125, 164). Re-import never overwrites a reviewed field (REQ-161, 221).

**Why.** One choke point is testable and auditable; versions give history without relying on core revisions.

**Rejected.** A boolean on a mutable post (leaks, no history). Core post statuses alone (plugins can change them). A published-revision pointer (no longer needed since edits are direct).

**Consequences.** Some duplication (state row, status, search row, version snapshot). REQ-210 has no name for a customer-submitted profile awaiting approval; reuse *Imported – not public* with `origin = customer` (Q73).
