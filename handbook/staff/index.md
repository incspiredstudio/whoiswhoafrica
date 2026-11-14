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

The WHOISWHO screens sit in their own menu groups near the top of the left menu. Each group shows only the screens the user may open, and a group with nothing to open is hidden. A screen the user may not open answers "forbidden". These are the defaults.

| Menu group and screens | Reviewer | Editor | Administrator |
|---|---|---|---|
| Dashboard (the WordPress one) | Review tiles | Review and claims tiles | Everything, including sponsored story figures |
| WHOISWHO: Setup (including the Turnstile keys and demo content) | No | No | Yes |
| Directory: Profiles, Duplicates | Yes | Yes | Yes |
| Directory: Add profile, Edit profile, Home page settings | No | Yes | Yes |
| Directory: My profiles (profiles assigned to a user after a claim) | No | No | Yes |
| Review: Review queue | Yes | Yes | Yes |
| Review: Claims, Corrections, Requests and reports | No | Yes | Yes |
| Imports: Sources | Yes | Yes | Yes |
| Imports: Import | No | No | Yes |
| Audience: Enquiries, Subscribers, Customers | No | Yes | Yes |
| Audience: Email log | No | No | Yes |
| Commercial: Sponsored stories (story and placement steps, no amounts) | No | Yes | Yes |
| Commercial: the money steps on Sponsored stories | No | No | Yes |
| Awards: Nominations, Award categories | No | Yes | Yes |
| Compliance: Suppression list | Yes (read-only) | Yes (read-only) | Yes |
| Compliance: Audit log, Purge log | No | No | Yes |
| Site settings: Required plugins, Sprint thresholds | No | No | Yes |
| Posts (stories), Events, Comments, Media, Pages | No | Yes | Yes |
| Users, Tools, Plugins, Appearance, Settings (WordPress) | No | No | Yes |

Do not confuse **Site settings** (the WHOISWHO menu near the top of the left menu) with WordPress's own **Settings** menu lower down, which holds General, Reading, Permalinks, Discussion and FluentSMTP.

## What WordPress already provides

| Need | Where |
|---|---|
| Sign-in, the optional two-factor step, password reset | WordPress login and the Two Factor plugin. See [Users, roles and two-factor](/staff/users-and-two-factor). |
| Adding users and changing a role | Users |
| Per-role and per-user capability grants | The User Role Editor plugin (Users > User Role Editor) |
| Stories with scheduling, revisions and trash | Posts |
| Terms for industries, cities, countries, organisation types and professions | Term screens under each taxonomy |
| Revisions of every profile edit | Built in |
| Personal data export and erase | Tools > Export Personal Data and Erase Personal Data |
| CSV exports | Actions on the list screens and Tools > Export |

## Pages in this guide

| Page | Covers |
|---|---|
| [Add a profile by hand](/staff/add-profile) | The Add profile screen and the duplicate check |
| [Profile review and publication states](/staff/profile-review) | The review queue, the six states, editing, bulk unpublish |
| [Profile sections](/staff/profile-sections) | Writing and editing Bio, Achievement, Influence and Legacy |
| [Photos and credits](/staff/photos-and-credits) | Open licences, credit lines and adding a photo |
| [Import data](/staff/importing) | Bundles from the acquisition team: dry run, confirm, undo, the full import |
| [Claims](/staff/claims) | Deciding claims, the Claimed mark, assigned users |
| [Corrections](/staff/corrections) | Applying or declining suggested corrections |
| [Requests and reports](/staff/requests-and-reports) | Removals, reports, data requests, the suppression list |
| [Sponsored stories](/staff/sponsored-stories) | The deal tracker and the labelled slot |
| [Events](/staff/events) | Creating and publishing events |
| [Stories](/staff/stories) | Stories, Featured, curation, comment moderation |
| [Award categories and nominations](/staff/awards) | Categories, the Nominations switch, stage and status |
| [Enquiries](/staff/enquiries) | The Get listed, contact and profile enquiry inbox |
| [Newsletter](/staff/newsletter) | Sign-ups and unsubscribes |
| [Audit trail](/staff/audit-trail) | Audit, purge and email logs and exports |
| [Users, roles and two-factor](/staff/users-and-two-factor) | Adding and removing staff, capabilities, two-factor and recovery codes |

## Rules for every screen

1. Public only after review. An individual is public only after a person approves them.
2. Money never buys recognition. It can only buy a labelled story slot.
3. Everything staff do on profiles, sponsored deals, imports, bulk unpublish, suppression, nominations and capability grants is written to the audit log.
4. Claimed is a staff mark. There are no Verified profiles.
5. Notes and reasons entered by staff are never public. Do not put personal data in free-text reasons.
