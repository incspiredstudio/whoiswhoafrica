# Change review: D-033 as amended (2026-11-08)

Status: **Corrected model recorded. Rulings in section 5 are open.** This replaces the first version of this review, which covered the AI-publishes model (see git history). Documents in section 2 are changed. Those in sections 3 and 4 are listed, **not changed**. Numbering: D-032 and Q40–Q45 stay free for the pending brief review; REQ-229–236 and Q56–Q62 belong to D-034; this uses REQ-237–238 and Q63–Q67.

## 1. Result in one paragraph
The pipeline never publishes. AI gathers, does seven basic checks, stores in a private staging database and exports. A named staff member imports into WordPress, where reviewers work. Organisations go live on import, labelled and noindex; individuals and entrepreneurs are not public until approved. Acquisition takes about 6.5 hours by estimate. Review is a separate track of about 190 reviewer-hours: at 2 × 15 h/week, 1,000 public and reviewed by 17 January if the import path exists by 23 November. The import path is now the first task, and nothing can be reviewed without it.

## 2. Changed in this commit

| Document | Change |
|---|---|
| `docs/decisions.md` | D-033 recorded as amended, original text kept and struck through. Notes on D-013, D-015, D-016, D-017, D-029 updated. |
| `docs/requirements.md` | See 2.1. |
| `acquisition/docs/acquisition-plan.md` | v3. Sections 3–8 and 10–12 rewritten, including both tracks; 1.1, 1.7, 2.1 and the header updated. D-034's lines kept. Sections 1.2–1.6, 2.2–2.6 and 9 unchanged. |
| `docs/questions.md` | Q63–Q67 added. Q32, Q47, Q48, Q50–Q53 closed. Q31, Q33, Q35, Q36, Q38, Q39, Q46, Q49, Q54, Q55 reworded. Summary recounted. |
| `docs/architecture.md` | One note at the top: the import path is a priority input for the revision. Nothing else. |
| `docs/change-review-2026-11-08-no-accounts.md` | Item 7 and one decisions row corrected, as its author agreed. |
| `STATUS.md` | Stage, done, next up. |

### 2.1 Every REQ changed

| REQ | Change |
|---|---|
| 148, 208 | **Amended again.** Earlier versions kept struck through. |
| 210 | Amended: six states; Staged, quick look, amber, red and AI takedown gone |
| 211 | Amended: private store outside WordPress; no publishing credentials; resumable |
| 212 | Amended: publish gate becomes import checks in WordPress |
| 214 | Amended: colours become the seven basic checks and the verification report; organisations keep no named person |
| 221 | Amended: hash confirms what is imported is what was drafted; review packs and AI republishing gone |
| 222 | Amended: review in WordPress; sample and hidden-audit rules gone |
| 226 | Amended: instant hide on removal gone |
| 227 | Amended: can also act on one import batch |
| 228 | Amended: import audit and a simpler dashboard |
| 216 | One clause: applies to organisations on import |
| 213, 215, 219, 220, 223, 224, 225 | **Withdrawn:** AI audit, quick look, AI roles, AI takedown, monitor, automatic pause, cap |
| 237 | **New:** basic import path, first task |
| 238 | **New, later:** AI monitoring and takedown as a separate WordPress feature |
| 051, 084, 132, 141, 144, 193, 206 | Pointer notes updated |
| 083, 229 | Reference to the non-human roles removed |
| 163, 168, 170 | Conflict flags narrowed to AI-drafted organisations |
| 217, 218 | Unchanged |

## 3. Other documents affected (not changed)

