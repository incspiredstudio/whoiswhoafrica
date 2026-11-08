# WHOISWHO — Contradictions, Questions and Risks

**Status summary (69 items): Answered 26 · Default accepted 12 · Pending - team lead 1 · Open 30 (Q26–Q28 architecture; Q29, Q31–Q39 acquisition plan; Q46–Q55 publish first; Q56–Q62 no public accounts).**
Contradictions 13: Answered 9, Default accepted 4, Pending 0. Questions 56: Answered 17, Default accepted 8, Pending 1 (Q16), Open 30. Q40–Q45 are reserved by the pending brief review (docs/change-review-2026-11-07.md) and are not yet logged here.
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
| C13 | Answered | §9.3 counting rule vs unpriced packages | Register states depended on packages. | There are no tiers or packages. A "paid subscription" for the sprint target means a paid arrangement with status Confirmed in the register (§9.3 rule). | D-028 |

---

## Questions

### BLOCKING

| Q | Status | Question | Why it matters | Decision / default | Ref |
|---|---|---|---|---|---|
| Q1 | Answered | How many organisation and individual profiles, by when, for what purpose? | Import volume, human review workload, exposure of unreviewed data. | **Amended by D-033.** **Corrected.** There is no private preview: the staging/demo site is public and can be indexed. A profile is public only after a human reviewer approves it; drafts and unreviewed profiles are admin-only. Target: ~100 organisation and ~100 individual reviewed profiles live; keep sourcing in parallel. The kill switch and suppression list must work on public, indexed pages (immediate unpublish, 410/noindex). | D-012 |
| Q2 | Answered | Are public unclaimed individual stubs in the MVP? | Data model, legal exposure. | **Yes, in MVP and published without consent.** Professional facts only, each with source and retrieval date, human-reviewed first, visible claim / correct / remove link, permanent suppression list. **Global kill switch** unpublishes all unclaimed individual profiles at once. | D-013 |
| Q3 | Default accepted | Which source types are acceptable? | Ingestion and reviewer trust. | Registries, regulators, exchange listings, Wikidata, licensed APIs, official sites; each source approved before use. Never LinkedIn or other social platforms; never news article text or photos. | D-014 |
| Q4 | Answered | Who reviews, and how many per week? | Review is the quality gate. | **Amended by D-033: review now follows publication for acquired records.** Default accepted with one change: **100% human review before anything publishes (no sampling)**. Reviewers: `<name 1>`, `<name 2>` (names not yet supplied). About 40 profiles per reviewer per week; add a third reviewer if backlog grows. | D-015 |
| Q5 | Answered | Legal basis for publishing personal data? | Consent flows, takedown handling. | Legitimate interests with right to object. Position requested from Incspired Legal by 13 Nov, in parallel with the build; **not a build blocker**. If Legal objects, use the kill switch and adjust. | D-016 |
| Q6 | Answered | Is AI drafting allowed in the MVP? | Vendor, data transfer, review UI. | **Allowed** for organisations and individuals under rules: restate source facts only; every claim linked to a source passage and dropped if not; no claims on legal trouble, health, family, finances, politics; neutral tone; human approves every draft with claims beside sources; internal AI-assisted field plus model and prompt version; provider that does not train on our data; Legal confirms the transfer. | D-017 |
| Q7 | Answered | Free tier, and what each paid tier adds? | Entitlements. | **No tiers, packages or entitlements.** Every listing has the same format. Commercial relationships are arrangements recorded in the admin register: type (sponsored post, sponsored profile, media/editorial package, awards sponsorship, other), customer, amount, currency, payment method and reference, date received, confirmed by, start/end dates, notes. | D-028 |
| Q8 | Answered | Packages, prices, billing period, VAT? | Reporting only. | Resolved by Q7: no packages. Amounts are entered per arrangement. | D-028 |
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
| Q17 | Answered | Can one organisation have several users? | Roles. | **No, for now.** One login per profile owner (individual or organisation). Multi-user organisations are deferred and can be added later. | D-030 |
| Q18 | Default accepted | How are claims verified and abuse handled? | Impersonation. | Organisation: email at its domain or document. Individual: document plus manual approval. Report button on every profile. | D-022 |
| Q19 | Answered | Sprint target: 10 subscriptions or NGN 5m? | Reporting. | Resolved by C8: either condition; report both. | D-008 |
| Q20 | Answered | Brand string and domain? | Copy, SEO, email. | Resolved by C1: brand WHOISWHO, domain wiwi.africa. | D-001 |
| Q21 | Default accepted | Retention and deletion periods? | NDPA. | Subscriber data 6 years after last payment, nominations 2 years, suppression record kept indefinitely (name only); subject to Legal's review. | D-023 |
| Q22 | Answered | Photos and text ownership? | Copyright. | Default accepted, plus images under open licences (e.g. Wikimedia Commons) when licence and attribution are stored with the image. No other third-party photos. | D-024 |
| Q23 | Default accepted | Profile analytics in MVP? | Scope. | View count only. | D-025 |
| Q24 | Default accepted | Languages and countries? | Scope. | English, Nigeria only. | D-025 |
| Q25 | Default accepted | Event tickets and sponsor invoices in the platform? | Scope. | No; manual. | D-025 |

