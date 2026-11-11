# Staff guide

This guide explains how the team runs WHOISWHO from the WordPress admin: who does what, in which screen, and what the public sees as a result. Staff are the only people with accounts.

## The three default roles

| Role | In one line |
|---|---|
| Reviewer | Reviews incoming records, resolves duplicates, assigns existing terms and can bulk unpublish. Read-only on most other things. |
| Editor | Everything a Reviewer does, plus editing and publishing profiles, claims, enquiries, corrections, requests and reports, stories, events, awards and curation. |
| Administrator | Everything, including the money details of sponsored story deals, imports, exports, the audit log, users, roles and settings. |

A role is only a starting bundle of named permissions called capabilities. The Administrator can add or remove one capability for one user, or create more roles. See [Roles and what each can do](/concepts/roles).

## The menu each role sees

The left menu follows each user's capabilities. A screen the user may not open answers "forbidden". These are the defaults.

| Menu group | Reviewer | Editor | Administrator |
|---|---|---|---|
| Dashboard | Review and directory tiles | Adds support and nominations counts | Everything, including sponsored story figures |
| Review: Review queue, Duplicates | Yes | Yes | Yes |
| Directory: Profiles, Sources, Suppression list (read-only) | Yes | Yes | Yes |
| Directory: Claims | No | Yes | Yes |
| Directory: Import | No | No | Yes |
| Inbox: Enquiries, Corrections, Requests and reports | No | Yes | Yes |
| Content: Events | No | Yes | Yes |
| Commercial: Sponsored stories (story and placement steps, no amounts) | No | Yes | Yes |
| Commercial: Customers, and the money steps on Sponsored stories | No | No | Yes |
| Awards: Nominations, Award categories | No | Yes | Yes |
| Platform: Audit log, Email log, Purge log, Sprint thresholds | No | No | Yes |

## What WordPress already provides

| Need | Where |
|---|---|
| Sign-in, two-factor step, password reset | WordPress login and the two-factor plugin |
| Adding users and changing a role | Users |
| Per-role and per-user capability grants | The role editor plugin (Users > Role editor) |
| Stories with scheduling, revisions and trash | Posts |
| Terms for industries, cities, countries, organisation types and professions | Term screens under each taxonomy |
| Revisions of every profile edit | Built in |
| Personal data export and erase | Tools > Export Personal Data and Erase Personal Data |
| CSV exports | Actions on the list screens and Tools > Export |

## Pages in this guide

| Page | Covers |
|---|---|
| [Profile review and publication states](/staff/profile-review) | Import, the review queue, the six states, bulk unpublish |
| [Claims](/staff/claims) | Deciding claims, the Claimed mark, assigned users |
| [Corrections](/staff/corrections) | Applying or declining suggested corrections |
| [Requests and reports](/staff/requests-and-reports) | Removals, reports, data requests, the suppression list |
| [Sponsored stories](/staff/sponsored-stories) | The deal tracker and the labelled slot |
| [Events](/staff/events) | Creating and publishing events |
| [Stories](/staff/stories) | Stories, Featured, curation, comments |
| [Award categories and nominations](/staff/awards) | Categories, the Nominations switch, stage and status |
| [Enquiries](/staff/enquiries) | The Get listed, contact and profile enquiry inbox |
| [Newsletter](/staff/newsletter) | Sign-ups and unsubscribes |
| [Audit trail](/staff/audit-trail) | Audit, purge and email logs and exports |

## Rules for every screen

1. Public only after review. An individual is public only after a person approves them.
2. Money never buys recognition. It can only buy a labelled story slot.
3. Everything staff do on profiles, sponsored deals, imports, bulk unpublish, suppression, nominations and capability grants is written to the audit log.
4. Claimed is a staff mark. There are no Verified profiles.
5. Notes and reasons entered by staff are never public. Do not put personal data in free-text reasons.
