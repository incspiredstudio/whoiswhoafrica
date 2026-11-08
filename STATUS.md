# STATUS

## Stage
D-034 (2026-11-08): no login or signup on the public site; only staff use wp-admin. Requirements (REQ-229–236, six withdrawn, eighteen amended), questions Q56–Q62, acquisition plan, GUIDE and the wireframe are updated; architecture.md and ADR-004 are not (rewritten at Gate 2). See docs/change-review-2026-11-08-no-accounts.md. docs/design-inspiration.md added (all points unverified; no browser available).

Gate 1 answered. D-031: the CMS is WordPress on cPanel (supersedes the Laravel proposal; architecture.md and ADRs not yet revised). D-033: publish first, human review after (acquired records). **Now: your rulings on Q46–Q55, then wireframe review** (prototype/, not yet updated for D-033). After review: revise architecture.md for WordPress and D-033 (Gate 2), then /plan (Gate 3).

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
- docs/acquisition-plan.md (proposed): first 1,000 profiles, inclusion criteria, source map with terms checked 2026-11-07, pipeline, capacity, 50-profile pilot, controls, WordPress tooling T-1–T-17, costs; decisions logged as Q29–Q39. Nothing fetched.
- D-033 publish first recorded. D-015, D-029, REQ-148, REQ-208 amended; REQ-210–228 added (publication states, label, noindex, AI roles, takedown, monitor, pause, cap). Acquisition plan v2 (sections 3–6 and timeline rewritten; 7, 8, 10–12 updated). Q46–Q55 logged, Q30 closed. Impact list: docs/change-review-2026-11-08-publish-first.md (other documents and wireframe listed, not changed).

## In flight
- (none)

## Blocked
- GitHub labels and project board: `gh` not installed or authenticated (not a build blocker)

## Decisions
See docs/decisions.md. Key: no payments on the site (D-005); admin commercial register (D-006); individual stubs published without consent with kill switch (D-013); AI drafting allowed with strict rules (D-017); full awards workflow behind OFF flags (D-019); domain wiwi.africa (D-001); no tiers or packages, commercial arrangements only (D-028); public indexed site (D-029); publish first, human review after, labelled and noindex until reviewed (D-033).

## Next up
- You: rule on Q56–Q62 (defaults in docs/change-review-2026-11-08-no-accounts.md section 5); open both reference sites in a browser to confirm docs/design-inspiration.md
- You: answer Q46–Q55 first, then Q29, Q31–Q39 (docs/acquisition-plan.md section 12) by Mon 9 Nov; send Legal the publish-first questions (plan 6.5) by 9 Nov; name reviewers (Q4) and Editor-in-Chief; approve pilot sources by Tue 10 Nov. Shadow pilot 10–18 Nov; plan update and go/no-go on 20 Nov; first public record 23 Nov if the go-live gate (plan 5.5) passes.
- Legal: AI transfer basis by 13 Nov (blocks drafting individuals); LIA, DPIA, NDPC registration tier and a view on publish first before the first individual is published
- After your rulings: apply the listed edits to GUIDE, CLAUDE.md, architecture, ADRs, questions risk register, prototype and inventory (docs/change-review-2026-11-08-publish-first.md sections 3–4)
- You: review the wireframe (prototype/index.html); answer docs/wireframe-findings.md; enable GitHub Pages (Settings > Pages > Source = GitHub Actions) if you want the link
- Then: revise architecture.md for WordPress (Gate 2); answer Q26–Q28
- Then: /plan
- You: supply reviewer names (Q4); request Legal's position by 13 Nov (Q5); open accounts by 13 Nov
- Pending - team lead: Q16 (does not block architecture)
