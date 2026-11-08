# Consistency report: wireframe review fixes (2026-11-08)

Branch `chore/wireframe-review-fixes`, built on `chore/repo-restructure`. Covers items 1 to 8 of the wireframe review. `acquisition/` was not touched; what needs changing there is in section 4.

## 1. What changed

**New decisions (docs/decisions.md):** D-035 dashboard counts, no directory target · D-036 no claiming · D-037 only the Nominations flag · D-038 no source registry in the website admin · D-039 controlled lists are WordPress taxonomies · D-040 corrections, removals and reports in the Support inbox · D-041 left menu by role.

**Amended or superseded decisions:** D-002, D-003, D-009, D-010 (see 3.1), D-012 (superseded by D-035), D-013, D-019, D-022 (superseded by D-036), D-025, D-034 (claim parts superseded; corrections amended).

**Requirements** (never renumbered):
- Withdrawn: REQ-124, 151, 152, 173, 230, 231.
- Amended: REQ-009, 015, 018, 038, 069, 088, 089, 094, 104, 132, 136, 137, 138, 150, 156, 160, 165, 174, 181, 191, 193, 194, 206, 227, 232, 233, 235, 236. Priority `M (flag)` became `M` on REQ-033, 034, 036, 037, 038, 198, 200, 202, 234 (only the Nominations flag exists). REQ-196 and 197 stay flagged.
- New: REQ-239 (dashboard counts and role views), REQ-240 (menus by role), REQ-241 (read-only source beside each claim).

**Questions:** Q49, Q18, Q57, Q59 closed as Answered (overtaken). Q11, Q12, Q24, C10, Q34, Q2, Q56, Q58, Q62 reworded. Q68 added (kill switch scope). Counts updated.

**Other docs:** GUIDE (labels table, flag and kill-switch terms), design-inspiration (card labels), contracts/import-format (one "claimed" removed), wireframe inventory (121 entries, regenerated) and findings 38 to 51.

**Wireframe:** details in `prototype/STATUS.md`. Smoke test: 4,450 checks, 0 failures (Node, stubbed DOM; not tried in a real browser).

### Stale items found and fixed (item 1d)
"About 100 + 100" target and progress bars · "2 reviewers × about 40 = 80" and the backlog warning · "Country list is Nigeria only" and the disabled country select · "Nigerian only" award eligibility text · the Reviewer 1 / Reviewer 2 split · "Owner-supplied" in the review diff · "Unclaimed" wording on home, directory, profile and How-we-compile pages · inventory gap rows for REQ-191, 192, 206.

## 2. Checks run
- No wireframe or inventory text contains a claim feature, Claimed or Unclaimed, an "x / 100" bar, the Directory target tile, a judging, announcements or AI-drafting flag, or "Nigeria only".
- The wireframe cites no Withdrawn REQ. The inventory no longer cites REQ-124 or 173.
- Every decision except D-026 (a working principle) lists a REQ.
- Every REQ the inventory cites exists. 45 REQs have no UI, each with a stated reason; none unexplained.
- The remaining word "claim" in requirements, decisions and questions is either a withdrawn row (kept for traceability), the original text inside an amended decision, or an AI "claim" meaning a statement of fact.

## 3. Still inconsistent, or needs your ruling

