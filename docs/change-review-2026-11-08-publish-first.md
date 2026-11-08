# Change review: publish first, human review after (D-033, 2026-11-08)

Status: **Decision recorded. Rulings in section 5 are open.** The documents in section 2 are changed. Those in sections 3 and 4 are listed, **not changed**, so nothing moves until you rule. Numbering: D-032 and Q40–Q45 stay free for the pending brief review (`docs/change-review-2026-11-07.md`), so this uses D-033 and Q46–Q55.

## 1. Result in one paragraph
D-033 is recorded with your seven controls. It amends D-015, D-029, REQ-148, REQ-208 and plan sections 3–6, as you asked. Modelling it shows it brings "live" forward by one to two weeks. It adds about 6% to review time. It does not bring search visibility forward, and mid-way it delays it, because quick looks use reviewer hours and pending profiles are noindex. Reviewer hours remain the main lever, and WordPress safeguards are now the critical path to the first public record.

## 2. Changed in this commit

| Document | Change |
|---|---|
| `docs/decisions.md` | New D-033. D-015 and D-029 marked amended, original text kept. D-012, D-013, D-016, D-017 marked as in conflict, text otherwise unchanged. |
| `docs/requirements.md` | REQ-148 and REQ-208 amended, old text struck through. New section 12: REQ-210 to REQ-228, state table, transition table. Short pointers on REQ-006, 044, 051, 083, 084, 132, 141, 144, 193, 206. Conflict flags on REQ-163, 168, 170, 191. |
| `docs/acquisition-plan.md` | v2. Sections 3, 4, 5, 6 rewritten, including the timeline. Sections 1.1, 1.7, 2.1, 7, 8, 10, 11, 12 and the header updated so the plan does not contradict itself. Sections 1.2–1.6, 2.2–2.6 and 9 unchanged. |
| `docs/questions.md` | Q46–Q55 added. Q30 closed. Q1, Q4, Q31, Q35, Q36, Q38, Q39 updated. Summary recounted. |
| `STATUS.md` | Stage, done, next up. |

## 3. Other documents affected (not changed)

| Document | Where | What must change |
|---|---|---|
| `CLAUDE.md` | Conventions: "only Review\PublishRevision sets profiles.published_revision_id" | The AI Publisher path must be allowed and still go through one choke point. Laravel wording is stale anyway (D-031). |
| `CLAUDE.md` | Test-name example "REQ-208 hides unreviewed profiles from the sitemap" | Still true. Keep. |
| `docs/GUIDE.md` | Line 81 "Nothing publishes without human review" | **Now false.** Replace with the D-033 rule. |
| `docs/GUIDE.md` | Section with labels (line 52) | Add: pending label, publication states, quick look, AI takedown, green/amber/red. |
| `docs/architecture.md` | Line 14; flow F1 (lines 80–88); line 200; line 243; line 388; lines 418–419; line 461 | "Nothing public before approval" is no longer the requirement. F1 needs states, a publish gate and a state-by-surface matrix. To be done in the WordPress revision (D-031), not separately. |
| `docs/architecture.md` | Flow F2 (lines 92–99) | Add AI takedown as a trigger, plus bulk unpublish of pending. |
| `docs/architecture.md` | Line 273 "staging… public and indexable per D-029, real data only after approval" | Conflicts with "staging database" unless Q53 = private store. |
| `docs/architecture.md` | Line 327 and 332 (build order); line 110 and section 8 (flags, keys) | New first items: T-7, T-18, T-19, T-11. New `auto_publish` and `ai_takedown` flags; separate tokens. |
| `docs/architecture.md` | Lines 48, 64 (cPanel cron) | The continuous monitor may not fit. Add a runner decision (Q26). |
| `docs/adr/ADR-005` | Whole | Context says nothing is public before human approval; "set only by Review". Needs a new or superseding ADR. |
| `docs/adr/ADR-006` | Decision | 200 + noindex is a new case; AI takedown and bulk unpublish are new triggers. |
| `docs/adr/ADR-009` | Decision | Add the AI audit and monitor. Same code-verifies principle. |
| `docs/adr/ADR-010` | "built from published revisions only" | Pending profiles are in on-site search but not on indexable lists. |
| `docs/adr/ADR-011` | Cron-only queue | Monitor cadence and fetch delays (at least 5 seconds per host) need testing against the 55-second worker. |
| `docs/adr/ADR-013` | "Staff actions" | Add AI actors. |
| `docs/adr/ADR-004`, `ADR-003` | Admin surface; hosting | New queues and settings; hosting of the monitor. Both are already superseded in part by D-031. |
| `.env.example` | Lines 62–63 (`AI_DRAFT_MODEL`, `AI_PROMPT_VERSION`) | Add audit model and prompt version, and service-token names, when the build starts. |
| `docs/questions.md` | Risk register; "Top 3 risks"; Q2, Q6 text | Still say "100% human review" and "must be in place before any individual profile goes public". Rewrite after you rule. |
| `docs/spec.md` (the brief) | 11.3 "avoid: unverified claims"; 15.4; 16 "editorial review", "verification, editorial oversight" | No edit: your decisions override the brief. **Tension to be aware of.** See risk 11 in plan 10.2. |
| `docs/change-review-2026-11-07.md` | Q45 (crawler name) | Now also the monitor's identity. No clash in numbering. |
| `prototype/README.md` | Flow descriptions | Update with the prototype. |
| `docs/wireframe-findings.md` | Finding 6 (REQ-015 vs D-015) | Now turns on Q48. |
| `docs/wireframe-findings.md` | Finding 7 (visitor at an unreviewed URL = 404) | **Now wrong** for pending profiles: 200 with a label. Still 404 for staged and held. |
| `docs/wireframe-findings.md` | Finding 21 (telling the subject) | The control sat on the review screen, before publication. It needs a new home, and Legal's answer matters more when publication is automatic. |
| `docs/wireframe-findings.md` | Finding 25 (capacity = 80 a week) | Now the REQ-225 rule. |
| `docs/wireframe-findings.md` | Finding 15 (validating a removal request) | Pending profiles hide on request, so abuse handling needs designing. |
| To be written | `docs/review-guidelines.md`; `docs/runbook.md`; `docs/traceability.md`; Terms, Privacy, how-we-compile copy | Quick look and sample rules; takedown, pause and fire-drill steps; REQ-210–228; Legal's wording. |
| No effect | `.github/workflows/pages.yml`; awards and commerce decisions (D-005, D-019, D-021, D-028) apart from REQ-226 guards | None. |

