# Awards

The module that runs the awards programme: editions, categories, nominations, stages and winners, and the public pages that show nominees and winners.

## Responsibility

- Collect nominations from anyone, with no account.
- Give each nomination a stage (Nominated or Winner) and a status (Live or Not live).
- Let staff add a profile to nominations, set the stage and status, and remove a nomination, with the approver recorded.
- Show Live nominees and Live winners on the public pages.
- Stay completely separate from commercial data.

Judging and scoring happen offline, outside the site (D-064). The module stores no judge, conflict or score data.

## Content types, taxonomies and tables

| Table | Holds |
|---|---|
| `wiw_editions` | An awards year or edition, its dates and wording |
| `wiw_award_categories` | Categories, their group, order and an archived flag. A category with nominations cannot be deleted |
| `wiw_nominations` | Nominator (personal data, none for a staff-added nomination), the linked profile, category, evidence or note, stage (Nominated or Winner), status (Live or Not live), source (public form or staff-added), the optional staff-only Contact email, and who sent a nomination or winner email and when |
| `wiw_nomination_changes` | The recorded stage and status changes and removals, each with the staff member who made it |

Nomination data is private while a nomination is Not live, and public pages are built only from a reviewed profile.

## Admin screens

| Screen | What it does |
|---|---|
| Nominations | The "Nominations page: Open / Closed" toggle, the list, filter, "Add profile to nominations", the stage control (Nominated or Winner), the status control (Live or Not live), Remove, "Send nomination email" and the winner email (both to the staff-only Contact email). This replaces the old Shortlist and winners page and the Feature flag screen (D-065, D-066) |
| Nomination detail | Nominator, nominee, evidence, change history, duplicate warnings, internal notes |
| Award categories | Add, rename, reorder and remove categories under the five award groups. Removal is blocked while a category has nominations (REQ-266) |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `wiw_manage_awards` | no | yes | yes |
| `wiw_toggle_nominations` (fixed) | no | no | yes |
| `wiw_export_data` (nominations export) | no | no | yes |

## Public read models and endpoints

| Route | Behaviour |
|---|---|
| `/awards`, `/awards/rules` | The edition, categories and process. Categories come from the Award categories screen; the eligibility and judging-criteria text are page copy edited in the page editor (REQ-260) |
| `/awards/category/{n}` | A category with its Live nominees and Live winners |
| `/nominate` | The nomination form when the switch is on. When off, "Nominations are closed", with links to the nominees or winners once published |
| `/awards/nominees`, `/awards/nominee/{ref}` | Public nominee pages, for Live nominations only |
| `/awards/winners` | The winners page, with a celebration effect on load (theme) |
| `/link/{token}` | Nominator verification (signed link, Platform) |

Public pages say "nominees" and "winners". There are no shortlist or finalist steps.

## Jobs, hooks and events

| Event | Behaviour |
|---|---|
| Nomination submitted | Throttle per IP and email; duplicate check on normalised nominee name, organisation and category; email the nominator a verification link |
| Nominator verified | The nomination is created Nominated and Not live |
| Removal request upheld for a nominee | Hide the nomination and add the name to the suppression list |
| Toggle changed | Audit with the actor, the time and the optional reason |
| Status set Live | Build the public nominee page from the reviewed profile; purge cache. A pending-review profile is refused |
| Profile added to nominations | Create a nomination linked to the profile and category, Nominated and Not live, source staff-added; audit with the actor |
| Stage or status set | Record the change with the staff member; audit. Live and Winner together put the nomination on the Winners page |
| Nomination email or winner email sent | Send a plain message with no link to the Contact email through Platform mail; record who sent it and when; audit without the address |
| Nomination removed | After confirmation, delete the nomination and audit it |
| Category removal requested | Refuse if any nomination uses the category; otherwise remove and audit |

## Business rules

- **One switch.** Whether nominations are open is the Nominations flag in settings, and nothing else. It defaults to off. An Administrator changes it from the toggle on the Nominations screen, and each change is an audit row with the actor, the time and an optional reason. Later stages are run outside the site, and only their published results appear.
- **Anyone may nominate.** It is free, needs no account, and the nominator's email is verified. The nominator's name is never shown to the nominee or the public.
- **No nominee workflow.** The site does not email nominees or collect their acceptance. A nominee who objects uses the normal removal request, which suppresses.
- **Offline judging.** Judging and scoring happen outside the site. Staff only record the outcome: who won.
- **No money path.** Payment never creates a nomination or affects a stage or status. The module has no reference to Commerce.
- **Contact email.** Optional, staff-only. It is never public, never in an export and never in a log or error message, and it is listed in the PII catalogue (`whoiswho-core/config/pii.php`). Retention and erasure remove it.
- **Two settings.** Stage is Nominated or Winner; status is Live or Not live. A new nomination starts Nominated and Not live. Each change stores the staff member who made it and is written to the audit log. No setting depends on a score.
- **Add by profile only.** "Add profile to nominations" offers existing profiles that are reviewed, pending review or in the review queue (Imported not public). There is no free-text name, so a person or organisation must have a profile first. A staff member with `wiw_add_profile` gets a "Create profile" shortcut that opens Directory > Add profile with the searched name filled in and returns to the Nominations screen after saving. A nomination of a profile that is not reviewed stays Not live (REQ-226).
- **Categories are data, criteria are copy.** Categories live on the Award categories screen. Eligibility and judging-criteria text are page copy.
- **The programme details are still being settled.** Judging detail is handled offline and may change; the current rules are the defaults.

## Tests that must exist

- `test_REQ_194_nominations_flag_defaults_off_and_only_administrator_toggles_with_audit`
- `test_REQ_259_switch_off_shows_closed_page_and_hides_form`
- `test_REQ_196_nominator_email_verified_by_single_use_link`
- `test_REQ_196_duplicate_nomination_is_detected`
- `test_REQ_198_upheld_removal_of_a_nominee_adds_to_suppression_list`
- `test_REQ_199_awards_module_has_no_reference_to_commerce` (architecture test)
- `test_REQ_198_not_live_nomination_is_never_public`
- `test_REQ_202_stage_and_status_changes_record_the_approver_without_a_score`
- `test_REQ_266_category_with_nominations_cannot_be_removed`
- `test_REQ_266_category_change_is_audited_and_shows_on_the_awards_page`
- `test_REQ_267_add_profile_creates_a_nomination_linked_to_the_profile`
- `test_REQ_268_contact_email_is_not_in_public_output_exports_or_logs`
- `test_REQ_268_contact_email_is_in_the_pii_catalogue_and_erased_with_the_nomination`
- `test_REQ_268_send_nomination_email_requires_a_contact_email_and_records_sender_and_time`
- `test_REQ_268_audit_entry_for_nomination_email_holds_no_address`
- `test_REQ_268_winner_email_uses_the_contact_email`
- `test_REQ_267_nomination_cannot_be_set_live_for_a_pending_review_profile`
- `test_REQ_267_editor_sees_the_toggle_read_only`

## Depends on

Directory (reviewed profiles for nominee pages), Compliance (suppression list)

## Requirement ids covered

REQ-030 to 034, 038, 106, 194 to 196, 198 to 202, 250, 259, 266 to 268. REQ-036, 037 and 234 are withdrawn (D-064).
