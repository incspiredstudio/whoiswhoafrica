# WHOISWHO — Contradictions, Questions and Risks

Companion to `docs/requirements.md`. Written 2026-11-06 (day 3 of the sprint; Phase 1 ends in 14 days).
Reply format: **"Accept all defaults except Q2: …, Q5: …"**

---

## Contradictions and gaps in the spec

| # | Where | Problem | Proposed resolution |
|---|---|---|---|
| C1 | §2.1 vs §1 / title | Name is "WHOISWHO", "WHOISWHOINAFRICA.COM" and "WHOISWHO IN AFRICA". | Brand = WHOISWHO; domain whoiswhoinafrica.com; edition = "WHOISWHO in Africa: The Maiden Edition". |
| C2 | §1 "not simply a site where people upload profiles" vs §5.1, §8 P1–P2 | The only built flow is self-submitted, paid profiles. The spec also wants a populated directory by 31 Jan but 10 paying subscribers cannot populate it. | Resolved by the data-acquisition decisions (Q1–Q6). |
| C3 | §3.4 "paid ≠ verified" vs §6.1 "Verification processes" as a paid benefit | Selling verification looks like buying credibility. | Subscription pays for a verification *review*, never the outcome; status is independent of payment (Q11). |
| C4 | §6.1 "Featured directory placement" vs §3.4 and the "Featured" rung of the status ladder | Featured is either paid (then it is advertising) or earned (then it cannot be a product benefit). | Featured = editorial only; paid placement = "Sponsored" label (Q11). |
| C5 | §8 P1 deliverables (site, payments, templates, directory) vs P1 exit "brand direction approved by 20 Nov" | Build runs in parallel with the brand it must implement. Design tokens (REQ-052) can absorb this, but not layout rework. | Build to tokens; accept visual re-skin after 20 Nov (Q13). |
| C6 | §8 P1 "Establish payment" vs P2 sales start 21 Nov | Payments must be *live*, not just designed, on day 1 of selling. Merchant-account KYC is a lead-time item. | REQ-099; start gateway onboarding now (Q9). |
| C7 | §8 P3 exit "Website and subscription journey operational" vs P2 "Onboard initial subscribers, publish profiles" | Subscribers are expected to be onboarded a month before the site is declared operational. | Treat the P1 build as the real MVP; P3 is hardening. |
| C8 | §8–9 target "10 subscriptions **or** NGN 5m" | 10 subscriptions at NGN 5m total implies about NGN 500k each; if packages are cheaper, the two targets diverge. No price exists. | Report both; CEO declares which is the gate (Q8, Q19). |
| C9 | §12.2 "Nominate" in the initial site vs §8 P3 "Establish the nomination and judging process" | A public nomination form launches before the rules it depends on exist. | Launch nomination form only after N8 rules are published (REQ-106). |
| C10 | §7.2 pan-African ambition vs "Nigeria first"; §12.2 Country filter | Scope of countries, regulators and currencies is open. | Nigeria only for MVP; country field exists for later (Q24). |
| C11 | §5.1 individual profiles "contact options" vs privacy | Contact data of notable people is a harassment and data risk. | Enquiry relay only; no public email/phone by default (REQ-018, REQ-060). |
| C12 | §15.5 CaromHQ record-keeping vs §13.2 shared platform services | No interface to CaromHQ is described. | CSV exchange first (Q16). |
| C13 | §9.3 counts only paid, registered, onboarded subscriptions vs §5.1 premium tiers not yet priced | The register's states cannot be finalised without packages. | Q8. |

---

## Questions

### BLOCKING

