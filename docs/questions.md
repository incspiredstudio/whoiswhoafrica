# WHOISWHO — Contradictions, Questions and Risks

Companion to `docs/requirements.md`. Written 2026-11-06. Software is built by parallel Claude Code agents with one human supervisor, so build time is not treated as a constraint; the spec's dates are business targets. Only external lead times and your decisions are tracked here.
Reply format: **"Accept all defaults except Q2: …, Q5: …"**

---

## Contradictions and gaps in the spec

| # | Where | Problem | Proposed resolution |
|---|---|---|---|
| C1 | §2.1 vs §1 / title | Name is "WHOISWHO", "WHOISWHOINAFRICA.COM" and "WHOISWHO IN AFRICA". | Brand = WHOISWHO; domain whoiswhoinafrica.com; edition = "WHOISWHO in Africa: The Maiden Edition". |
| C2 | §1 "not simply a site where people upload profiles" vs §5.1, §8 P1–P2 | The only built flow is self-submitted, paid profiles. The spec also wants a populated directory by 31 Jan but 10 paying subscribers cannot populate it. | Resolved by the data-acquisition decisions (Q1–Q6). |
| C3 | §3.4 "paid ≠ verified" vs §6.1 "Verification processes" as a paid benefit | Selling verification looks like buying credibility. | Subscription pays for a verification *review*, never the outcome; status is independent of payment (Q11). |
| C4 | §6.1 "Featured directory placement" vs §3.4 and the "Featured" rung of the status ladder | Featured is either paid (then it is advertising) or earned (then it cannot be a product benefit). | Featured = editorial only; paid placement = "Sponsored" label (Q11). |
| C5 | §8 P1 deliverables (site, payments, templates, directory) vs P1 exit "brand direction approved by 20 Nov" | Brand assets arrive on the same day the site is expected to look finished. | Build to a provisional design-token theme; apply approved assets when delivered (REQ-052, Q13). |
| C6 | §8 P1 "Establish payment" vs P2 sales start 21 Nov | Payments must be *live*, not just designed, on day 1 of selling. Merchant-account approval is an external lead time. | REQ-099; start gateway onboarding now (Q9). |
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
| Q1 | BLOCKING | How many organisation and individual profiles must exist, by what date, and for what purpose (sales demos vs public launch)? | Sets the import volume, the human review workload, and what "directory populated" (REQ-104) means. | (a) demo only (b) public launch (c) both, different volumes | **Both.** Demos by 20 Nov: 25 organisation + 10 consented individual profiles on a non-public preview. Public launch by 31 Jan: ≥ 100 organisations, ≥ 10 paid, ≥ 30 consented individuals. |
| Q2 | BLOCKING | Are public "unclaimed" individual stubs (approach C) in the MVP, or deferred? | Changes the data model (provenance, suppression, status ladder), legal exposure and review load. | (a) MVP (b) defer; use consent-only individuals (c) private-link variant: profile pre-built but visible only to the person until they accept | **(b) Defer.** Organisation stubs in MVP; individuals only via paid, complimentary-with-written-consent, or accepted private-link. Revisit after Legal review. |
| Q3 | BLOCKING | Which source types are acceptable? | Determines ingestion tooling and what the reviewers can rely on. | (a) registries, regulators, exchange, Wikidata, licensed APIs, official sites (b) add news sites (c) add social platforms | **(a) only**, subject to per-source approval (REQ-137). Never LinkedIn or other social platforms; never news article text or photos. |
| Q4 | BLOCKING | Who reviews acquired and submitted profiles, and how many per week? | Review is the throughput limit and the main quality control. | (a) Venture team (b) Incspired shared services (c) contract editors | **Two named reviewers** at about 40 profiles/week each. 100% review of individuals and anything AI-assisted; organisation stubs 100% for the first 50, then 25% sampling while error rate stays under 2%. |
| Q5 | BLOCKING | Will Legal sign off the basis for publishing personal data (NDPA 2023), including notifying people and the DPIA/DPO duties? Who owns this and by when? | Without it, no individual can be published without consent. Wrong assumptions mean a rewrite of consent flows and takedown handling. | (a) consent only (b) legitimate interests with objection right (c) mixed by category | **(a) Consent only** for MVP. Legal gives a written position before any stub work (C) starts. Owner: Incspired Legal, requested by 13 Nov. |
| Q6 | BLOCKING | Is AI drafting (approach D) allowed in the MVP? | Adds a vendor (data transfer), claim-to-source engine and review UI. | (a) no (b) organisations only, reviewer-assist (c) individuals too | **(a) No** for MVP. Human-written profiles. Revisit in February with (b). |
| Q7 | BLOCKING | Is there a free tier, and what exactly does each paid tier add (including corporate multi-profile)? | Defines the data model, permissions and the reason anyone pays. Spec implies profiles are paid but also wants free "complimentary" listings. | (a) no free tier (b) free claimed basic listing + paid enhanced (c) all listings free, paid = features | **(b).** Free claimed basic listing for organisations; individuals free only after consent. Paid adds enhanced layout, media, enquiries, review priority, analytics later. |
| Q8 | BLOCKING | What are the packages, prices (NGN), billing period and VAT treatment? | Price × 10 vs NGN 5m decides what "success" means, and drives payment and invoicing logic. | Any | **Annual only, NGN, 3 tiers (Individual, Business, Institution) plus a custom corporate quote.** Your approval needed by 18 Nov. Plans stored as configuration. |
| Q9 | BLOCKING | Which payment processor and methods, and who owns the merchant account? | Merchant approval is an external lead time (typically days to weeks; confirm with the provider) and gates live selling from 21 Nov. | Paystack, Flutterwave, Stripe, bank transfer only | **Paystack (card + transfer) plus manual bank-transfer confirmation.** Start merchant onboarding this week; fallback is manual invoices until live. |
| Q10 | BLOCKING | Refund, cancellation, renewal and expiry policy? | Determines subscription states, emails and admin actions. | Refundable/not; auto-renew/manual | **14-day refund if not yet published; none after publication except for platform fault. Manual renewal with reminders at 30 and 7 days. On expiry, drop to a free basic listing, do not delete.** |
| Q11 | BLOCKING | Exact meaning of Unclaimed / Claimed / Verified / Featured / Sponsored, and whether Featured or Verified can be bought. | Core to the credibility promise (§3.4); a wrong guess forces data-model and UI change. | See C3, C4 | **Verified = checklist, independent of payment. Featured = editorial only. Sponsored = paid, labelled, orthogonal.** |
| Q12 | BLOCKING | How much of the awards process is built in software by 31 Jan? | Judging needs scoring criteria and conflict-of-interest rules from you that do not exist yet. | (a) nomination form + admin list only (b) plus judging module (c) none, use external forms | **(a).** Judging and announcement run outside the platform for the maiden edition. |
| Q13 | BLOCKING | Brand assets (logo, identity, typography, colours, photography direction): who delivers them and when? May the build proceed with a provisional theme? | Pages can be built on tokens, but the live launch needs approved assets. | (a) provisional theme now, swap on delivery (b) wait for assets | **(a).** Agents build on a neutral provisional theme; Brand & Creative delivers assets by 20 Nov; you approve before public launch. |
| Q14 | BLOCKING | Who owns and provisions the external accounts: domain registrar and DNS for whoiswhoinafrica.com, hosting, transactional email, payment gateway, error monitoring, code repository secrets? Where may data be hosted? | Agents cannot create accounts or hold secrets; DNS and email authentication take time to propagate; hosting location affects NDPA transfer analysis. | You / Incspired IT; region Africa / EU | **You or Incspired IT create the accounts by 13 Nov and put secrets in the host's secret store (never in the repo). Data hosted in an African or EU region.** |
| Q15 | BLOCKING | Can third parties nominate people without their consent, and what is shown before consent? | Nominee data is personal data; public display of non-consenting nominees raises defamation and NDPA exposure. | Open nominations / consent-first | **Nominations private; nominee is contacted for consent before any public display; only consented shortlisted nominees appear.** |