| Document | Where | What must change |
|---|---|---|
| `docs/GUIDE.md` | "Nothing publishes without human review" (line 84) | **False for imported organisations.** Replace with the D-033 rule. |
| `docs/GUIDE.md` | Labels (line 52) | Add the review-pending label, publication states, verification report |
| `CLAUDE.md` | "only Review\PublishRevision sets profiles.published_revision_id" | Import is now the second way a profile can become public. Laravel wording is stale anyway (D-031). |
| `docs/architecture.md` | Line 14; flow F1 (lines 80–88); lines 200, 243, 388, 418–419, 461 | "Nothing public before approval" holds for individuals, not organisations. F1 needs states and an import path. Do it in the WordPress revision. |
| `docs/architecture.md` | Lines 327, 332 (build order) | Import path and review queue come first |
| `docs/architecture.md` | Line 273 "staging… real data only after approval" | Conflicts with importing real data into a public site (Q64) |
| `docs/adr/ADR-005` | Whole | Context says nothing is public before human approval. New or superseding ADR needed. |
| `docs/adr/ADR-006` | Decision | 200 + noindex is a new case; bulk unpublish by batch is a new trigger |
| `docs/adr/ADR-009` | Decision | Unchanged in principle; the verification report and run are new |
| `docs/adr/ADR-010` | "built from published revisions only" | Labelled organisations are in on-site search but not indexable lists |
| `docs/adr/ADR-013` | "Staff actions" | Add imports |
| `.env.example` | Lines 62–63 | No new AI variables needed now |
| `docs/questions.md` | Risk register; Top 3 risks | Has a "not yet rewritten" note. Rewrite after you rule. |
| `docs/spec.md` (the brief) | 11.3 "avoid: unverified claims"; 15.4; 16 "editorial review", "verification, editorial oversight" | No edit: your decisions override the brief. Live, unreviewed organisation profiles sit awkwardly with these. |
| `docs/change-review-2026-11-07.md` | Q45 (crawler name) | Still relevant to the run |
| `docs/wireframe-findings.md` | Finding 7 | Still partly true: 404 for imported records; 200 with a label for organisations |
| `docs/wireframe-findings.md` | Finding 21 (telling the subject) | Fits the review screen again for individuals |
| `docs/wireframe-findings.md` | Finding 25 (capacity = 80 a week) | Now a reviewer-hours choice (Q31) |
| To be written | `acquisition/docs/review-guidelines.md`; `docs/runbook.md`; `docs/traceability.md`; Terms, Privacy, how-we-compile copy; `acquisition/pipeline/` | Review steps; run and import steps; REQ-237; Legal's wording; the pipeline |
| **No longer affected** | Monitor runner, ADR-011, AI credentials, fire drill | Removed with the AI-publishes model |

## 4. Wireframe inventory (`docs/wireframe-inventory.md`)
Generated from `prototype/inventory.js`. Nothing is changed yet. The prototype still shows the older "nothing public before approval" model, so this is a smaller change than the first version suggested. Individuals keep that behaviour.

### 4.1 Existing entries

| Entry | Screen | What changes |
|---|---|---|
| V10, V11 | Business and institution profiles | New state: label, correct/remove link, noindex; then "Reviewed on <date>" |
| V09 | Individual profile | "Reviewed on <date>" replaces "Last reviewed" |
| V01, V03, V04, V05, V06, V07 | Home, Discover, Industry lists | Reviewed only, with a link to a noindex view of labelled organisations (Q54) |
| V08, V38 | Search, no results | Labelled organisations shown with the label. Copy "Unreviewed… never appear here" needs changing. |
| V15 | Unreviewed profile | Split: organisation (page with label) and individual (404, as now) |
| V13 | 404 | Imported – not public and rejected |
| V12 | 410 | Unchanged |
| V34, V28, V29, V30, V35, V50 | How we compile, FAQ, Privacy, Terms, Complaints, Data rights | Describe the label and organisations going live before review. Legal's wording. |
| V37 | Share preview | Labelled card |
| V39, V42 | Correct, Claim | A labelled profile moves up the queue |
| V48, V49 | Sitemap, robots previews | Labelled organisations excluded from the sitemap; robots.txt must not block them |
| V20, A29, A16, A06 | Nominee page, nomination, arrangement, profile admin | Labelled profile cannot be used for awards, sponsorship, Verified or Featured (REQ-226) |
| A02, A46 | Dashboard, throughput | Imported, public, reviewed, awaiting review |
| A03 | Review queue | Copy "reviewed by a person before it is public" holds for individuals only; add batch filter |
| A04 | Review item | Show verification report; approve on a live record means "mark reviewed" |
| A05 | Profiles list | State filter and column |
| A19 | Source registry | Access recoding (Q55) |
| A20 | Bulk import with dry run | **Becomes the REQ-237 import path:** file, hash check, states set, batch undo |
| A21, A22 | Duplicates, suppression | Duplicates flagged by the run; suppression checked at import |
| A23 | AI drafting | Verification reports and dropped claims, read-only; the run happens outside WordPress |
| A27 | Kill switch | Add bulk unpublish by batch beside it |
| A33 | Audit log | Import events |
| A37 | Compliance | DPIA, lawful basis, staging store |
| A47 | Settings | Label wording |
| A48 | Purge log | Bulk unpublish entries |

