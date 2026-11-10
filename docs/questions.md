# WHOISWHO — Contradictions, Questions and Risks

**Status summary (104 items): Answered 61 · Default accepted 24 · Deferred 8 · Pending 0 · Open 11.** Updated 2026-11-09 (D-049 to D-053). The Open items are Q81–Q91, raised by WordPress (D-053).
Contradictions 13: Answered 9 · Default accepted 4 · Deferred 0 · Pending 0 · Open 0. Questions 91: Answered 52 · Default accepted 20 · Deferred 8 · Pending 0 · Open 11.
**WP type key (D-053):** `Native` = WordPress core does it. `Native+config` = a role capability set, taxonomy, custom post type or field. `Custom plugin` = genuinely custom code. `Theme` = front end. `Process` = policy, not software. Terms in `docs/GUIDE.md`.
**Scope key:** `SITE` = the website and WordPress. `ACQUISITION` = the pipeline and its data. `BOTH` = binds both sides, or crosses the contract in `docs/contracts/import-format.md`. Numbers never change.
Status values: `Answered`, `Default accepted`, `Deferred` (Legal skipped, judging, CaromHQ: revisited later), `Pending - team lead`, `Open`. Decisions are recorded in `docs/decisions.md` (D-###).

Companion to `docs/requirements.md`. Updated 2026-11-06 after Gate 1 answers.
Working principle (D-026): finish as early as possible; the brief's dates are ceilings; only external lead times and human approvals are real constraints.
**Scope change (D-005):** no payment processing on the website. Customers pay offline; admins record payments in a commercial register.

---

## Contradictions and gaps in the spec

| # | Status | Where | Problem | Resolution | Decision | WP type | Scope |
|---|---|---|---|---|---|---|---|
| C1 | Answered | §2.1 vs §1 / title | Name appears as "WHOISWHO", "WHOISWHOINAFRICA.COM" and "WHOISWHO IN AFRICA". | Brand = WHOISWHO ("Who Is Who In Africa"). Domain = **wiwi.africa**, also the email sending domain. Edition = "WHOISWHO in Africa: The Maiden Edition". | D-001, D-052 | Theme | BOTH |
| C2 | Answered | §1 "not simply a site where people upload profiles" vs §5.1, §8 | The only built flow was paid self-submitted profiles; 10 customers cannot populate a directory. | The WHOISWHO team seeds the directory from sourced public data (organisations first, then public figures as unclaimed profiles with sources and a claim/correct/remove link). Paid self-submitted profiles are a second path. | D-002 | Process | BOTH |
| C3 | Default accepted | §3.4 "paid ≠ verified" vs §6.1 verification as a paid benefit | Selling verification looks like buying credibility. | A subscription never buys the verification outcome; status is independent of payment. | D-003 | Process | SITE |
| C4 | Default accepted | §6.1 "Featured placement" vs the Featured rung of the ladder | Featured is either paid or earned. | Featured = editorial only; paid placement = Sponsored label. | D-003 | Process | SITE |
| C5 | Answered | §8 P1 deliverables vs brand approval on 20 Nov | Brand assets arrive after the site is built. | Build with a provisional theme. All brand values (colours, fonts, logo, spacing) sit in one central theme config; nothing hard-coded elsewhere. | D-004 | Theme | SITE |
| C6 | Answered (withdrawn) | §8 P1 "Establish payment" vs sales from 21 Nov | Payment readiness and merchant approval. | **WITHDRAWN.** No payment processing on the website; payments are offline and admins record them manually. No gateway, no Paystack. | D-005 | Process | SITE |
| C7 | Answered | §8 P3 exit vs P2 onboarding | Subscribers onboarded before the site is "operational". | First release = public site (awards, directory, profiles, editorial) plus admin tools. Admins onboard paying customers through the manual register. Later phase is hardening and polish only. | D-007 | Process | SITE |
| C8 | Answered | §8–9 "10 subscriptions or NGN 5m" | Two targets, no price. | Target is met if EITHER 10 paid subscriptions (per §9.3) OR NGN 5m confirmed revenue. Revenue is recorded by stream (subscription, media/editorial, sponsorship). The dashboard shows both numbers and each stream; no gate is hard-coded. | D-008 | Custom plugin | SITE |
| C9 | Answered | §12.2 Nominate vs §8 P3 nomination process | Nominations would launch before the rules exist. | Build the nomination form and admin queue now behind a feature flag that is OFF. Until rules and eligibility are published the page shows "Nominations open soon" with email sign-up. Turning the flag on needs CEO approval. | D-009 | Custom plugin | SITE |
| C10 | Default accepted | §7.2 pan-African vs Nigeria first | Country scope. | **Amended (D-010): Nigeria first, including other African countries and pan-African bodies.** (Was: Nigeria only for MVP; country field kept for later.) | D-010 | Native+config | BOTH |
| C11 | Default accepted | §5.1 contact options vs privacy | Contact data is a harassment risk. | Enquiry relay only; no public email/phone by default. | D-010 | Custom plugin | SITE |
| C12 | Answered | §15.5 CaromHQ vs shared services | No interface described. | CSV exchange first. Data designed (stable IDs, change timestamps) so a direct integration can be added. What is needed to build it: see D-011. | D-011 | Custom plugin | SITE |
| C13 | Answered | §9.3 counting rule vs unpriced packages | Register states depended on packages. | There are no tiers or packages. A "paid subscription" for the sprint target means a paid arrangement with status Confirmed in the register (§9.3 rule). | D-028 | Custom plugin | SITE |

---

## Questions

### BLOCKING

| Q | Status | Question | Why it matters | Decision / default | Ref | WP type | Scope |
|---|---|---|---|---|---|---|---|
| Q1 | Answered | How many organisation and individual profiles, by when, for what purpose? | Import volume, human review workload, exposure of unreviewed data. | **Amended by D-033.** **Corrected.** There is no private preview: the staging/demo site is public and can be indexed. A profile is public only after a human reviewer approves it; drafts and unreviewed profiles are admin-only. Target: ~100 organisation and ~100 individual reviewed profiles live; keep sourcing in parallel. Bulk unpublish by filter (D-043) and the suppression list must work on public, indexed pages (immediate unpublish, 410/noindex). | D-012 | Process | BOTH |
| Q2 | Answered | Are public acquired individual stubs in the MVP? | Data model, legal exposure. | **Yes, in MVP and published without consent.** Professional facts only, each with source and retrieval date, human-reviewed first, visible claim / correct / remove link, permanent suppression list. **Bulk unpublish by filter (D-043, replacing the kill switch)** can unpublish all acquired individual profiles at once. | D-013 | Process | BOTH |
| Q3 | Default accepted | Which source types are acceptable? | Ingestion and reviewer trust. | Registries, regulators, exchange listings, Wikidata, licensed APIs, official sites; each source approved before use. Never LinkedIn or other social platforms; never news article text or photos. | D-014 | Process | ACQUISITION |
| Q4 | Answered | Who reviews, and how many per week? | Review is the quality gate. | **Amended by D-033: review now follows publication for acquired records.** Default accepted with one change: **100% human review before anything publishes (no sampling)**. Reviewers: `<name 1>`, `<name 2>` (names not yet supplied). About 40 profiles per reviewer per week; add a third reviewer if backlog grows. | D-015 | Process | BOTH |
| Q5 | Answered | Legal basis for publishing personal data? | Consent flows, takedown handling. | Legitimate interests with right to object. Position requested from Incspired Legal by 13 Nov, in parallel with the build; **not a build blocker**. If Legal objects, use bulk unpublish by filter (D-043) and adjust. | D-016 | Process | BOTH |
| Q6 | Answered | Is AI drafting allowed in the MVP? | Vendor, data transfer, review UI. | **Allowed** for organisations and individuals under rules: restate source facts only; every claim linked to a source passage and dropped if not; no claims on legal trouble, health, family, finances, politics; neutral tone; human approves every draft with claims beside sources; internal AI-assisted field plus model and prompt version; provider that does not train on our data; Legal confirms the transfer. | D-017 | Process | BOTH |
| Q7 | Answered | Free tier, and what each paid tier adds? | Entitlements. | **No tiers, packages or entitlements.** Every listing has the same format. Commercial relationships are arrangements recorded in the admin register: type (sponsored post, sponsored profile, media/editorial package, awards sponsorship, other), customer, amount, currency, payment method and reference, date received, confirmed by, start/end dates, notes. | D-028 | Process | SITE |
| Q8 | Answered | Packages, prices, billing period, VAT? | Reporting only. | Resolved by Q7: no packages. Amounts are entered per arrangement. | D-028 | Process | SITE |
| Q9 | Answered (withdrawn) | Payment processor and merchant account? | — | **WITHDRAWN.** No payment processor, no merchant account. Payment is offline; admins record it. | D-005 | Process | SITE |
| Q10 | Answered | Refund, cancellation, renewal, expiry? | Admin states. | Handled offline by Finance. The platform stores renewal and expiry dates and shows reminders to admins. | D-018 | Process | SITE |
| Q11 | Default accepted | Meaning of Verified / Featured / Sponsored? (Unclaimed and Claimed removed by D-036.) | Credibility promise. | Verified = checklist, independent of payment. Featured = editorial only. Sponsored = labelled, orthogonal, never implies verification. | D-003 | Custom plugin | SITE |
| Q12 | Answered | How much of the awards process is built? | Scope. | ****Judging and announcements flags removed by D-037; only the Nominations flag remains.** Full workflow in software (option b):** categories, nominations, eligibility screening, judges, scoring, COI declarations, shortlisting, final approval, winner notification, winner pages. All rules configurable in admin. Judges never see payment, subscription or sponsorship data. Nominations, judging and announcements stay behind flags that are OFF until CEO approves rules and panel. | D-019 | Custom plugin | SITE |
| Q13 | Answered | Brand assets: who delivers? | Public launch look. | **Does not affect progress** (D-049). Brand designers supply assets when ready and the theme tokens file is updated then (C5). | D-004 | Theme | SITE |
| Q14 | Answered | Accounts, DNS, hosting, data location? | Architecture, hosting. | Domain **wiwi.africa** and cPanel shared hosting already acquired. Use the domain. cPanel is optional: /architect chooses hosting, justifies it and lists the exact accounts to open. You create accounts by 13 Nov and put secrets in the host's secret store, never in the repo. Region: Africa or EU. | D-020 | Process | SITE |
| Q15 | Answered | Nominations without consent? | Nominee data. | Anyone can nominate; the nominee need not consent to be nominated. Nominations are private until eligibility screening is done; then the nominee gets an email to accept, decline or correct. Public nominee pages only at SHORTLIST, from reviewed profile and sourced facts. A decline removes the nominee and adds them to suppression. Payment never affects a nomination. Nominator email verified, rate-limited, duplicate-checked. No public voting. | D-021 | Custom plugin | SITE |

### ASSUMABLE

| Q | Status | Question | Why it matters | Decision / default | Ref | WP type | Scope |
|---|---|---|---|---|---|---|---|
| Q16 | Deferred - last | CaromHQ API and customer import/export? | Integration. | **One of the last things built** (D-051). Syncing with CaromHQ is looked at after everything else is done. CSV import and export stays the starting point (D-011). | D-011 | Custom plugin | SITE |
| Q17 | Answered | Can one organisation have several users? | Roles. | **No, for now.** One login per profile owner (individual or organisation). Multi-user organisations are deferred and can be added later. | D-030 | Native | SITE |
| Q18 | Answered | How are claims verified and abuse handled? | Impersonation. | **Overtaken by D-036: there is no claiming.** Original: Organisation: email at its domain or document. Individual: document plus manual approval. Report button on every profile. | D-022 | Process | SITE |
| Q19 | Answered | Sprint target: 10 subscriptions or NGN 5m? | Reporting. | Resolved by C8: either condition; report both. | D-008 | Process | SITE |
| Q20 | Answered | Brand string and domain? | Copy, SEO, email. | Resolved by C1: brand WHOISWHO, domain wiwi.africa. | D-001 | Theme | BOTH |
| Q22 | Answered | Photos and text ownership? | Copyright. | Default accepted, plus images under open licences (e.g. Wikimedia Commons) when licence and attribution are stored with the image. No other third-party photos. | D-024 | Native+config | BOTH |
| Q23 | Default accepted | Profile analytics in MVP? | Scope. | View count only, kept by a small plugin counter (core has none) that works with page caching. | D-025 | Custom plugin | SITE |
| Q24 | Default accepted | Languages and countries? | Scope. | English; Nigeria first (D-010 amended). | D-025 | Native+config | BOTH |
| Q25 | Default accepted | Event tickets and sponsor invoices in the platform? | Scope. | No; manual. | D-025 | Process | SITE |

### Architecture questions (raised by /architect, 2026-11-06)

| Q | Status | Question | Why it matters | Default if you do not know | WP type | Scope |
|---|---|---|---|---|---|---|
| Q26 | Answered | What does the cPanel account actually offer? PHP version (need 8.3), SSH access, Composer, cron every minute, MySQL/MariaDB version, TRIGGER privilege, outbound HTTPS, disk/inode/memory limits, data-centre region, uptime history, backups. | Decides hosting (ADR-003) and deploy method (ADR-012). Spike S1 checks it. | **Build locally on WordPress, then import the site to the live host** (D-050). No up-front study of the host; its limits are checked at import time. | Process | SITE |
| Q27 | Answered | Which Incspired shared services can we reuse (existing Cloudflare account, email provider, monitoring, Sentry, backup storage, a VPS)? | Fewer new accounts and processors. | **Not needed now** (D-050). WordPress and the cPanel account are enough to start. | Process | SITE |
| Q28 | Answered | Who owns and pays for the new accounts (Cloudflare, Brevo, R2, Sentry, Anthropic API), and who is the named admin on each? | Account creation by 13 Nov (Q14); processor register (REQ-065). | **Not needed now** (D-049). No Cloudflare, Brevo, R2 or Sentry account is opened. Email uses SMTP from cPanel mail (D-050). The Anthropic API account for acquisition stays with the acquisition side. | Process | BOTH |

### Acquisition plan questions (raised 2026-11-07, updated 2026-11-08 for D-033, see acquisition/docs/acquisition-plan.md section 12)

Q29 would amend D-010 and D-025. Q29, Q31–Q33 block the pilot (target start 10 Nov).

| Q | Status | Question | Why it matters | Default if you do not know | WP type | Scope |
|---|---|---|---|---|---|---|
| Q29 | Default accepted | Split of the first 1,000: 500 organisations / 200 entrepreneurs / 300 individuals; 850 Nigeria / 150 other African (amends D-010, D-025)? | Source work, Legal per country | As proposed; non-Nigerian from batch B3 after Legal clears each country | Process | ACQUISITION |
| Q30 | Answered | Keep 100% review (D-015) or allow sampling of structured registry organisation records (T1) after a measured gate? | Tooling (T-14), trust; saves only 2–5% | **Overtaken by D-033.** T-14 dropped. | Process | BOTH |
| Q31 | Answered | Review order (organisations first, or individuals after T1)? (Reviewer hours and target dates removed.) | Track 2 in plan 4.2 | **Reviewer hours are dropped** (D-049): not a project input, so no hours-based dates. Review order stays organisations first. | Process | SITE |
| Q32 | Answered | Pilot review in hash-locked review packs before WordPress is ready, or wait for the WordPress review queue? | Pilot start date | **Answered by D-033 as amended:** WordPress review queue; review packs removed; content hash kept. | Custom plugin | BOTH |
| Q33 | Default accepted | Approve all sources recommended for the run (plan 2.2–2.5), once unread terms are read, with the supervisor as named approver (REQ-137)? | The run starts right after this | Approve | Process | ACQUISITION |
| Q38 | Answered | AI budget cap US$250 (the reviewer hourly rate was removed) | Cost estimate | AI budget cap US$250 stays. **The reviewer hourly rate is dropped** (D-049): reviewer pay is not an input to the project. | Process | BOTH |
| Q39 | Default accepted | AI model and inference location for drafting (Opus 5.5 global, US-only at 1.1x, or Sonnet 5.5)? | Transfer record (REQ-169) | Opus 5.5, global, unless Legal prefers US-only | Process | ACQUISITION |

### Publish-first questions (raised 2026-11-08, see D-033, acquisition/docs/change-review-2026-11-08-publish-first.md section 5 and plan section 12)

Updated when D-033 was amended: Q47, Q48, Q50–Q53 are closed. Q46, Q55 and Q63–Q67 should be answered first.

| Q | Status | Question | Why it matters | Default if you do not know | WP type | Scope |
|---|---|---|---|---|---|---|
| Q46 | Answered | D-017, REQ-163 and REQ-168 require human approval of every AI draft before going live. For AI-drafted organisations (T2): go live as written and amend those three for organisations; go live with the AI summary hidden until approved; or wait for review? | Your state on import does not fit them for T2 | **Published as written** (D-049). AI summaries go live, and the whole platform gets an in-house review before it launches. Profiles still pass the D-033 human review. | Custom plugin | BOTH |
| Q47 | Answered | Control 2: keep the quick look for individuals and entrepreneurs, or publish them unreviewed too? | Exposure | **Answered by D-033 as amended:** neither. Individuals and entrepreneurs are not public until a reviewer approves. You may change this. | Custom plugin | BOTH |
| Q48 | Answered | Does D-033 cover acquired records only? | Scope | **Answered by D-033 as amended:** it describes the acquisition pipeline and its import only; customer submissions, owner changes, complimentary listings and nominees stay approval-first. | Process | BOTH |
| Q49 | Answered | What counts toward targets: public or reviewed? (D-012, REQ-104, REQ-191) | Reporting; noindex lasts until review | **Overtaken by D-035:** no directory-size target on the website. Report counts by state. | Custom plugin | BOTH |
| Q50 | Answered | Cap on pending profiles? | Exposure | **Removed by D-033 as amended, pending Q66.** | Custom plugin | SITE |
| Q51 | Answered | Automatic pause thresholds? | When publishing stops by itself | **Removed by D-033 as amended, pending Q66.** S1 and S2 rules in plan 6.1 are applied by people. | Custom plugin | SITE |
| Q52 | Answered | AI takedown limits? | | **Removed by D-033 as amended.** Future item REQ-238. | Custom plugin | SITE |
| Q53 | Answered | Is the "staging database" private, or the one behind staging.wiwi.africa? | | **Answered by D-033 as amended:** a store outside WordPress that exports for import, so private. Q64 covers which WordPress takes the import. | Process | ACQUISITION |
| Q54 | Default accepted | Do indexable list pages show reviewed profiles only, with labelled organisations found by search, direct link or a noindex view? | Noindex on a profile does not stop its name being indexed through a list | Reviewed only; organisations-first review fills the lists by 13 Dec | Theme | SITE |
| Q55 | Default accepted | Many sources are manual-only (CBN, PenCom, NAICOM, SEC, NCC, State House). Should agents read each one's terms and robots rules before the run so you can approve re-coding where allowed, with the rest left out? | The pipeline cannot gather from them as coded; typing them in does not fit the 24 hours | Yes | Process | ACQUISITION |
| Q56 | Default accepted | D-034 leaves profile owners with no way for the owner to see or edit anything. Confirm that owners only ever use forms and email, and staff do all edits and lookups (REQ-232, 236)? | Staff workload; owner experience | Yes. Revisit after the first 100 correction requests. | Process | SITE |
| Q57 | Answered | Who approves a correction to name, title, organisation, awards or achievement claims? | Staff-made edits are the only control now | **Overtaken by D-040:** staff edit directly; every edit is audited; no second-approver step. | Native+config | SITE |
| Q58 | Default accepted | Life of a single-use signed link (REQ-233): intake, consent, nominee, nominator and correction/removal/report email verification? | Security against convenience | 14 days; nominator and requester verification 48 hours; reissued by staff on request. | Custom plugin | SITE |
| Q59 | Answered | How does staff check a claimant is the person or an authorised representative (D-022) when there is no account? | Fraud and dispute risk | **Overtaken by D-036: no claiming.** Original: Organisation: email on the organisation's domain (verified link) or a document. Individual: document plus a call-back or an email from an official address. Doubtful cases go to the Editor-in-Chief. | Process | SITE |
| Q61 | Answered | Only Visitor, Reviewer, Editor and Administrator exist (D-044). Who holds each at launch? | Least privilege; the Administrator should be few people | **No names needed yet** (D-049). The build only has to let the Administrator add users and give each one a role (REQ-244, REQ-245). People are added in wp-admin when they start. | Native | SITE |
| Q62 | Answered | Do enquiries to profiles go to the staff inbox or to a contact address the owner gave staff (REQ-018)? Who sends the owner the view count and sources (REQ-236)? | Privacy; staff workload | **Enquiries go to the Administrators by default** (D-049). The Administrator can give the enquiries capability to another user in the role editor (D-046). | Native+config | SITE |

### Questions from the correction to D-033 (raised 2026-11-08, see plan section 12)

| Q | Status | Question | Why it matters | Default if you do not know | WP type | Scope |
|---|---|---|---|---|---|---|
| Q64 | Default accepted | Which WordPress takes the import, given the demo/staging site is public and indexable (D-029)? | Real unreviewed data; D-029 | One site, importing everything as not public until the safeguards in plan 5.4 pass | Process | BOTH |
| Q65 | Default accepted | Import all 500 organisations at once, or the first 100, reviewed, then the rest? | With no cap, the first error rate would arrive after everything is live | First 100, then the rest (about 3 working days at 2 × 15 h/week) | Process | BOTH |
| Q66 | Default accepted | Control 5 (cap and automatic pause) and instant hide on removal requests: confirm removed, with people applying the S1 and S2 rules at each import? | You listed them under neither "keep" nor "remove" | Confirm removed | Process | BOTH |
| Q67 | Default accepted | Import path: CSV or JSON files run by a named staff member, or REST with a token that can only create not-public records? | Architecture input; whether the pipeline holds any WordPress credentials | Files first: a JSON Lines file uploaded by the Administrator on one custom import screen under Tools, or run with WP-CLI. No REST service account. | Custom plugin | BOTH |
| Q68 | Answered | **Overtaken by D-043: the kill switch is removed; bulk unpublish by filter replaces it.** Original: the kill switch (D-036, REQ-193) now unpublished "acquired individual profiles". Is that all individual profiles whose origin is the acquisition import, including those later made Verified or Featured, with customer-submitted or consented individual profiles exempt? | The switch is the legal safety control for people listed without consent; with no claiming, nothing else marks someone as having agreed | Yes: all import-origin individual profiles; customer-submitted or consented ones are exempt. | Custom plugin | SITE |
| Q69 | Answered | Who may use bulk unpublish by filter (D-043): Editor and Administrator, or Administrator only? It can take down many live profiles at once. | Blast radius of a mistake; speed in a legal emergency | **Administrator and Reviewer** (D-045). Others only by a capability grant (D-046). | Native+config | SITE |
| Q70 | Answered | With Finance and Sales/Support gone (D-044), the Administrator confirms payments and enters the commercial register. Is one person confirming and entering acceptable (REQ-022 asks for a named confirmed-by admin)? | Separation of duties for the counting rule | **The Administrator alone is fine** (D-045). | Native+config | SITE |
| Q71 | Answered | Who handles disputes and removal requests now that Editor-in-Chief is gone (REQ-174)? | Legal safeguard | **Whoever holds the handle-disputes capability; the Editor by default.** Roles are defaults; the Administrator can grant or revoke capabilities per user across roles (D-046). | Native+config | SITE |
| Q72 | Default accepted | Which capabilities can never be granted to a non-Administrator (D-046)? | Stops a grant from turning a Reviewer into an Administrator by stages | Users and roles, settings, the commercial register, plugin installation and the Nominations flag stay Administrator-only (WordPress core already limits users, settings and plugins to the Administrator; the plugin adds the register and the flag). Everything else is grantable and audited. | Native+config | SITE |
| Q73 | Default accepted | What state does a customer-submitted profile awaiting approval have? REQ-210 has none | The publish gate must cover every profile | Reuse "Imported – not public" (admin-only until a human approves) | Custom plugin | SITE |
| Q74 | Answered | Where does the read-only Sources screen get source names? The import contract carries only `source_id` | REQ-243 | **A basic `sources[]` structure for now** (D-049). It is revised once acquisition is done. | Custom plugin | BOTH |
| Q75 | Answered | Media offload to object storage (R2): plugin or custom code? | Cost, maintenance | **Withdrawn** (D-050). WordPress's media library handles images; there is no object storage. | Native | SITE |
| Q76 | Answered | Two-factor for staff: plugin or custom code? | REQ-047 | **A maintained two-factor plugin enforced for every staff role** (D-050). No spike. | Native+config | SITE |
| Q77 | Default accepted | Reword REQ-237 "CSV or REST" to "file or REST"? The contract is JSON Lines run by an Administrator in wp-admin or CLI; REST would need a service account (a fourth role) | Roles are fixed at three (D-044) | Yes: file upload; no REST service account | Custom plugin | BOTH |
| Q78 | Answered | One hierarchical places taxonomy, or separate country and city taxonomies? | REQ-009 | **Answered by WordPress (D-053):** one hierarchical places taxonomy (country > city). Core taxonomies are hierarchical and have their own term screens (D-039). | Native+config | SITE |
| Q79 | Answered | Background jobs on shared cPanel: Action Scheduler or a custom job table? | ADR-011 | **WordPress's own scheduler (WP-Cron, or Action Scheduler if needed)** (D-050). No spike. | Native | SITE |
| Q80 | Default accepted | How is the website code folder `site/` named in CLAUDE.md ownership rules, and who writes it? | Avoids it counting as a shared doc | `site/` is written only by the website build sessions; shared docs stay outside it | Process | SITE |


## Legal questions (skipped for now, D-051)

Legal is skipped for now. Nothing in the build waits for it. The defaults below are kept as the rule and are revisited when Legal is brought in. Consequence: Q35's default (the first individual goes public only after Legal's written answer) still holds, so individual profiles stay not public until Legal is revisited. Organisations are not affected.

