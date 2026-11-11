# Glossary

This page defines the codes and product terms used across the docs, with a pointer to where each is recorded. `docs/GUIDE.md` has the original longer version.

## Codes

| Code | Meaning | Where |
|---|---|---|
| REQ-### | One testable requirement. Numbers never change; a removed one is marked withdrawn | Requirements |
| D-### | A recorded decision, with its date and the REQs it affects | Decisions |
| Q# | A question that needs an answer, with status Open, Answered or Default accepted | Questions |
| C# | A contradiction or gap found in the brief | Questions, top table |
| N# | A non-software work item such as brand, legal or sales | Requirements, non-software section |
| ADR | An architecture decision record: one short note per big technical choice | `docs/adr/` |
| M# | A plan milestone; M0 is the foundation | The plan |
| Scope | SITE, ACQUISITION or BOTH, on every REQ, D, C and Q | The record tables |
| Gate | A stop where a human approves before work continues | `docs/GUIDE.md` |

## Product terms

| Term | Meaning |
|---|---|
| Profile | A public record of a person, business or institution, with a source beside each fact |
| Publish-first | Organisation profiles can be public before a person has reviewed them, under the label "Compiled automatically from public sources. Human review pending." Lists show reviewed profiles only (D-033, D-054) |
| Review queue | Where staff check imported records before publishing |
| Import batch | One delivery of records from the acquisition pipeline, filterable on the Profiles screen |
| Claimed | A profile whose claim a staff member approved. Staff see a checkmark; the public sees nothing, and the Claim button disappears (D-059) |
| Claim | A request from a person or representative to take care of a profile. Decided within 5 days |
| Assigned user | The staff user kept on a claimed profile to update it. Their edits go to review (REQ-253) |
| Verified | Retired. There are no Verified profiles (D-059) |
| Featured | A flag on stories, using the native WordPress feature. Profiles are never Featured (D-055) |
| Event | Its own content type for something happening on a date: a conference, launch or ceremony. Has its own list and pages, not part of Stories. A story can link one event (REQ-262, 264, 265) |
| Sponsored | The paid-placement label. Only stories carry it, it is always visible with the sponsor's name, and the paid slot never reorders other stories or search (REQ-263) |
| Arrangement (deal) | A sponsored story deal with one sponsor, one story and one paid slot. The only type for now is the sponsored post |
| Sponsored stories screen | The one staff screen for a deal's four steps: deal agreed, payment recorded, story ready, placement. Money never changes an award, a rank or a profile label |
| Suppression list | People who asked to be removed, so they are never republished |
| Bulk unpublish | Unpublish every profile matching a filter at once, with a reason; reversible and audited (D-043) |
| 410 | The "gone" response a removed page returns, so search engines drop it |
| Nominee | A person or organisation put forward for an award. Publicly there are only nominees and winners (D-055). A nomination's stage is Nominated or Winner, and its status is Live or Not live (D-066) |
| Nominator | Someone who submits a nomination; their name is never shown to the nominee or public |
| Nominations flag | The single switch that opens and closes nominations, a toggle on the Nominations screen (D-037, REQ-259, D-065) |
| Offline judging | Judges decide and score outside the site; staff only record who won |
| Capability | A named permission. Roles are default bundles of capabilities |
| Role | Visitor (no account), Reviewer, Editor or Administrator. The Administrator can add more (D-044, D-059) |
| Curation | Choosing what the home and stories pages show: Latest, Random, Manual or Pinned then fill (REQ-257) |
| Single-use link | A signed link that works once, used for nominator checks and consent pages |
| Sample data | The made-up names in the prototype. Every name is a sample |

## Tools and plugins

| Name | What it is |
|---|---|
| WordPress | The CMS the site is built on (D-031) |
| `whoiswho-core` | The custom plugin that holds the product logic |
| `whoiswho-guard` | The must-use plugin that enforces the rules |
| wpDiscuz | The comments plugin chosen for stories (REQ-258) |
| VitePress | The tool that builds this docs site |
| Lenis | The smooth-scroll library in the prototype |

## Status words

| Word | Meaning |
|---|---|
| Specified (S) | The brief or the requirements name the page or screen |
| Implied (I) | Nobody wrote it down but the product needs it |
| Withdrawn | Removed by a decision; kept in the file for traceability |
| Amended | Changed by a later decision; the note says which one |
| Draft copy | Wording still to be approved by Brand or Legal |
