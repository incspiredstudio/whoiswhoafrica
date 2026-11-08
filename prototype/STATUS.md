# Prototype status (wireframe session)

Updated 2026-11-08 on branch `chore/wireframe-review-fixes`. Nothing committed by this session.

## Done in this pass (review fixes 1 to 7, 8b)
- **Dashboard (D-035):** the Directory target tile and every "x / 100" bar are gone; the review-capacity card, its backlog warning and the demo button are gone. Counts by state (imported, pending human review, reviewed, published). Dashboards differ by role; the sprint target, revenue per stream and arrangements show only for Administrator, Finance and Sales/Support. The top-bar role switcher covers visitor and all seven staff roles.
- **No claiming (D-036):** Request to claim (V42), claim verification (A08, A09, A50), "Claim this profile" and the home-page claim card are removed. No Claimed or Unclaimed label, state, filter, token or CSS. Remaining labels: Verified, Featured, Sponsored, and "Reviewed on <date>". A staff-approved customer profile no longer becomes Claimed.
- **Kill switch scope:** every individual profile whose origin is the acquisition import (`origin: "import"`), even if later Verified, Featured or Sponsored. Customer-submitted individuals are not affected (default pending Q68).
- **One flag (D-037):** only `nominations`. Judging, announcements and ai_drafting are gone. Score entry is not gated. Shortlist, finalist and winner pages go public when staff press Publish (new `pub` field); the winners page when staff publish the announcement. The kill switch is shown apart from the flag, in plain words.
- **No Source registry (D-038):** Sources screen (A19) and Approve/Reject are gone. Import (A20) dry-runs against the import contract and shows a note that sources are approved in the acquisition pipeline. Reviewers see each claim's source URL and retrieval date read-only.
- **AI drafting screen (A23) removed (D-041).**
- **Taxonomies (D-039):** the Controlled lists screen (A25) is replaced by native-style term screens (A57) for Industries, Cities (under countries), Countries, Organisation types and Professions. Editors and Administrators edit terms; Reviewers cannot open them and only assign existing terms on the profile (A58). Country list is Nigeria-first with other African countries and pan-African bodies; the country filter works.
- **No corrections screen (D-040):** Abuse reports (A10), Corrections, owner change requests and removals (A11) and the "Send to review" and second-approver steps (A49) are gone. Correction, Removal and Report arrive in the Support inbox (A12) as typed items with a status and a due countdown. Staff edit a profile directly (A58); edits and unpublishes are audited; a valid removal suppresses. Personal-data exports are Administrator-only and logged.
- **Menu by role (D-041):** the Reviewer menu is exactly Dashboard, Review queue, Duplicates, Stale records, Profiles, Suppression list, Support inbox. Roles and permissions (A54) lists the capability matrix and the menu for every role. Routes enforce it (403).
- localStorage key `wiw-wireframe-v5`. 19 flows, all runnable. Inventory 121 entries. Smoke test: 4,450 checks, 0 failures (Node, stubbed DOM; not tried in a real browser).

## For shared docs (the owner applies; this session does not edit them)
Requirements and decisions that the wireframe now contradicts or no longer uses:
- **Claiming (D-036):** REQ-124, 132 ("published as Unclaimed"), 150 (ladder), 151 (Unclaimed label), 152, 156 (Claim action), 160 and 173 ("claimed owners"), 174 (claim disputes), 193 ("unclaimed individual profiles"), 216 ("Unclaimed" wording), 230, 231, 094 ("claim received, claim resolved"), 089 ("claims" in the inbox). D-022, D-002, D-003, D-013, D-034 (claim parts). GUIDE terms.
- **Dashboard (D-035):** REQ-191 (about 100 + 100 target), REQ-206 (backlog warning vs capacity), D-012 ("about 100"), the acquisition plan's capacity assumptions. Inventory gap rows were removed.
- **Flags (D-037):** REQ-194 (list of flags), REQ-165 (AI drafting flag), D-009, D-019; "flag" wording in REQ-038, 198.
- **Sources (D-038):** REQ-136, 137, 138 are enforced in the pipeline, not in the website admin; REQ-092 and 237 (dry run) no longer mention an approved-source toggle.
- **Taxonomies (D-039):** REQ-009 says controlled lists; D-010 text "Nigeria only"; Q-list for the countries.
- **Corrections (D-040):** REQ-232 (usual review, second approver), REQ-015 (sensitive fields wait for review), Q57 (second approver for sensitive fields) are stale; REQ-158 timings now appear as the Support inbox countdown; REQ-069 export rule.
- **Menus (D-041):** REQ-235 and 083 role lists; REQ-239 to 241 are cited by A02, A54 and A04.
- **Kill switch:** Q68 (scope default is imported individuals regardless of label).

## Unclear points (all in docs/wireframe-findings.md, 38 to 51)
Staff edits are live with one person's sign-off (no second approver); which roles may edit profiles directly; who runs imports (assumed Editor and Editor-in-Chief); Editor-in-Chief on taxonomy terms (assumed); who publishes nominee pages (Awards manager, Editor-in-Chief, Administrator); Finance and Sales/Support lose their CSV exports (Administrator-only), which affects the CaromHQ exchange (Q16); the sprint-target bars stay for commercial roles.

## Not done
The wireframe still has no D-033 publish-first states, no AI Publisher or AI Monitor roles, and shows the pending-review label only as a preview on the review screen.
