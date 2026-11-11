# Privacy and data rights

WHOISWHO publishes facts about real people, so privacy is a design rule. This page covers what is held, what is never stored, how long data is kept, how people use their rights, and what loads in the browser.

## What is held

| Data | Where | Public? |
|---|---|---|
| Profile facts, each with source and retrieval date | Profile records | Yes, once the profile is public |
| Requester name, email and message | Enquiries, Corrections, Requests and reports | No |
| Claim details (name, role, work email, phone, evidence, consent) | Claims | No |
| Nominator details | Nominations | Never |
| Nominee data, including the staff-only Contact email | Nominations | Private while Not live. The Contact email is never public. |
| Newsletter sign-ups and consents | Subscribers | No |
| Story comments (name, email, text) | Comments | Name and text after approval. Email never shown. |
| Staff accounts and their activity | WordPress users, audit log | No |

Every column or field that holds personal data is listed in one catalogue. The catalogue drives retention, export logging, log scrubbing and the data-request export. A test fails if personal data is missing from it.

## What is never stored

The site has no field for these, and free text is scanned for them:

- home address, phone, personal email, family details
- date of birth or age
- health, religion, politics, ethnicity, finances, biometrics, criminal records
- allegations
- personal social media handles

An individual's profile holds only name, current title, current organisation, industry, public-record roles, published awards and an official or organisational website. Individuals under 18 cannot hold a profile. This is enforced by review, since no age is stored.

## People's rights

| Right | How a person uses it |
|---|---|
| Access, correct, erase, object, port | The data-rights page, which opens a request with a due date |
| Correct a profile | The correction form |
| Remove a profile | The removal form |
| Stop marketing email | One-click unsubscribe in every message |

No account is needed. Email ownership is checked with a single-use link.

| Step | Target |
|---|---|
| Acknowledge a request | 1 business day |
| Hide a profile after a valid removal request from the subject | 2 business days |
| Resolve a correction | 7 days |

A valid removal adds the person to the permanent suppression list, so imports never bring them back.

## Retention

A retention schedule exists for each class of data, and a scheduled job deletes or anonymises data when it expires.

| Data | Retention |
|---|---|
| Subscriber data | 6 years after the last payment |
| Nominations | 2 years |
| Suppression records | Kept, name only |
| Signed links | Expire after 14 days, or 48 hours for nominator and requester checks |
| Import files and passages | Deleted after a retention period |
| Logs | 30 days, with IDs rather than names or emails. The audit log is kept. |

## Consent and notices

A privacy notice appears at every point where data is collected. Where consent is used, it is stored with a timestamp and the policy version. Marketing email has its own opt-in.

## Cookies and analytics

The default analytics are cookieless and first-party. Non-essential cookies and scripts load only after the visitor accepts. The site shows a cookie notice with Accept and Decline, and a Cookie preferences link in the footer reopens the choice.

## Access inside the team

1. Access is least-privilege through capabilities.
2. Bulk export of personal data is Administrator-only and logged. Reviewers cannot export.
3. Evidence uploads are limited to JPG, PNG and PDF, stored outside the web root and never served inline.
4. No personal data goes into logs or error messages.

## Records the organisation keeps

Keep these as dated documents, and update them whenever something changes.

| Record | What it lists |
|---|---|
| Processor register | Every service that handles personal data, where it operates and its data-processing agreement. Add a row whenever a service is added, for example a mail provider, an uptime monitor or the comments plugin's email use. |
| Lawful basis register | The legal basis for each category of person. Public figures are published on legitimate interests with a right to object. Email sign-ups rely on consent. |
| Breach response | A short procedure: contain, assess, notify the regulator and affected people within the legal deadline. Record each incident as a dated entry in the runbook without personal data. |

Photographs of real people are personal data. Photographs are used as supplied.