| Q | Type | Question | Why it matters | Options | Recommended default |
|---|---|---|---|---|---|
| Q1 | BLOCKING | How many organisation and individual profiles must exist, by what date, and for what purpose (sales demos vs public launch)? | Sets import tooling, review staffing, and whether the "directory populated" exit criterion (REQ-104) is realistic. | (a) demo only (b) public launch (c) both, different volumes | **Both.** Demos by 20 Nov: 25 organisation + 10 consented individual profiles on a non-public preview. Public launch by 31 Jan: ≥ 100 organisations, ≥ 10 paid, ≥ 30 consented individuals. |
| Q2 | BLOCKING | Are public "unclaimed" individual stubs (approach C) in the MVP, or deferred? | Changes the data model (provenance, suppression, status ladder), legal exposure and review load. | (a) MVP (b) defer; use consent-only individuals (c) private-link variant: profile pre-built but visible only to the person until they accept | **(b) Defer.** Organisation stubs in MVP; individuals only via paid, complimentary-with-written-consent, or accepted private-link. Revisit after Legal review. |
| Q3 | BLOCKING | Which source types are acceptable? | Determines ingestion tooling and what the reviewers can rely on. | (a) registries, regulators, exchange, Wikidata, licensed APIs, official sites (b) add news sites (c) add social platforms | **(a) only**, subject to per-source approval (REQ-137). Never LinkedIn or other social platforms; never news article text or photos. |
| Q4 | BLOCKING | Who reviews acquired and submitted profiles, and how many per week? | Review is the throughput limit and the main quality control. | (a) Venture team (b) Incspired shared services (c) contract editors | **Two named reviewers** at about 40 profiles/week each. 100% review of individuals and anything AI-assisted; organisation stubs 100% for the first 50, then 25% sampling while error rate stays under 2%. |
| Q5 | BLOCKING | Will Legal sign off the basis for publishing personal data (NDPA 2023), including notifying people and the DPIA/DPO duties? Who owns this and by when? | Without it, no individual can be published without consent. Wrong assumptions mean a rewrite of consent flows and takedown handling. | (a) consent only (b) legitimate interests with objection right (c) mixed by category | **(a) Consent only** for MVP. Legal gives a written position before any stub work (C) starts. Owner: Incspired Legal, requested by 13 Nov. |
| Q6 | BLOCKING | Is AI drafting (approach D) allowed in the MVP? | Adds a vendor (data transfer), claim-to-source engine and review UI. | (a) no (b) organisations only, reviewer-assist (c) individuals too | **(a) No** for MVP. Human-written profiles. Revisit in February with (b). |
| Q7 | BLOCKING | Is there a free tier, and what exactly does each paid tier add (including corporate multi-profile)? | Defines the data model, permissions and the reason anyone pays. Spec implies profiles are paid but also wants free "complimentary" listings. | (a) no free tier (b) free claimed basic listing + paid enhanced (c) all listings free, paid = features | **(b).** Free claimed basic listing for organisations; individuals free only after consent. Paid adds enhanced layout, media, enquiries, review priority, analytics later. |
| Q8 | BLOCKING | What are the packages, prices (NGN), billing period and VAT treatment? | Price × 10 vs NGN 5m decides what "success" means, and drives payment and invoicing logic. | Any | **Annual only, NGN, 3 tiers (Individual, Business, Institution) plus a custom corporate quote.** CEO sets prices by 18 Nov. Plans stored as configuration. |
| Q9 | BLOCKING | Which payment processor and methods, and who owns the merchant account? | KYC and onboarding take days to weeks and gate day-one selling (21 Nov). | Paystack, Flutterwave, Stripe, bank transfer only | **Paystack (card + transfer) plus manual bank-transfer confirmation.** Start merchant onboarding this week; fallback is manual invoices until live. |
| Q10 | BLOCKING | Refund, cancellation, renewal and expiry policy? | Determines subscription states, emails and admin actions. | Refundable/not; auto-renew/manual | **14-day refund if not yet published; none after publication except for platform fault. Manual renewal with reminders at 30 and 7 days. On expiry, drop to a free basic listing, do not delete.** |
| Q11 | BLOCKING | Exact meaning of Unclaimed / Claimed / Verified / Featured / Sponsored, and whether Featured or Verified can be bought. | Core to the credibility promise (§3.4); a wrong guess forces data-model and UI change. | See C3, C4 | **Verified = checklist, independent of payment. Featured = editorial only. Sponsored = paid, labelled, orthogonal.** |
| Q12 | BLOCKING | How much of the awards process is built in software by 31 Jan? | Nominations are cheap; judging with scoring and COI controls is a sub-product. | (a) nomination form + admin list only (b) plus judging module (c) none, use external forms | **(a).** Judging and announcement run outside the platform for the maiden edition. |
| Q13 | BLOCKING | What must exist on 20 Nov and 21 Nov? Is a thin "Get Featured" page with live payment and intake form (unbranded or provisionally branded) acceptable as "product ready"? | The spec's P1 product list is not achievable in full in 14 days with a build team of unknown size. | (a) thin slice 21 Nov, full by 15 Dec (b) wait for full build | **(a).** 21 Nov: pricing, checkout, intake, confirmation. 15 Dec: profiles, directory, review queue. 31 Jan: search polish, stories, nominations. |
| Q14 | BLOCKING | Who builds it, with how many people, on what stack and hosting, and where may data live? | Sets architecture, schedule realism and NDPA transfer analysis. | Incspired shared team vs external; managed platform vs custom | **1–2 engineers + Incspired design; TypeScript web app with managed Postgres, hosted in an African or EU region; managed auth and email.** |
| Q15 | BLOCKING | Can third parties nominate people without their consent, and what is shown before consent? | Nominee data is personal data; public display of non-consenting nominees raises defamation and NDPA exposure. | Open nominations / consent-first | **Nominations private; nominee is contacted for consent before any public display; only consented shortlisted nominees appear.** |

### ASSUMABLE

