# WHOISWHO — Contradictions, Questions and Risks

**Status summary (38 items): Answered 21 · Default accepted 12 · Pending - team lead 5 · Open 0.**
Contradictions 13: Answered 8, Default accepted 4, Pending 1. Questions 25: Answered 13, Default accepted 8, Pending 4.
Status values: `Answered`, `Default accepted`, `Pending - team lead`, `Open`. Decisions are recorded in `docs/decisions.md` (D-###).

Companion to `docs/requirements.md`. Updated 2026-11-06 after Gate 1 answers.
Working principle (D-026): finish as early as possible; the brief's dates are ceilings; only external lead times and human approvals are real constraints.
**Scope change (D-005):** no payment processing on the website. Customers pay offline; admins record payments in a commercial register.

---

## Contradictions and gaps in the spec

| # | Status | Where | Problem | Resolution | Decision |
|---|---|---|---|---|---|
| C1 | Answered | §2.1 vs §1 / title | Name appears as "WHOISWHO", "WHOISWHOINAFRICA.COM" and "WHOISWHO IN AFRICA". | Brand = WHOISWHO ("Who Is Who In Africa"). Domain = **wiwi.africa**, also the email sending domain. Edition = "WHOISWHO in Africa: The Maiden Edition". | D-001 |
| C2 | Answered | §1 "not simply a site where people upload profiles" vs §5.1, §8 | The only built flow was paid self-submitted profiles; 10 customers cannot populate a directory. | The WHOISWHO team seeds the directory from sourced public data (organisations first, then public figures as unclaimed profiles with sources and a claim/correct/remove link). Paid self-submitted profiles are a second path. | D-002 |
| C3 | Default accepted | §3.4 "paid ≠ verified" vs §6.1 verification as a paid benefit | Selling verification looks like buying credibility. | A subscription never buys the verification outcome; status is independent of payment. | D-003 |
| C4 | Default accepted | §6.1 "Featured placement" vs the Featured rung of the ladder | Featured is either paid or earned. | Featured = editorial only; paid placement = Sponsored label. | D-003 |
| C5 | Answered | §8 P1 deliverables vs brand approval on 20 Nov | Brand assets arrive after the site is built. | Build with a provisional theme. All brand values (colours, fonts, logo, spacing) sit in one central theme config; nothing hard-coded elsewhere. | D-004 |
| C6 | Answered (withdrawn) | §8 P1 "Establish payment" vs sales from 21 Nov | Payment readiness and merchant approval. | **WITHDRAWN.** No payment processing on the website; payments are offline and admins record them manually. No gateway, no Paystack. | D-005 |
| C7 | Answered | §8 P3 exit vs P2 onboarding | Subscribers onboarded before the site is "operational". | First release = public site (awards, directory, profiles, editorial) plus admin tools. Admins onboard paying customers through the manual register. Later phase is hardening and polish only. | D-007 |
| C8 | Answered | §8–9 "10 subscriptions or NGN 5m" | Two targets, no price. | Target is met if EITHER 10 paid subscriptions (per §9.3) OR NGN 5m confirmed revenue. Revenue is recorded by stream (subscription, media/editorial, sponsorship). The dashboard shows both numbers and each stream; no gate is hard-coded. | D-008 |
| C9 | Answered | §12.2 Nominate vs §8 P3 nomination process | Nominations would launch before the rules exist. | Build the nomination form and admin queue now behind a feature flag that is OFF. Until rules and eligibility are published the page shows "Nominations open soon" with email sign-up. Turning the flag on needs CEO approval. | D-009 |
| C10 | Default accepted | §7.2 pan-African vs Nigeria first | Country scope. | Nigeria only for MVP; country field kept for later. | D-010 |
| C11 | Default accepted | §5.1 contact options vs privacy | Contact data is a harassment risk. | Enquiry relay only; no public email/phone by default. | D-010 |
| C12 | Answered | §15.5 CaromHQ vs shared services | No interface described. | CSV exchange first. Data designed (stable IDs, change timestamps) so a direct integration can be added. What is needed to build it: see D-011. | D-011 |
| C13 | Pending - team lead | §9.3 counting rule vs unpriced packages | Register states depend on packages. | Resolved by Q8. Plans and entitlements are built as configuration, so this does not block /architect. | D-027 |

---

## Questions

### BLOCKING

| Q | Status | Question | Why it matters | Decision / default | Ref |
|---|---|---|---|---|---|
| Q1 | Answered | How many organisation and individual profiles, by when, for what purpose? | Import volume and human review workload. | **Override.** Private staging (login-protected, noindex) with ~100 organisations and ~100 individuals for testing and demos. Public launch (31 Jan is the ceiling): at least the same volume of fully reviewed profiles. Keep sourcing in parallel. A second human reviews every profile before public. | D-012 |
| Q2 | Answered | Are public unclaimed individual stubs in the MVP? | Data model, legal exposure. | **Yes, in MVP and published without consent.** Professional facts only, each with source and retrieval date, human-reviewed first, visible claim / correct / remove link, permanent suppression list. **Global kill switch** unpublishes all unclaimed individual profiles at once. | D-013 |
| Q3 | Default accepted | Which source types are acceptable? | Ingestion and reviewer trust. | Registries, regulators, exchange listings, Wikidata, licensed APIs, official sites; each source approved before use. Never LinkedIn or other social platforms; never news article text or photos. | D-014 |
| Q4 | Answered | Who reviews, and how many per week? | Review is the quality gate. | Default accepted with one change: **100% human review before anything publishes (no sampling)**. Reviewers: `<name 1>`, `<name 2>` (names not yet supplied). About 40 profiles per reviewer per week; add a third reviewer if backlog grows. | D-015 |
| Q5 | Answered | Legal basis for publishing personal data? | Consent flows, takedown handling. | Legitimate interests with right to object. Position requested from Incspired Legal by 13 Nov, in parallel with the build; **not a build blocker**. If Legal objects, use the kill switch and adjust. | D-016 |
| Q6 | Answered | Is AI drafting allowed in the MVP? | Vendor, data transfer, review UI. | **Allowed** for organisations and individuals under rules: restate source facts only; every claim linked to a source passage and dropped if not; no claims on legal trouble, health, family, finances, politics; neutral tone; human approves every draft with claims beside sources; internal AI-assisted field plus model and prompt version; provider that does not train on our data; Legal confirms the transfer. | D-017 |
| Q7 | Pending - team lead | Free tier, and what each paid tier adds (incl. corporate multi-profile)? | Entitlements. | Not decided. Tiers are admin-entered labels and records; entitlements are configuration. Does not block architecture. | D-027 |
| Q8 | Pending - team lead | Packages, prices, billing period, VAT? | Reporting only; no checkout exists. | Not decided. Prices are admin-entered labels and records. Does not block architecture. | D-027 |
| Q9 | Answered (withdrawn) | Payment processor and merchant account? | — | **WITHDRAWN.** No payment processor, no merchant account. Payment is offline; admins record it. | D-005 |
| Q10 | Answered | Refund, cancellation, renewal, expiry? | Admin states. | Handled offline by Finance. The platform stores renewal and expiry dates and shows reminders to admins. | D-018 |
| Q11 | Default accepted | Meaning of Unclaimed / Claimed / Verified / Featured / Sponsored? | Credibility promise. | Verified = checklist, independent of payment. Featured = editorial only. Sponsored = labelled, orthogonal, never implies verification. | D-003 |
| Q12 | Answered | How much of the awards process is built? | Scope. | **Full workflow in software (option b):** categories, nominations, eligibility screening, judges, scoring, COI declarations, shortlisting, final approval, winner notification, winner pages. All rules configurable in admin. Judges never see payment, subscription or sponsorship data. Nominations, judging and announcements stay behind flags that are OFF until CEO approves rules and panel. | D-019 |
| Q13 | Default accepted | Brand assets: who delivers, and may the build proceed on a provisional theme? | Public launch look. | Provisional theme now; assets applied through the central theme config (C5). | D-004 |
| Q14 | Answered | Accounts, DNS, hosting, data location? | Architecture, hosting. | Domain **wiwi.africa** and cPanel shared hosting already acquired. Use the domain. cPanel is optional: /architect chooses hosting, justifies it and lists the exact accounts to open. You create accounts by 13 Nov and put secrets in the host's secret store, never in the repo. Region: Africa or EU. | D-020 |
| Q15 | Answered | Nominations without consent? | Nominee data. | Anyone can nominate; the nominee need not consent to be nominated. Nominations are private until eligibility screening is done; then the nominee gets an email to accept, decline or correct. Public nominee pages only at SHORTLIST, from reviewed profile and sourced facts. A decline removes the nominee and adds them to suppression. Payment never affects a nomination. Nominator email verified, rate-limited, duplicate-checked. No public voting. | D-021 |

### ASSUMABLE

| Q | Status | Question | Why it matters | Decision / default | Ref |
|---|---|---|---|---|---|
| Q16 | Pending - team lead | CaromHQ API and prospect import/export? | Integration. | Not decided. CSV import/export in MVP; data designed for later integration (D-011). | D-011 |
| Q17 | Pending - team lead | Can one organisation have several users? | Roles. | Not decided. Roles and organisation membership are configuration-friendly; no build block. | D-027 |
| Q18 | Default accepted | How are claims verified and abuse handled? | Impersonation. | Organisation: email at its domain or document. Individual: document plus manual approval. Report button on every profile. | D-022 |
| Q19 | Answered | Sprint target: 10 subscriptions or NGN 5m? | Reporting. | Resolved by C8: either condition; report both. | D-008 |
| Q20 | Answered | Brand string and domain? | Copy, SEO, email. | Resolved by C1: brand WHOISWHO, domain wiwi.africa. | D-001 |
| Q21 | Default accepted | Retention and deletion periods? | NDPA. | Subscriber data 6 years after last payment, nominations 2 years, suppression record kept indefinitely (name only); subject to Legal's review. | D-023 |
| Q22 | Answered | Photos and text ownership? | Copyright. | Default accepted, plus images under open licences (e.g. Wikimedia Commons) when licence and attribution are stored with the image. No other third-party photos. | D-024 |
| Q23 | Default accepted | Profile analytics in MVP? | Scope. | View count only. | D-025 |
| Q24 | Default accepted | Languages and countries? | Scope. | English, Nigeria only. | D-025 |
| Q25 | Default accepted | Event tickets and sponsor invoices in the platform? | Scope. | No; manual. | D-025 |

---

## BLOCKING items not Answered or Default accepted

**Q7** and **Q8** (both `Pending - team lead`). Neither blocks /architect: tiers, prices and entitlements are admin-entered labels and configuration, and there is no checkout. **Gate 1 is clear.**

Non-blocking pending: Q16, Q17, C13.

Still needed from you (not blockers for architecture): reviewer names for Q4, Legal's position by 13 Nov (Q5), accounts by 13 Nov (Q14).

---

## External lead times and approvals

Build time is not the constraint. Merchant approval is removed (no payments).

| Item | Owner | Needed by | Gates | Your action |
|---|---|---|---|---|
| Accounts created, secrets placed in the host's secret store | You / Incspired IT | 13 Nov (Q14) | Staging and production deploy | Open the accounts /architect lists |
| DNS and email authentication (SPF, DKIM, DMARC) on wiwi.africa | You / Incspired IT | With the accounts | Public site, outbound email | Add the records |
| Legal: Terms, Privacy, NDPA position, AI data transfer | Incspired Legal | Position by 13 Nov (Q5); Terms and Privacy before the first enquiry is collected | Collecting leads; public launch of individual stubs | Request and chase |
| Brand assets | Brand & Creative | Before public launch (Q13) | Public look | Approve |
| Reviewer names and availability | You / Editorial | Before the first import (Q4) | Any profile going public | Provide names |
| Source approvals (terms, robots, licence per source) | Editorial + Legal | Before each import (REQ-137) | Data acquisition | Approve |
| Award rules, eligibility page, judging panel | You | Before the flags are switched on (REQ-194) | Nominations, judging, announcements | Approve |
| Package and price labels | You | Whenever ready (Q7, Q8) | Informational packages page | Decide; not a blocker |

---

## Risk register

| Risk | Impact | Mitigation in requirements |
|---|---|---|
| **Nigeria Data Protection Act 2023** — publishing personal data without consent (now in MVP, Q2). Duties include lawful basis, transparency, data-subject rights, cross-border transfers (including the AI provider), breach notification, possible DPO/registration duties. | Regulatory action, forced takedowns. | Legitimate-interests assessment (REQ-057), kill switch (REQ-193), removal in 2 business days (REQ-158), suppression (REQ-207), DPIA (REQ-072). Legal's position is requested but not yet received. |
| **Defamation / false light** | Legal claims, reputational harm. | Neutral-content checklist (REQ-163), source per field (REQ-142), 100% human review (REQ-148), takedown (REQ-071). |
| **AI hallucination** — now allowed in MVP (Q6) | Fabricated facts about real people. | Claim-to-source mapping with automatic drop (REQ-166), prohibited topics (REQ-167), reviewer sees claims beside sources (REQ-168). |
| **Combined Q2 + Q6**: AI-drafted profiles of non-consenting people at scale, reviewed by two people at ~40 per week each. | Review fatigue; errors reach the public. | REQ-206 workload warnings; third reviewer; kill switch; start with organisations first. |
| **Platform terms of service** | Legal exposure. | Source allowlist, no social platforms (REQ-138). |
| **Reputational harm if profiles are wrong or stale** | Press, subjects. | Review gate, "last reviewed" date, refresh cycle, corrections log (REQ-144, 160, 164). |
| **Copyright** | Takedown, claims. | No third-party photos except open-licence images with attribution (REQ-162, REQ-203). |
| **Pay-to-win perception** | Brand collapse. | Status separation, no data path from commercial records to awards (REQ-024, 037, 184, 199, 201). |
| **Empty-directory problem** | Low conversion. | Team-seeded organisations and individuals (Q1, REQ-191–192). |
| **Human review capacity** (agents draft fast; only humans approve) | Backlog of unpublished profiles. | REQ-206; Q4 sizing. |
| **Manual commercial register** — errors or fraud in hand-entered payments | Wrong revenue count against the target. | Confirmed-by field, computed counting rule, audit log (REQ-022, 179, 188). |
| **Legal objection after launch** | Mass takedown. | Kill switch ready from day one. |

---

## Top 3 risks to the idea

1. **Publishing information about real people without their consent, drafted by AI.** One wrong statement hurts a person and the brand. The safeguards (source per claim, 100% human review, kill switch, suppression, fast removal) must be in place before any individual profile goes public, and Legal's position is still outstanding.
2. **Credibility.** The product sells recognition, so any hint of pay-to-win, or thin and wrong profiles at launch, undermines it. Commercial data is walled off from awards and trust labels.
3. **Human capacity and external approvals.** Agents can build and draft quickly; the real limits are reviewers, Legal, brand assets, award rules and your approvals. The table above lists each with a needed-by date.