### 4.2 New entries needed

| Proposed ID | Screen or state | REQ |
|---|---|---|
| V52 | List view including labelled organisations (noindex) | REQ-218 |
| A57 | Import batches: history, counts, undo | REQ-237, 228 |
| A58 | Bulk unpublish by batch | REQ-227 |

### 4.3 Flows, code and coverage

| Item | What changes |
|---|---|
| Flow 1 "Nothing is public before approval" | True for individuals; add the organisation path |
| Flows 2, 3, 14 | Add bulk unpublish; labelled organisations |
| Flow 17 "Import with dry run" | Becomes the REQ-237 path |
| Flow 18 "AI drafting" | Shows the report, not an in-WordPress run |
| `prototype/core.js`, `data.js`, `flows.js`, `inventory.js`, `views-public.js`, `views-staff1.js`, `views-staff2.js` | States, label, lists and screens above |
| `scripts/smoke-prototype.js` | New assertions for the labelled state and import |
| `scripts/build-inventory.js` | REQ-237 is uncited until `inventory.js` cites it, and withdrawn rows drop out of coverage. REQ-210–228 were already uncited. |

## 5. What still conflicts or needs your decision
Plan section 12 gives options and recommendations.

| # | Q | Issue | Default |
|---|---|---|---|
| 12 | Q46 | **AI-drafted organisation text public unreviewed** conflicts with D-017, REQ-163, REQ-168 | Written as you specified. **I recommend hiding the AI summary until approved:** same speed, nothing amended. |
| 22 | Q63 | **Legal's AI transfer basis** blocks individuals' data (REQ-169, not on your list of limits), so "all 1,000 in one run" depends on it | Two runs |
| 23 | Q64 | **Which WordPress takes the import?** D-029 makes staging public and indexable; architecture line 273 says real data only after approval | One site, all not public until safeguards pass |
| 24 | Q65 | **All 500 organisations live at once?** No error rate would exist yet | First 100, then the rest |
| 25 | Q66 | **Cap and automatic pause:** you listed them under neither keep nor remove; also instant hide | Confirmed removed; people apply S1 and S2 rules |
| 26 | Q67 | **CSV or REST** | Files first, so the pipeline holds no WordPress credentials |
| 21 | Q55 | **Manual-only sources** cannot feed the run as coded | Agents read terms; you approve re-coding; the rest left out |
| 3 | Q31 | **Reviewer hours and review order** | 2 × 15 h/week, organisations first |
| 15 | Q49 | **What counts,** with D-012, REQ-104, REQ-191 | Report both; target = public |
| 7 | Q35 | **First individual public** versus D-016 | Wait for Legal |
| 20 | Q54 | **Lists** show reviewed only, so Discover Businesses is thin until reviews land | Reviewed only |

**Assumptions I added that you may want to check:**
- Organisation records keep no named person before review, since individuals are not public until approved.
- A flagged duplicate is not public on import, even for an organisation.
- A named staff member starts each import, which is how "the pipeline never publishes" holds when organisations go live on import.
- REQ-143 (no low-confidence or unsourced field) stays, enforced at import, although it is not one of your seven pipeline checks.
- REQ-227 (bulk unpublish) stays, because the kill switch does not cover organisations.
- The smoke-lot stop rules and all hour-by-hour times are my estimates until the first run measures them.

**Could not verify:**
- The purchase needed to reach each Anthropic tier, and the limits of the Evaluation tier (not published on the page read). The rate-limit figures themselves were read on 2026-11-08.
- The NDPA accuracy section (I recall s.24). The readable copy of the Act is a scanned image.
- Whether approved sources can fill every quota. That is only known after seeding.
