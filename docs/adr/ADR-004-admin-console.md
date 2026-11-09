# ADR-004 Admin console: wp-admin with three roles and capability grants; staff-only authentication

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-047, 083 to 095, 108, 109, 177 to 189, 229, 235, 239 to 244 · Replaces "Admin and judge console: Filament"

**Context.** A large admin surface: review queue with diffs and sources, Support inbox, register, leads, nominations and score entry, imports, read-only Sources, audit log. There are no visitor, owner, nominator, nominee or judge accounts (D-034), no judge area, and only three staff roles (D-044).

**Decision.** Use wp-admin with custom screens (`WP_List_Table` and a few custom pages). Define roles `wiw_reviewer`, `wiw_editor` and the WordPress `administrator` as **default bundles of named `wiw_*` capabilities**. The Administrator can grant or revoke single capabilities per user across roles (D-046, REQ-244) on a Users and capabilities screen; WordPress's per-user capability storage is used, every change is audited, and a `user_has_cap` filter keeps a short list of capabilities Administrator-only. Remove the built-in subscriber, contributor, author and editor roles. Registration is closed, there is no default role, no application passwords, no XML-RPC. Two-factor is enforced before first use of wp-admin (a maintained plugin enforced for every staff role; Q76 answered, no spike). Menus and routes follow effective capabilities; the Reviewer menu is exactly the seven items of REQ-240.

**Why.** No admin framework to buy or learn; capabilities map directly to the decisions; one login surface to defend.

**Rejected.** Filament (Laravel only). A separate admin application. A judge panel or any front-end login (rejected by D-034, D-019).

**Consequences.** Admin code is WordPress-specific, but domain services are not. Every role and capability pairing needs a test (200 and 403 matrix, granted and revoked cases).