## 4. Wireframe inventory (`docs/wireframe-inventory.md`)
This file is generated from `prototype/inventory.js`, so changes go there and are then regenerated. Nothing is changed yet.

### 4.1 Existing entries

| Entry | Screen | What changes |
|---|---|---|
| V01 | Home | Lists show reviewed profiles only |
| V03, V04, V05, V06, V07 | Discover and Industry lists | Reviewed only, with a link to a noindex "include pending" view (Q54) |
| V08, V38 | Search, no results | Pending shown with label; results are noindex. Copy "Unreviewed… never appear here" is now wrong. |
| V09, V10, V11 | Profile pages | New state: label, correct/remove link, noindex, "Reviewed on <date>". Replaces "Last reviewed". Shown with Unclaimed. |
| V12 | 410 | Also shown after AI takedown, with no reason |
| V13 | 404 | Staged, held and rejected only |
| V15 | Unreviewed profile | **Rewrite:** visitors now see the page with the label; staff see state, colour and audit |
| V20 | Nominee page | Reviewed profiles only |
| V28, V29, V30, V34, V35, V50 | FAQ, Privacy, Terms, How we compile, Complaints, Data rights | Copy must describe automatic publishing, the label and AI takedown. V34 is the main one. Legal's wording. |
| V37 | Share preview | Pending share card carries the label |
| V39, V40, V42, V44 | Correct, Remove, Claim, Report | Correct and claim move a pending profile up the queue. Remove hides at once. |
| V48, V49 | Sitemap, robots previews | Pending excluded from sitemap; robots.txt must not block them |
| O05, O15, O18 | Owner dashboard, claim status, sources | Show publication state for a claimed profile that was pending |
| O06, O09, O11, O22 | Editor, submitted, editing, complimentary | No change if Q48 = (a); these stay approval-first |
| A02, A46 | Dashboard, throughput | Live, reviewed, pending; oldest pending; thresholds; pause state; heartbeat |
| A03 | Review queue | **Rewrite:** post-publication; sample first. Copy "reviewed by a person before it is public" is now wrong. |
| A04 | Review item | Approve means "mark reviewed"; reject means 410. Hide audit on samples; record severity. |
| A05, A06 | Profiles list, profile admin | State filter and column; takedown history; Verified, Featured and Sponsored blocked while pending |
| A10, A11 | Reports, corrections and removals | Link to pending state and instant hide |
| A16 | Arrangement edit | Cannot link to a pending profile |
| A19 | Source registry | Monitor-allowed field; M sources |
| A20 | Bulk import | Imports to the staging database; publishing is by the gate |
| A21, A22 | Duplicates, suppression | Duplicates make amber; monitor re-checks suppression |
| A23 | AI drafting | Add audit verdicts and colours |
| A26 | Feature flags | Automatic publishing and AI takedown flags |
| A27 | Kill switch | Add bulk unpublish of pending beside it |
| A29 | Nomination detail | Pending profile cannot be used |
| A33, A34 | Audit log, users and roles | AI actors; two service roles |
| A37 | Compliance | DPIA and lawful basis covering publish first |
| A39 | Stale records | Review debt now also shows here |
| A47 | Settings | Thresholds, cap, ramp, cadence, label wording |
| A48 | Purge log | AI takedowns |