### Architecture questions (raised by /architect, 2026-11-06)

| Q | Status | Question | Why it matters | Default if you do not know |
|---|---|---|---|---|
| Q26 | Open | What does the cPanel account actually offer? PHP version (need 8.3), SSH access, Composer, cron every minute, MySQL/MariaDB version, TRIGGER privilege, outbound HTTPS, disk/inode/memory limits, data-centre region, uptime history, backups. | Decides hosting (ADR-003) and deploy method (ADR-012). Spike S1 checks it. | Assume it fails and plan the VPS fallback (about US$10–20/month) until S1 passes. |
| Q27 | Open | Which Incspired shared services can we reuse (existing Cloudflare account, email provider, monitoring, Sentry, backup storage, a VPS)? | Fewer new accounts and processors. | Open the new accounts listed in architecture.md section 8. |
| Q28 | Open | Who owns and pays for the new accounts (Cloudflare, Brevo, R2, Sentry, Anthropic API), and who is the named admin on each? | Account creation by 13 Nov (Q14); processor register (REQ-065). | You (the supervisor) own them; Incspired IT is added as a second admin. |

### Acquisition plan questions (raised 2026-11-07, updated 2026-11-08 for D-033, see docs/acquisition-plan.md section 12)

Q29 would amend D-010 and D-025. Q29, Q31–Q33 block the pilot (target start 10 Nov).

| Q | Status | Question | Why it matters | Default if you do not know |
|---|---|---|---|---|
| Q29 | Open | Split of the first 1,000: 500 organisations / 200 entrepreneurs / 300 individuals; 850 Nigeria / 150 other African (amends D-010, D-025)? | Source work, Legal per country | As proposed; non-Nigerian from batch B3 after Legal clears each country |
| Q30 | Answered | Keep 100% review (D-015) or allow sampling of structured registry organisation records (T1) after a measured gate? | Tooling (T-14), trust; saves only 2–5% | **Overtaken by D-033.** T-14 dropped. |
| Q31 | Open | Reviewer hours and target date for 1,000 | Section 4 capacity | 2 reviewers × 15 h/week → 1,000 live by 10 Jan, all reviewed by 17 Jan |
| Q32 | Open | Pilot review in hash-locked review packs before WordPress is ready, or wait for the WordPress review queue? | Pilot start date | Review packs |
| Q33 | Open | Approve the pilot sources marked "Approve for pilot" (plan 2.2–2.5) with the supervisor as named approver (REQ-137)? | No fetch without approval | Approve |
| Q34 | Open | Photos on unclaimed individuals and organisation logos? | Personality rights, trademark | None in the first 1,000 until Legal advises |
| Q35 | Open | First individual published only after Legal's written answer (assessment, DPIA and the new publish-first questions in plan 6.5), or under D-016 with the kill switch? Also amends REQ-170 wording. | Legal exposure; automatic publishing raises it | Wait for Legal; organisations publish first. Legal can take until about 7 Dec without moving a date. |
| Q36 | Open | Include current public office-holders (no party field)? | Sensitive data (NDPA s.30) | Include, no party field, always amber (full human review before going live) |
| Q37 | Open | Listed companies from issuer pages + Wikidata, or wait for an NGX data licence? | NGX terms ban aggregation | Issuer pages + Wikidata; ask NGX for a quote |
| Q38 | Open | AI budget cap US$450 (was US$250; audit and monitoring added) and the reviewer hourly rate | Cost estimate | Cap approved; rate needed |
| Q39 | Open | AI model and inference location, for drafting, audit and monitoring (Opus 5.5 global, US-only at 1.1x, or Sonnet 5.5)? | Transfer record (REQ-169) | Opus 5.5, global, unless Legal prefers US-only |

### Publish-first questions (raised 2026-11-08, see D-033, docs/change-review-2026-11-08-publish-first.md section 5 and plan section 12)

Q46–Q49, Q53 and Q55 change the model and should be answered first. Q50–Q52 and Q54 are values I proposed.

