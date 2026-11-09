# WHOISWHO — Contradictions, Questions and Risks

**Status summary (87 items): Answered 54 · Default accepted 25 · Deferred 8 · Pending 0 · Open 0.** Updated 2026-11-09 (D-049 to D-051).
Contradictions 13: Answered 9 · Default accepted 4 · Deferred 0 · Pending 0 · Open 0. Questions 74: Answered 45 · Default accepted 21 · Deferred 8 · Pending 0 · Open 0. Q40–Q45 are reserved by the pending brief review (docs/change-review-2026-11-07.md) and are not yet logged here.
**Scope key:** `SITE` = the website and WordPress. `ACQUISITION` = the pipeline and its data. `BOTH` = binds both sides, or crosses the contract in `docs/contracts/import-format.md`. Numbers never change.
Status values: `Answered`, `Default accepted`, `Deferred` (Legal skipped, judging, CaromHQ: revisited later), `Pending - team lead`, `Open`. Decisions are recorded in `docs/decisions.md` (D-###).

Companion to `docs/requirements.md`. Updated 2026-11-06 after Gate 1 answers.
Working principle (D-026): finish as early as possible; the brief's dates are ceilings; only external lead times and human approvals are real constraints.
**Scope change (D-005):** no payment processing on the website. Customers pay offline; admins record payments in a commercial register.

---

## Contradictions and gaps in the spec

| # | Status | Where | Problem | Resolution | Decision | Scope |
|---|---|---|---|---|---|---|
| C1 | Answered | §2.1 vs §1 / title | Name appears as "WHOISWHO", "WHOISWHOINAFRICA.COM" and "WHOISWHO IN AFRICA". | Brand = WHOISWHO ("Who Is Who In Africa"). Domain = **wiwi.africa**, also the email sending domain. Edition = "WHOISWHO in Africa: The Maiden Edition". | D-001 | BOTH |
| C2 | Answered | §1 "not simply a site where people upload profiles" vs §5.1, §8 | The only built flow was paid self-submitted profiles; 10 customers cannot populate a directory. | The WHOISWHO team seeds the directory from sourced public data (organisations first, then public figures as unclaimed profiles with sources and a claim/correct/remove link). Paid self-submitted profiles are a second path. | D-002 | BOTH |
| C3 | Default accepted | §3.4 "paid ≠ verified" vs §6.1 verification as a paid benefit | Selling verification looks like buying credibility. | A subscription never buys the verification outcome; status is independent of payment. | D-003 | SITE |
| C4 | Default accepted | §6.1 "Featured placement" vs the Featured rung of the ladder | Featured is either paid or earned. | Featured = editorial only; paid placement = Sponsored label. | D-003 | SITE |
| C5 | Answered | §8 P1 deliverables vs brand approval on 20 Nov | Brand assets arrive after the site is built. | Build with a provisional theme. All brand values (colours, fonts, logo, spacing) sit in one central theme config; nothing hard-coded elsewhere. | D-004 | SITE |
| C6 | Answered (withdrawn) | §8 P1 "Establish payment" vs sales from 21 Nov | Payment readiness and merchant approval. | **WITHDRAWN.** No payment processing on the website; payments are offline and admins record them manually. No gateway, no Paystack. | D-005 | SITE |
| C7 | Answered | §8 P3 exit vs P2 onboarding | Subscribers onboarded before the site is "operational". | First release = public site (awards, directory, profiles, editorial) plus admin tools. Admins onboard paying customers through the manual register. Later phase is hardening and polish only. | D-007 | SITE |
| C8 | Answered | §8–9 "10 subscriptions or NGN 5m" | Two targets, no price. | Target is met if EITHER 10 paid subscriptions (per §9.3) OR NGN 5m confirmed revenue. Revenue is recorded by stream (subscription, media/editorial, sponsorship). The dashboard shows both numbers and each stream; no gate is hard-coded. | D-008 | SITE |
| C9 | Answered | §12.2 Nominate vs §8 P3 nomination process | Nominations would launch before the rules exist. | Build the nomination form and admin queue now behind a feature flag that is OFF. Until rules and eligibility are published the page shows "Nominations open soon" with email sign-up. Turning the flag on needs CEO approval. | D-009 | SITE |
| C10 | Default accepted | §7.2 pan-African vs Nigeria first | Country scope. | **Amended (D-010): Nigeria first, including other African countries and pan-African bodies.** (Was: Nigeria only for MVP; country field kept for later.) | D-010 | BOTH |
| C11 | Default accepted | §5.1 contact options vs privacy | Contact data is a harassment risk. | Enquiry relay only; no public email/phone by default. | D-010 | SITE |
| C12 | Answered | §15.5 CaromHQ vs shared services | No interface described. | CSV exchange first. Data designed (stable IDs, change timestamps) so a direct integration can be added. What is needed to build it: see D-011. | D-011 | SITE |
| C13 | Answered | §9.3 counting rule vs unpriced packages | Register states depended on packages. | There are no tiers or packages. A "paid subscription" for the sprint target means a paid arrangement with status Confirmed in the register (§9.3 rule). | D-028 | SITE |

---

## Questions

### BLOCKING

| Q | Status | Question | Why it matters | Decision / default | Ref | Scope |
|---|---|---|---|---|---|---|
| Q1 | Answered | How many organisation and individual profiles, by when, for what purpose? | Import volume, human review workload, exposure of unreviewed data. | **Amended by D-033.** **Corrected.** There is no private preview: the staging/demo site is public and can be indexed. A profile is public only after a human reviewer approves it; drafts and unreviewed profiles are admin-only. Target: ~100 organisation and ~100 individual reviewed profiles live; keep sourcing in parallel. Bulk unpublish by filter (D-043) and the suppression list must work on public, indexed pages (immediate unpublish, 410/noindex). | D-012 | BOTH |
| Q2 | Answered | Are public acquired individual stubs in the MVP? | Data model, legal exposure. | **Yes, in MVP and published without consent.** Professional facts only, each with source and retrieval date, human-reviewed first, visible claim / correct / remove link, permanent suppression list. **Bulk unpublish by filter (D-043, replacing the kill switch)** can unpublish all acquired individual profiles at once. | D-013 | BOTH |
| Q3 | Default accepted | Which source types are acceptable? | Ingestion and reviewer trust. | Registries, regulators, exchange listings, Wikidata, licensed APIs, official sites; each source approved before use. Never LinkedIn or other social platforms; never news article text or photos. | D-014 | ACQUISITION |
| Q4 | Answered | Who reviews, and how many per week? | Review is the quality gate. | **Amended by D-033: review now follows publication for acquired records.** Default accepted with one change: **100% human review before anything publishes (no sampling)**. Reviewers: `<name 1>`, `<name 2>` (names not yet supplied). About 40 profiles per reviewer per week; add a third reviewer if backlog grows. | D-015 | BOTH |
| Q5 | Answered | Legal basis for publishing personal data? | Consent flows, takedown handling. | Legitimate interests with right to object. Position requested from Incspired Legal by 13 Nov, in parallel with the build; **not a build blocker**. If Legal objects, use bulk unpublish by filter (D-043) and adjust. | D-016 | BOTH |
| Q6 | Answered | Is AI drafting allowed in the MVP? | Vendor, data transfer, review UI. | **Allowed** for organisations and individuals under rules: restate source facts only; every claim linked to a source passage and dropped if not; no claims on legal trouble, health, family, finances, politics; neutral tone; human approves every draft with claims beside sources; internal AI-assisted field plus model and prompt version; provider that does not train on our data; Legal confirms the transfer. | D-017 | BOTH |
| Q7 | Answered | Free tier, and what each paid tier adds? | Entitlements. | **No tiers, packages or entitlements.** Every listing has the same format. Commercial relationships are arrangements recorded in the admin register: type (sponsored post, sponsored profile, media/editorial package, awards sponsorship, other), customer, amount, currency, payment method and reference, date received, confirmed by, start/end dates, notes. | D-028 | SITE |
| Q8 | Answered | Packages, prices, billing period, VAT? | Reporting only. | Resolved by Q7: no packages. Amounts are entered per arrangement. | D-028 | SITE |
| Q9 | Answered (withdrawn) | Payment processor and merchant account? | — | **WITHDRAWN.** No payment processor, no merchant account. Payment is offline; admins record it. | D-005 | SITE |
| Q10 | Answered | Refund, cancellation, renewal, expiry? | Admin states. | Handled offline by Finance. The platform stores renewal and expiry dates and shows reminders to admins. | D-018 | SITE |
| Q11 | Default accepted | Meaning of Verified / Featured / Sponsored? (Unclaimed and Claimed removed by D-036.) | Credibility promise. | Verified = checklist, independent of payment. Featured = editorial only. Sponsored = labelled, orthogonal, never implies verification. | D-003 | SITE |
| Q12 | Answered | How much of the awards process is built? | Scope. | ****Judging and announcements flags removed by D-037; only the Nominations flag remains.** Full workflow in software (option b):** categories, nominations, eligibility screening, judges, scoring, COI declarations, shortlisting, final approval, winner notification, winner pages. All rules configurable in admin. Judges never see payment, subscription or sponsorship data. Nominations, judging and announcements stay behind flags that are OFF until CEO approves rules and panel. | D-019 | SITE |
| Q13 | Answered | Brand assets: who delivers? | Public launch look. | **Does not affect progress** (D-049). Brand designers supply assets when ready and the theme tokens file is updated then (C5). | D-004 | SITE |
| Q14 | Answered | Accounts, DNS, hosting, data location? | Architecture, hosting. | Domain **wiwi.africa** and cPanel shared hosting already acquired. Use the domain. cPanel is optional: /architect chooses hosting, justifies it and lists the exact accounts to open. You create accounts by 13 Nov and put secrets in the host's secret store, never in the repo. Region: Africa or EU. | D-020 | SITE |
| Q15 | Answered | Nominations without consent? | Nominee data. | Anyone can nominate; the nominee need not consent to be nominated. Nominations are private until eligibility screening is done; then the nominee gets an email to accept, decline or correct. Public nominee pages only at SHORTLIST, from reviewed profile and sourced facts. A decline removes the nominee and adds them to suppression. Payment never affects a nomination. Nominator email verified, rate-limited, duplicate-checked. No public voting. | D-021 | SITE |

### ASSUMABLE

| Q | Status | Question | Why it matters | Decision / default | Ref | Scope |
|---|---|---|---|---|---|---|
| Q16 | Deferred - last | CaromHQ API and prospect import/export? | Integration. | **One of the last things built** (D-051). Syncing with CaromHQ is looked at after everything else is done. CSV import and export stays the starting point (D-011). | D-011 | SITE |
| Q17 | Answered | Can one organisation have several users? | Roles. | **No, for now.** One login per profile owner (individual or organisation). Multi-user organisations are deferred and can be added later. | D-030 | SITE |
| Q18 | Answered | How are claims verified and abuse handled? | Impersonation. | **Overtaken by D-036: there is no claiming.** Original: Organisation: email at its domain or document. Individual: document plus manual approval. Report button on every profile. | D-022 | SITE |
| Q19 | Answered | Sprint target: 10 subscriptions or NGN 5m? | Reporting. | Resolved by C8: either condition; report both. | D-008 | SITE |
| Q20 | Answered | Brand string and domain? | Copy, SEO, email. | Resolved by C1: brand WHOISWHO, domain wiwi.africa. | D-001 | BOTH |
| Q22 | Answered | Photos and text ownership? | Copyright. | Default accepted, plus images under open licences (e.g. Wikimedia Commons) when licence and attribution are stored with the image. No other third-party photos. | D-024 | BOTH |
| Q23 | Default accepted | Profile analytics in MVP? | Scope. | View count only. | D-025 | SITE |
| Q24 | Default accepted | Languages and countries? | Scope. | English; Nigeria first (D-010 amended). | D-025 | BOTH |
| Q25 | Default accepted | Event tickets and sponsor invoices in the platform? | Scope. | No; manual. | D-025 | SITE |

### Architecture questions (raised by /architect, 2026-11-06)

| Q | Status | Question | Why it matters | Default if you do not know | Scope |
|---|---|---|---|---|---|
| Q26 | Answered | What does the cPanel account actually offer? PHP version (need 8.3), SSH access, Composer, cron every minute, MySQL/MariaDB version, TRIGGER privilege, outbound HTTPS, disk/inode/memory limits, data-centre region, uptime history, backups. | Decides hosting (ADR-003) and deploy method (ADR-012). Spike S1 checks it. | **Build locally on WordPress, then import the site to the live host** (D-050). No up-front study of the host; its limits are checked at import time. | SITE |
| Q27 | Answered | Which Incspired shared services can we reuse (existing Cloudflare account, email provider, monitoring, Sentry, backup storage, a VPS)? | Fewer new accounts and processors. | **Not needed now** (D-050). WordPress and the cPanel account are enough to start. | SITE |
| Q28 | Answered | Who owns and pays for the new accounts (Cloudflare, Brevo, R2, Sentry, Anthropic API), and who is the named admin on each? | Account creation by 13 Nov (Q14); processor register (REQ-065). | **Not needed now** (D-049). No Cloudflare, Brevo, R2 or Sentry account is opened. Email uses SMTP from cPanel mail (D-050). The Anthropic API account for acquisition stays with the acquisition side. | BOTH |

### Acquisition plan questions (raised 2026-11-07, updated 2026-11-08 for D-033, see acquisition/docs/acquisition-plan.md section 12)

Q29 would amend D-010 and D-025. Q29, Q31–Q33 block the pilot (target start 10 Nov).

| Q | Status | Question | Why it matters | Default if you do not know | Scope |
|---|---|---|---|---|---|
| Q29 | Default accepted | Split of the first 1,000: 500 organisations / 200 entrepreneurs / 300 individuals; 850 Nigeria / 150 other African (amends D-010, D-025)? | Source work, Legal per country | As proposed; non-Nigerian from batch B3 after Legal clears each country | ACQUISITION |
| Q30 | Answered | Keep 100% review (D-015) or allow sampling of structured registry organisation records (T1) after a measured gate? | Tooling (T-14), trust; saves only 2–5% | **Overtaken by D-033.** T-14 dropped. | BOTH |
| Q31 | Answered | Review order (organisations first, or individuals after T1)? (Reviewer hours and target dates removed.) | Track 2 in plan 4.2 | **Reviewer hours are dropped** (D-049): not a project input, so no hours-based dates. Review order stays organisations first. | SITE |
| Q32 | Answered | Pilot review in hash-locked review packs before WordPress is ready, or wait for the WordPress review queue? | Pilot start date | **Answered by D-033 as amended:** WordPress review queue; review packs removed; content hash kept. | BOTH |
| Q33 | Default accepted | Approve all sources recommended for the run (plan 2.2–2.5), once unread terms are read, with the supervisor as named approver (REQ-137)? | The run starts right after this | Approve | ACQUISITION |
| Q38 | Answered | AI budget cap US$250 (the reviewer hourly rate was removed) | Cost estimate | AI budget cap US$250 stays. **The reviewer hourly rate is dropped** (D-049): reviewer pay is not an input to the project. | BOTH |
| Q39 | Default accepted | AI model and inference location for drafting (Opus 5.5 global, US-only at 1.1x, or Sonnet 5.5)? | Transfer record (REQ-169) | Opus 5.5, global, unless Legal prefers US-only | ACQUISITION |

### Publish-first questions (raised 2026-11-08, see D-033, acquisition/docs/change-review-2026-11-08-publish-first.md section 5 and plan section 12)

Updated when D-033 was amended: Q47, Q48, Q50–Q53 are closed. Q46, Q55 and Q63–Q67 should be answered first.

| Q | Status | Question | Why it matters | Default if you do not know | Scope |
|---|---|---|---|---|---|
| Q46 | Answered | D-017, REQ-163 and REQ-168 require human approval of every AI draft before going live. For AI-drafted organisations (T2): go live as written and amend those three for organisations; go live with the AI summary hidden until approved; or wait for review? | Your state on import does not fit them for T2 | **Published as written** (D-049). AI summaries go live, and the whole platform gets an in-house review before it launches. Profiles still pass the D-033 human review. | BOTH |
| Q47 | Answered | Control 2: keep the quick look for individuals and entrepreneurs, or publish them unreviewed too? | Exposure | **Answered by D-033 as amended:** neither. Individuals and entrepreneurs are not public until a reviewer approves. You may change this. | BOTH |
| Q48 | Answered | Does D-033 cover acquired records only? | Scope | **Answered by D-033 as amended:** it describes the acquisition pipeline and its import only; customer submissions, owner changes, complimentary listings and nominees stay approval-first. | BOTH |
| Q49 | Answered | What counts toward targets: public or reviewed? (D-012, REQ-104, REQ-191) | Reporting; noindex lasts until review | **Overtaken by D-035:** no directory-size target on the website. Report counts by state. | BOTH |
| Q50 | Answered | Cap on pending profiles? | Exposure | **Removed by D-033 as amended, pending Q66.** | SITE |
| Q51 | Answered | Automatic pause thresholds? | When publishing stops by itself | **Removed by D-033 as amended, pending Q66.** S1 and S2 rules in plan 6.1 are applied by people. | SITE |
| Q52 | Answered | AI takedown limits? | | **Removed by D-033 as amended.** Future item REQ-238. | SITE |
| Q53 | Answered | Is the "staging database" private, or the one behind staging.wiwi.africa? | | **Answered by D-033 as amended:** a store outside WordPress that exports for import, so private. Q64 covers which WordPress takes the import. | ACQUISITION |
| Q54 | Default accepted | Do indexable list pages show reviewed profiles only, with labelled organisations found by search, direct link or a noindex view? | Noindex on a profile does not stop its name being indexed through a list | Reviewed only; organisations-first review fills the lists by 13 Dec | SITE |
| Q55 | Default accepted | Many sources are manual-only (CBN, PenCom, NAICOM, SEC, NCC, State House). Should agents read each one's terms and robots rules before the run so you can approve re-coding where allowed, with the rest left out? | The pipeline cannot gather from them as coded; typing them in does not fit the 24 hours | Yes | ACQUISITION |
| Q56 | Default accepted | D-034 leaves profile owners with no way for the owner to see or edit anything. Confirm that owners only ever use forms and email, and staff do all edits and lookups (REQ-232, 236)? | Staff workload; owner experience | Yes. Revisit after the first 100 correction requests. | SITE |
| Q57 | Answered | Who approves a correction to name, title, organisation, awards or achievement claims? | Staff-made edits are the only control now | **Overtaken by D-040:** staff edit directly; every edit is audited; no second-approver step. | SITE |
| Q58 | Default accepted | Life of a single-use signed link (REQ-233): intake, consent, nominee, nominator and correction/removal/report email verification? | Security against convenience | 14 days; nominator and requester verification 48 hours; reissued by staff on request. | SITE |
| Q59 | Answered | How does staff check a claimant is the person or an authorised representative (D-022) when there is no account? | Fraud and dispute risk | **Overtaken by D-036: no claiming.** Original: Organisation: email on the organisation's domain (verified link) or a document. Individual: document plus a call-back or an email from an official address. Doubtful cases go to the Editor-in-Chief. | SITE |
| Q61 | Answered | Only Visitor, Reviewer, Editor and Administrator exist (D-044). Who holds each at launch? | Least privilege; the Administrator should be few people | **No names needed yet** (D-049). The build only has to let the Administrator add users and give each one a role (REQ-244, REQ-245). People are added in wp-admin when they start. | SITE |
| Q62 | Answered | Do enquiries to profiles go to the staff inbox or to a contact address the owner gave staff (REQ-018)? Who sends the owner the view count and sources (REQ-236)? | Privacy; staff workload | **Enquiries go to the Administrators by default** (D-049). The Administrator can give the enquiries capability to another user in the role editor (D-046). | SITE |

### Questions from the correction to D-033 (raised 2026-11-08, see plan section 12)

| Q | Status | Question | Why it matters | Default if you do not know | Scope |
|---|---|---|---|---|---|
| Q64 | Default accepted | Which WordPress takes the import, given the demo/staging site is public and indexable (D-029)? | Real unreviewed data; D-029 | One site, importing everything as not public until the safeguards in plan 5.4 pass | BOTH |
| Q65 | Default accepted | Import all 500 organisations at once, or the first 100, reviewed, then the rest? | With no cap, the first error rate would arrive after everything is live | First 100, then the rest (about 3 working days at 2 × 15 h/week) | BOTH |
| Q66 | Default accepted | Control 5 (cap and automatic pause) and instant hide on removal requests: confirm removed, with people applying the S1 and S2 rules at each import? | You listed them under neither "keep" nor "remove" | Confirm removed | BOTH |
| Q67 | Default accepted | Import path: CSV or JSON files run by a named staff member, or REST with a token that can only create not-public records? | Architecture input; whether the pipeline holds any WordPress credentials | Files first | BOTH |
| Q68 | Answered | **Overtaken by D-043: the kill switch is removed; bulk unpublish by filter replaces it.** Original: the kill switch (D-036, REQ-193) now unpublished "acquired individual profiles". Is that all individual profiles whose origin is the acquisition import, including those later made Verified or Featured, with customer-submitted or consented individual profiles exempt? | The switch is the legal safety control for people listed without consent; with no claiming, nothing else marks someone as having agreed | Yes: all import-origin individual profiles; customer-submitted or consented ones are exempt. | SITE |
| Q69 | Answered | Who may use bulk unpublish by filter (D-043): Editor and Administrator, or Administrator only? It can take down many live profiles at once. | Blast radius of a mistake; speed in a legal emergency | **Administrator and Reviewer** (D-045). Others only by a capability grant (D-046). | SITE |
| Q70 | Answered | With Finance and Sales/Support gone (D-044), the Administrator confirms payments and enters the commercial register. Is one person confirming and entering acceptable (REQ-022 asks for a named confirmed-by admin)? | Separation of duties for the counting rule | **The Administrator alone is fine** (D-045). | SITE |
| Q71 | Answered | Who handles disputes and removal requests now that Editor-in-Chief is gone (REQ-174)? | Legal safeguard | **Whoever holds the handle-disputes capability; the Editor by default.** Roles are defaults; the Administrator can grant or revoke capabilities per user across roles (D-046). | SITE |
| Q72 | Default accepted | Which capabilities can never be granted to a non-Administrator (D-046)? | Stops a grant from turning a Reviewer into an Administrator by stages | Users and roles, settings, the commercial register, plugin installation and the Nominations flag stay Administrator-only. Everything else is grantable and audited. | SITE |
| Q73 | Default accepted | What state does a customer-submitted profile awaiting approval have? REQ-210 has none | The publish gate must cover every profile | Reuse "Imported – not public" (admin-only until a human approves) | SITE |
| Q74 | Answered | Where does the read-only Sources screen get source names? The import contract carries only `source_id` | REQ-243 | **A basic `sources[]` structure for now** (D-049). It is revised once acquisition is done. | BOTH |
| Q75 | Answered | Media offload to object storage (R2): plugin or custom code? | Cost, maintenance | **Withdrawn** (D-050). WordPress's media library handles images; there is no object storage. | SITE |
| Q76 | Answered | Two-factor for staff: plugin or custom code? | REQ-047 | **A maintained two-factor plugin enforced for every staff role** (D-050). No spike. | SITE |
| Q77 | Default accepted | Reword REQ-237 "CSV or REST" to "file or REST"? The contract is JSON Lines run by an Administrator in wp-admin or CLI; REST would need a service account (a fourth role) | Roles are fixed at three (D-044) | Yes: file upload; no REST service account | BOTH |
| Q78 | Default accepted | One hierarchical places taxonomy, or separate country and city taxonomies? | REQ-009 | One hierarchical taxonomy (country > city) | SITE |
| Q79 | Answered | Background jobs on shared cPanel: Action Scheduler or a custom job table? | ADR-011 | **WordPress's own scheduler (WP-Cron, or Action Scheduler if needed)** (D-050). No spike. | SITE |
| Q80 | Default accepted | How is the website code folder `site/` named in CLAUDE.md ownership rules, and who writes it? | Avoids it counting as a shared doc | `site/` is written only by the website build sessions; shared docs stay outside it | SITE |


## Legal questions (skipped for now, D-051)

Legal is skipped for now. Nothing in the build waits for it. The defaults below are kept as the rule and are revisited when Legal is brought in. Consequence: Q35's default (the first individual goes public only after Legal's written answer) still holds, so individual profiles stay not public until Legal is revisited. Organisations are not affected.

