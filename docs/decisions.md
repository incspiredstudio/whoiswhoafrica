# Decisions

Recorded at Gate 1 (2026-11-06). Each links to its contradiction (C#) or question (Q#) in `docs/questions.md` and to the requirements in `docs/requirements.md`.

| ID | Links | Decision | REQs |
|---|---|---|---|
| D-001 | C1, Q20 | Brand = WHOISWHO ("Who Is Who In Africa"). Domain = wiwi.africa (replaces whoiswhoinafrica.com everywhere, including the email sending domain). Edition = "WHOISWHO in Africa: The Maiden Edition". | REQ-204 |
| D-002 | C2 | The team seeds the directory from sourced public data: organisations first, then public figures as unclaimed profiles with sources and a claim/correct/remove link. Paid self-submitted profiles are a second path. | REQ-132, 133, 191 |
| D-003 | C3, C4, Q11 | Accepted: subscription never buys verification; Featured is editorial only; Sponsored is a separate label and never implies verification. | REQ-024, 153–155, 183–184 |
| D-004 | C5, Q13 | Build with a provisional theme; all brand values live in one central theme config. | REQ-052, 190 |
| D-005 | C6, Q9 | **Scope change.** No payment processing on the website: no gateway, no Paystack, no checkout. Payment is offline; admins record it. The site is for the awards plus a directory of businesses, entrepreneurs and individuals. Payment/checkout REQs withdrawn: REQ-011, 048, 070, 073, 074, 076, 097, 099, 113, 114, 117, 118, 119. | REQ-177–181 |
| D-006 | Scope change | Subscriptions become an admin-managed commercial register (customer, tier label, amount, currency, method, reference, date received, confirmed-by, status, term, segment, complimentary, notes). Admin controls: Sponsored (sponsor name and disclosure), Featured, Verified, tier label and expiry, sponsors attached to awards/categories/pages, internal notes, full audit log. | REQ-179–189 |
| D-007 | C7 | First release = public site (awards, directory, profiles, editorial) plus admin tools; admins onboard customers via the register. Later phase is hardening and polish. | REQ-103 |
| D-008 | C8, Q19 | Target is met if either 10 paid subscriptions (brief §9.3) or NGN 5m confirmed revenue. Revenue by stream. Dashboard shows both numbers and each stream; no hard-coded gate. | REQ-022, 105, 180, 181 |
| D-009 | C9 | Nominations form and admin queue are built behind an OFF flag; the page shows "Nominations open soon" with email sign-up. Turning the flag on needs the CEO's explicit approval. | REQ-106, 194, 195 |
| D-010 | C10, C11 | Nigeria only for MVP; enquiry relay only, no public email/phone by default. | REQ-018, 053, 060 |
| D-011 | C12, Q16 | CSV exchange first; data has stable IDs and change timestamps for a later integration. **To build a direct CaromHQ integration we need to know:** whether it has a public API and documentation; authentication method; objects (contacts, companies, deals, pipeline stages, notes); field mapping; webhooks; rate limits; sandbox availability; sync direction; ID ownership; expected volumes. | REQ-077, 205 |
| D-012 | Q1 | **Corrected by D-029.** Target ~100 organisation and ~100 individual reviewed profiles live; sourcing continues in parallel; a second human reviews every profile before it is public. | REQ-148, 191, 192 |
| D-013 | Q2 | Individual stubs are in the MVP and published without consent: professional facts only, source and retrieval date per field, human review first, claim/correct/remove link, permanent suppression list, and a global kill switch for all unclaimed individuals. | REQ-133, 139, 156, 193, 207 |
| D-014 | Q3 | Accepted source types: registries, regulators, exchange listings, Wikidata, licensed APIs, official sites; each approved before use. Never LinkedIn or social platforms; never news text or photos. | REQ-136–138 |
| D-015 | Q4 | 100% human review before anything publishes. Reviewers: `<name 1>`, `<name 2>` (to be supplied). ~40 profiles per reviewer per week; add a third if backlog grows. | REQ-148, 206 |
| D-016 | Q5 | Legal basis = legitimate interests with right to object. Incspired Legal asked by 13 Nov; not a build blocker. If Legal objects, use the kill switch and adjust. | REQ-057, 072, 193 |
| D-017 | Q6 | AI drafting allowed for organisations and individuals under strict rules (source-only claims, dropped if unsupported, prohibited topics, neutral tone, human approval, internal AI-assisted field with model and prompt version, no-training provider, Legal confirms transfer). | REQ-165–170 |
| D-018 | Q10 | Refunds, cancellation and renewals are handled offline by Finance. The platform stores renewal/expiry dates and shows admin reminders. | REQ-115, 116, 189 |
| D-019 | Q12 | Full awards workflow in software, every rule configurable; judges never see commercial data; nominations, judging and announcements behind OFF flags until CEO approval. | REQ-033–038, 194, 200–202 |
| D-020 | Q14 | wiwi.africa and cPanel hosting already acquired. cPanel is optional; /architect chooses hosting, justifies it and lists accounts to open. Secrets are placed by you in the host's secret store by 13 Nov; never in the repo. Region Africa or EU. | REQ-204 |
| D-021 | Q15 | Anyone can nominate without nominee consent. Private until screening; nominee emailed to accept, decline or correct; public pages only at shortlist; decline → removed and suppressed; payment never affects nominations; nominator email verified, rate-limited, duplicate-checked; no public voting. | REQ-064, 196–199 |
| D-022 | Q18 | Accepted: org claims via domain email or document; individual claims via document and manual approval; report button on every profile. | REQ-152, 090 |
| D-023 | Q21 | Accepted retention defaults, subject to Legal's review. | REQ-063 |
| D-024 | Q22 | Accepted, plus open-licence images with stored licence and attribution. | REQ-162, 203 |
| D-025 | Q23, Q24, Q25 | Accepted: view count only; English and Nigeria only; events and sponsor invoices handled manually. | REQ-019, 053 |
| D-026 | Working principle | Speed is the priority: finish as early as possible; the brief's dates are ceilings; parallelise; only external lead times and human approvals are real constraints. Added to CLAUDE.md. | — |
| D-027 | Q16 | **Pending - team lead.** Not decided and not invented. Does not block architecture. (Q7, Q8, C13 moved to D-028; Q17 to D-030.) | REQ-077 |
| D-030 | Q17 | One login per profile owner, individual or organisation. Multi-user organisations are deferred. REQ-111 withdrawn for now; the organisation editor role in REQ-109 is not built. | REQ-109, 111 |
| D-028 | Q7, Q8, C13 | **No tiers, packages or entitlements; every listing has the same format.** Commercial relationships are arrangements in the admin register: type (sponsored post, sponsored profile, media/editorial package, awards sponsorship, other), customer, amount, currency, payment method and reference, date received, confirmed by, start/end dates, notes. A "paid subscription" for the sprint target = a paid arrangement with status Confirmed (brief §9.3). Withdrawn: REQ-010, 012, 017, 185. | REQ-022, 116, 179–185 |
| D-029 | Q1 (correction) | The demo/staging site is public and indexable; there is no private preview. A profile is public only after human approval; drafts and unreviewed profiles are admin-only. Removal, suppression, nominee decline and the kill switch must work on public indexed pages: immediate unpublish, 410 and noindex, sitemap and CDN purge. | REQ-191–193, 208, 209 |
| D-031 | Architecture comparison (architecture.md s13) | **The CMS is WordPress on cPanel.** Supersedes the Laravel + Filament proposal in architecture.md and ADR-001, 003, 004. architecture.md and the ADRs are NOT yet revised; that happens after the wireframe review. No WordPress, database or API is set up yet. Next step is a clickable UI wireframe in /prototype (inventory in docs/wireframe-inventory.md). | REQ-208, 209 |

## Pending (team lead)

| Item | What is needed | Blocks /architect? |
|---|---|---|
| Q16 | CaromHQ API and prospect exchange | No — CSV first |

Resolved by D-028: Q7, Q8, C13.
