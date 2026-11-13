# Roles and capabilities

How access works in the admin: three default roles, the capabilities behind them, which ones cannot be moved, and how the Administrator changes things without code. The single source for the list is the "Capabilities and default role mapping" section of Requirements; the code's copy is `config/capabilities.php`. The screen-by-screen menu is on the [staff guide index](./).

## Principles

- A role is a default bundle. The Administrator can change what a role contains, or grant and revoke one capability for one user (REQ-244, D-046).
- WordPress stores capabilities on roles and on individual users, so no code change is needed to move a duty between people.
- There is one role per user and one unique email per user. The plugin blocks adding a second role through the user screen (REQ-245).
- Menus, routes and actions follow the user's effective capabilities. A screen the user may not open answers 403 (REQ-240).
- Every grant, revocation and use of a sensitive capability is audited (REQ-051, REQ-188).
- Leavers are deactivated the same day, and the change is audited (REQ-235).

## Default roles by area

R is Reviewer, E is Editor, A is Administrator.

| Area | What it allows | R | E | A |
|---|---|---|---|---|
| Review | Review queue, duplicates (`wiw_review_records`, `wiw_resolve_duplicates`) | yes | yes | yes |
| Directory, read | See non-public profiles, Sources, suppression list (read-only) | yes | yes | yes |
| Terms | Assign existing terms to a profile | yes | yes | yes |
| Terms | Add, rename and merge terms (`edit_terms`) | no | yes | yes |
| Bulk unpublish | Unpublish by filter and restore a batch (`wiw_bulk_unpublish`) | yes | no | yes |
| Single unpublish | Hide one profile (`wiw_unpublish_profiles`) | no | yes | yes |
| Profiles | Create, edit, publish and trash profiles (`edit_wiw_profiles` and related) | no | yes | yes |
| Claims | Review claims, set Claimed, assign the profile's user (`wiw_review_claims`) | no | yes | yes |
| Curation | Home page and stories curation (`wiw_curate_home`) | no | yes | yes |
| Events | Create, edit, schedule and unpublish events, link one to a story (`wiw_manage_events`) | no | yes | yes |
| Stories | Create, schedule, publish, trash (`edit_posts`, `edit_others_posts`, `publish_posts`) | no | yes | yes |
| Sponsored stories | Choose the story and schedule or end the labelled slot (`wiw_set_sponsored`) | no | yes | yes |
| Enquiries and corrections | Enquiries and Corrections screens (`wiw_support_inbox`); decide corrections and removals (`wiw_handle_disputes`) | no | yes | yes |
| Requests and reports | Data requests, removals and profile reports (`wiw_handle_requests`) | no | yes | yes |
| Awards | Nominations: stage, status, add and remove, nomination and winner emails (`wiw_manage_awards`) | no | yes | yes |
| Imports | Run an import (`wiw_run_imports`) | no | no | yes |
| Sponsored deals (money) | Add a deal, record the payment, customers (`wiw_manage_register`) | no | no | yes |
| Exports | CSV exports of sponsored story deals, nominations and personal data (`wiw_export_data`) | no | no | yes |
| Audit | Read the audit log (`wiw_view_audit`) | no | no | yes |
| Suppression | Change the suppression list (`wiw_edit_suppression`) | no | no | yes |
| Dashboard | Commercial widgets on the WordPress Dashboard (`wiw_view_dashboard_commercial`); review widgets need `wiw_view_dashboard_review` | no | no | yes |
| Nominations switch | Turn the Nominations flag on or off from the Nominations screen (`wiw_toggle_nominations`); Editors see it read-only | no | no | yes |
| Users and settings | Users, roles, capabilities, settings (including Sprint thresholds), plugins | no | no | yes |

Capabilities in the code that the table above does not name (all from `config/capabilities.php`, catalogue version 4):

| Capability | Allows | R | E | A |
|---|---|---|---|---|
| `moderate_comments` (core) | Approve, hold or delete story comments | no | yes | yes |
| `edit_pages`, `edit_others_pages`, `publish_pages`, `edit_published_pages` (core) | Edit About, FAQ, rules and legal pages | no | yes | yes |
| `upload_files`, `delete_posts`, `edit_published_posts` (core) | Media library; delete and edit published stories | no | yes | yes |
| `wiw_view_dashboard_review` | Review and directory dashboard widgets | yes | yes | yes |
| `wiw_view_sources`, `wiw_view_suppression` | Read-only Sources and Suppression list | yes | yes | yes |
| `wiw_manage_subscribers` | Subscribers screen (newsletter list) | no | yes | yes |
| `wiw_edit_assigned_profiles` | My profiles: edit only profiles assigned to the user | no | no | yes |
| `wiw_handle_disputes` | Decide corrections and removal requests | no | yes | yes |
| `wiw_receive_enquiries` | Receive enquiries sent to profiles | no | no | yes |
| `wiw_manage_capabilities` | Edit role bundles and per-user grants (fixed) | no | no | yes |
| `export` (core) | Core Tools > Export | no | no | yes |