| Q | Status | Question | Why it matters | Default if you do not know | Scope |
|---|---|---|---|---|---|
| Q21 | Deferred - Legal skipped | Retention and deletion periods? | NDPA. | Subscriber data 6 years after last payment, nominations 2 years, suppression record kept indefinitely (name only); subject to Legal's review. (Ref: D-023) | BOTH |
| Q34 | Deferred - Legal skipped | Photos on acquired individuals and organisation logos? | Personality rights, trademark | None in the first 1,000 until Legal advises | BOTH |
| Q35 | Deferred - Legal skipped | First individual goes public only after Legal's written answer (assessment, DPIA), or under D-016 with bulk unpublish by filter? Also amends REQ-170 wording. | Legal exposure | Wait for Legal. Review can run meanwhile; approvals wait. | BOTH |
| Q36 | Deferred - Legal skipped | Include current public office-holders (no party field)? | Sensitive data (NDPA s.30) | Include, no party field | BOTH |
| Q37 | Deferred - Legal skipped | Listed companies from issuer pages + Wikidata, or wait for an NGX data licence? | NGX terms ban aggregation | Issuer pages + Wikidata; ask NGX for a quote | ACQUISITION |
| Q63 | Deferred - Legal skipped | Legal's AI transfer basis blocks individuals' data (REQ-169). Run organisations straight after source approvals and individuals when Legal confirms, wait and run all together, or send without it (amends D-017)? | "All 1,000 in one run" depends on it | Two runs | ACQUISITION |

