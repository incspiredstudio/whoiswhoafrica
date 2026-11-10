# Consistency report: wireframe review fixes (2026-11-08, two rounds)

Branch `chore/wireframe-review-fixes`, built on `chore/repo-restructure` (neither merged). Round 3 added D-045, D-046, REQ-244, Q69 to Q72 answered or added, and the WordPress rewrite of architecture, ADRs and CLAUDE.md. Round 1 = D-035 to D-041. Round 2 (follow-up) = D-042 to D-044. `acquisition/` was not touched; what needs changing there is in section 4.

## 1. What changed

**New decisions:** D-035 dashboard counts, no directory target · D-036 no claiming · D-037 only the Nominations flag · D-038 no source registry in the admin (amended by D-042) · D-039 taxonomies · D-040 Support inbox, direct staff edits, Administrator-only exports · D-041 menus by role (superseded by D-044) · **D-042 read-only Sources screen** · **D-043 kill switch removed; bulk unpublish by filter** · **D-044 four roles only (Visitor, Reviewer, Editor, Administrator).**

**Amended or superseded decisions:** D-002, D-003, D-009, D-010, D-012, D-013, D-016, D-019, D-022, D-025, D-029, D-033 (control 6), D-034 (claims; seven roles), D-035, D-036, D-037, D-038, D-040, D-041.

**Requirements** (never renumbered):
- Withdrawn: REQ-124, 151, 152, 173, 227, 230, 231.
- Amended in round 2: REQ-036, 072, 083, 088, 133, 136, 174, 178, 181, 193, 194, 209, 234, 235, 239, 240, plus REQ-077 and 087 (exports are Administrator-only).
- New: REQ-239 to REQ-241 (round 1), **REQ-242** (filterable source and import batch fields), **REQ-243** (read-only Sources screen).

**Questions:** Q68 closed (kill switch gone). Q61, Q60, Q1, Q2, Q5, Q35 reworded. New: Q69 (who may bulk unpublish), Q70 (one Administrator records and confirms payments), Q71 (who handles disputes without an Editor-in-Chief).

**Other docs:** GUIDE (labels, roles, bulk unpublish term), requirements header, wireframe inventory (121 entries) and findings 38 to 59.

### Round 2, item by item
| Item | Status |
|---|---|
| 1 Sources read-only | **Changed.** Read-only Sources screen A59 (profile count, last import, errors, removals, link to filter Profiles); Profiles list filters and groups by source and import batch; the staff profile shows each field's source; REQ-242 and 243 added; REQ-136 to 138 stay enforced in the pipeline |
| 2 Claim verification and AI drafting out of the menus | **Already done** in round 1; now smoke-tested (no routes, no menu entries) |
| 3 Kill switch | **Changed.** Removed everywhere in the wireframe and docs; bulk unpublish by filter (source, type, import batch, state) with reason, count confirmation, 410 and noindex, audit rows and per-batch restore. D-013, D-016, D-029, D-033 control 6, D-036, D-037, REQ-072, 133, 193, 194, 209, 227 amended |
| 4 Four roles | **Changed.** Visitor, Reviewer, Editor, Administrator only; permission matrix and per-role menus on the Roles screen; role switcher offers the four. REQ-083, 174, 178, 181, 234, 235, 239, 240 and the decisions listed above amended |

## 2. Checks run
- The wireframe and inventory contain no kill switch, claim feature, Claimed or Unclaimed label, "x / 100" bar, extra flag, or the removed roles.
- No Withdrawn REQ is cited by the wireframe or inventory; every cited REQ exists. 41 REQs have no UI, each with a stated reason, none unexplained.
- Smoke test: 2,069 checks, 0 failures (Node with a stubbed DOM; not tried in a real browser). The count is lower than before because three roles' checks are gone.
- The words "Finance" and "Sales" remaining in REQ-086, 115 and D-018 mean the offline business function (refunds, payments), not a system role. Noted in the requirements header and GUIDE.

## 3. Still inconsistent, or needs your ruling

### 3.1 Rulings
**Answered (D-045, D-046):** bulk unpublish by filter is for the Administrator and Reviewer (Q69) · the Administrator alone confirms payments (Q70) · roles are default bundles and the Administrator can grant or revoke capabilities per user across roles; disputes go to whoever holds the handle-disputes capability (Q71, REQ-244) · staff edits go live on one person's sign-off · D-010 confirmed. I read "add certain reviewer capabilities to admin and vice versa" as "between roles"; the Administrator already has everything.

