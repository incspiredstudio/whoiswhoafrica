# Staff guide

This section explains how the team runs WHOISWHO from the WordPress admin: who does what, in which screen, and what the public sees as a result. It is written for trainers, the product owner and the developers building the admin.

The prototype in `prototype/` shows the staff screens as wireframes ("Preview of flows only. The real admin will be WordPress"). Where a page here describes something that exists only in the prototype, or only as a requirement, it says so.

## The three roles

Staff are the only people with accounts. Visitors, profile owners, nominators, nominees and judges have none (D-034, REQ-229). There are three default roles, and a role is only a starting bundle of capabilities (D-044, REQ-235).

| Role | In one line |
|---|---|
| Reviewer | Reviews incoming records, resolves duplicates, assigns existing terms, can bulk unpublish. Read-only on most things. |
| Editor | Everything a Reviewer does, plus editing and publishing profiles, claims, the support inbox, stories, awards and curation. |
| Administrator | Everything, including the commercial register, imports, exports, the audit log, users, roles and settings. |

The anonymous Visitor has no capabilities.

## How capabilities work

- A capability is a named permission, for example `wiw_review_claims`. Core WordPress capabilities have plain names; the plugin `whoiswho-core` adds the ones that start with `wiw_`.
- The Administrator can add or remove a single capability for one user, in wp-admin, without code (REQ-244, D-046). Menus, screens and actions follow the user's effective capabilities, and WordPress answers 403 for a screen the user's capabilities do not cover (REQ-240).
- A few capabilities are fixed to the Administrator and cannot be granted (Q72, in Questions).

The full matrix is on [Roles and capabilities](./roles-and-capabilities).

## The menu each role sees

The left menu is built from the user's capabilities. These are the defaults from the prototype (REQ-240); the Administrator can change them per user.

| Menu group | Reviewer | Editor | Administrator |
|---|---|---|---|
| Dashboard | Review and directory tiles | Adds support and nominations counts | Everything, including sprint and revenue |
| Review: Review queue, Duplicates | Yes | Yes | Yes |
| Directory: Profiles, Sources, Suppression list (read-only) | Yes | Yes | Yes |
| Directory: Claims | No | Yes | Yes |
| Directory: Import | No | No | Yes |
| Inbox: Support inbox | No | Yes | Yes |
| Inbox: Data requests | No | No | Yes |
| Content: Events | No | Yes | Yes |
| Commercial: Story sponsors | No | Yes | Yes |
| Commercial: Customers, Register | No | No | Yes |
| Awards: Nominations, Award categories | No | Yes | Yes |
| Platform: Audit log, Email log, Purge log, Sprint thresholds | No | No | Yes |

## What WordPress already provides

These are not designed in the wireframe, because WordPress does them natively (D-053, WordPress fit report, D-059).

| Need | Provided by |
|---|---|
| Sign-in with a two-factor step, password reset, sessions | WordPress login and a maintained two-factor plugin (REQ-245) |
| Users list, adding users, changing a role | Core Users screens; one role per user |
| Roles and permissions, per-user capability grants | A maintained role-editor plugin on top of core roles; see [Roles and capabilities](./roles-and-capabilities) (not drawn in the prototype, D-067) |
| Exports | CSV actions on the list screens and core Tools > Export, with the `wiw_export_data` check and an audit entry (not drawn in the prototype, D-067) |
| Stories | Core posts: scheduling, revisions, trash. Featured is the native sticky flag |
| Taxonomy terms (industries, cities, countries, organisation types, professions) | Native term screens; hierarchy is core |
| Profile revisions and old-URL redirects | Core revisions and redirects (REQ-125) |
| Personal data export and erase tools | Core Privacy tools, with custom due dates on top |

## Pages in this guide

| Page | Covers |
|---|---|
| [Roles and capabilities](./roles-and-capabilities) | The matrix, fixed capabilities, granting, extra roles |
| [Review and publishing](./review-and-publishing) | Review queue, import, duplicates, publish-first labels |
| [Profiles and claims](./profiles-and-claims) | Profile screen, direct edits, Claimed, claim deadline and reminders |
| [Support and requests](./support-and-requests) | Inbox, corrections, removals, reports, data requests, suppression, bulk unpublish |
| [Commercial and sponsors](./commercial-and-sponsors) | Customers, register, counting rule, story sponsors |
| [Awards admin](./awards-admin) | Award categories, and the Nominations screen: the open or closed toggle, add a profile, stages, nominees and winners |
| [Curation and content](./curation-and-content) | Home and stories curation, stories, comments, page copy |
| [Events](./events) | Create and publish events, link one to a story |
| [Audit and compliance](./audit-and-compliance) | Audit, purge and email logs, data requests, exports (provided by WordPress) |

## Rules that apply to every screen

- **Public only after review.** An organisation can be live before review but carries a visible label; an individual is public only after a person approves them (REQ-208).
- **Money never buys recognition.** Payment never affects nominations, judging, winners, profile ranking or any label. It can only buy a labelled story placement (REQ-183, REQ-199, REQ-263).
- **Everything staff do on profiles, labels, the register, imports, bulk unpublish, suppression and capability grants is audited** (REQ-051, REQ-188).
- **Claimed is a staff mark.** There are no Verified profiles (D-059).
