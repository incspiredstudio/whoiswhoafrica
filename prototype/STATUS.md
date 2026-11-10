# Prototype status (wireframe session)

Updated 2026-11-08 on branch `chore/wireframe-review-fixes`. Nothing committed by this pass. Smoke test: 2,069 checks, 0 failures (Node, stubbed DOM; not tried in a real browser). The localStorage key is `wiw-wireframe-v6`. 19 flows. Inventory: 121 entries, 0 unexplained REQs.

## Done in the follow-up pass (D-042 to D-044)
1. **Sources, read-only (D-038, D-042; REQ-242, 243).** Changed.
   - New screen A59, `#/admin/sources`, for Reviewer, Editor and Administrator. One row per source: profile count, last import date, import errors, removals, and a link that filters Profiles by that source. No add, edit, approve, reject or publish control exists anywhere (smoke-tested). A note says approval (REQ-136 to 138) happens in the acquisition pipeline.
   - Every sample profile now has `srcName` and `batch` (several sources; batches B-2026-11-01, 03, 05, 06; customer profiles have "Customer submission" and no batch).
   - The Profiles list (A05) filters by source, import batch, origin, type, state and name, and groups by source or batch.
   - Each field shows its source URL and retrieval date, read-only: already done in the review queue (A04); added a "Fields and their sources" card to the staff profile screen (A06). Public profiles already showed sources.
2. **Claim verification and AI drafting absent from every menu and route.** Already done in the earlier pass (A08, A09, A50, A23 removed). Now smoke-tested: no `/admin/claim`, `/admin/ai`, and no menu label with claim or AI drafting.
3. **Kill switch removed (D-043).** Changed.
   - Removed: screen A27, `W.isKilled`, the visibility "kill" branch, the banner, the flag-page card, the dashboard line, flow 3, state `kill`, and all copy.
   - Replaced by **bulk unpublish by filter** on Profiles (A05): filter by source, type, import batch and state (and origin, name); live match count of published matches; reason required; a confirmation that shows the count and the first five names; each profile goes to 410 with noindex, leaves the sitemap and search, and gets a purge-log entry; one audit row per profile plus one batch row; "Recent bulk actions" with **Restore this batch**. Editor and Administrator only.
   - Flow 3 is now "Bulk unpublish by filter, 410 and restore".
   - The suppression list and the removal flow are unchanged. A valid removal handled by an Editor adds the name automatically; manual changes are Administrator only.
4. **Roles (D-044).** Changed. Only Visitor, Reviewer, Editor, Administrator, in the roles list, personas, top-bar switcher, ACLs, flows, seed data (users, "confirmed by", lead owners, score entries) and copy. The Roles and permissions screen (A54, Administrator) shows the permission matrix for the three staff roles and each role's menu. Permissions follow your list; leads and the Support inbox moved to Editor; commercial register, customers, import, exports, audit, users and roles, suppression changes, the Nominations flag and the sprint and revenue tiles are Administrator only. Reviewers get no Support inbox and no edit, publish or label controls (only "Assign existing terms").

Removed screens: A27 Kill switch. New: A59 Sources. Reworked: A02, A05, A06, A12, A22, A31, A54, and flows 2, 3, 5, 6, 8, 9, 10, 11, 13, 14, 15, 17, 18, 19.

## Round 3 (D-045, D-046): bulk unpublish for Reviewer and Administrator; per-user capabilities
- Bulk unpublish by filter and its restore are for Administrator and Reviewer only (capability `bulk_unpublish`). Editors lose it unless the Administrator grants it. Single unpublish/restore stays with Editor and Administrator (`unpublish_profile`).
- Roles are default bundles of about 30 named capabilities (`W.CAPS`). New Administrator-only screen **Users and capabilities** (A60, `#/admin/capabilities`): grant or revoke any non-locked capability for one sample user per role; audited; menu and 403s follow effective capabilities. Locked to Administrators: `users_roles`, `settings`, `commercial_register` (proposed limit, Q72). Disputes and removals: `handle_disputes` (Editor by default). localStorage key `wiw-wireframe-v7`. Smoke: 2,100 checks, 0 failures (Node, stubbed DOM).

