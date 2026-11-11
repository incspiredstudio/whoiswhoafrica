# Roles in code

How roles, capabilities, the audit logger and the module boundaries are built, and how the boundaries are enforced by tests.

Requirements: REQ-047, 051, 183, 184, 188, 199, 235, 240, 244. Decisions: D-044, D-045, D-046, D-053. Records: ADR-004, ADR-013. Architecture: section 3.1 and 4.3.

## Roles are bundles; capabilities are the permissions

| Role | WordPress role | Default scope |
|---|---|---|
| Reviewer | `wiw_reviewer` | Review and read-only capabilities |
| Editor | `wiw_editor` | Reviewer plus content, awards, support, claims and curation |
| Administrator | `administrator` plus every `wiw_*` capability | Everything |
| Visitor | None | No account, no capabilities |

On activation the plugin removes WordPress's built-in subscriber, contributor, author and editor roles. `default_role` is empty and registration is closed (REQ-229).

**There is one list of capabilities and their defaults.** It lives in the "Capabilities and default role mapping" section of the requirements. Do not copy it into code comments or other docs.

## Core and plugin capabilities

| Kind | Examples | Notes |
|---|---|---|
| Core, generated for the profile type | `edit_wiw_profiles`, `publish_wiw_profiles`, `read_private_wiw_profiles` | Requires the profile type to declare its own capability type |
| Core | `edit_posts`, `upload_files`, `manage_terms`, `list_users`, `install_plugins`, `manage_options` | Stories use the core post capabilities; Featured on a story is the sticky flag |
| Plugin, `wiw_` prefix | `wiw_review_records`, `wiw_unpublish_profiles`, `wiw_bulk_unpublish`, `wiw_review_claims`, `wiw_curate_home`, `wiw_manage_events`, `wiw_set_sponsored`, `wiw_manage_awards`, `wiw_support_inbox`, `wiw_handle_requests`, `wiw_run_imports`, `wiw_export_data`, `wiw_view_audit`, `wiw_edit_suppression` | One capability per screen and action |

`wiw_set_trust_labels` is withdrawn: profiles have no trust labels (D-059).

## Locked capabilities

Some capabilities cannot be granted to a non-Administrator. They are **fixed**:

- user and role management
- site settings
- plugin and theme installation
- sponsored story deal money details (`wiw_manage_register`)
- the capability screen (`wiw_manage_capabilities`)
- the Nominations flag (`wiw_toggle_nominations`)

A `user_has_cap` filter enforces this, not just the hidden menu. Even if a grant is stored for a locked capability, the filter returns false for a non-Administrator (Q72).

The same filter lets a Reviewer with the bulk-unpublish capability unpublish a single profile in an emergency, without edit rights.

## Per-user grants

The Administrator can grant or revoke single capabilities for one user across roles, and can create new roles (REQ-244). WordPress already stores a per-user grant or an explicit `false` in the user's capabilities. A maintained role-editor plugin provides the interface (Q86, answered). The non-grantable list is still enforced by the guard plugin, and every grant and revocation is audited.

Menus, routes and actions follow the user's **effective** capabilities. Deny by default: each screen registers under a named capability, and each action checks one.

## The audit logger

`Platform\AuditLogger` is the only writer of `wiw_audit_log`.

- Rows are append-only, with no update or delete methods.
- Each row stores the previous row's hash, so a break is detectable.
- Where the database account allows it, triggers block UPDATE and DELETE.
- A periodic export is kept off the server, and a verify command checks the chain.
- Every staff action on profiles, labels, sponsored story deals, imports, bulk unpublish, suppression, the Nominations flag and capability grants goes through it, with actor, time, and old and new values.

Bulk unpublish writes one row per profile plus one for the batch.

## Module boundaries

Code lives in plugin modules: `Directory`, `Review`, `Import`, `Trust`, `Commerce`, `Content`, `Awards`, `Compliance` and `Platform`. Edit only the module you own. These rules are enforced by architecture tests in CI:

| Rule | Why |
|---|---|
| `Awards` never references `Commerce` | No path from payment to nominations or winners (REQ-199) |
| `Commerce` and the Sponsored label never write the Claimed mark or a story's Featured flag | Payment cannot change marks (REQ-184) |
| Only `Review\StateMachine` writes a profile's publication state or lets post status become `publish` | One choke point for visibility |
| Public templates read only through `PublicProfile` and `PublicStory` | They cannot forget the label or noindex |
| Only `AuditLogger` writes the audit log | Tamper evidence |

After D-059 the `Trust` module no longer owns Verified and Featured on profiles. It handles the Claimed state and the Sponsored link.

## Tests for roles

- A matrix of every role and capability pairing, asserting 200 or 403 on each screen and action.
- Cases for a granted capability and a revoked one.
- Menu tests: the Reviewer menu is exactly Dashboard, Review queue, Duplicates, Profiles, Sources (read-only) and Suppression list (read-only), and grows only by grants (REQ-240).
- Leavers are deactivated the same day, and the change is audited (REQ-235).

See [Testing and CI](/build/testing-and-ci).

## Rules for writing capability code

- Every new capability is added to the one list in the requirements and to its test.
- No `eval`. No raw `$_GET` or `$_POST` outside validators.
- Admin handlers stay thin and call domain services.
- Verify a nonce on every admin action.
