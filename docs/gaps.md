# Gaps record

Written 2026-11-14, after the no-code production build. It records every gap found while building and testing that was **not** fixed, with the recommended option. Fixed items are listed at the end so the record is complete. Owner decisions are marked **Owner**.

Sources:
- The lane reports.
- The security review (`docs/security-review.md`).
- The production-level install test: a fresh WordPress 7.1.4 on MariaDB with the environment left at production, installed only from the release zips through wp-admin, with real SMTP.
- The full import test of all 13,407 acquisition records through the Import screen.

## Needs an owner decision

| # | Gap | Recommended option |
|---|---|---|
| G2 | Two-factor is optional (owner decision). OWASP ASVS L2 expects it for staff (SEC-13). A reminder now exists (issue 814): every staff member without it sees a notice on every admin screen and a Dashboard box, and gets one email at their first sign-in, until they turn it on. | Every Administrator and Editor turns it on before go-live (handbook: Users and two-factor). The Setup checklist shows who still has not. |
| G4 | The CLAUDE.md rule "production runs migrations only through the deploy script" now has an exception: the Administrator's guarded "Update the database" button (after a backup). | Confirm. The docs (CLAUDE.md, runbook) already describe it. |
| G5 | The directory "When shared" panel is now shown on profiles (REQ-006 amended). Earlier wording said it never renders live. | Confirm. |
| G6 | The wpDiscuz subscription bar (opt-in, double opt-in) is still shown. | Keep it (it is covered by data export and erasure), or turn it off in `WpdiscuzSettings`. |
| G7 | The prototype's directory/winners card ribbons ("Nominee", "Winner"), the contact bullet list, and some copy (404 heading, winners kicker, industries lede) differ from the site. | Ribbons: a follow-up issue. Copy: edit as content. |
| G8 | There is no screen to write or send a newsletter (only Audience > Subscribers with CSV export). | Choose a sending path that uses the built-in unsubscribe link before sending starts. |
| G9 | Five records are skipped by the exporter (historical states: Kingdom of Kongo, Kanem-Bornu, Federation of Rhodesia and Nyasaland). Image licences refused: about 60 ported CC, 76 GFDL, 2,416 owner-copyright logos. | Map the five to PAN or a successor state. Decide whether to allow the ported CC variants. |

## Data and acquisition

| # | Gap | Recommended option |
|---|---|---|
| G10 | Staging provenance labels Claude-session text as "deepseek". AI narrative is exported with confidence medium (staging said low). | The acquisition session fixes this in `reword.py`. |
| G11 | There are no claim or passage checks for AI narrative sections (REQ-166/168). | A pipeline step that emits claims for narrative sentences. |
| G12 | Criterion codes HIST and LEAD were added to the contract (v1.1). | Keep them, with a reviewer confirming before publication. |
| G13 | No suppression list exists yet, so pipeline check 2 is nominal. The website's check 3 is the real one. | Build the list before the first public release. |
| G14 | `current_organisation_ref` is set only on exact organisation-name matches. U7 (an unpublished person linked to an organisation) is still open. | Match by Wikidata id. |
| G15 | The acquisition README does not mention `export_bundle.py`, and its line "no contract export yet" is outdated. | The acquisition session updates its README. |
| G16 | Two sample profiles are titled in Arabic script in the data. The industry list mixes raw values ("central banking"). | Check on the data side; prefer the English or Latin label. |
| G17 | Image refusals are not part of the import result returned to acquisition (`ImportResult`). | Add a contract line saying whether per-image codes travel back. |
| G18 | A record removed from a later export is never unpublished. | Decide a rule: flag for review on absence. |
| G19 | The importer does not write `_wiw_external_ids`, so Add profile's duplicate check by Wikidata id only finds hand-added profiles. | Write external ids on import. |
| G20 | Batch undo moves profiles to rejected instead of deleting them (by design). | Explain this in the handbook import page. |

## Product and admin

