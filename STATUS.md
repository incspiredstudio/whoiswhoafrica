# STATUS

**Shared-docs owner: restructure session, until `chore/repo-restructure` is merged; then the wireframe session.** Stages are in `CLAUDE.md`. To hand over, commit and change this line.

**Repo layout (2026-11-08):** `docs/` shared; `prototype/` wireframe; `acquisition/` data acquisition. Interface: `docs/contracts/import-format.md`. Other sessions keep their status in `prototype/STATUS.md` or `acquisition/STATUS.md`.

## Stage
**Brief v2 and brand (D-052, 2026-11-09):** brand stays WHOISWHO; legal name whoiswhoafrica; `docs/spec.md` is now the WIWI brief (most current; old copy in docs/archive/spec-versions/); Q40–Q45 logged and answered; no crawler name.

**Unanswered questions sorted (D-049 to D-051, 2026-11-09):** 54 answered, 25 default accepted, 8 deferred, none open. No reviewer pay, hours or names needed (D-049). Build locally on WordPress and import to the live host; no object storage, spikes or new accounts; email by cPanel SMTP (D-050). Legal questions, judging and CaromHQ sync are deferred on purpose and have their own sections or are built last (D-051). Architecture, ADRs, .env.example and CLAUDE.md are being updated to match.

**Defaults accepted (D-047, 2026-11-08):** every open question now carries its default (34 questions: Q26–Q29, Q31, Q33–Q39, Q46, Q54–Q56, Q58, Q60–Q67, Q72–Q80); only Q16 is still pending (team lead). D-048: staff sign-in has no role selector (REQ-245). The WIWI rename questions (Q40–Q45, docs/archive/change-review-2026-11-07.md) are not logged and not applied: the brand stays WHOISWHO until you rule.

Wireframe review fixes applied 2026-11-08 in two rounds (merged by fast-forward into `chore/structured-build-setup`, together with `chore/repo-restructure`, 2026-11-08; not pushed, not merged to main). Round 1: D-035 to D-041. Round 2: D-042 read-only Sources screen, D-043 kill switch replaced by bulk unpublish by filter, D-044 only four roles (Visitor, Reviewer, Editor, Administrator). REQ-239 to 243 added; REQ-124, 151, 152, 173, 227, 230, 231 withdrawn. Round 3: your rulings D-045 (bulk unpublish: Administrator and Reviewer; Administrator alone confirms payments; one-person sign-off accepted; D-010 confirmed) and D-046 (capabilities per user, REQ-244); architecture.md, ADR-001 to 013, CLAUDE.md and .env.example rewritten for WordPress (proposed v2, awaiting your Gate 2 approval); Q73 to Q80 added. See docs/archive/consistency-report.md: open rulings (section 3.1) and the list for the acquisition session (section 4). The wireframe still lacks the D-033 publish-first states.

D-034 (2026-11-08): no login or signup on the public site; only staff use wp-admin. Requirements (REQ-229–236, six withdrawn, eighteen amended), questions Q56–Q62, acquisition plan, GUIDE and the wireframe are updated; architecture.md and ADR-004 are not (rewritten at Gate 2). See docs/archive/change-review-2026-11-08-no-accounts.md. docs/design-inspiration.md added (all points unverified; no browser available).

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
- 2026-11-10 (D-061): Stale records, Prospects and Leads removed from the docs (REQ-093, 160, 176, 178 withdrawn); enquiries go to the Support inbox as type Enquiry. The prototype pass is done too (state key v13).
- 2026-11-11 (D-067): Roles, Users and capabilities and Exports reframed as WordPress-provided; Compliance and breach-log screens removed (registers are documents); Settings renamed Sprint thresholds. The prototype pass is done (state key v19).
- 2026-11-11 (D-069): Support inbox split into Enquiries, Corrections and Requests and reports (REQ-270, 271); Sprint tracking theme setting (REQ-272); parent theme `whoiswho` plus active child `whoiswho-child` (REQ-273). Prototype and docs updated.
- 2026-11-11 (D-068, REQ-269): one arrangement type (sponsored post); the Register and Story sponsors screens are merged into one Sponsored stories screen with a four-step deal tracker; REQ-023 withdrawn; counting rule drops onboarding. Prototype and docs both done (state key v20).
- 2026-11-10 (D-062): Events added as a story type (REQ-262) and paid story placement (REQ-263). Payment never affects nominations, judging, winners, profile ranking or labels; it can only buy a labelled story slot. Docs updated; the prototype pass is separate.
- 2026-11-10 (D-063): Events are their own post type `wiw_event`, not a story type (REQ-262 amended; REQ-264 events pages, header and footer links and a Home block; REQ-265 a story links one event; REQ-263 placements are stories only). Capability `wiw_manage_events`. The Home page loses the red awards band; the marquee stays. The prototype still shows events inside Stories until its own pass lands.
- 2026-11-10 (D-064): Judging panel and conflicts and Scores and results removed from the docs (REQ-036, 037, 234 withdrawn; REQ-034, 200, 202 amended). Judging and scoring are offline; staff only mark shortlisted, finalist and winner. The prototype still shows them until its own pass lands.
- 2026-11-11 (D-065): Awards admin simplified in the docs: Award categories (categories only), the Nominations open/closed toggle and stage control on the Nominations screen, Shortlist and winners and the Feature flag screen removed (REQ-266, 267 added; REQ-038, 194, 200, 202, 259 amended). The prototype is updated separately.
- 2026-11-11 (D-066): nomination stage is Nominated or Winner and status is Live or Not live; nominee invitation and response link removed (REQ-197 withdrawn); optional staff-only Contact email with staff-sent nomination and winner emails (REQ-268). Docs updated; the prototype pass is separate.
- Repo split into website and acquisition on `chore/repo-restructure`: acquisition plan and its change review moved to `acquisition/docs/`; `.gitignore` added; Scope tags on every REQ, D, C and Q; `docs/contracts/import-format.md` drafted; ownership rules in CLAUDE.md and GUIDE. No content amendments applied.