| Q | Type | Question | Why it matters | Options | Recommended default |
|---|---|---|---|---|---|
| Q16 | ASSUMABLE | Does CaromHQ have an API? How are prospects imported/exported (approach B tooling)? | Avoids an integration build. | API / CSV | CSV import/export only in MVP; no scraping code in the product. |
| Q17 | ASSUMABLE | Can one organisation have several users? | Roles and permissions. | Single login / owner + editors | Owner + up to 3 editors per organisation; one login per individual. |
| Q18 | ASSUMABLE | How are claims verified and abuse handled? | Impersonation is the main fraud route. | Email domain / documents / call-back | Organisation: email at its domain or document; individual: document + manual approval; one report button on every profile. |
| Q19 | ASSUMABLE | Is the sprint target met by 10 subscriptions *or* NGN 5m? | Reporting and incentives. | Either / both | Report both; 10 paid subscriptions is the gate; revenue shown alongside. |
| Q20 | ASSUMABLE | Domain and brand string confirmed? | Domain, email, SEO. | See C1 | whoiswhoinafrica.com; brand WHOISWHO. |
| Q21 | ASSUMABLE | Data retention and deletion periods? | NDPA compliance, storage. | — | Subscriber data 6 years after last payment (tax), nominations 2 years, removed-person suppression record kept indefinitely (name only). |
| Q22 | ASSUMABLE | Who owns uploaded photos and text? | Copyright. | Licence / warranty | Owner-uploaded only; owner grants licence and warrants rights; no scraped photos. |
| Q23 | ASSUMABLE | Are profile analytics needed in MVP? | Scope. | Yes/no | View count only; richer analytics later. |
| Q24 | ASSUMABLE | Languages and countries at launch? | Content and compliance scope. | — | English, Nigeria only; country field kept for later. |
| Q25 | ASSUMABLE | Are event tickets, tables and sponsor invoices handled in the platform? | Avoids a commerce build. | Yes/no | No. Manual invoices and external ticketing. |

Counts: 25 questions — 15 BLOCKING, 10 ASSUMABLE.

---

## Is the timeline realistic?

- **20 Nov (Phase 1).** The full product list (site, profile templates, directory structure, subscriptions, payment) is not realistic for a team of unknown size in 14 days, particularly while pricing, brand and the legal pages are open. A thin slice (checkout + intake + confirmation) is realistic (Q13).
- **21 Nov.** Real payments need merchant approval; this is the biggest external lead time.
- **20 Dec.** Reviewing, publishing and onboarding first subscribers is realistic if review capacity exists (Q4).
- **31 Jan.** Directory, stories, nominations and operations are realistic; judging tooling, individual stubs and AI drafting are not worth attempting in this window.
- **Commercial target.** 10 paid by 31 Jan from a 14-day-old product with no price, no testimonial and an empty directory depends on relationships more than software. The funnel in §9.2 (500 → 10, 2%) is an assumption, not data.

---

## Risk register (data acquisition and general)

| Risk | Impact | Mitigation in requirements |
|---|---|---|
| **Nigeria Data Protection Act 2023** — publishing personal data of non-consenting individuals; obligations on lawful basis, transparency, data-subject rights, cross-border transfers, breach notification, possible registration/DPO duties; fines scale with turnover. | Regulatory action, forced takedowns. | Consent-only default (Q2, Q5); REQ-057–059, 065–067, 072; Legal sign-off. |
| **Defamation / false light** — a wrong or negative statement about a named person or company. | Legal claims, reputational harm. | Neutral-content checklist (REQ-163), source-per-field (REQ-142), takedown process (REQ-071, REQ-158). |
| **AI hallucination** — fabricated career facts. | Credibility loss, defamation. | Off in MVP (Q6); if on, claim-to-source mapping and human review (REQ-166–168). |
| **Platform terms of service** — LinkedIn and others forbid scraping; contract and account bans. | Legal, and loss of sales channel. | Source allowlist, no social platforms (REQ-138). |
| **Reputational harm if profiles are wrong or stale** | Subjects and press turn against brand. | Review gate, "last reviewed" date, refresh cycle, corrections log (REQ-148, 144, 160, 164). |
| **Copyright** — photos and prose copied from sources. | Takedown, claims. | No third-party photos or prose (REQ-162). |
| **Pay-to-win perception** | Brand collapse. | Separation of status, sponsored and awards; judge isolation (REQ-024, 037, 155). |
| **Empty-directory problem** | Low conversion of buyers. | Organisation stubs (A) plus consented individuals (Q1). |
| **Single-point-of-failure review capacity** | Backlog, late publication. | Q4 sizing and throughput tracking (REQ-149). |
| **Payment onboarding delay** | No sales on 21 Nov. | Start KYC now; manual invoice fallback (Q9). |

---

## Top 3 risks to the idea

1. **Legal and reputational exposure from publishing third-party information** (NDPA, defamation, wrong profiles). It is the one risk that can hurt people and the brand at the same time; consent-first individuals and organisation-only stubs contain it.
2. **Credibility and conversion.** The product sells recognition, so any hint of pay-to-win, or an empty directory at launch, undermines the 10-subscription target. The target depends on a sales funnel with no price, no proof and no data yet.
3. **Schedule.** The spec asks for payments, profiles, directory and brand in 14 days while pricing, the brand, legal terms, merchant account and build team are all unconfirmed. Without a thin-slice decision (Q13), sales on 21 Nov is at risk.