| # | Gap | Recommended option |
|---|---|---|
| G21 | A generated `HEALTHZ_TOKEN` is never shown, so the detailed /healthz is unreachable without a constant. | A masked reveal on the Setup screen for the Administrator. |
| G22 | `SIGNED_LINK_KEY` is unused (signed links are random tokens hashed with SHA-256). | Keep it for future HMAC use, or drop it from `.env.example`. |
| G23 | The guard still needs a File Manager step at install (owner: keep as is). | Optional later: ship it inside the core zip with a one-click "Install the guard". |
| G24 | Turnstile is not on the two-step nomination form. | Add the widget, verified on step one. |
| G25 | Sites built from the repo's wp-config template have DISALLOW_FILE_MODS on, so Required plugins cannot install there. | Add FluentSMTP and wpDiscuz through wpackagist to composer for template-built deploys. |
| G26 | The privacy link in the comment consent tick depends on the Privacy Policy page being set. Demo Pages set it; a site without demo pages must set it in Settings > Privacy. | Add a Setup checklist line. |
| G27 | "Import everything as not public" defaults to on, so no organisation is public until an Administrator switches it off. | Say so on the Setup checklist and in the handbook. |
| G30 | A pending image change shows `image_credit` as raw JSON in the review queue. | A friendly display. |
| G31 | No alias-name duplicate check. | An alias field later. |
| G33 | List values the demo adds remain after demo removal. With a bundle shipped, 8 invented stand-in profiles (used by demo awards) appear in the directory next to the 50 real ones. | Remove demo-added terms that nothing uses. Label the stand-ins "(sample)". |
| G34 | Existing installs keep their old About content, because starter pages never overwrite. | Runbook note: re-insert the About pattern. |
| G35 | wpDiscuz article rating is hidden by CSS. | Remove the rule if ratings are wanted. |
| G50 | The Review item screen overflows by 30 px at 360. When a hidden detail screen is open, its menu group is not highlighted. | Admin tables scroll inside a wrapper; fix `parent_file` for detail screens. |
| G51 | The Directory > Profiles list still shows raw type and state codes. | Reuse `Review\Admin\Labels` there. |
| G52 | The guard-missing reminder is a red error notice, but the checklist calls the guard "Recommended". | Make it a warning. |
| G53 | Re-importing an undone batch also takes back a profile that a person rejected after the batch was registered (it returns to the review queue). | Acceptable; skip profiles whose last rejection was by a person if this matters. |
| G54 | Profile owner accounts (D-071) have no retention rule yet. | Suggested: delete an owner account that has owned no profile for 12 months. **Owner** to confirm. |
| G55 | Profile owners cannot change their own email address; staff change it under Users. | Add self-service email change with confirmation to the new address. |
| G56 | Claim evidence standard is still open (issue #10). | Default: one of an official web page naming the claimant, a registration number or a signed letter; an Editor approves; two people for a high-profile organisation. **Owner** to confirm. |
| G57 | Profile owners are not redirected away from admin-ajax.php, so plugins such as wpDiscuz keep working; each ajax handler checks its own capability. | Acceptable; review any new plugin's ajax handlers for owner access. |
| G58 | Staff users assigned to a profile before D-071 keep the assignment (shown as "(earlier assignment)") but lose the old My profiles screen. None exist on live. | Transfer such profiles to a Profile owner account. |
| G59 | The owner account journey (sign in, edit, review, wp-admin redirect) is tested in Playwright locally only: CI does not set the e2e test mode, so the spec skips there. The two-factor step for an owner who turns it on is not browser-tested. | Enable the account spec in CI with a seeded owner. |
| G60 | Defaults picked for owner accounts, for the owner to confirm: lost-password emails go only to owner-only accounts (staff use wp-login); staff who sign in at /account/ go to wp-admin; owner login names are random; a claim whose email belongs to a staff account cannot get an owner account; one pending change per profile at a time (a second is blocked). | **Owner** to confirm. |
| G61 | Staff UI kit (REQ-275): phase 1 covered the Review queue, Profiles, Edit profile and Add profile; phase 2 (PRs #863, #864, #865) converted the remaining staff tables and forms (Platform and Import, Content, Commerce, Awards and Review, Claims, Enquiries, Requests, Corrections and Suppression), and issue #866 added read-only lists, GET paging and the one-page pager option to the kit. Done. | Nothing open; new staff screens use ListTable and Form (docs/plugins/platform.md). |
| G62 | Defaults picked for the staff UI kit, for the owner to confirm: bulk Approve asks for the five-point checklist once for all selected rows; bulk Reject takes one shared reason and reason code; pending revisions and flagged changes have no checkbox (they are decided on the item screen); there is no Return action in the review flow, so none was added; the single-row Unpublish link on Profiles keeps its earlier behaviour (no confirmation). | **Owner** to confirm. |
| G63 | WordPress core's own "Collapse Menu" button fails axe's label-content-name-mismatch rule on every admin screen. The staff UI kit spec checks only the screen content (`#wpbody-content`). | Core issue; nothing to change in the plugin. |
| G64 | Staff UI kit phase 1 defaults (REQ-275), for the owner to confirm (see also G62): bulk Approve on the Review queue asks for the five-point checklist once for all selected items; bulk Reject takes one shared reason for all of them; there is no Return action in the review flow, so none was added; Assign to me is both a row action and a bulk action, and it assigns the selected items to the person who runs it. | **Owner** to confirm. |
| G65 | Staff UI kit phase 2A defaults (Claims, Enquiries, Requests, Corrections, Suppression), for the owner to confirm: bulk Reject on Claims, bulk Decline on Corrections and bulk Remove on the suppression list each take one shared reason for all selected items; Approve and create account (Claims), Valid and Not valid (removals), Hide now (reports) and Send information are single-item only and are not offered in bulk. **The handbook (docs/pages/admin/support-and-requests.md) says the suppression list has "no remove button on purpose", but the code has an Administrator-only Remove (`wiw_edit_suppression`), now also a bulk action.** The handbook text is left as it is until the owner decides. | **Owner** to decide: keep Remove (and fix the handbook) or take it out of the code. |
| G66 | Staff UI kit phase 2B defaults (Content, Commerce, Awards, Review), for the owner to confirm: bulk Remove on Nominations uses the kit's confirmation (the browser asks, or the confirmation step without JavaScript) as its removal confirmation; Rename and Move (Award categories), Customer edit, Duplicates merge and Restore this batch (bulk unpublish) are single-item only and are not offered in bulk. | **Owner** to confirm. |
| G67 | Staff UI kit phase 2C defaults (Platform and Import), for the owner to confirm: bulk Undo on Import batches spends at most 20 seconds starting undos per request (the rest are skipped with "time ran out; select them again"); a large batch that bulk Undo only starts is counted in the "N undone" notice, and its batch page carries on with Continue; Install and Activate on Required plugins are hidden from anyone without WordPress's `install_plugins` or `activate_plugins`; Sprint thresholds shows no "saved" notice after saving. | **Owner** to confirm. |

## Security (accepted Low and Info, see docs/security-review.md)

| # | Gap | Recommended option |
|---|---|---|
| G36 | SEC-07: newsletter double opt-in confirms on a plain GET. | GET shows a button, POST confirms, before sending starts. |
| G37 | SEC-08: the audit export, logs, server import folder and shipped sample are protected only by `.htaccess`. nginx-only hosts would serve them. | Check for 403 at go-live; move them outside the web root later. |
| G38 | SEC-09: per-IP limits use REMOTE_ADDR. | Behind Cloudflare, restore the real IP with mod_remoteip first. |
| G39 | SEC-10: wpDiscuz reply-subscription mail is not rate-limited by us. | Turn off the subscription bar (G6) or add a limit. |
| G40 | SEC-14: `release.json` and vendor `installed.json` are visible in the web root. | Deny them in `.htaccess`. |
| G41 | SEC-16: profile view counts can be inflated; form tokens can be replayed within their hour. | Acceptable for launch. |

## Performance and testing

| # | Gap | Recommended option |
|---|---|---|
| G42 | Story pages with wpDiscuz load 212.6 KB of script (jQuery plus wpDiscuz) against a 20 KB budget. | A per-path budget for stories with comments, or trimming wpDiscuz assets. |
| G43 | Directory and search times with 13k **public** profiles were not measured (the import defaults to not public). | Measure after the list clean-up (G3) and publication. |
| G44 | A full import takes about 90 minutes on a single-threaded local server, and the background queue did 80% of the work only while pages were loading. | Add a cPanel cron for wp-cron with DISABLE_WP_CRON. |
| G45 | Hero videos total 1.85 MB. | Re-encode MP4 at 350 kbps or less and add an AV1 WebM for clip 1 (about 1.2 MB total). |
| G46 | Lighthouse was not run (installing it was blocked in this environment). | Run Lighthouse on staging. |
| G47 | Flaky tests on a loaded machine: `Performance\SearchAt10000Test` (p95 near 0.3 s), the required-plugins notice e2e, `page-reveals` on /awards. Isolation: the WordPress test install empties posts but not the plugin's own tables, so import tests run twice on one database fail ("profile already has a state"); `ApprovalFlipTest` is order-dependent when filtered. CI is fine on its empty database. | Run perf tests outside the main suite; truncate the plugin's tables in the test bootstrap. |
| G48 | Home at 360: the "Scroll" cue over the hero video has low contrast in axe. | A darker scrim behind the cue. |
| G49 | Inboxes for the production SMTP test were not checked; only the site's "sent" status and FluentSMTP's "delivered" are confirmed. | The owner checks the +prodtest and +prodform inbox messages. |

## Fixed during this build (for the record)

- G3 and G32 (classification tree, contract v1.2, 2026-11-14): industries are a fixed tree (sectors, their industries and Unclassified) and organisation types a curated list, both seeded at first run and by migration 2026112103 from `config/classification.php`. The importer never adds industries, sectors or organisation types: a contract 1.2 record carries its classification, and an older record maps through the aliases or to Unclassified (or Other). The import adds an unknown head office city under the record's primary country itself (owner decision 2026-11-14), so "Add all as new places" is gone. The upgrade folds the old raw terms into the tree and empties the retired sector list. Issue #815 (curated lists on the site) is superseded by this work: its acquisition half shipped as contract v1.2, and its site half is replaced by the tree.

- G1 (owner approved 2026-11-14, #813): the guard switches off the built-in code editors itself (the effect of DISALLOW_FILE_EDIT without wp-config). Uploads stay open for Administrators, and the red DISALLOW_FILE_MODS notice is replaced by a dismissible Dashboard note.

- The HTTPS redirect locked out a new site that had no certificate yet. It now waits for an https site address.
- Live updates were stuck on "run the deploy". A guarded "Update the database" button was added.
- FluentSMTP stored email bodies (with signed links) in its log. Logging is forced off.
- Demo awards and home pins named real sample people. They now use invented stand-ins.
- The "Settings" menu clashed with WordPress Settings. It is renamed "Site settings".
- Security: the login lockout per account and per address (SEC-01), the zip entry size cap (SEC-02), the sideload prefilter (SEC-03), admin-ajax exposure (SEC-04), log folder deny files (SEC-05) and import folder deny files (SEC-06).
- Edit profile: paragraph boxes and counters, photo changes, and plain labels. The Customers screen is now Administrator only.
- From the production tests: profile slug double-encoding, wpDiscuz comment feedback, Editor block-editor errors, the username shown in bylines, duplicate and raw admin menus, WordPress default content, demo screen text, the backup notice, the claim email wording, the logo preload warning, SHA256SUMS format, palette PNG warnings, INSTALL.md wording, the sample zip in the public folder, the guard blocking theme/plugin zip updates, undo deleting re-used images, re-importing an undone batch, step time budgets, and the SCHEMA wording (lane F, round 10).
- G28 (round 10 integration): Add profile and the review labels now read field labels through `FieldAllowlist::label()` (the `label` key in `config/profile-fields.php`); `AddProfileScreen::field_label` is gone. Add profile's paragraph boxes have the Edit profile character counter.
- G29 (round 10 integration): `mandate` has `max` 2000 in `config/profile-fields.php`.
