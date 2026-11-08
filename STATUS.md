# STATUS

**Shared-docs owner: restructure session, until `chore/repo-restructure` is merged; then the wireframe session.** Stages are in `CLAUDE.md`. To hand over, commit and change this line.

**Repo layout (2026-11-08):** `docs/` shared; `prototype/` wireframe; `acquisition/` data acquisition. Interface: `docs/contracts/import-format.md`. Other sessions keep their status in `prototype/STATUS.md` or `acquisition/STATUS.md`.

## Stage
Wireframe review fixes applied 2026-11-08 in two rounds on branch `chore/wireframe-review-fixes` (built on `chore/repo-restructure`; neither merged). Round 1: D-035 to D-041. Round 2: D-042 read-only Sources screen, D-043 kill switch replaced by bulk unpublish by filter, D-044 only four roles (Visitor, Reviewer, Editor, Administrator). REQ-239 to 243 added; REQ-124, 151, 152, 173, 227, 230, 231 withdrawn. Round 3: your rulings D-045 (bulk unpublish: Administrator and Reviewer; Administrator alone confirms payments; one-person sign-off accepted; D-010 confirmed) and D-046 (capabilities per user, REQ-244); architecture.md, ADR-001 to 013, CLAUDE.md and .env.example rewritten for WordPress (proposed v2, awaiting your Gate 2 approval); Q73 to Q80 added. See docs/consistency-report.md: open rulings (section 3.1) and the list for the acquisition session (section 4). The wireframe still lacks the D-033 publish-first states.

D-034 (2026-11-08): no login or signup on the public site; only staff use wp-admin. Requirements (REQ-229–236, six withdrawn, eighteen amended), questions Q56–Q62, acquisition plan, GUIDE and the wireframe are updated; architecture.md and ADR-004 are not (rewritten at Gate 2). See docs/change-review-2026-11-08-no-accounts.md. docs/design-inspiration.md added (all points unverified; no browser available).

Gate 1 answered. D-031: the CMS is WordPress on cPanel (supersedes the Laravel proposal; architecture.md and ADRs not yet revised). D-033 as amended (2026-11-08): the acquisition pipeline never publishes; records are imported into WordPress and reviewed there; organisations live on import, labelled and noindex; individuals and entrepreneurs not public until approved. **Now: your rulings (plan section 12, Q46, Q55, Q63–Q67 first), then wireframe review** (prototype/, not yet updated for D-033). After review: revise architecture.md for WordPress and D-033, with the import path (REQ-237) as priority input (Gate 2), then /plan (Gate 3).

## Done
- Spec copied to docs/spec.md; process files and templates created
- docs/requirements.md: REQ-001–209 (18 withdrawn: payment, tier and package REQs)
- docs/questions.md: 13 contradictions, 25 questions with Status column
- docs/decisions.md: D-001–D-030
- docs/GUIDE.md added
- docs/architecture.md + docs/adr/ADR-001–013 (proposed), CLAUDE.md Commands and Conventions, .env.example
- CLAUDE.md speed rule added
- Architecture alternatives comparison (architecture.md section 13)
- D-031 WordPress decision; docs/wireframe-inventory.md (145 entries); clickable wireframe in prototype/ (19 flows); docs/wireframe-findings.md (25 unclear points); scripts/smoke-prototype.js and scripts/build-inventory.js
- acquisition/docs/acquisition-plan.md (proposed): first 1,000 profiles, inclusion criteria, source map with terms checked 2026-11-07, pipeline, capacity, 50-profile pilot, controls, WordPress tooling T-1–T-17, costs; decisions logged as Q29–Q39. Nothing fetched.
- D-033 recorded, then amended 2026-11-08 (original text kept). REQ-148, 208 and section 12 amended; REQ-213, 215, 219, 220, 223–225 withdrawn; REQ-237 (import path, first task) and REQ-238 (future AI monitoring and takedown) added. Acquisition plan v3: acquisition about 6.5 hours (estimate), review a separate track. Q63–Q67 added; Q32, Q47, Q48, Q50–Q53 closed. Impact list: acquisition/docs/change-review-2026-11-08-publish-first.md (other documents and wireframe listed, not changed).
- Repo split into website and acquisition on `chore/repo-restructure`: acquisition plan and its change review moved to `acquisition/docs/`; `.gitignore` added; Scope tags on every REQ, D, C and Q; `docs/contracts/import-format.md` drafted; ownership rules in CLAUDE.md and GUIDE. No content amendments applied.

## In flight
- (none)

## Blocked
- GitHub labels and project board: `gh` not installed or authenticated (not a build blocker)

## Decisions
See docs/decisions.md. Key: no payments on the site (D-005); admin commercial register (D-006); individual stubs published without consent with kill switch (D-013); AI drafting allowed with strict rules (D-017); full awards workflow behind OFF flags (D-019); domain wiwi.africa (D-001); no tiers or packages, commercial arrangements only (D-028); public indexed site (D-029); pipeline never publishes; organisations live on import, labelled and noindex; individuals not public until approved (D-033 as amended).

## Next up
- You: rule on docs/consistency-report.md section 3.1; merge the two branches in order (`chore/repo-restructure`, then `chore/wireframe-review-fixes`)
- Acquisition session: apply docs/consistency-report.md section 4 in `acquisition/`
- You: rule on Q56–Q62 (defaults in docs/change-review-2026-11-08-no-accounts.md section 5); open both reference sites in a browser to confirm docs/design-inspiration.md
- You: answer Q46, Q55, Q63–Q67, Q31 first, then the rest of plan section 12, by Mon 9 Nov; send Legal the questions in plan 6.5 by 9 Nov; name reviewers (Q4) and Editor-in-Chief; check the Anthropic account tier. Approve all run sources by Tue 10 Nov: the run starts straight after.
- Agents: read unread source terms and prepare registry entries (9 Nov); run A organisations (10 Nov); run B individuals once Legal confirms the transfer basis (by 13 Nov).
- **First build task: basic WordPress import path (REQ-237, plan T-0), then the review queue. File as a GitHub issue when gh is available.**
- Legal: AI transfer basis by 13 Nov (blocks run B); Terms, Privacy, label wording before organisations go live; LIA, DPIA, NDPC registration tier before the first individual goes public
- After your rulings: apply the listed edits to GUIDE (line 84 is now false for organisations), CLAUDE.md, architecture, ADRs, questions risk register, prototype and inventory (acquisition/docs/change-review-2026-11-08-publish-first.md sections 3–4)
- You: review the wireframe (prototype/index.html); answer docs/wireframe-findings.md; enable GitHub Pages (Settings > Pages > Source = GitHub Actions) if you want the link
- Then: revise architecture.md for WordPress (Gate 2); answer Q26–Q28
- Then: /plan
- You: supply reviewer names (Q4); request Legal's position by 13 Nov (Q5); open accounts by 13 Nov
- Pending - team lead: Q16 (does not block architecture)