| Q | Status | Question | Why it matters | Default if you do not know | WP type | Scope |
|---|---|---|---|---|---|---|
| Q21 | Deferred - Legal skipped | Retention and deletion periods? | NDPA. | Subscriber data 6 years after last payment, nominations 2 years, suppression record kept indefinitely (name only); subject to Legal's review. (Ref: D-023) | Process | BOTH |
| Q34 | Deferred - Legal skipped | Photos on acquired individuals and organisation logos? | Personality rights, trademark | None in the first 1,000 until Legal advises | Process | BOTH |
| Q35 | Deferred - Legal skipped | First individual goes public only after Legal's written answer (assessment, DPIA), or under D-016 with bulk unpublish by filter? Also amended REQ-170 wording (REQ-170 withdrawn by D-054). | Legal exposure | Wait for Legal. Review can run meanwhile; approvals wait. | Process | BOTH |
| Q36 | Deferred - Legal skipped | Include current public office-holders (no party field)? | Sensitive data (NDPA s.30) | Include, no party field | Process | BOTH |
| Q37 | Deferred - Legal skipped | Listed companies from issuer pages + Wikidata, or wait for an NGX data licence? | NGX terms ban aggregation | Issuer pages + Wikidata; ask NGX for a quote | Process | ACQUISITION |
| Q63 | Deferred - Legal skipped | Legal's AI transfer basis blocks individuals' data (REQ-169). Run organisations straight after source approvals and individuals when Legal confirms, wait and run all together, or send without it (amends D-017)? | "All 1,000 in one run" depends on it | Two runs | Process | ACQUISITION |