### ASSUMABLE

| Q | Type | Question | Why it matters | Options | Recommended default |
|---|---|---|---|---|---|
| Q16 | ASSUMABLE | Does CaromHQ have an API? How are prospects imported/exported (approach B tooling)? | Avoids an integration build. | API / CSV | CSV import/export only in MVP; no scraping code in the product. |
| Q17 | ASSUMABLE | Can one organisation have several users? | Roles and permissions. | Single login / owner + editors | Owner + up to 3 editors per organisation; one login per individual. |
| Q18 | ASSUMABLE | How are claims verified and abuse handled? | Impersonation is the main fraud route. | Email domain / documents / call-back | Organisation: email at its domain or document; individual: document + manual approval; one report button on every profile. |
| Q19 | ASSUMABLE | Is the sprint target met by 10 subscriptions *or* NGN 5m? | Reporting and incentives. | Either / both | Report both; 10 paid subscriptions is the gate; revenue shown alongside. |
| Q20 | ASSUMABLE | Brand string confirmed (see C1)? | Copy, SEO, email sender names. | See C1 | Brand WHOISWHO; domain whoiswhoinafrica.com. |
| Q21 | ASSUMABLE | Data retention and deletion periods? | NDPA compliance, storage. | — | Subscriber data 6 years after last payment (tax), nominations 2 years, removed-person suppression record kept indefinitely (name only). |
| Q22 | ASSUMABLE | Who owns uploaded photos and text? | Copyright. | Licence / warranty | Owner-uploaded only; owner grants licence and warrants rights; no scraped photos. |
| Q23 | ASSUMABLE | Are profile analytics needed in MVP? | Scope. | Yes/no | View count only; richer analytics later. |
| Q24 | ASSUMABLE | Languages and countries at launch? | Content and compliance scope. | — | English, Nigeria only; country field kept for later. |
| Q25 | ASSUMABLE | Are event tickets, tables and sponsor invoices handled in the platform? | Avoids a commerce build. | Yes/no | No. Manual invoices and external ticketing. |