| Q | Status | Question | Why it matters | Default if you do not know |
|---|---|---|---|---|
| Q46 | Open | D-017, REQ-163 and REQ-168 require human approval of every AI draft before going live. Amend them, and does an AI-drafted organisation (T2) get a quick look, no look, or stay review-first? | D-033 cannot work with them as written; T2 is not named in your tiers | Amend; T2 gets a quick look |
| Q47 | Open | Control 2: keep the quick look for individuals and entrepreneurs, or publish them unreviewed too? D-013 needs a matching amendment either way. | Exposure; no look gains one week and 10 hours | Keep the quick look |
| Q48 | Open | Does D-033 cover acquired records only, leaving customer submissions, owner edits, complimentary listings and nominees approval-first (REQ-014, 015, 175, 198)? | Scope of the change | Acquired records only |
| Q49 | Open | What counts toward targets: live or reviewed? Does every record get a human review, with "samples" only setting the order? (D-012, REQ-104, REQ-191) | Reporting; noindex lasts until review | Report both; target = live; D-012 stays reviewed; every record is reviewed |
| Q50 | Open | Cap on pending profiles: 400 total and 150 individuals, or two weeks of review capacity if lower; ramp 50, 150, 300, then cap? | Exposure against speed; 600/300 brings 1,000 live forward two weeks | As proposed; raise only after 200 individuals are reviewed cleanly |
| Q51 | Open | Automatic pause thresholds in plan 6.1? | When publishing stops by itself | Accept |
| Q52 | Open | AI takedown limits: any code on pending; S1 only on reviewed, claimed, verified or sponsored profiles, otherwise a flag; AI restores technical codes only? | You said "any it finds unsuitable"; a false takedown of a customer profile is costly | As proposed |
| Q53 | Open | Is the "staging database" a private store no public site reads, or the one behind staging.wiwi.africa (public, D-029)? | Real unreviewed data on a public staging site; architecture line 273 | Private store |
| Q54 | Open | Do indexable list pages show reviewed profiles only, with pending found by search, direct link or a noindex view? | Noindex on a profile does not stop its name being indexed through a list | Reviewed only |
| Q55 | Open | Many sources are manual-only (CBN, PenCom, NAICOM, SEC, NCC, State House). Should agents read each one's terms and robots rules so you can approve re-coding where allowed, with the rest staying amber? | Pipeline cannot gather from them or monitor them; T1 may be mostly amber | Yes |
| Q56 | Open | D-034 leaves Claimed profiles with no way for the owner to see or edit anything. Confirm that owners only ever use forms and email, and staff do all edits and lookups (REQ-232, 236)? | Staff workload; owner experience | Yes. Revisit after the first 100 claims. |
| Q57 | Open | Who approves a correction to name, title, organisation, awards or achievement claims? | Staff-made edits are the only control now | A second staff member (Reviewer or above) approves; other fields need one staff member. |
| Q58 | Open | Life of a single-use signed link (REQ-233): intake, consent, nominee, nominator and claim verification? | Security against convenience | 14 days; nominator and claim verification 48 hours; reissued by staff on request. |
| Q59 | Open | How does staff check a claimant is the person or an authorised representative (D-022) when there is no account? | Fraud and dispute risk | Organisation: email on the organisation's domain (verified link) or a document. Individual: document plus a call-back or an email from an official address. Doubtful cases go to the Editor-in-Chief. |
| Q60 | Open | Who enters judges' scores, and how are judges' identities and scores kept private and consistent without a portal? Judging format (offline panel meeting, scoring sheets, emailed forms)? | Needed before judging opens; judges' conflict declarations | Judges score on a sheet or in a meeting; the Awards manager enters scores and a second staff member checks the entry; sheets are kept. |
| Q61 | Open | Are the roles right, and who holds each one at launch (Awards manager, Editor-in-Chief, Finance)? Is Editor-in-Chief a separate role from Administrator? | Least privilege; the Administrator should be few people | Seven roles as in REQ-235; Administrator limited to 2 named people. Names needed. |
| Q62 | Open | Do enquiries to claimed profiles go to the staff inbox or to a contact address the owner gave staff (REQ-018)? Who sends the owner the view count and sources (REQ-236)? | Privacy; staff workload | Forward to the owner's contact address held by staff; staff send the view count on request. |

---

## BLOCKING items not Answered or Default accepted

None. Q7, Q8 and C13 are resolved (D-028). **Gate 1 is clear.**

Non-blocking pending: Q16. Architecture questions Q26–Q28 are open with defaults; Q26 blocks the first deploy (M0), not Gate 2.

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

---

## Risk register

**Not yet rewritten for D-033.** Entries citing "100% human review" before publishing are out of date; current risks are in docs/acquisition-plan.md section 10.2.

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
| **Public, indexed pages of real people** — search engines cache pages that are later removed | Removed or wrong profiles remain visible in search results | REQ-208, REQ-209 (410, noindex, sitemap and CDN purge); N21 search-engine removal requests |
| **Empty-directory problem** | Low conversion. | Team-seeded organisations and individuals (Q1, REQ-191–192). |
| **Human review capacity** (agents draft fast; only humans approve) | Backlog of unpublished profiles. | REQ-206; Q4 sizing. |
| **Manual commercial register** — errors or fraud in hand-entered payments | Wrong revenue count against the target. | Confirmed-by field, computed counting rule, audit log (REQ-022, 179, 188). |
| **Legal objection after launch** | Mass takedown. | Kill switch ready from day one. |

---

## Top 3 risks to the idea

1. **Publishing information about real people without their consent, drafted by AI.** One wrong statement hurts a person and the brand. The safeguards (source per claim, 100% human review, kill switch, suppression, fast removal) must be in place before any individual profile goes public, and Legal's position is still outstanding.
2. **Credibility.** The product sells recognition, so any hint of pay-to-win, or thin and wrong profiles at launch, undermines it. Commercial data is walled off from awards and trust labels.
3. **Human capacity and external approvals.** Agents can build and draft quickly; the real limits are reviewers, Legal, brand assets, award rules and your approvals. The table above lists each with a needed-by date.