## Judging questions (revisit later, D-051)

Judging is offline and not being decided now (D-034). Questions about judges go here.

| Q | Status | Question | Why it matters | Default if you do not know | WP type | Scope |
|---|---|---|---|---|---|---|
| Q60 | Deferred - revisit later | Who enters judges' scores, and how are judges' identities and scores kept private and consistent without a portal? Judging format (offline panel meeting, scoring sheets, emailed forms)? | Needed before judging opens; judges' conflict declarations | Judges score on a sheet or in a meeting; an Editor enters scores and a second staff member checks the entry; sheets are kept. | Custom plugin | SITE |

## Brand name questions (brief v2, answered 2026-11-09, D-052)

From `docs/archive/change-review-2026-11-07.md`.

| Q | Status | Question | Why it matters | Decision / default | WP type | Scope |
|---|---|---|---|---|---|---|
| Q40 | Answered | Is the brand name WHOISWHO (D-001) or WIWI (brief v2)? | Names, copy, legal notices, assets | **WHOISWHO.** | Theme | BOTH |
| Q41 | Answered | If WIWI: is "WHOISWHO" retired, and what is the edition called? | Edition name | **Not applicable.** WHOISWHO stays; edition = "WHOISWHO in Africa: The Maiden Edition" (D-001). | Theme | BOTH |
| Q42 | Answered | Which is current in the brief: the text (WIWI) or the master-brand image (WHOISWHO)? | The brief disagrees with itself | **`docs/brief/wiwi-venture-brief.md` is the most current brief** and is now `docs/spec.md`. Its scope, numbers and dates apply; its "WIWI" product name is read as WHOISWHO. | Process | BOTH |
| Q43 | Answered | Which name does Legal use in Terms, Privacy and commercial terms? | Terms and Privacy (REQ-100) | **whoiswhoafrica.** Held as a brand config value. Clearance of the name stays a Legal item (skipped for now, D-051). | Process | SITE |
| Q44 | Answered | Show the pronunciation "We We Africa" on About? | Name clarity | **No.** The pronunciation belonged to WIWI; the brand is WHOISWHO. Default reversed because Q40 settled the other way. | Theme | SITE |
| Q45 | Answered | What is the crawler called and what does it announce? | User agent | **No crawler name is needed** (D-052). The "WHOISWHO-bot" identity and info page are dropped from the acquisition plan; robots.txt and source terms are still obeyed (REQ-138). | Process | ACQUISITION |

