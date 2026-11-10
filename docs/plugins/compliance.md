# Compliance

The module that deals with people and their rights: the support inbox, corrections, removals, reports, data requests, the suppression list, consents and retention. The processor register, lawful basis and retention schedule are documents, not screens.

## Responsibility

- Receive every public request: enquiries, corrections, removals, reports, complaints, data requests, contact messages.
- Track each with an owner, an age and a due date.
- Keep the permanent suppression list.
- Record consent, delete or anonymise data when it expires, and answer data-subject requests.
- Run the retention job from the schedule in the docs. The processor register and breach response are documents and an offline procedure, not screens (D-067).

## Content types, taxonomies and tables

| Table | Holds |
|---|---|
| `wiw_support_items` | Typed items (enquiry, correction, removal, report, complaint, data request): profile, requester name and email (personal data), message, status, owner, due date, hashed IP |
| `wiw_suppression` | Normalised name, identifiers, reason, who added it and when. Permanent. Never purged by retention. Exported to acquisition as a snapshot |
| `wiw_consents` | Subject email, purpose, policy version, granted and revoked times |
| PII catalogue (`config/pii.php`) | Every personal-data column and meta key; drives retention, export and erase |

## Admin screens

| Screen | What it does |
|---|---|
| Support inbox | One list for all typed items with a due-date countdown on removals |
| Data requests | Access, correction, erasure, objection and portability requests, each with a due date |
| Suppression list | Read for Reviewers and Editors. Changed manually only by Administrators |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `wiw_support_inbox` | no | yes | yes |
| `wiw_handle_disputes` | no | yes | yes |
| `wiw_view_suppression` | yes | yes | yes |
| `wiw_edit_suppression` | no | no | yes |
| `wiw_receive_enquiries` | no | no | yes (grantable) |

The capability list has no separate data-request capability. The data requests screen uses the inbox capability unless a separate one is added.

## Public read models and endpoints

| Route | Behaviour |
|---|---|
| `/correct/{slug}`, `/remove/{slug}`, `/report/{slug}` | Public forms with no account. A request records requester name, relationship, email and detail |
| `/contact`, `/data-rights` | The contact form and the data-rights form |
| `/confirm/{token}` | Confirms a requester's email by signed link |
| `/complaints`, `/unsubscribe`, privacy and cookie pages | Policy pages and the one-click unsubscribe |
| Cookie notice | A small card that appears only after a little scrolling, or at once on a short page |

Every form has a bot check, a per-IP and per-email throttle and a time-based form token. A normal WordPress nonce would go stale in cached HTML.

## Jobs, hooks and events

| Job | Behaviour |
|---|---|
| Due-date reminders | Flag items nearing or past their due date to the inbox owners (the same job that serves claim reminders) |
| Retention | Delete or anonymise data per the schedule, driven by the PII catalogue |
| Removal | A valid removal unpublishes through the state machine and adds to the suppression list |
| Suppression snapshot | Exported for the acquisition side |
| Email events | Sent, bounce and complaint recorded in the email log |

## Business rules

- **Targets.** Acknowledge within one business day. Hide the profile within two business days of a valid removal request from the subject. Resolve corrections within seven days.
- **Email check.** A correction, removal or report counts only after the requester's email is verified by a signed link. Staff then judge validity and check identity by hand.
- **No account.** Nothing needs login, for the requester or the owner.
- **The suppression list is permanent.** No import can bypass it. Only an Administrator edits it manually. A removal handled by an Editor adds the entry automatically, and nobody below Administrator can undo it. It also covers declined nominees.
- **A dispute is handled by whoever holds `wiw_handle_disputes`.** Editors by default.
- **No sensitive data.** The site does not collect or store sensitive personal data such as health, religion, politics, ethnicity, biometrics or criminal records.
- **Consent has a record.** Wherever consent is used, store the time and the policy version. Marketing email needs a separate opt-in and a one-click unsubscribe.
- **Retention.** Every data class has a schedule, and a job enforces it.
- **A subject can ask for information.** Profile status, view count, sources used and enquiries are sent by staff through the inbox (REQ-236).

## Tests that must exist

- `test_REQ_158_removal_request_hides_profile_within_two_business_days`
- `test_REQ_233_signed_link_is_single_use_and_get_does_not_consume_it`
- `test_REQ_207_suppression_cannot_be_bypassed_by_import`
- `test_REQ_207_only_administrator_edits_suppression_manually`
- `test_REQ_232_correction_needs_verified_email_before_counting`
- `test_REQ_059_data_request_has_due_date_and_export_lists_all_pii`
- `test_REQ_063_retention_job_removes_expired_data`
- `test_REQ_061_marketing_email_requires_separate_opt_in`
- Architecture test: a column marked personal data and missing from the PII catalogue fails the build.

## Depends on

Review (`StateMachine` for removals), Directory (profile lookups), Platform (signed links, mail, jobs, audit, capabilities).

## Requirement ids covered

REQ-018, 057 to 067, 075, 089, 090, 147, 157, 158, 174, 207, 229, 232, 233, 236, 248.
