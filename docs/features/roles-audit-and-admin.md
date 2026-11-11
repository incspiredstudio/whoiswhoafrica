# Roles, audit and admin

Who can sign in, what each person can do, what the dashboard shows each role, and the tamper-evident record of what staff did.

## What it does

- Limits accounts to staff, with two-factor sign-in enforced.
- Gives each user a role, which is a default bundle of capabilities the Administrator can adjust per user.
- Shows each role a menu and a dashboard that match their capabilities, and refuses screens they cannot use.
- Writes every sensitive staff action to an append-only, hash-chained audit log.
- Provides exports and read-only registers for the Administrator.

## Who uses it and how

- As a Reviewer, I see the review and directory tiles and only the screens my capabilities allow.
- As an Editor, I also see support and awards counts and can edit, publish, curate and handle requests.
- As an Administrator, I can grant or revoke a single capability for a user, create extra roles, and read the audit log.
- As an Administrator, I can verify that the audit chain is intact.

## The pieces

### Theme

None for the public side. WordPress admin screens are used.

### Plugin

- `Platform` module: capability registry, the `user_has_cap` filter that blocks granting fixed capabilities, role bundles, menu and route registration under `wiw_*` capabilities.
- `AuditLogger`: the only writer of the audit log. Each row holds actor, time, action, target, old and new values or reason, and the hash of the previous row.
- Dashboard tiles per role, with plain counts by state and no profile-count target or progress bar (REQ-239).
- Review throughput, queue age and rejection reasons (REQ-149, 206); profile counts by type, source, status and age (REQ-171); weekly reporting (REQ-088).
- Role defaults and the capability list, tested as a role-by-capability matrix.
- Guard plugin: blocks a second role on a user, closes registration, blocks user enumeration.

### Pages and screens

- Staff: Dashboard (differs by role), Audit log (with a verify-chain action), Purge log, Email log, Sprint thresholds. Roles, per-user capability grants and exports are provided by WordPress and a role-editor plugin and are not drawn in the prototype (D-067).
- WordPress core: sign-in with a two-factor plugin, Users, password reset, session management.

### Third-party plugins

- A maintained two-factor plugin, enforced for all staff roles.
- A maintained role-editor plugin for per-user grants (D-060).

### Data

- Audit log table, hash-chained and exported nightly off the host. Capabilities stored in WordPress roles and user meta.

## Business rules

- There are three staff roles plus the anonymous Visitor, who has no account. The roles are bundles: Reviewer (review and read-only), Editor (Reviewer plus content, awards, support, claims, curation), Administrator (everything) (REQ-235).
- Staff accounts need strong passwords and two-factor authentication, enforced before first use of wp-admin, with recovery codes and lockout after repeated failures (REQ-047).
- There is no registration, login or account area for visitors, owners, nominators, nominees or judges. Front-end registration is closed (REQ-229).
- One unique email and one role per user; sign-in has no role selector (REQ-245).
- The Administrator can grant or revoke a capability for a user, across roles, and edit what a role contains, without code. Every grant, revocation and use of a sensitive capability is audited (REQ-244).
- Fixed to the Administrator and enforced by filter: user and role management, settings, plugin and theme installation, the sponsored story money details, the capability screen, the Nominations flag.
- Menus and routes follow effective capabilities: hidden menu, 403 on the screen. The default Reviewer menu is Dashboard (review tiles), Review queue, Duplicates, Profiles, Sources (read-only), Suppression list (read-only) (REQ-240).
- Leavers are deactivated the same day and the change is audited (REQ-235).
- All staff actions on profiles, customer records and reviews, imports, labels, arrangements, sponsors, notes, capability grants, flag changes, nomination stage and status changes and nomination emails, and exports are written to the audit log (REQ-051, 188, 228).
- Bulk export of personal data is Administrator-only and logged (REQ-069).
- Commercial dashboard tiles show to the Administrator only (REQ-239).

## Acceptance criteria

- [ ] A user cannot reach any wp-admin screen without completing two-factor; five failed attempts lock the account.
- [ ] Front-end registration, user enumeration (author query, users REST route, user sitemaps) and application passwords are disabled.
- [ ] Each role's menu equals the list derived from its capabilities; a screen outside the user's capabilities returns 403.
- [ ] A role-by-capability matrix test passes for Reviewer, Editor and Administrator against the documented defaults.
- [ ] Granting a capability to one user changes that user's menu immediately and writes an audit row; revoking it restores the 403.
- [ ] Granting a fixed capability to a non-Administrator has no effect.
- [ ] A user cannot grant themselves a capability and cannot change their own role.
- [ ] Adding a second role to a user is refused.
- [ ] Every audited action writes one row with actor, time, action, target and old and new values or reason.
- [ ] The "verify chain" action reports intact on an untouched log and fails when a row is altered in a test.
- [ ] Only the audit service writes to the log table; an architecture test fails if other code does.
- [ ] The Reviewer dashboard shows no commercial tiles; the Administrator's shows sprint count and confirmed revenue.
- [ ] The dashboard shows counts by state and no directory-size target or progress bar.
- [ ] Deactivating a user ends their sessions and writes an audit row.

## Depends on

- Every feature (all staff screens use the capability system).
- Email and notifications (staff notices); Privacy and data rights (exports, logs).

## Out of scope or later

- Public or judge accounts.
- A fourth default role (the Administrator creates extra roles when needed).
- Single sign-on and third-party identity.
- Live monitoring dashboards beyond the weekly report.

## Requirement ids covered

REQ-047, 051, 069, 088, 149, 171, 187, 188, 206, 228, 229, 235, 239, 240, 244, 245. Weekly reporting live by 20 Dec 2026 (REQ-102).
