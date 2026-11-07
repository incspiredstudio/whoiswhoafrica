# STATUS

## Stage
Gate 1 answered. D-031: the CMS is WordPress on cPanel (supersedes the Laravel proposal; architecture.md and ADRs not yet revised). **Now: wireframe review** (prototype/). After review: revise architecture.md for WordPress (Gate 2), then /plan (Gate 3).

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

## In flight
- (none)

## Blocked
- GitHub labels and project board: `gh` not installed or authenticated (not a build blocker)

## Decisions
See docs/decisions.md. Key: no payments on the site (D-005); admin commercial register (D-006); individual stubs published without consent with kill switch (D-013); AI drafting allowed with strict rules (D-017); full awards workflow behind OFF flags (D-019); domain wiwi.africa (D-001); no tiers or packages, commercial arrangements only (D-028); public indexed site, human approval before visibility (D-029).

## Next up
- You: answer Q29–Q39 (docs/acquisition-plan.md section 12) by Mon 9 Nov; name reviewers (Q4) and Editor-in-Chief; approve pilot sources by Tue 10 Nov. Pilot 10–18 Nov; plan v2 on 20 Nov.
- Legal: AI transfer basis by 13 Nov (blocks drafting individuals); LIA, DPIA, NDPC registration tier before the first individual is published
- You: review the wireframe (prototype/index.html); answer docs/wireframe-findings.md; enable GitHub Pages (Settings > Pages > Source = GitHub Actions) if you want the link
- Then: revise architecture.md for WordPress (Gate 2); answer Q26–Q28
- Then: /plan
- You: supply reviewer names (Q4); request Legal's position by 13 Nov (Q5); open accounts by 13 Nov
- Pending - team lead: Q16 (does not block architecture)
