# Roles and what each can do

Access in the admin follows roles and capabilities. Three default roles ship with the site. A role is a bundle of named capabilities, and the Administrator can change a bundle, grant or remove one capability for one user, or create more roles. This page lists the defaults and the permissions that cannot move.

## Principles

1. A role is a default bundle. A capability can be granted or revoked per user without code.
2. Each user has one role and one unique email.
3. Menus, routes and actions follow effective capabilities. A screen a user may not open answers "forbidden".
4. Every grant, revocation and use of a sensitive capability is audited.
5. Leavers are deactivated the same day, and the change is audited.
6. Staff sign in with two-factor authentication.

## Default roles by area

R is Reviewer, E is Editor, A is Administrator.

| Area | What it allows | R | E | A |
|---|---|---|---|---|
| Review | Review queue, duplicates | yes | yes | yes |
| Directory, read | See non-public profiles, Sources, suppression list (read-only) | yes | yes | yes |
| Terms | Assign existing terms to a profile | yes | yes | yes |
| Terms | Add, rename and merge terms | no | yes | yes |
| Bulk unpublish | Unpublish by filter and restore a batch | yes | no | yes |
| Single unpublish | Hide one profile | no | yes | yes |
| Profiles | Create, edit, publish and trash profiles | no | yes | yes |
| Claims | Review claims, set Claimed, assign the profile's user | no | yes | yes |
| Curation | Home and Stories curation | no | yes | yes |
| Events | Create, edit, schedule and unpublish events | no | yes | yes |
| Stories | Create, schedule, publish and trash | no | yes | yes |
| Sponsored stories | Choose the story and schedule or end the slot | no | yes | yes |
| Enquiries and corrections | The Enquiries and Corrections screens, and deciding corrections and removals | no | yes | yes |
| Requests and reports | Data requests, removals and profile reports | no | yes | yes |
| Awards | Nominations: stage, status, add and remove, emails | no | yes | yes |
| Imports | Run an import | no | no | yes |
| Sponsored deals (money) | Add a deal, record the payment, customers | no | no | yes |
| Exports | CSV exports of deals, nominations and personal data | no | no | yes |
| Audit | Read the audit log | no | no | yes |
| Suppression | Change the suppression list | no | no | yes |
| Dashboard | Commercial tiles | no | no | yes |
| Nominations switch | Open or close nominations. Editors see it read-only. | no | no | yes |
| Users and settings | Users, roles, capabilities, settings, plugins | no | no | yes |

## Permissions that stay with the Administrator

These are fixed. The guard plugin removes them from anyone who is not an Administrator, so granting one by mistake has no effect.

- User and role management.
- Settings, and installing or switching plugins and themes.
- Sponsored story deals and money details.
- The Nominations switch.
- Editing role bundles and per-user grants.

## Grant a capability

1. The Administrator opens Users, picks the user and opens the Capabilities panel added by the role editor plugin.
2. Tick or untick one capability. For example, give an Editor bulk unpublish, or give a Reviewer Home curation.
3. The change takes effect at once, and the user's menu and forbidden pages change with it.
4. The grant or revocation is written to the audit log.

A user cannot grant themselves anything.

## Where each task is done in WordPress

| Task | Where |
|---|---|
| See or edit what a role can do | Users > Role editor |
| Give or take away one capability for one user | Users > Edit user > Capabilities |
| Create a new role | The role editor: name it, copy an existing role, then tick capabilities |
| Add a user and choose their role | Users > Add New |
| See the menu a role gets | Sign in as a test user with that role |
| Export data | Export actions on list screens, and Tools > Export |

## Extra roles

Only the three default roles ship. The Administrator can create more and give them any capability that is not fixed. A limited "Profile manager" role is one example for a claimed profile's assigned user.