## Judging questions (revisit later, D-051)

Judging is offline and not being decided now (D-034). Questions about judges go here.

| Q | Status | Question | Why it matters | Default if you do not know | Scope |
|---|---|---|---|---|---|
| Q60 | Deferred - revisit later | Who enters judges' scores, and how are judges' identities and scores kept private and consistent without a portal? Judging format (offline panel meeting, scoring sheets, emailed forms)? | Needed before judging opens; judges' conflict declarations | Judges score on a sheet or in a meeting; an Editor enters scores and a second staff member checks the entry; sheets are kept. | SITE |

---

## BLOCKING items not Answered or Default accepted

None. Q7, Q8 and C13 are resolved (D-028). **Gate 1 is clear.**

Nothing blocks the build. Deferred on purpose: the Legal questions, the judging questions and Q16 (CaromHQ, one of the last things built).

Still needed from you: nothing blocks the build. Legal's position (Q5) and the Legal section are skipped for now (D-051).

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

**Not yet rewritten for D-033 as amended.** Entries citing "100% human review" before publishing now hold for individuals and entrepreneurs, not imported organisations; current risks are in acquisition/docs/acquisition-plan.md section 10.2.

| Risk | Impact | Mitigation in requirements |
|---|---|---|
| **Nigeria Data Protection Act 2023** — publishing personal data without consent (now in MVP, Q2). Duties include lawful basis, transparency, data-subject rights, cross-border transfers (including the AI provider), breach notification, possible DPO/registration duties. | Regulatory action, forced takedowns. | Legitimate-interests assessment (REQ-057), bulk unpublish by filter (REQ-193), removal in 2 business days (REQ-158), suppression (REQ-207), DPIA (REQ-072). Legal's position is requested but not yet received. |
| **Defamation / false light** | Legal claims, reputational harm. | Neutral-content checklist (REQ-163), source per field (REQ-142), 100% human review (REQ-148), takedown (REQ-071). |
| **AI hallucination** — now allowed in MVP (Q6) | Fabricated facts about real people. | Claim-to-source mapping with automatic drop (REQ-166), prohibited topics (REQ-167), reviewer sees claims beside sources (REQ-168). |
| **Combined Q2 + Q6**: AI-drafted profiles of non-consenting people at scale, reviewed by two people at ~40 per week each. | Review fatigue; errors reach the public. | REQ-206 workload warnings; third reviewer; bulk unpublish by filter; start with organisations first. |
| **Platform terms of service** | Legal exposure. | Source allowlist, no social platforms (REQ-138). |
| **Reputational harm if profiles are wrong or stale** | Press, subjects. | Review gate, "last reviewed" date, refresh cycle, corrections log (REQ-144, 160, 164). |
| **Copyright** | Takedown, claims. | No third-party photos except open-licence images with attribution (REQ-162, REQ-203). |
| **Pay-to-win perception** | Brand collapse. | Status separation, no data path from commercial records to awards (REQ-024, 037, 184, 199, 201). |
| **Public, indexed pages of real people** — search engines cache pages that are later removed | Removed or wrong profiles remain visible in search results | REQ-208, REQ-209 (410, noindex, sitemap and CDN purge); N21 search-engine removal requests |
| **Empty-directory problem** | Low conversion. | Team-seeded organisations and individuals (Q1, REQ-191–192). |
| **Human review capacity** (agents draft fast; only humans approve) | Backlog of unpublished profiles. | REQ-206; Q4 sizing. |
| **Manual commercial register** — errors or fraud in hand-entered payments | Wrong revenue count against the target. | Confirmed-by field, computed counting rule, audit log (REQ-022, 179, 188). |
| **Legal objection after launch** | Mass takedown. | Bulk unpublish by filter ready from day one. |

---

## Top 3 risks to the idea

1. **Publishing information about real people without their consent, drafted by AI.** One wrong statement hurts a person and the brand. The safeguards (source per claim, 100% human review, bulk unpublish by filter, suppression, fast removal) must be in place before any individual profile goes public, and Legal's position is still outstanding.
2. **Credibility.** The product sells recognition, so any hint of pay-to-win, or thin and wrong profiles at launch, undermines it. Commercial data is walled off from awards and trust labels.
3. **Human capacity and external approvals.** Agents can build and draft quickly; the real limits are reviewers, Legal, brand assets, award rules and your approvals. The table above lists each with a needed-by date.
