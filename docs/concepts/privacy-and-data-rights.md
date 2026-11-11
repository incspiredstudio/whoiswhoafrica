# Privacy and data rights

The platform publishes facts about real people, so privacy is a design rule and not an afterthought. This page covers what is held, what is never stored, how long data is kept, how people use their rights, and what loads in the browser.

Requirements: REQ-057 to 072, 139, 140, 147, 158, 233. Decisions: D-013, D-016, D-023, D-051. Architecture: section 6.4 and 6.6.

## What is held

| Data | Where it sits | Public? |
|---|---|---|
| Profile facts, each with source and retrieval date | Profile records and provenance | Yes, once the profile is public |
| Requester name and email, message | Enquiries, Corrections, Requests and reports | No |
| Claim details (name, role, work email, phone, evidence, consent) | Claims screen | No |
| Nominator details | Nominations | **Never** (REQ-064) |
| Nominee data, including the staff-only Contact email | Nominations | Private while the nomination is Not live; the Contact email is never public |
| Enquiries and customers | Commercial records | No |
| Newsletter sign-ups, consents | Consent records | No |
| Story comments (name, email, text) | wpDiscuz | Name and text only after approval; email never shown |
| Staff accounts and their activity | WordPress users, audit log | No |

Every personal-data column and meta key is listed in one catalogue file (`whoiswho-core/config/pii.php`). It drives retention, the export log, log scrubbing and the data-request export. A test fails if a personal-data column is missing from it.

## What is never stored

The schema has no field for these, and free text is scanned for them (REQ-067, 139, 140):

- home address, phone, personal email, family details
- date of birth or age
- health, religion, politics (including party affiliation), ethnicity, finances, biometrics, criminal records
- allegations
- personal social media handles

An individual's profile holds only name, current title, current organisation, industry, public-record roles, published awards and an official or organisational website. Individuals under 18 cannot hold a profile (REQ-068); because no age is stored, this is enforced by the seed rules and the reviewer checklist, not by a field check.

## Lawful basis

For public figures published without consent, the recorded basis is **legitimate interests with a right to object** (REQ-057, D-016). Legal's written position was requested but is **deferred and does not block the build** (D-051). Bulk unpublish is the safety net if Legal objects.

### Lawful basis register

This table is the register. It is a document in these docs, not an admin screen (D-067). Update it when Legal confirms a position.

| Category of person | Basis (default) | Status |
|---|---|---|
| Public figures published without consent | Legitimate interests, with a right to object | Awaiting Legal |
| Profile owners and customers | Contract or consent | Awaiting Legal |
| Nominators and nominees | Consent or legitimate interests | Awaiting Legal |
| Email sign-ups | Consent, stored with the policy version | Built |

## People's rights

| Right | How a person uses it |
|---|---|
| Access, correct, erase, object, port | The data-rights page, which opens a request with a due date (REQ-059) |
| Correct a profile | The correction form, handled on the Corrections screen |
| Remove a profile | The removal form |
| Stop marketing email | One-click unsubscribe on every message (REQ-061) |

No account is needed for any of these. Email ownership is checked with a single-use link ([No accounts](/concepts/no-accounts-model)).

### Removal and the suppression list

| Step | Target |
|---|---|
| Acknowledge a request | Within 1 business day |
| Hide the profile after a valid removal request from the subject | Within 2 business days |
| Resolve a correction | Within 7 days |

These come from REQ-158. The Requests and reports screen shows a countdown for removals, and breaches are flagged. A valid removal adds the person to the permanent suppression list so imports never bring them back (REQ-147, 207). See [Publication states](/concepts/publication-states).

## Retention

A retention schedule exists per data class, and a scheduled job deletes or anonymises data when it expires (REQ-063). The accepted defaults, subject to Legal's review (Q21, D-023):

| Data | Default |
|---|---|
| Subscriber data | 6 years after the last payment |
| Nominations | 2 years |
| Suppression records | Kept indefinitely, name only |
| Signed links | Expire after 14 days (48 hours for nominator and requester checks) |
| Import files and passages | Deleted after a retention period; passages reduced to hashes or removed after review |
| Logs | 30 days, with IDs rather than names or emails. The audit log is kept. |

## Consent and the privacy notice

A privacy notice appears at every point of collection. Where consent is used, it is stored with a timestamp and the policy version (REQ-058). Marketing email needs its own opt-in (REQ-061).

## Cookies and analytics

Non-essential cookies and analytics load only after consent (REQ-062). The default analytics are cookieless and first-party (REQ-078), so no consent banner is needed for them. The public site shows a cookie notice with Accept and Decline, and a Cookie preferences link in the footer.

## Access inside the team

Access is least-privilege through capabilities. Bulk export of personal data is Administrator-only and logged; Reviewers cannot export (REQ-069). Evidence uploads and intake files are stored outside the web root.

## Processors and breaches

### Processor register

This table is the register of every processor, its location and its data-processing agreement (REQ-065). It is a document, not an admin screen (D-067). Add a row whenever a service that handles personal data is added, including Cloudflare, error reporting or an uptime monitor if they come later. The comments plugin and its email use belong here too.

| Processor | Role | Location | Agreement |
|---|---|---|---|
| Web hosting (cPanel host) | Hosts the site and database | To be confirmed (Q26, Q87) | Not yet |
| Email provider (cPanel mail) | Transactional email | To be confirmed | Not yet |
| Analytics (privacy-friendly, first-party) | Visit counts | To be confirmed | Not yet |
| AI provider (acquisition pipeline, not the website) | Drafts records before import | To be confirmed; Legal confirms the transfer (REQ-169) | Not yet |

### Data-protection impact assessment and contact

The impact assessment and a named data-protection contact are process tasks run in parallel (REQ-072). Status: in progress. They are tracked on the [launch checklist](/build/launch-checklist) and not on any screen.

### Breach response

A breach-response procedure covers detection, logging and notice to the regulator and affected people within the legal deadline (REQ-066). It is an offline procedure written in the runbook (`docs/runbook.md`), with a checklist: contain, assess, notify. There is no breach-log screen (D-067). The incident record is kept as a dated entry in the runbook's incident section, without personal data.

Photographs of real people are personal data. Photographs are used as supplied; there is no background-removal tool (D-060).

## Open points

- Legal's position on individuals going public, organisations going live before review, photos and retention periods (Q5, Q35).
- A data-protection impact assessment and a named data-protection contact (REQ-072).