**Defaults accepted by you (D-047):** all remaining questions, including Q72 to Q80 (non-grantable capabilities, submitted-profile state, `sources[]` in the contract, R2, two-factor, places taxonomy, background jobs, `site/` ownership). Exports Administrator-only, the sprint target for the Administrator only, the Editor's wide powers and Administrator-only suppression changes stand as built.

**Staff sign-in (D-048, REQ-245):** no role selector; an email is one user with one role.

### 3.2 Not yet reflected
- **Wireframe has no publish-first states** (REQ-210 to 228) for the amended D-033.
- **architecture.md, the ADRs, CLAUDE.md and .env.example are rewritten for WordPress** (proposed v2, awaiting your Gate 2 approval). ADR-009 (AI drafting) is superseded: that work is in the acquisition pipeline. Laravel and Filament remain only as history. Cost, versions, plugin choices and host limits are marked "verify" until the M0 spikes. Import is a file upload (JSON Lines), not REST (Q77).
- **docs/contracts/import-format.md**: unknown U8 and the Sources names (Q74) need the contract owner; the architecture proposes answers for U1 to U14 in section 3.4 but the contract is unedited.
- **CLAUDE.md** now names `site/` for the website code. The ownership rule for it is Q80.
- **docs/spec.md** is unchanged (it lists a Judge role, Subscription plans, claim and kill-switch features). It stays as the intent.
- **traceability.md and plan.md** do not exist yet.
- **docs/change-review-2026-11-07.md** (the WIWI rename review) is resolved: brand stays WHOISWHO, legal name whoiswhoafrica, no crawler name (D-052). Q40–Q45 are logged and answered.
- **Earlier change reviews** (`change-review-2026-11-08-no-accounts.md`) describe the seven roles and the claim flow as they were. They are history; D-036 and D-044 supersede them.

## 4. In `acquisition/` (not touched; needs changing there)
- **Claims:** header (line ~9) and step 13 (~252): replace the request-to-claim text with correction, removal and report handled in the Support inbox (D-036, D-040). T-24 (request to claim and claim verification): drop. T-26 mentions claim email verification: change to correction, removal and report requesters. T-25 (second approver): update to D-040. T-28 "flagged": update to D-037.
- **Kill switch (line ~584 and elsewhere):** replace with bulk unpublish by filter (D-043); "kill switch covers unclaimed individuals only" no longer applies. REQ-227 is withdrawn; its batch unpublish is a filter on import batch.
- **T-1 "Source registry: admin screen":** the registry lives in the pipeline; the website has a read-only Sources screen (D-042, REQ-243).
- **Roles:** any mention of Editor-in-Chief, Reviewer duties beyond review, Finance, Sales/Support or Awards manager. The Editor handles disputes (REQ-174).
- **Unclaimed wording:** line ~682 ("a published unclaimed profile helps a sales conversation") and ~804 (photos on unclaimed individuals; Q34 text is already corrected in questions.md).
- **Capacity and targets:** lines ~13, ~379, ~526 (D-012 and REQ-191 "target reviewed profiles live", "first 100 organisations", "backlog above 2 weeks of capacity: workload warning REQ-206"). The website shows no capacity figure or directory target (D-035). Keep these as planning estimates only, labelled as such.
- **REQ-165:** AI drafting has no website flag; the plan should not refer to one.
- **Workload note (D-034):** mentions claims; reword.
- **Import contract and batches:** the website's bulk unpublish and Sources screen filter by `source_id` and import batch, so the contract's `source_id` and batch ID must stay stable (U8).

## 5. Next
1. Rule on 3.1.
2. Merge order: `chore/repo-restructure`, then `chore/wireframe-review-fixes`.
3. Acquisition session applies section 4.
4. Gate 2: rewrite `architecture.md` for WordPress with D-034 to D-044 as input.


## Addendum 2026-11-09 (D-049 to D-051): further changes needed in `acquisition/`

- Remove reviewer hourly rate, reviewer hours and hours-based dates (Q31, Q38) from the plan, cost estimate and workload notes. Keep the US$250 AI cap.
- Legal is skipped for now (D-051): do not make steps wait on Legal; mark Legal-dependent items deferred. Q35's default still keeps individuals not public.
- Q74: the manifest `sources[]` is a basic structure for now (D-049); confirm and change after acquisition.
- Q28: new accounts are not needed now; the Anthropic API account stays with acquisition.
- Q46: AI summaries are published as written; the platform gets an in-house review before launch.
- CaromHQ sync (Q16) is built last (D-051).
- Crawler (D-052, Q45): drop the "WHOISWHO-bot/1.0" identity and info-page step (plan 2.1, step 2 Fetch); keep robots.txt and terms checks.