### 4.2 New entries needed

| Proposed ID | Screen or state | REQ |
|---|---|---|
| V51 | "Include profiles pending review" list (noindex) | REQ-218 |
| V52 | Removal requested: pending profile hidden at once | REQ-226 |
| A49, A50 | Quick-look queue and item | REQ-215 |
| A51, A52 | AI takedown queue and review item | REQ-220, 221 |
| A53 | Monitor: findings and heartbeat | REQ-223 |
| A54 | Publishing controls: pause state, cap, ramp stages, lifting a pause | REQ-224, 225 |
| A55 | Bulk unpublish of pending | REQ-227 |
| A56 | Staging records: colour and reason, amber and red queues | REQ-211, 214 |

### 4.3 Flows, code and coverage

| Item | What changes |
|---|---|
| Flow 1 "Nothing is public before approval" | **No longer true.** Becomes "Publish gate, label and noindex". |
| Flows 2, 3, 14, 17, 18 | Extend for AI takedown, bulk unpublish, instant hide, staging database, audit |
| New flows | "AI publishes, human reviews, becomes indexable"; "AI takedown, human review, restore"; "Automatic pause and cap" |
| `prototype/core.js` line 47 (`vis`), line 290 | New states and visibility |
| `prototype/data.js` | New states and sample records |
| `prototype/flows.js` line 5 | Flow 1 and new flows |
| `prototype/inventory.js` | The entries above |
| `prototype/views-public.js` lines 82, 102, 105, 112, 124, 234, 255 | Search copy, staff banner, label, sitemap and how-we-compile |
| `prototype/views-staff1.js` lines 72–73, 88, 107, 113, 151–159 | Dashboard, queue, review item, profile list |
| `prototype/views-staff2.js` | Flags, kill switch, settings, purge log, new screens |
| `prototype/views-awards.js` lines 38, 105 | Check nominee pages are reviewed only |
| `scripts/smoke-prototype.js` | New assertions for states, label, noindex, takedown, pause |
| `scripts/build-inventory.js` | Its summary lines change. Until `inventory.js` cites REQ-210–228, regenerating will report them as uncovered. GAP row for REQ-132 says "after review". |

## 5. What now conflicts or needs your decision
Plan section 12 gives options and recommendations. Q numbers are in `docs/questions.md`.

**Conflicts you did not list, which D-033 cannot work with as written:**

| Conflict | Text today | Ruling |
|---|---|---|
| D-017, REQ-168, REQ-163 | A human approves every AI draft, claim by claim; "Reviewers apply a neutral-content checklist" | Q46 |
| D-013 | Individuals: "human review first" | Q47 |
| D-012, REQ-191, REQ-104 | Target of "reviewed" profiles; "published" now has two meanings | Q49 |
| D-016 | Legal "not a build blocker", kill switch if they object | Q35 |
| D-029, architecture line 273 | Staging is public; "staging database" could mean that one | Q53 |
| REQ-014, 015, 175, 198 | Approval-first for customers, owner edits, complimentary, nominees | Q48 (my default keeps them) |

**Consequences of your controls that you may want to weigh:**

| Finding | Why it matters | Ruling |
|---|---|---|
| Noindex on a profile does not stop its name being indexed through a list | I made indexable lists reviewed-only, so the directory looks thinner early | Q54 |
| "Unsuitable" could mean anything | I limited automatic takedown of reviewed, claimed and sponsored profiles to S1 | Q52 |
| The kill switch covers unclaimed individuals only | Pending organisations had no equivalent, so I added REQ-227 | Tell me if you do not want it |
| Most T1 sources are manual-only | The pipeline cannot gather from them or monitor them yet, so T1 may be mostly amber | Q55 |
| The label may be read as an admission | Legal must see it before publication | Q35, plan 6.5 |
| Continuous monitoring may not fit shared cPanel | Possible small extra cost and a Gate 2 decision | Q26 |

**Items I could not verify:** NDPA sections on accuracy and automated decisions. The readable copy today is a scanned image, so I cite them from memory, and they are marked unverified in plan 6.4. All timing and error assumptions are pre-pilot.