## WordPress questions (raised 2026-11-09, D-053)

What building on WordPress raises. Each has a recommended default; the status is Open until you answer.

| Q | Status | Question | Why it matters | Default if you do not know | WP type | Scope |
|---|---|---|---|---|---|---|
| Q81 | Answered 2026-11-10 (D-060) | Which WordPress plugins are allowed, and what is the vetting rule? Is there a cap on the number of plugins? **Answer: Custom plugins are built where needed and public plugins are fine where needed. No cap on the number. Each is still recorded in the dependency register (REQ-065) and the Administrator approves additions.** | Every plugin is code we do not control: security, updates and slowdowns. | A plugin is allowed only if it is actively maintained (updated within 12 months, tested with the current WordPress), widely used or from a known vendor, and does one job we would otherwise build. Cap of 15 plugins in total; each is recorded in the processor and dependency register (REQ-065). No page builders. The Administrator approves each addition. | Native+config | SITE |
| Q82 | Answered 2026-11-10 (D-060) | 10,000+ listings: what keeps lists, search and sitemaps fast and indexable? **Answer: A cache plugin is enough unless measured speed says otherwise. Profiles stay a custom post type with paginated lists, split sitemaps, only public profiles listed.** | Profiles will grow beyond 1,000; slow lists hurt users and indexing. | Profiles are a custom post type with paginated lists. A small lookup table for state, type, country, source and batch is added only if a measured list query is slow. Use an object cache if the host offers one. Split sitemaps by type and size (core sitemaps already page at 2,000 URLs; adjust), and only reviewed or labelled profiles appear (REQ-208, 217). | Custom plugin | SITE |
| Q83 | Answered 2026-11-10 (D-060) | Backup policy: how often, how long kept, and who tests a restore? **Answer: Backups are set up in cPanel. Adding them to the launch checklist (not done until they run and a restore is tested).** | A bad import or update must be reversible. | Daily database and weekly file backups from cPanel (plus a backup plugin if cPanel cannot), kept 14 daily and 8 weekly, one off-server copy downloaded weekly. A restore is tested before go-live and every quarter. Owner: the Administrator. | Process | SITE |
| Q84 | Answered 2026-11-10 (D-060) | Update policy for WordPress core, plugins and themes: when, and who applies them? **Answer: Custom plugins are reviewed every week. Other updates follow the recommended policy: try locally, apply weekly, security fixes within 48 hours.** | Unpatched plugins are the main source of WordPress breaches. | Core minor and security releases update automatically. Other updates are tried on the local copy first and applied by the Administrator weekly. Security fixes within 48 hours. Major core updates after a local test. | Process | SITE |
| Q85 | Answered 2026-11-10 (D-060) | Security hardening for the live site: which settings, and what rate limits? **Answer: Recommended hardening accepted.** | wp-admin is the one public login surface (REQ-047, 122). | Set `DISALLOW_FILE_EDIT`, turn XML-RPC off, keep registration off, enforce two-factor for every staff role, limit login attempts, keep only the REST routes staff need, and add a firewall (Cloudflare free tier or a security plugin) at go-live. Changing the login URL is optional. | Native+config | SITE |
| Q86 | Answered 2026-11-10 (D-060) | Per-user capability grants (D-046): a vetted role-editor plugin, or a small custom screen limited to `wiw_*` capabilities? **Answer: A maintained role-editor plugin.** | WordPress stores per-user capabilities but has no core screen for them; the non-grantable list (Q72) must be enforced. | A small custom screen limited to `wiw_*` capabilities, so the non-grantable list is enforced and every grant is audited. A role-editor plugin is the fallback. | Native+config | SITE |
| Q87 | Open | What are the limits of the live cPanel host (PHP version, memory, execution time, cron, inodes, database size, backups), and who checks them? | The site is built locally and imported (D-050); the host may not fit. | Check at import time and record in the runbook. Needed: a current PHP version, at least 256 MB memory, a working cron, enough inodes and disk for the media library. If the host fails, move to a small VPS. | Process | SITE |
| Q88 | Answered 2026-11-10 (D-060) | Use a page builder, or a block theme with the block editor? **Answer: Recommended: no page builder, a custom block theme. Page copy is edited in the block editor, so there is no conflict with Q98.** | Page builders add weight, lock-in and security surface. | No page builder. A custom block theme with design tokens (REQ-052, 190). | Theme | SITE |
| Q89 | Answered 2026-11-10 (D-060) | Multisite or a single WordPress site? **Answer: A single site.** | Multisite adds roles, plugins and hosting complexity. | A single site. | Native | SITE |
| Q90 | Answered 2026-11-10 (D-060) | How does content move from the local build to the live site, and what happens after go-live? **Answer: Recommended: one-way migration to a staging copy, then switch. After go-live the live site is never overwritten.** | Importing over live data would lose edits (D-050). | One-way: export the database and files with WP-CLI or a migration plugin (for example WP Migrate) to a staging copy on the host, check it, then switch. After go-live the live site is the source of truth and is never overwritten; later content comes through the import file (REQ-237). | Process | SITE |
| Q91 | Answered 2026-11-10 (D-060) | Search: core WordPress search with filters, or a relevance plugin or search service? **Answer: Recommended: core search with filters at launch.** | Directory search is the first action (design-inspiration section 3). | Core search with taxonomy and field filters at launch. Add a relevance plugin or external search only if measured results are poor. | Native+config | SITE |
| Q92 | Answered 2026-11-10 (D-060) | What counts as enough evidence for a claim, and who approves it? **Answer: Evidence is checked offline. The website only accepts and processes the claim; the claim form keeps the evidence note and staff record the decision.** | A wrong approval gives the wrong person control of a profile and the Claimed mark (D-057, D-059). | A page on the official website naming the claimant, or a registration number, or a signed letter; the Editor approves; two people for a high-profile organisation. | Custom | SITE |
| Q93 | Answered 2026-11-10 (D-059) | Is the profile's assigned user a new role (Profile manager) or an existing staff user? **Answer: an ordinary staff user. The Administrator creates any extra role and assigns capabilities; no fourth default role.** | D-044 allows only three staff roles; a manager account for a profile owner is a new kind of account (D-057 amends D-034). | A fourth, limited role, Profile manager, with a capability to edit only assigned profiles and no access to anything else; created only by the Administrator. | Custom | SITE |
| Q94 | Answered 2026-11-10 (D-059) | Do the assigned user's edits publish straight away or after review? **Answer: after review. A Reviewer or Administrator approves every edit to a claimed profile.** | Straight publishing would break the review rule (REQ-222, 226). | After review, in the same queue as other edits. | Custom | SITE |
| Q95 | Answered 2026-11-10 (D-060) | Which tool removes photo backgrounds on import: a self-hosted model or a paid service? **Answer: Withdrawn. There is no background-removal plugin and no cut-out job. Photographs are used as supplied.** | Photos of real people are personal data; a paid service is a new processor and cost (D-057, REQ-255). | A self-hosted tool inside the plugin, run as a background job; a paid service only after Legal adds it to the processor list. Keep the original and allow a switch back. | Plugin | SITE |
| Q96 | Answered 2026-11-10 (D-060) | The logo reads "wiwi Africa" but the site text says WHOISWHO (D-052). Which is the public name? **Answer: WHOISWHO is the public name. The logo's wordmark is the shortened form of it, so no change is needed.** | A logo and a name that differ confuse visitors and search results (D-058). | One name everywhere. If the logo is final, use "wiwi Africa" in text too; if WHOISWHO stays, the logo needs a new wordmark. | Theme | SITE |
| Q97 | Answered 2026-11-10 (D-060) | Story comments: who moderates, how long do they stay open, and how long are they kept? **Answer: WordPress default comment moderation.** | Comments are personal data and a defamation risk (D-059, REQ-258). | Editors moderate, comments close 90 days after publication, comments are kept while the story is public and erased with the commenter's data request, no comments on Sponsored stories until Legal agrees. | Plugin | SITE |
| Q98 | Answered 2026-11-10 (D-060) | Which page editor edits the page copy: Elementor or the native block editor? **Answer: Whichever works best: the native block editor with theme patterns.** | Elementor adds a licence, weight and a plugin to keep updated; the block editor is built in and works with theme patterns (D-059, REQ-260). | The native block editor with theme block patterns; use Elementor only if the team already knows it and accepts the cost. | Theme | SITE |
| Q99 | Answered 2026-11-10 (D-060) | Are the Freepik photos, the hero videos and the Repline font licensed for the live site? **Answer: The assets are licensed. The photographs in the prototype are sample photos only.** | They were added to the prototype without a confirmed licence; using unlicensed assets on a public site is a legal and takedown risk. | Brand confirms the licence for each asset before launch, or we replace it with licensed or commissioned material. | Theme | SITE |
| Q100 | Answered 2026-11-10 (D-060) | Which edition years do the sample winners and categories show (2026 to 2028)? **Answer: Not needed as a decision: every year on the sample pages is a placeholder, and Awards enters the real edition in the admin.** | The winners page, the nominees page and the share previews print the year. | Use the real edition once Awards confirms it; until then label every year as sample. | Theme | SITE |
| Q101 | Answered 2026-11-10 (D-060) | Which colour is the primary brand colour: the orange or another? **Answer: Orange is the primary colour.** | The brand guide shows several colours; buttons, links and focus states all follow the primary. | Orange for calls to action, blue for structure, as in the prototype, until Brand confirms. | Theme | SITE |
| Q102 | Open | How does an approved claimant get access to edit their profile, and what can they change? | Claiming is only useful if the owner can keep the profile current, and there are no public accounts (D-060, REQ-261). | Staff create an ordinary wp-admin user for the claimant with a limited Profile manager role, two-factor enforced. The user sees only their assigned profiles, can change descriptive fields, links and photograph, cannot change status, slug or arrangements, and every edit goes to review (REQ-253). | Custom | SITE |

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
| **Pay-to-win perception** | Brand collapse. | Status separation, no data path from commercial records to awards (REQ-037, 184, 199, 201). |
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