### 3.1 Needs a ruling
| # | Item | Why | Recommended default |
|---|---|---|---|
| 1 | **Direct staff edits go live on one person's sign-off** (D-040, REQ-015, REQ-232) | D-015 says 100% human review before anything publishes. A staff edit is human, but there is no second check. Q57 (second approver) was closed as overtaken by your instruction | Accept; every edit is audited and versioned. Reconsider for name, title, organisation, awards if errors show |
| 2 | **Who may edit a profile directly** | The wireframe lets Reviewer, Editor and Editor-in-Chief; a Reviewer editing is wide | Reviewer: edit and unpublish; keep. Confirm |
| 3 | **Kill switch scope** (Q68, REQ-193) | Set to all import-origin individuals, even if later Verified or Featured; customer-submitted ones exempt | Yes |
| 4 | **CSV exports are Administrator-only** (REQ-069, D-040) | REQ-077 (register, leads, enquiries to CaromHQ, Q16) and REQ-087 (register CSV) need exports by Finance and Sales/Support; REQ-131 is the venture's own data export | Administrator-only for personal data. Finance exports the register through an Administrator request, or the register export is carved out. Rule which |
| 5 | **Sprint target kept** (10 subscriptions or NGN 5m, with bars) | Your no-target rule covered directory size. REQ-181 keeps the commercial target for Administrator, Finance and Sales/Support | Keep |
| 6 | **Editor-in-Chief edits taxonomy terms; Editor and Editor-in-Chief run imports; Awards manager, Editor-in-Chief and Administrator publish nominee pages** | Assumed in the wireframe, not stated by you | Confirm |
| 7 | **D-010 was never actually amended** | You referred to "the amended D-010". The file still said Nigeria only (also D-025, C10, Q24). I amended them now, per your instruction (Nigeria first, other African countries and pan-African bodies) | Confirm |
| 8 | **Support inbox access** | Reviewer sees the inbox (your menu) but not Enquiries? Wireframe shows Correction, Removal, Report to Reviewer and enquiries to Sales/Support | Keep |

### 3.2 Not yet reflected
- **Wireframe has no publish-first states** (REQ-210 to 228) and none for the amended D-033. The pending-review label appears only as a preview on the review screen.
- **architecture.md and ADRs** still describe Laravel with owner area, `claims` table, `trust_status` with unclaimed/claimed, judge panel, a flags list with judging and announcements, spatie roles including Judge and Owner, and a Source registry screen. Rewrite at Gate 2; do not carry any of this over.
- **docs/contracts/import-format.md**, unknown U8 ("until the WordPress registry exists") assumes a registry in WordPress. D-038 puts the registry in the pipeline. Rule it when the contract's unknowns are settled.
- **CLAUDE.md** stack line still says Laravel (D-031). Convention "feature flags through Flags::on()" now means one flag.
- **docs/spec.md** (the brief) is unchanged: it lists a Judge role, a "Subscription plans" page, claim and owner features. The brief stays as intent.
- **docs/traceability.md and docs/plan.md** do not exist yet.
- **docs/change-review-2026-11-07.md** (the WIWI rename review) is untracked and still awaits your rulings (Q40 to Q45 are reserved, not logged).

## 4. In `acquisition/` (not touched; needs changing there)
- Header (line ~9) and step 13 (line ~252): describe a request-to-claim form and "set to Claimed". Replace with correction, removal and report handled in the Support inbox (D-036, D-040).
- Tooling **T-24** (request to claim and claim verification): drop. **T-26** mentions claim email verification: change to correction, removal and report requesters. **T-25** (correction request with a second approver) and **T-28** ("flagged"): update to D-040 and D-037.
- **T-1 "Source registry: admin screen"**: D-038 says no registry in the website admin; the registry lives in the pipeline.
- **Line ~584**: "kill switch covers unclaimed individuals only"; change to acquired individual profiles.
- **Line ~682 and ~804**: "unclaimed profile" and "photos on unclaimed individuals" (also Q34 text, now corrected in questions.md).
- **Line ~526 and capacity assumptions** (2 reviewers, 40 per week, "Review backlog above 2 weeks of capacity"): the website shows no capacity figure (D-035, REQ-206). Keep capacity as a planning estimate only, labelled as such.
- **Line ~13 and ~379** ("D-012 and REQ-191 target reviewed profiles live", "Reaches the first 100 organisations"): D-012 is superseded; the 1,000 and batch sizes are acquisition planning figures. The website will not show them.
- **REQ-165** is now acquisition-only with no website flag; the plan's AI step should not refer to a site flag.
- **Workload note (D-034)** mentions claims; reword.

## 5. Next
1. Rule on 3.1 (eight items).
2. Merge `chore/repo-restructure` and then this branch (or tell me the order).
3. Acquisition session applies section 4.
4. Gate 2: rewrite `architecture.md` for WordPress with D-034 to D-041 as input.