## For shared docs (the owner applies; this session does not edit them)
Role names removed (Editor-in-Chief, Sales/Support, Finance, Awards manager) and the kill switch are still cited in:
- **Requirements:** REQ-083 and REQ-235 (seven roles), REQ-239 (sprint, revenue and arrangements for Administrator only; Editor adds support and nominations counts), REQ-240 (Reviewer menu now includes Sources and has no Support inbox), REQ-174 (Editor-in-Chief), REQ-193 and REQ-227 (kill switch, "same effect as REQ-193"), REQ-209 (kill switch listed among unpublish triggers), REQ-136 (a read-only Sources screen now exists; pipeline approval unchanged), REQ-022, 178, 179, 181, 202 (role names), REQ-069, 077, 087 (exports Administrator only; leads and register exports). REQ-242 and REQ-243 are cited by A05 and A59 but do not exist until you add them.
- **Decisions:** D-013 and D-033 control 6 (kill switch), D-036 (the kill-switch scope sentence), D-034 and D-041 (seven roles; Reviewer menu), D-038 (no Sources screen: now read-only), D-040 (Reviewers saw the Support inbox).
- **Questions:** Q68 (kill-switch scope, now a filter default), Q61 (roles and holders), Q57 and Q60 (role names).
- **GUIDE.md:** the Kill switch term and the roles text.
- **acquisition/**: nothing edited here.

## Unclear points
All in `docs/wireframe-findings.md`, rows 52 to 59 (and rows 4, 5, 20, 31, 39, 40, 41, 42, 45, 46, 50 updated). In short: who does the old Finance and Sales work (Administrator and Editor); one Administrator records and confirms a payment with no second check; Reviewers cannot see removal requests; Verified, Featured, Sponsored and slug are Editor and Administrator; bulk unpublish includes Verified and Featured profiles, and only published ones; import errors on the Sources screen are sample numbers.

## Not done
The wireframe still has no D-033 publish-first states, so the "Compiled automatically ... Human review pending" label appears only as a preview on the review screen.


### Round 3 additions to amend (D-045, D-046, REQ-244)
- **REQ-193, D-043:** who may bulk unpublish is now Administrator and Reviewer (not Editor); say it is a capability.
- **REQ-235, 240, 083, D-044:** the role defaults changed (Reviewer gains bulk_unpublish; Editor loses it). Add the capability model (new REQ-244): roles are default bundles, the Administrator can grant or revoke single capabilities per user, audited. Locked set and Q72.
- **REQ-174, 232, D-040:** disputes and removals are handled by the `handle_disputes` capability holder (Editor by default), not by a fixed role.
- **Q69, Q70, Q71:** answered by your rulings; Q72 (locked capabilities) is new.
- **Role matrix wording** in GUIDE, consistency report and requirements header: Reviewer can bulk unpublish; Editor cannot by default.

## WordPress fit (2026-11-09)
Inventory entries now carry wpScreen (native, customised-native, custom) and a recommendation. Four removals recommended (A01, A24, A34, A57), two rulings needed (A54, A60); findings 70 to 76. No screen was changed.

## High-fidelity public pages (2026-11-09, not committed)
Home page rebuilt to an Awwwards-level concept: one particle stage morphs Africa on a globe into the brand marks over the colour blocks Influence, Achievement and Legacy. Other public pages use the brand guide's colours and Repline, but keep the earlier, plainer layouts. The user asked to see the home page first, so docs/design-direction.md and docs/design-system.md are not written yet and the inner pages are not yet raised to the home page's level. Repline's licence is unconfirmed: theme/fonts/Repline.otf came from the user's Downloads folder. Brand name conflict open: the guide says WiWi Africa, D-052 says WHOISWHO. Files: theme/home-view.js, home.js, home.css, stage3d.js, shapes.js, tokens.css, site.css.

## 2026-11-09 (late): stories, claims, search
- Stories page: no hero; a post grid starts the page (one large tile, two beside it, then three-column cards, a sign-up block, more cards). D-056, REQ-249.
- Claiming returns (D-057): public "Claim this profile" form (V57), staff Claims screen (A61), Verified shown to staff as a checkmark, an assigned user per claimed profile. State key is now `wiw-wireframe-v15`. REQ-251 to 253; open Q92 to Q94.
- Search across profiles and stories is now a requirement (REQ-254). Background removal was dropped (REQ-255 withdrawn, D-060).
- Smoke test: 3005 checks, 0 failures. Inventory: 128 entries, 0 unexplained.

## 2026-11-10: backend clean-up (D-059)
- Removed: "Sponsored profile" arrangement type and the profile Sponsored form; wireframe screens A01 (sign-in), A24 (articles), A34 (users and roles), A57 (taxonomy terms); A58 folded into A06. The Sponsors screen is now "Story sponsors". Flows 18 and 19 removed (17 flows now). Public copy says "Get listed", not "featured".
- Claimed replaces Verified: the staff checkmark means Claimed; approving a claim sets Claimed only; the Verified checklist form is gone; the public Claim button is hidden once a profile is claimed (and /claim shows "Already claimed").
- Claims screen shows every form field, a decide-by date (5 days) and reminder status.
- Nominations: the switch alone decides open or closed ("Nominations are closed", no sign-up form).
- Stories: comments section (design illustration of wpDiscuz; submissions are held "awaiting moderation").
- Docs: D-059; REQ-025 and 153 withdrawn; REQ-150, 179, 183, 184, 252, 253 amended; new REQ-256 to 260 (claim reminders, home page curation, comments, nominations switch, page editor); capability `wiw_curate_home`; Q93 answered; Q97, Q98 new; architecture 4.4. Not drawn in the wireframe: the Home page settings screen (REQ-257).
- Smoke: 2,879 checks, 0 failures (the count fell because the removed screens' checks went). Inventory: 123 entries, 0 unexplained.
- 2026-11-10 (D-060, REQ-262, REQ-263): stories are articles and events. New Event story type with event fields (public Upcoming events strip, Events filter, event detail block, no payment), staff Stories screen (A62), and Story sponsors (A18) rebuilt as dated paid slots (sponsor, story, Home/Stories/Both, start, end; one slot per page; labelled Sponsored by name; never reorders other stories). State key is now wiw-wireframe-v15. Flows 18 and 19 added. Note: flow 7 still talks about a Sponsored label on a profile (stale since D-055). (Superseded 2026-11-11: events are now their own post type, see the next note.)
- 2026-11-11 (D-062 follow-up, REQ-262 amended, REQ-264, REQ-265): **events are their own post type, not a story type.** Events list (V58, `#/events`) and Event page (V59), Events in the header, mobile menu and footer, an Events section on Home (next three upcoming), staff Events screen (A63, capability `manage_events` = wiw_manage_events, Editor and Administrator), Stories screen (A62) loses the event fields and gains a Related event select; a story shows its Related event card at the end. Events cannot be Sponsored and are not in search. The red awards block on Home is removed; the endless strip of award categories stays. State key is now `wiw-wireframe-v19`. Smoke test and inventory pass.

- 2026-11-11: Events list and Event page redesigned as a calendar (agenda rows, Next up block, event poster, sticky details card) so they are distinct from stories. One minimal filter bar (W.filterBar) now serves Events, Stories, Directory and Winners: inline selects that apply on change, count, Clear only while filtered, no Apply button or chips.
- 2026-11-11 (D-064): Judging panel and conflicts (A31) and Scores and results (A51, A52, A53) removed; judging and scoring happen outside the site. Staff still shortlist, name finalists and winners, and publish; no capability beyond nominations is needed (score_entry and final_approval removed). Awards setup keeps eligibility and the published judging-criteria text. State key is now wiw-wireframe-v19.
- 2026-11-11 (REQ-266, REQ-267): Awards setup is now Award categories (A30): add, rename, reorder, remove (blocked while a category has nominations); eligibility and judging text are fixed page copy in the views (REQ-260). Nominations (A28) gains the Open/Closed switch (audited, optional reason) that replaces the Feature flag screen (A26, removed with the nominations_flag capability), Add profile to nominations (picker; a profile is required), a stage select on the nomination (A29: Submitted, Screened, Shortlisted, Finalist, Winner, Ineligible; approver recorded and audited), a Publish toggle per row and Publish winners announcement. Shortlist and winners (A32) removed. Awards menu is Nominations and Award categories. State key is now wiw-wireframe-v19. Smoke 2892 checks, 0 failures; inventory 118 entries, 0 unexplained.

## 2026-11-11: nominations simplified (D-066)
- A nomination has a Stage (Nominated or Winner) and a Status (Live or Not live). Live is what makes it public; a Live Winner shows on the Winners page. Staff can add a profile with an optional staff-only contact email, send the nomination email, prepare the winner email, and remove a nomination.
- Removed: Submitted, Screened, Shortlisted, Finalist, Ineligible, the invite step, the nominee response page and token (N06 to N09), and the Publish winners announcement panel. State key is now wiw-wireframe-v19.
- 2026-11-11 (D-067): WordPress provides Roles and permissions (A54), Users and capabilities (A60), Exports (A41) and Compliance with the breach log (A37, A38), so they are dropped from the prototype; Settings (A47) is now Sprint thresholds at `#/admin/thresholds`. The capability matrix (`W.CAPS`) stays as the permission engine. State key is now `wiw-wireframe-v19`.