The Administrator holds every capability in the catalogue. The catalogue has a version number; when it is raised, the next sync adds new capabilities to the roles and never removes a grant the Administrator added later. The fixed capabilities (core user, option, plugin and theme capabilities, `wiw_manage_register`, `wiw_toggle_nominations`, `wiw_manage_capabilities`) are marked `fixed` in the catalogue.

`wiw_set_trust_labels` is withdrawn: profiles have no trust labels (D-059). Featured on a story uses the core sticky flag.

## Capabilities that stay with the Administrator

These are marked fixed. They are enforced by a `user_has_cap` filter, not only by hiding the screen, so granting them by mistake has no effect (Q72, in Questions):

- User and role management (`create_users`, `edit_users`, `promote_users`, `delete_users`).
- Settings (`manage_options`) and installing or switching plugins and themes.
- Sponsored story deals and money details (`wiw_manage_register`).
- The Nominations switch (`wiw_toggle_nominations`).
- Editing role bundles and per-user grants (`wiw_manage_capabilities`).

In the prototype the locked capabilities are the money details, users and roles, and settings. Q72 is still a proposed limit.

## How roles and grants work in WordPress

The Roles and permissions, Users and capabilities and Exports screens are not drawn in the prototype. WordPress provides them, so they are documented here instead. The prototype has stand-ins only so a person can try a role.

| Task | Where it happens in WordPress |
|---|---|
| See which capabilities a role has, and edit the role | The role-editor plugin's role screen (Users > Role editor). It lists every capability, including each `wiw_*` one, with a tick per role |
| Give or take away one capability for one user | The role-editor plugin's user screen (Users > Edit user > Capabilities). A tick adds a per-user grant; an explicit deny removes a capability the role would give |
| Create a new role | The same plugin: name the role, copy an existing one, then tick capabilities |
| Add a user and choose their role | Core Users > Add New. One role per user; extra capabilities come from grants |
| See the menu a role gets | Log in as a test user of that role. WordPress shows only the menu items the capabilities cover (REQ-240) |
| Export data | CSV actions on the list screens (sponsored story deals, customers, nominations, profiles, support items) and core Tools > Export. Each needs `wiw_export_data` and is logged (REQ-087) |

## Granting a capability, step by step

1. The Administrator opens Users, picks the user and opens the Capabilities panel the role-editor plugin adds.
2. They tick or untick one capability. For example, give an Editor `wiw_bulk_unpublish`, or give a Reviewer `wiw_curate_home` or `edit_wiw_profiles`.
3. The change takes effect at once. The user's menu and 403 pages change with it.
4. The grant or revocation is written to the audit log by the plugin's hook into the capability change.

A user cannot grant themselves anything. A fixed capability cannot be granted to a non-Administrator, whatever the plugin shows: the guard plugin's `user_has_cap` filter removes it (Q72). The role-editor plugin must itself be Administrator-only.

## Creating extra roles

The three default roles are the only ones that ship. The Administrator can create more in wp-admin and give them any non-fixed capabilities (D-059, Q93 answered). The assigned user for a claimed profile is an ordinary staff user, and a limited "Profile manager" role is one example of a role the Administrator might create (REQ-253).

## WordPress native, custom, or still open

| Part | Status |
|---|---|
| Per-role and per-user capabilities | Native WordPress |
| Capability names for profiles | Generated by registering the profile post type with its own capability type |
| The screens for editing roles and per-user grants | Not in core. A maintained role-editor plugin provides them (Q86, answered). They are not drawn in the prototype (D-067) |
| Exports | WordPress list-table actions and core Tools > Export; the plugin adds the capability check and the audit entry. Not drawn in the prototype (D-067) |
| The fixed-capability filter | Custom plugin |

What the build must keep from the old prototype screens: each role's menu follows its capabilities, grants are made one user at a time, and every change is audited.