## In flight
- (none)

## Blocked
- GitHub labels and project board: `gh` not installed or authenticated (not a build blocker)

## Decisions
See docs/decisions.md. Key: no payments on the site (D-005); admin commercial register (D-006); individual stubs published without consent with kill switch (D-013); AI drafting allowed with strict rules (D-017); full awards workflow behind OFF flags (D-019); domain wiwi.africa (D-001); no tiers or packages, commercial arrangements only (D-028); public indexed site (D-029); pipeline never publishes; organisations live on import, labelled and noindex; individuals not public until approved (D-033 as amended).

## Next up
- You: rule on docs/archive/consistency-report.md section 3.1; merge the two branches in order (`chore/repo-restructure`, then `chore/wireframe-review-fixes`)
- Acquisition session: apply docs/archive/consistency-report.md section 4 in `acquisition/`
- You: rule on Q56–Q62 (defaults in docs/archive/change-review-2026-11-08-no-accounts.md section 5); open both reference sites in a browser to confirm docs/design-inspiration.md
- You: approve all run sources (acquisition plan section 12) when ready. Legal and reviewer hours/names are not needed now (D-049, D-051). Check the Anthropic account tier.
- Agents: read unread source terms and prepare registry entries (9 Nov); run A organisations (10 Nov); run B individuals once Legal confirms the transfer basis (by 13 Nov).
- **First build task: basic WordPress import path (REQ-237, plan T-0), then the review queue. File as a GitHub issue when gh is available.**
- Legal: AI transfer basis by 13 Nov (blocks run B); Terms, Privacy, label wording before organisations go live; LIA, DPIA, NDPC registration tier before the first individual goes public
- After your rulings: apply the listed edits to GUIDE (line 84 is now false for organisations), CLAUDE.md, architecture, ADRs, questions risk register, prototype and inventory (acquisition/docs/change-review-2026-11-08-publish-first.md sections 3–4)
- You: review the wireframe (prototype/index.html); answer docs/wireframe-findings.md; enable GitHub Pages (Settings > Pages > Source = GitHub Actions) if you want the link
- Then: Gate 2 approval of architecture.md (WordPress, built locally, D-050)
- Then: /plan
- You: nothing blocking. Reviewer names (Q4) and accounts (Q14) are not needed now; Legal (Q5) is skipped for now (D-051)
- Deferred: Q16 CaromHQ sync (one of the last things built), the Legal section, the judging section (D-051)

## Docs site (2026-11-10)
`docs/` is now a VitePress site (`cd docs && npm install && npm run dev`). New written guides (44 pages): `docs/getting-started/`, `docs/guide/public-site/`, `docs/guide/staff/`, `docs/concepts/`, `docs/build/`, `docs/design/`, plus `docs/index.md` and `docs/overview.md`. The requirements, decisions, questions, inventory, architecture and ADR files stay as the reference records the guides cite. Moved to `docs/archive/`: the two change reviews, the consistency report and `spec-versions/`. The two venture briefs moved from the repo root to `docs/brief/` (renamed `whoiswho-venture-brief.md`, `wiwi-venture-brief.md`). References in STATUS.md, decisions, questions and GUIDE were updated; `acquisition/` was not touched, so its link to `docs/change-review-2026-11-08-no-accounts.md` now needs `archive/` added.