Counts: 25 questions — 15 BLOCKING, 10 ASSUMABLE.

---

## External lead times and approvals

Build time is not the constraint. These items are, because they depend on third parties or on you. Durations are typical, not confirmed.

| Item | Owner | Needed by | Gates | Your action |
|---|---|---|---|---|
| Payment merchant approval (KYC, business documents) | You / Finance | 20 Nov (live selling 21 Nov, REQ-099) | Real payments (Q9) | Choose provider, open the account, share test keys via the secret store |
| Domain, DNS, email authentication (SPF, DKIM, DMARC) | You / Incspired IT | 13 Nov | Staging URL, transactional email (Q14) | Create accounts and DNS records |
| Legal sign-off: Terms, Privacy, subscription terms; NDPA position on personal data | Incspired Legal | Terms/Privacy by 20 Nov (REQ-100); NDPA position by 13 Nov (Q5) | Collecting any customer data; any individual publication | Request, chase, approve |
| Brand assets | Brand & Creative | 20 Nov (Q13) | Public launch look | Approve |
| Package and price decisions | You | 18 Nov (Q8) | Plans page, checkout | Decide |
| Award categories, eligibility and judging rules | You | Before nominations open (REQ-106) | Nominate page, awards pages | Approve (N8) |
| Reviewers named and available | You / Editorial | Before first import (Q4) | Any profile going public | Name two reviewers |
| Source approvals (terms, robots, licence per source) | Editorial + Legal | Before each import (REQ-137) | Data acquisition | Approve sources |
| Seed content and photos | Subscribers / Marketing | Per profile | Profile completeness | — |

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
| **Human review capacity** (agents can draft quickly; only humans approve) | Backlog of unpublished profiles. | Q4 sizing and throughput tracking (REQ-149). |
| **Merchant approval delay** | No live payments on 21 Nov. | Start onboarding now; manual bank-transfer invoices as fallback (Q9, REQ-074). |

---

## Top 3 risks to the idea

1. **Legal and reputational exposure from publishing third-party information** (NDPA, defamation, wrong profiles). It is the one risk that can hurt people and the brand at the same time; consent-first individuals and organisation-only stubs contain it.
2. **Credibility and conversion.** The product sells recognition, so any hint of pay-to-win, or an empty directory at launch, undermines the 10-subscription target. The target depends on a sales funnel with no price, no proof and no data yet.
3. **External approvals gate go-live.** Merchant approval, legal sign-off, brand assets, reviewer availability and your pricing decisions are not speed-up-able by building faster. The table above lists each with a needed-by date; the earliest ones (accounts, merchant application, NDPA position) should start this week.
