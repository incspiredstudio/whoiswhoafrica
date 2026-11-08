# WHOISWHO: Acquisition plan for the first 1,000 published profiles

Status: **Proposed v3, 2026-11-08: rewritten for D-033 as amended.** This is a plan only. Nothing has been fetched, scraped or imported. Terms and licences were checked online on 2026-11-07 (section 2.6 lists what was read). This is not legal advice; legal points are for Incspired Legal to confirm.

Relies on decisions D-002, D-010, D-012 to D-017, D-024, D-029, D-031, D-033, D-034 and requirements REQ-132 to REQ-176 and REQ-191 to REQ-238. Codes are explained in `docs/GUIDE.md`.

**What changed in v3.** D-033 is corrected: the pipeline never publishes. AI gathers, does basic verification only, stores in a staging database and exports. Records are imported into WordPress and reviewed there. Organisations go live on import, labelled and noindex; individuals and entrepreneurs are not public until a reviewer approves. Removed: second AI audit, AI publishing, review packs, quick look, cap, automatic pause, continuous AI monitoring and AI takedown (the last two are a future item). Sections 3–8 and 10–12 are rewritten, and 1.1, 1.7 and 2.1 updated. Other affected documents: `docs/change-review-2026-11-08-publish-first.md`.

**D-034 (no public accounts, 2026-11-08).** There are no owner accounts. "Claim" is a request-to-claim form that staff verify and set to Claimed; owner edits are correction requests staff apply; refresh reminders are emails with a link to the form; the requester check in 6.3 uses a verified email link. Tooling T-23 to T-28 and the workload note are in sections 3.1, 6.3 and 7.2. Legal and the staff-role questions are Q56–Q62.

**Still in conflict with recorded decisions (need your ruling, see section 12):**
- D-017, REQ-163 and REQ-168 require human approval of every AI draft before publication. This now affects AI-drafted organisations only (decision 12).
- D-012 and REQ-191 target *reviewed* profiles live (decision 15).
- D-010 and D-025 say Nigeria only. This plan includes about 150 profiles from other African countries, as you asked (decision 1).

---

## 1. Inclusion criteria and the split of the 1,000

### 1.1 How the test works
A record is included only if it meets **one named criterion** (code below) **and** that criterion is backed by **an evidence URL from an approved source**. Both are carried on the record. The reviewer answers one question: "Does the evidence page show this criterion is met, for this exact entity, today?" No rejects with reason code `NOT_NOTABLE` and, if the profile is already live, unpublishes it. Popularity, press coverage, commercial interest and sales-prospect status are never criteria. The criterion code and evidence URL are stored on the record (tooling item T-13).

**Baseline for every record:**
- **It exists and is identifiable.** The official source names the entity unambiguously.
- **It is active or current.** An organisation is operating. An individual holds the role now: REQ-139 allows only a *current* title, so the MVP covers living people in current roles.
- **It is not suppressed** (REQ-147, REQ-207).

### 1.2 Organisations
Include if **any one** of the following is met.

| Code | Criterion | Evidence |
|---|---|---|
| O1 | Equity listed on a recognised African stock exchange | The issuer's own investor-relations page, or Wikidata P414 (stock exchange) plus the issuer page |
| O2 | Holds a licence in a named category from a national regulator. Categories: commercial, merchant and non-interest banks; payment service banks; pension fund administrators; insurers and reinsurers; SEC-registered fund managers and broker-dealers; NCC licence classes for major operators. Not every licensee: small microfinance banks, bureaux de change and individual agents are excluded. | The regulator's published list (CBN, PenCom, NAICOM, SEC, NCC) |
| O3 | Accredited university, or a national research institute | The NUC list, or the institute's enabling page |
| O4 | Startup that meets one of these: disclosed funding of at least US$1m (in an official company or investor announcement); alumnus of a named selective programme (Google for Startups Accelerator Africa, Founders Factory Africa, Africa Prize shortlist); or a portfolio company of a named institutional investor that has operated for 2 years or more | The programme or investor's official page plus the company's own site |
| O5 | Has a Wikidata item with at least one Wikipedia sitelink (an independent notability signal), and passes the existence check | The Wikidata QID |
| O6 | National public institution, multilateral or pan-African body, or a foundation or NGO with national reach that has operated for 3 years or more and publishes annual reports | Official site or annual report |
| O7 | Winner or finalist of an award on the editor's named-award list (kept by the Editor-in-Chief, REQ-174) | The award body's official announcement |

**Excluded in the MVP:**
- Political parties, religious organisations and campaign groups, because of sensitive-data and neutrality risk.
- Organisations the source shows as revoked, struck off or in liquidation. The reviewer rejects with reason `NOT_ACTIVE` and the reason is never published.

### 1.3 Entrepreneurs
Include a founder or co-founder only if both conditions hold:
- **(a)** The founding role is stated on the company's official site or in an official programme or investor announcement.
- **(b)** The company meets O1, O2, O4 or O7, **or** the person personally meets E2.

| Code | Criterion |
|---|---|
| E1 | Founder or co-founder of an organisation that meets O1, O2, O4 or O7 |
| E2 | Personally named winner, fellow or finalist of a programme on the named list: Anzisha Prize, Africa Prize for Engineering Innovation, and any programme the Editor-in-Chief adds |

**Rules:**
- **Editorial lists** such as Forbes Africa 30 Under 30 are **leads only**. A person found there must still meet E1 or E2 from a primary source.
- **Minors:** the profile stores no age (REQ-140). Youth programmes (Anzisha selects ages 15–22) are used only for cohorts **at least 5 years old**.
- **TEF:** the Tony Elumelu Foundation cohort (3,200 per year) is **not** a criterion. It is too broad.

### 1.4 Notable individuals
Include if the person currently holds a role in one of these classes, stated on an official source.

| Code | Criterion | Main source |
|---|---|---|
| I1 | Federal public office: minister, state governor, principal officer of the Senate or House, head of a federal regulator or agency | State House cabinet list, NASS, state government sites, agency sites |
| I2 | Chair, CEO/MD or executive director of an organisation that meets O1, O2, O3 or O6 | That organisation's leadership page or annual report |
| I3 | University vice-chancellor, or fellow of the Nigerian Academy of Science or the Nigerian Academy of Engineering | NUC list plus the university site; academy registers |
| I4 | National honour (OFR and above) or Senior Advocate of Nigeria | Official State House release; LPPC list (the site is currently down) |
| I5 | Head or commissioner of a multilateral or pan-African body | That body's official leadership page |
| I6 | Winner of an award on the named-award list | The award body's announcement |

**Excluded in the MVP:**
- People whose prominence comes from controversy, crime or litigation.
- Party officials, because the role is party-political.
- Religious leaders, because religion is sensitive personal data.
- Private persons, minors, and anyone whose only appearance is on a social platform.
- Deceased persons, because REQ-139 has only a current-title field.

**Never stored for office-holders:** party membership or affiliation. Political affiliation is sensitive personal data under NDPA s.30 and s.65, and s.30 has no "already public" exception.

### 1.5 Disambiguation rule (individuals)
A person is matched only when **name + organisation + role** appear together on one official page. Name similarity alone never matches. Two people with the same name are never merged automatically (REQ-146).

### 1.6 Proposed split of the 1,000

**By type**

| Type | Number | Share | Reasoning |
|---|---|---|---|
| Organisations | 500 | 50% | D-002 says organisations first. The structured sources and low legal risk make them fast and safe to review. They are also the anchor that entrepreneurs and executives hang off (E1, I2). |
| Entrepreneurs | 200 | 20% | Brief §9.1 targets founders. Each founder profile links to an organisation already in the directory, so it is cheaper to source. |
| Notable individuals | 300 | 30% | This is the "who is who" core and the individual-professional sales segment (brief §9.1: 4 of 10). It carries the highest risk and the longest review time, so it is capped below half. |

**By country** (D-010 says Nigeria only; the non-Nigerian share needs your ruling)

| Country | Orgs | Entrepreneurs | Individuals | Total | Reasoning |
|---|---|---|---|---|---|
| Nigeria | 420 | 175 | 255 | **850** | Nigeria first: this is where the market, the sales pipeline and the legal advice are |
| Ghana | 25 | 10 | 10 | 45 | Exchange pages are crawl-friendly. Data controllers must register (Act 843 s.27). |
| Kenya | 20 | 10 | 10 | 40 | Large startup ecosystem. Registration is required above a turnover and staff threshold (DPA 2019 s.18). |
| South Africa | 15 | 5 | 10 | 30 | Pan-African companies. POPIA objection rules apply (s.11(3)). |
| Pan-African bodies | 20 | 0 | 15 | 35 | AU, AfDB, Afreximbank, ECOWAS: few profiles, very notable |
| **Total** | **500** | **200** | **300** | **1,000** | |

Non-Nigerian profiles start no earlier than batch B3 (section 4.3) and only after Legal clears each country (section 6.5). They are sourced mostly from Wikidata and official exchange and institution pages, entered manually.

**Nigerian organisations by subtype (420):** regulated financial institutions 120 (O2), listed companies 80 (O1), universities and institutes 60 (O3), startups 90 (O4), other major companies 30 (O5/O7), public institutions, foundations and NGOs 40 (O6).

**Nigerian individuals by class (255):** corporate leaders 100 (I2), public office-holders 60 (I1), academics 50 (I3), honours, SANs and award winners 45 (I4/I6).

### 1.7 Tiers (used throughout this plan)

| Tier | What it is | Count in the 1,000 | AI drafted? | State on import (3.3) |
|---|---|---|---|---|
| **T1** | Organisation built only from structured registry or regulator fields: name, type, licence category, regulator, city, website, year founded. The text is templated, not AI. No named person. | 200 | No | Live, labelled, noindex |
| **T2** | Organisation with an AI-drafted summary from its official site or annual report. No named person. | 300 | Yes | Live, labelled, noindex (decision 12) |
| **T3** | Entrepreneur or individual, whether AI-drafted or not | 500 | Usually | Not public until a reviewer approves |

---

## 2. Source map

### 2.1 How to read this
- **Status:** No source is approved yet. Under REQ-137, a named person must approve each one. The "Recommendation" column is my proposal for your approval.
- **Verified** = the terms, robots or list page was read on 2026-11-07. **Partly** = part was read, or the rest came from secondary sources. **Unverified** = the page could not be read.
- **Access codes:** M = manual (a person reads the page and enters facts with the URL). P = polite fetch of single pages by an identified agent, honouring robots.txt. A = API. X = export or download file.
- **Effect on the run (decision 21):** M means a person reads the page, so the pipeline cannot gather from it as coded, and typing it in does not fit the 24-hour run. Before H0, agents read each M source's terms and robots rules; if you approve, it is re-coded P, A or X. Otherwise it is left out of the run. Many sources below are M today, including the T1 pilot sources CBN and PenCom.
- **Excluded everywhere:** LinkedIn (User Agreement s.8.2 bans scraping and bots), Facebook (Automated Data Collection Terms), Instagram, X/Twitter (crawling needs prior written consent), any login-walled site, news article text, and third-party photos (D-014, REQ-138).

### 2.2 Organisations: Nigeria

| Source | Gives | Access | Licence and terms | Risk | Checked | Recommendation |
|---|---|---|---|---|---|---|
| Wikidata | QID, name, founded, HQ, website, exchange (P414), founder (P112), CEO (P169) | A (SPARQL) | CC0 for main namespaces. WDQS limits: 60 s timeout, 5 parallel queries per IP, UA policy (`Client/ver (contact) lib/ver`) or a 403. | Low. Data may be stale, so it is cross-checked. | Verified | **Approve for pilot** |
| CBN licensed-institution lists | Bank and other licensee names, licence category | M (the list renders by JavaScript; an Excel export link was seen in search results, unconfirmed) | cbn.gov.ng/Legal.html: copying allowed "provided that CBN is stated expressly as the source"; "not permitted to amend or distort" | Low. Attribute CBN. | Partly | **Approve for pilot** |
| PenCom PFA list | 19 PFAs: name, MD, address, website | M | "© 2026 National Pension Commission"; no terms page | Low. Take org fields and the MD name only, never phone or email. | Verified | **Approve for pilot** |
| NAICOM operator lists | Insurers, brokers | M | "All Rights Reserved"; no terms page | Low–medium | Partly | Approve after the list page is read |
| SEC Nigeria registered operators | Name, category, CAC number, status, directors | M | Terms page exists (1 Dec 2025) but did not render | Medium. Contains personal data. Use org fields and the CAC number for matching only. | Partly | Approve after the terms are read |
| NCC licensee list | Company, licence number, class | M | No footer terms; robots blocks only admin paths | Low. Major classes only. | Verified | Approve |
| NUC university lists | Name, VC, website, year founded (77 federal; state and private lists similar) | M or P | No copyright notice; robots blocks only /wp-admin/ | Low. VC names can be stale, so verify on the university site. | Verified | **Approve for pilot** |
| NGX listed companies | Company, ticker, sector, dates | — | Terms ban "systematic or automated data collection… scraping, data mining" and say content "may not be… aggregated". Licensed reference data is available under a data agreement. | **High** without a licence | Verified | **Do not use the NGX site as a source.** Build O1 from issuers' own IR pages plus Wikidata P414. Ask NGX for a reference-data quote in parallel (decision 9). |
| FMDQ | Membership directory | — | No copying "without the prior written consent of FMDQ Group" | High | Partly | Reject |
| CAC public search | Name, RC number, status, registration date | M by humans only | Bots get HTTP 403. robots.txt disallows AI crawlers by name and sets `ai-train=no`. Secondary sources say the terms ban scraping (CAC's own text not seen). Paid access exists through the CAC–NIBSS super-agent API. | High for agents | Partly | **Reviewers only**, to confirm one RC number at a time. No agent access. |
| GLEIF LEI | Legal name, registration ID, parent links | A or X | CC0. Do not imply GLEIF endorsement; liquidated damages of CHF 100,000 per breach. | Low. Few Nigerian LEIs. | Verified | Approve, for matching only |
| Organisation's own website (about and leadership pages) | Description, leadership, founding facts | P (one or two pages per organisation) | Varies by site. Robots and terms are checked per domain and logged. | Medium | Per site | Approve as a **source class** with a per-domain robots check |
| data.gov.ng | Unknown | — | Site would not load | — | Unverified | Hold |
| Nigeria Startup Act register | Not published | — | — | — | Partly | Not available |
| OpenCorporates | Registry mirror | — | ODbL share-alike. Corporations must buy a licence (£2,250 to £12,000 a year). Nigeria shows no company count. | High cost for low coverage | Verified | Reject |
| Crunchbase, Briter, D&B, Kompass, Smile ID, Prembly | Company data | — | Each forbids republication or limits data to internal use (Briter s.4.3: may not "publicly republish") | High | Verified / Partly | Reject for publication |

### 2.3 Entrepreneurs (Nigeria first)

| Source | Gives | Access | Terms | Risk | Checked | Recommendation |
|---|---|---|---|---|---|---|
| Company's own site (founder or team page) | Founder name and role | P | Per domain | Medium | Per site | **Approve (primary source for E1)** |
| Wikidata P112 (founded by) | Founder link | A | CC0 | Low | Verified | **Approve for pilot** |
| Ventures Platform portfolio | About 80 companies with founder names | M or P | No terms or copyright seen; robots.txt empty | Low–medium | Partly | **Approve for pilot** (as a lead; the founder is confirmed on the company site) |
| Google for Startups Accelerator Africa alumni | About 99 startups, about 38 Nigerian; no founder names | M | robots.txt allows all; Google site terms not read | Low–medium | Partly | Approve after the terms are read |
| Founders Factory Africa, Microtraction, LoftyInc portfolios | Company names | M | "All Rights Reserved"; terms not read | Medium | Partly | Leads only, manual |
| Anzisha Prize fellows | 331 fellows (35 Nigerian): name, country, year, venture | M | No terms or copyright in the footer; robots not checked | Medium (minors, see 1.3) | Verified | Approve for cohorts at least 5 years old |
| Africa Prize for Engineering Innovation | Shortlists (2026: 16 innovators, 2 Nigerian) | M | "© Royal Academy of Engineering"; UK database right may apply | Low | Verified | Approve (one fact per person, cited) |
| Forbes Africa 30 Under 30 | Name, country | — | "All Rights Reserved"; an editorial selection | Medium | Partly | Leads only. Never reproduce the list. |
| Africa: The Big Deal | Deals of US$100k and up | — | Paid (about US$1,500 a year enterprise); publication licence not seen | Medium | Partly | Hold. Licence needed. |
| Y Combinator directory, Techstars, Obama Foundation Leaders | Companies and fellows | — | Terms ban scraping, bots or commercial use | High | Verified | Reject (manual reference only) |

### 2.4 Notable individuals (Nigeria first)

| Source | Gives | Access | Terms | Risk | Checked | Recommendation |
|---|---|---|---|---|---|---|
| Wikidata | Positions held, employer, awards | A | CC0 | Low. Cross-check, because it may be stale. | Verified | **Approve for pilot** |
| State House cabinet list | Ministry, portfolio, minister name (44 named) | M | "© 2026 The State House"; no terms page | Low | Verified | **Approve for pilot** |
| National Assembly members | Name, state, district, party | M | "© 2026 National Assembly"; terms links are dead | Low. **Do not take the party column.** | Partly | Approve. Principal officers only. |
| Governors (Nigeria Governors' Forum) | 36 governors | M | NGF is not a government body; "© 2016… All Rights Reserved" | Low–medium | Verified | Lead only. Confirm on each state government's site. |
| Organisation leadership pages and annual reports | Chairs, CEOs, executive directors | P or M | Per domain. Facts are reusable; bios and photos are not. | Medium | Partly | **Approve (primary source for I2)** |
| NUC lists, then the university site | Vice-chancellors | M | As in 2.2 | Low | Verified | **Approve for pilot** |
| Nigerian Academy of Engineering fellows directory | 140+ fellows | M | "All rights reserved"; terms not read | Low–medium | Partly | Approve after the terms are read |
| Nigerian Academy of Science | Inductions on news pages; a Wikipedia list exists | M | — | Medium | Partly | Lead only. Confirm on an NAS page. |
| National honours | Recipients by round | M | No gazette found online; press "full lists" only | Medium. Press transcription errors. | Partly | Approve only against the official State House release |
| SANs (LPPC) | Annual list | — | lppc.gov.ng had a DNS failure | — | Unverified | Hold |
| Wikipedia | Facts and references | M | CC BY-SA 4.0: prose reuse needs attribution and share-alike | Medium | Verified | **Lead only.** Cite the primary source it references. Never copy prose. |
| NESG summit speakers | Names, roles | — | robots allows; terms not read | — | Partly | Lead only (speaking is not a criterion) |
| NBA find-a-lawyer, COREN register, CIBN, ICAN | Membership | — | Membership is not notability; several sites are unreadable | — | Unverified / Partly | Reject as criteria |
| MDCN register | Doctors | — | HTTP 403; MDCN says it never released the register; health-related | High | Unverified | **Reject** |

### 2.5 Other countries and pan-African bodies

| Source | Gives | Access | Terms / robots | Checked | Recommendation |
|---|---|---|---|---|---|
| Wikidata (all countries) | As above | A | CC0 | Verified | **Approve** (main seed for non-Nigerian profiles) |
| Ghana Stock Exchange | Listed companies | M | robots.txt allows all except wp-admin; `ai-train=no` | Verified (robots) | Approve after the terms are read; facts from issuer pages |
| Ghana Registrar of Companies | Company search | M | GHS 30 per search | Partly | Verification only |
| Nairobi Securities Exchange | Listed companies | — | Fetch failed | Unverified | Hold; use issuer pages and Wikidata |
| Kenya Business Registration Service | Company search | — | Paid per search, login required | Partly | Verification only |
| JSE | Listed companies | M | robots allows all, `ai-train=no`, AI bots blocked; terms returned 403 | Partly | Issuer pages and Wikidata; JSE for manual checks only |
| CIPC (South Africa) | Registry | — | Login required; crawler clause | Partly | Reject |
| EGX, Casablanca Bourse | Listed companies | — | Fetch failed | Unverified | Defer (not in the first 1,000) |
| African Union | Leadership | M | robots allows `/`; the page is **stale** (shows the former Chair) | Partly | Approve with a cross-check |
| AfDB, Afreximbank, ECOWAS | Leadership | M | 403, 404, or page not found | Unverified | Hold until read |

**Images:** Wikimedia Commons is licensed per file (credit the creator). Its reuse page warns that "commercial use of images of people may require the explicit agreement of the subject". Logos are often trademarks. **Proposed default: no photographs on unclaimed individual profiles and no logos in the first 1,000 until Legal advises** (decision 6). This is stricter than D-024 allows.

### 2.6 Research log and open checks
**Read on 2026-11-07:**
- Wikidata licensing and the WDQS manual; the Wikimedia robot policy.
- Wikipedia's reuse page and Commons' reuse page.
- GLEIF terms; OpenCorporates terms and pricing; Crunchbase terms; the Briter data policy; D&B terms.
- The CBN legal page and the NGX terms; the FMDQ terms.
- Robots files for CAC, GSE, JSE and AU.
- The NUC, PenCom, State House, NCC and Anzisha pages; the YC, Techstars and Obama Foundation terms.
- The LinkedIn and Facebook terms.
- The NDPA 2023 text; the Copyright Act 2022 (s.2(5), s.3, s.7).
- Anthropic's commercial terms, privacy articles, data-retention, data-residency and pricing pages.

**Still to read before approval:**
- SEC Nigeria terms; CAC's own terms; NAICOM list pages.
- Google site terms; Founders Factory Africa terms; NAE terms; GSE terms.
- data.gov.ng, LPPC, AfDB, Afreximbank, ECOWAS.
- The full GAID 2025 text (only law-firm summaries were read).

An agent does these checks as part of preparing each source-registry entry (REQ-136). A source stays at "Proposed" until that is done.

**Copyright position (Copyright Act 2022, read today):**
- "Mere data" is not protected (s.3(a)).
- A compilation gives no exclusive right in the underlying data (s.2(5)).
- Government works are protected (s.7), and "directories" count as literary works.

**Working rule:** take facts one by one with a citation. Never mirror a list's selection, order, write-ups or photos. Terms of use are a separate contract, so where terms forbid automated access, only a person may consult the page.

---

## 3. Pipeline (D-033 as amended)

**The pipeline never publishes anything.** AI gathers the data, does basic verification only, stores it in the staging database and exports it for import into WordPress. Reviewers then work inside WordPress.

### 3.1 Steps and who does what

| # | Step | Agent or code does | Human does | Controls (REQ) |
|---|---|---|---|---|
| 0 | **Source approval** | Reads terms and robots rules; drafts each source-registry entry: URL, entity types, access method, terms quote, robots check date, risk | Supervisor approves or rejects (named approver). Legal reviews any source marked "Legal check". **The run starts straight after this.** | REQ-136, 137, 138 |
| 1 | **Seed list** | Builds about 1,200 candidates (20% spare) from approved sources only. Each carries a criterion code, evidence URL, country and type, against quotas. Suppression check (first of three). | Supervisor sets the quotas once | REQ-147, 192, 207 |
| 2 | **Fetch** | Fetches only approved sources. Identifies itself (`WHOISWHO-bot/1.0 (+https://wiwi.africa/bot; contact address)`), re-checks robots.txt each run, waits at least 5 seconds between requests per host, never logs in or gets around a block, and stores each page with a hash and retrieval date. Wikidata calls follow the WDQS limits. | — | REQ-138, 142 |
| 3 | **Entity resolution** | Matches on, in order: Wikidata QID, RC number, regulator licence number, LEI, official domain. Individuals are matched on name + organisation + role from one page. Probable duplicates are flagged, never merged. | A reviewer decides each flag in WordPress | REQ-145, 146 |
| 4 | **Field allowlist** | Keeps only allowlisted fields (REQ-139 for individuals). Drops forbidden data (REQ-140), including party affiliation. **Organisation records keep no named person** in any field that can be public before review. | — | REQ-139, 140 |
| 5 | **Draft** | T1: fills a fixed template from structured fields, with no AI. T2/T3: the AI receives numbered source passages and returns claims, each with a passage ID and an exact quote. Model and prompt version are recorded. | — | REQ-165–167 |
| 6 | **Basic verification** | The seven checks in 3.2. Writes a short verification report and a content hash on each record. | — | REQ-214 |
| 7 | **Store** | Writes the record, sources, provenance, report and hash to the staging database. Failed records stay there with the reason and are replaced from the spares. | — | REQ-211 |
| 8 | **Export** | Writes the import file with a manifest of hashes. | — | REQ-237 |
| 9 | **Import** | WordPress recomputes each hash and refuses a mismatch; suppression check (third of three); sets the state in 3.3. | **A named staff member starts each import,** after a dry run. | REQ-237, 212 |
| 10 | **Review in WordPress** | Shows each claim beside its source, with the verification report. Records review time. | The reviewer checks identity, the criterion, every claim against its source, and the neutral-content checklist. Decision: approve, reject (reason code) or return. **Approver ≠ importer.** | REQ-148, 163, 168, 206, 222 |
| 11 | **Approval** | Sets "Reviewed on <date>"; lifts noindex; adds sitemap entry and schema.org markup. An individual or entrepreneur becomes public at this point. | — | REQ-208, 216, 217 |
| 12 | **QA audit** | Draws a random 5% sample of reviewed profiles each week | The other reviewer, or the supervisor, re-checks the sample | REQ-149 |
| 13 | **Corrections, claims and removals** | Sends refresh reminders by email with a link to the correction form (no owner login, D-034). | Reviewers verify claims and set Claimed, and apply correction requests in wp-admin (REQ-230–232). The Editor-in-Chief handles disputes. | REQ-156–161, 164, 174, 226, 229–232 |

### 3.2 Basic verification: exactly seven checks

| # | Check | If it fails |
|---|---|---|
| 1 | The source is on the approved list | Record not exported |
| 2 | The source URL returned success | Retried, then not exported |
| 3 | Every AI-drafted claim has an exact quote found in the stored source text | **That claim is dropped;** the count is reported |
| 4 | Only allowlisted fields are kept | The field is dropped |
| 5 | The sensitive-term scan passes | Record not exported |
| 6 | The suppression check passes | Record not exported |
| 7 | Probable duplicates are flagged | Exported with the flag; **not public on import, even for an organisation,** until a reviewer decides (REQ-146) |

**Each record's verification report** is short: run ID, the seven results, sources with retrieval dates, claims kept and dropped, flags, model and prompt version, content hash. The reviewer sees it in WordPress.

**What this does not check.** Exact-quote matching proves the source says it, not that the source is right or current (the AU page in 2.5 shows a former Chair). It does not confirm identity or notability. Those are the reviewer's job, which is why the state on import matters, and why publishing AI-drafted organisation text before review is a decision for you (decision 12).

### 3.3 State on import (you may change these)

| Record | State on import | Public? | Search engines | After a human approves |
|---|---|---|---|---|
| **Organisation** | Published – review pending, labelled "Compiled automatically from public sources. Human review pending." | Yes | noindex | "Reviewed on <date>", indexable |
| **Individual or entrepreneur** | Imported – not public | No | — | Public, "Reviewed on <date>", indexable |
| **Any record with a duplicate flag** | Imported – not public | No | — | As above |

A profile can only go live on import once the safeguards in 5.4 pass. Until then, organisations import as not public too, so review can start earlier.

### 3.4 Rules for the AI step
- **Model.** Default to Claude Opus 5.5 for drafting. At about US$0.10 per profile (section 10.1), quality matters more than cost.
- **Data use and retention.** Under Anthropic's Commercial Terms, Anthropic "may not train models on Customer Content". API inputs and outputs are deleted within 30 days by default, and zero data retention is available on request.
- **Transfer basis.** Anthropic's data processing addendum covers EU, UK and Swiss transfers, not Nigeria. Our NDPA s.41/43 transfer basis must be recorded by us and confirmed by Legal before any individual's data is sent (REQ-169). Inference location defaults to "global"; US-only costs 1.1x.
- **Fetched text is data, never instructions.** Prompt injection in a source page cannot add a claim, because every claim must quote the stored passage exactly. The model holds no WordPress credentials.
- **Allowed fields only.** The prompt asks for allowlisted fields only and forbids praise, superlatives, and the topics in REQ-167.
- **Sites that block AI crawlers by name** (such as CAC). Their text is never fetched by an agent or passed to the model. People may consult them manually during review.

### 3.5 Where the data lives
- **Pipeline code** goes in `scripts/acquisition/` (infra area).
- **Fetched pages, drafts and personal data are never committed to git.**
- **The staging database** (REQ-211) is a private store outside WordPress, on the machine running the pipeline, with an encrypted backup. No public site reads it.
- **Review packs are gone.** Reviewers use the WordPress review queue. The content hash stays, so what is imported is what was drafted. If a reviewer then edits a field, the version history records it.

**Future item, not in acquisition scope:** continuous AI monitoring of published profiles and AI takedown, as a separate WordPress feature (REQ-238). Until then, refresh reminders (REQ-160), reports and corrections are how problems surface.

---

## 4. Timeline

There are two separate tracks: **acquisition,** which is machine time measured in hours, and **review,** which is human time measured in weeks.

### 4.1 Track 1: acquisition, under 24 hours for all 1,000

**The clock starts when source approvals are done (H0).**

**Work to do** (estimates, replaced by measurements from the smoke lot):

| Item | Estimate |
|---|---|
| Candidates, including 20% spare | About 1,200: 240 T1, 360 T2, 600 T3 |
| Web requests | About 2,500: 600 robots.txt, 40 list pages, 240 T1 website checks, 720 T2 pages, 900 T3 pages. About 2,900 with 15% retries. |
| AI drafting | 960 records, about 2 calls each: about 10.6M input and 2.4M output tokens |
| Assumed call time | About 45 seconds a call, with 20 running at once after a ramp-up |

**Hour by hour:**

| Elapsed | Step | What happens | What limits it |
|---|---|---|---|
| **0:00–0:15** | Preflight | Freeze the approved source list. Load the suppression list. Confirm the Anthropic key, tier and limits with small calls. | Anthropic account |
| **0:15–1:00** | Seed | Wikidata queries and about 40 list pages. About 1,200 candidates against quotas. First suppression check. | WDQS (5 parallel, 60-second timeout); 5 seconds per host |
| **1:00–1:45** | Smoke lot | The 50 pilot records go right through. Automatic stop rules below. | — |
| **1:00–3:00** | Fetch | About 2,900 requests. About 30 minutes at 10 workers, with time allowed for slow sites. The busiest host, at about 150 pages, takes 12.5 minutes. | 5 seconds per host; slow government sites |
| **1:45–2:00** | T1 templates | 240 records, no AI | None |
| **2:00–5:00** | Draft T2 and T3 | Starts small and ramps up. About 1.2 hours at 20 calls at once, 2.4 hours at 10. Three hours allowed. | Anthropic account; ramp-up |
| **3:00 and 5:00** | Retry passes | Pages that failed are tried again, with backoff | Sites that are down |
| **5:00–5:30** | Verify | Duplicate flags, the seven checks, reports, hashes | None |
| **5:30–6:00** | Top up | Failed records are replaced from the spares. Quota shortfalls are listed by class. | Whether approved sources can fill each quota |
| **6:00–6:30** | Export | Import file, hash manifest, run report, encrypted backup | None |

**Target: about 6.5 hours. Allow 8. Treat 12 as the point to stop and investigate.** No person is needed during the run; the supervisor should be reachable. The run saves its progress, so it can resume after an interruption.

**Automatic stop rules on the smoke lot,** which pause the run for a prompt or source fix: more than 30% of claims dropped; sensitive-term hits on more than 5% of records; more than 20% of source URLs failing. These numbers are my starting points.

**What limits it:**

| Limit | Finding | Effect |
|---|---|---|
| **Source approvals** | Human, and before the clock starts. **Decides whether the run can reach 1,000 at all.** Agents can prepare about 35 registry entries in about 2 hours, including the terms still unread (2.6). | Each source you reject or leave on hold lowers that quota. A source that stays manual-only (decision 21) cannot feed the run. |
| **Anthropic account** | On the Start tier, Opus 5.5 allows 4,000 requests, 2M input and 400k output tokens a minute, so the tokens would pass in about 6 minutes. Spend cap US$500 a month, against about US$110 here. **New organisations may start on a lower Evaluation tier whose numbers are not published.** Sudden increases can trigger errors, so traffic ramps up. | Not a limit on Start. **To finish in 18 hours, the account needs at least about 10k input and 2.3k output tokens a minute.** Check in the Console before H0. The purchase needed to reach each tier is unverified. |
| **Batch API** | 50% cheaper and "most batches" finish in under an hour, but a batch can take up to 24 hours before it expires. | **Not used,** because it could break the 24-hour target to save about US$55. |
| **Fetch rate per host** | 5 seconds per host is the plan's own politeness rule. | Adds minutes, not hours, because requests are spread over about 600 hosts. |
| **Legal: AI transfer basis (not on your list)** | REQ-169 and D-017 require this before any individual's data goes to the AI. Requested for 13 November. | **This can split the run:** see below. |
| **Legal: other countries** | Collecting personal data about non-Nigerian people needs clearance first (6.5). | 70 records (non-Nigerian individuals and entrepreneurs) wait for Legal. Non-Nigerian organisations can be collected but not imported. |

**Likely calendar if sources are approved on Tue 10 Nov** (decision 22):

| Run | When | Records | Elapsed |
|---|---|---|---|
| A: organisations | Tue 10 Nov, straight after approvals | 500 (plus spares) | About 4 hours |
| B: Nigerian individuals and entrepreneurs | As soon as Legal confirms the transfer basis (by Fri 13 Nov) | 430 (plus spares) | About 4.5 hours |
| C: non-Nigerian individuals and entrepreneurs | Once Legal clears each country (by 11 Dec) | 70 | About 1 hour |

If Legal has answered before H0 and you waive the country clearance, it is one run of about 6.5 hours.

### 4.2 Track 2: review in WordPress

**Starts at R0,** when the import path (REQ-237) and the review queue exist. **Dates below assume R0 = Monday 23 November and move with it, week for week.** The WordPress build has not started.

**Assumptions (pre-pilot; the pilot replaces these):**

| Item | Estimate | Note |
|---|---|---|
| T1 / T2 / T3 full review | 3 / 8 / 12 min | Fields; fields plus 4–8 AI claims; identity, criterion, claims, neutral-content check |
| Rework and rejects | +15% | Reviewing records that are then rejected or returned |
| Corrections, removals and QA audit | +10% | Ongoing work after publishing. D-034 adds 5–10 minutes per claim or correction on top. |
| **Effective minutes** | **T1 3.8 · T2 10.1 · T3 15.2** | Estimate × 1.15 × 1.10 |
| "D-015 reviewer" | 7.5 focused hours/week (450 min) | About 40 profiles a week at the blended mix |
| "Full-time reviewer" | 25 focused hours/week (1,500 min) | |
| Holidays | 21 Dec – 3 Jan at half capacity | Two weeks count as one |
| Review order | Pilot, then organisations, then individuals (decision 3) | Keeps unreviewed public text live for the least time |

**Total load:** T1 760 + T2 3,030 + T3 7,600 = **11,390 minutes, about 190 hours,** plus about 4 hours of pilot slowness. Individuals are two-thirds of it.

**Import batches** (the data can all be ready well before this; these are the order of import):

| Batch | Records | What | Goes in when |
|---|---|---|---|
| Pilot | 50 (30 organisations, 20 individuals and entrepreneurs) | Section 5 | R0 |
| B1 | 70 organisations | Reaches the first 100 organisations | R0, once safeguards pass |
| B2 | 320 Nigerian organisations | Rest of the Nigerian organisations | After B1, under decision 24 |
| B3 | 80 non-Nigerian organisations | | After Legal clears each country |
| B4 | 410 Nigerian individuals and entrepreneurs | Not public until approved | R0, once Legal's transfer basis is in and they have been collected |
| B5 | 70 non-Nigerian individuals and entrepreneurs | Not public until approved | After Legal clears each country |

**When each milestone is reached:**

| Milestone | 2 × 7.5 h/week (current commitment) | 2 × 15 h/week | 2 × full-time |
|---|---|---|---|
| 500 organisations public, labelled | About R0 plus a few days, under decision 24 | Same | Same |
| 100 reviewed | Week 2 (by 6 Dec) | Week 1 (by 29 Nov) | Week 1 |
| 250 reviewed | Week 2 (by 6 Dec) | Week 1 (by 29 Nov) | Week 1 |
| All 500 organisations reviewed and indexable | Week 6 (by 3 Jan) | Week 3 (by 13 Dec) | Week 2 (by 6 Dec) |
| 100 individuals approved and public | Week 8 (by 17 Jan) | Week 4 (by 20 Dec) | Week 2 (by 6 Dec) |
| 750 reviewed | Week 10 (by 31 Jan) | Week 5 (by 27 Dec) | Week 3 (by 13 Dec) |
| **1,000 public and reviewed** | **Week 14 (by 28 Feb).** About 765 by 31 Jan. | **Week 8 (by 17 Jan)** | **Week 4 (by 20 Dec)** |

Because individuals are not public until approved, 1,000 public and 1,000 reviewed now arrive together.

**Week by week at 2 × 15 h/week:**

| Week ending | Reviewed | Organisations reviewed | Individuals approved | Public |
|---|---|---|---|---|
| 29 Nov | 267 | 247 | 20 | 520 |
| 6 Dec | 445 | 425 | 20 | 520 |
| 13 Dec | 588 | 500 | 88 | 588 |
| 20 Dec | 706 | 500 | 206 | 706 |
| 27 Dec | 765 | 500 | 265 | 765 |
| 3 Jan | 824 | 500 | 324 | 824 |
| 10 Jan | 942 | 500 | 442 | 942 |
| 17 Jan | 1,000 | 500 | 500 | 1,000 |

**Review order is a real choice** at 2 × 15 h/week:

| Order | All organisations reviewed | 1,000 public |
|---|---|---|
| **Organisations first (default)** | **13 Dec** | 17 Jan |
| Individuals after T1 only | 17 Jan | 3 Jan |

Putting individuals first brings 1,000 public forward two weeks, but leaves AI-drafted organisation text live and unreviewed until 17 January.

### 4.3 Reviewers needed to reach 1,000 reviewed by week 4, 8 and 12

| Deadline | D-015 reviewers (7.5 h/week) | Full-time reviewers |
|---|---|---|
| Week 4 (20 Dec) | **7** | **2** |
| Week 8 (17 Jan) | **4** | **2** (1 reaches 24 Jan) |
| Week 12 (14 Feb) | **3** | **1**, but keep 2 for cover and the reviewer ≠ importer rule |

**Cheapest way to meet the 31 January sprint end:** the two existing reviewers each give about 2 days (15 focused hours) a week from R0, reaching 1,000 by 17 January. Alternatively, 3 reviewers at 7.5 hours reach it by 31 January, with no margin.

**Sensitivity:** if the pilot shows T3 takes 25 minutes instead of 12, the total load rises to about 17,400 minutes, and every column above rises by about 50%. The pilot result is the number that matters.

---

## 5. Pilot: the first 50 records

The pilot has two parts. Machine checks happen inside the run. Human review happens in WordPress when the queue exists.

### 5.1 Composition (Nigeria only)
- **15 T1 organisations:** 8 CBN-licensed banks, 4 PFAs, 3 NUC universities.
- **15 T2 organisations:** 5 listed companies (from their own IR pages), 6 startups (Ventures Platform or Google accelerator), 4 public institutions or foundations.
- **10 entrepreneurs (E1):** founders of pilot startups or companies.
- **10 individuals:** 3 ministers (I1), 3 bank CEOs (I2), 2 vice-chancellors (I3), 2 academy fellows (I3).

If Legal's transfer basis is not in when the run starts, the smoke lot uses the 30 organisations, and the 20 people go through with run B.

### 5.2 Schedule

| Date | Step | Owner |
|---|---|---|
| Mon 9 Nov | Answer section 12; name the reviewers; send Legal the questions in 6.5 | You |
| Mon 9 Nov | Agents read the unread terms and prepare all source-registry entries | Agents |
| Tue 10 Nov | Approve sources; Anthropic account live and tier checked (Q28) | You |
| Tue 10 Nov | Run A, including the smoke lot | Agents |
| By Fri 13 Nov | Legal confirms the AI transfer basis (REQ-169) | Incspired Legal |
| Same day | Run B | Agents |
| As soon as possible | First task: import path and review queue (T-0, REQ-237) | Agents, after Gate 2 |
| R0 (assumed 23 Nov) | Reviewer onboarding (2 h) using `docs/review-guidelines.md`. Pilot imported, not public. | You and reviewers |
| R0, days 1–2 | Calibration: both reviewers review the same 10 profiles and compare | Reviewers |
| R0, week 1 | Pilot review in WordPress. 20 profiles (10 organisations, 10 people) are reviewed by **both** reviewers to measure agreement. | Reviewers |
| R0, week 1 | Supervisor re-checks all 50 reviewed profiles against their sources (a 100% audit) | You |
| End of R0 week 1 | Report (5.3); plan updated with measured numbers | Agents, then your approval |

**Brief Phase 1 exit (20 November, "a clear acquisition plan"):** this plan, your answers and the run report meet it. Measured review times come later, once WordPress exists.

### 5.3 What the pilot measures

| Measure | How | Feeds |
|---|---|---|
| **Elapsed time and throughput of each step** | Run logs | Track 1 schedule |
| **Claims dropped; failure rate of each check** | Verification reports | Prompts, sources |
| **How much is wrong despite passing basic verification** | Reviewer findings and the supervisor's 100% audit | Whether organisations should go live on import (decision 12) |
| Review minutes per profile, by tier | Timer in the WordPress queue (median and 90th percentile) | Track 2 capacity |
| First-pass approval rate; reject reasons | Reason codes | Draft quality, source choice |
| Draft error rate by field and severity | Reviewer findings | Prompt and template fixes |
| Source quality: stale, wrong, link rot, by source | Errors traced to source | Source approvals (2.x) |
| Disambiguation failures | Duplicate and identity errors | Matching rules |
| Inter-reviewer agreement | Double-reviewed 20 | Guidelines, calibration |
| AI cost per profile | Usage logs | Section 10 cost |

**What 50 records cannot show.** Zero errors in 30 organisations still leaves a 95% upper bound of about 10%. This is why decision 24 recommends importing the first 100 organisations and reviewing them before the other 400 go live.

### 5.4 What must pass before organisations go live on import
All of these must pass on the site that takes the import:
1. Publication states (REQ-210) and the import path, including hash and suppression checks (REQ-212, 237).
2. Label, noindex, sitemap exclusion and listing rules, with the state-by-surface tests passing (REQ-208, 216–218).
3. 410, purge and restore (REQ-209); kill switch (REQ-193); bulk unpublish by batch (REQ-227).
4. Removal, correction and suppression workflows (REQ-156–158, 207, 229–232); audit log (REQ-228).
5. Legal items for organisations (6.5).

**The review queue does not have to wait for all of this.** Records can be imported as not public, and reviewers can start, as soon as the import path and queue exist.

### 5.5 Decision rules after the pilot

| Result | Action |
|---|---|
| Any S1 error in the pilot | Fix the cause. Re-run and re-review 25 profiles of that type before importing more of it. |
| S2 rate above 3% (organisations) or 2% (individuals) in records that passed verification | Revise prompts and sources. Review a further 25 for that tier. Consider making organisations not public on import. |
| Median T3 review above 20 min | Choose one: cut T3 fields to the core REQ-139 set; raise reviewer hours; or move volume from T3 to T2. |
| A source causes more than 10% of errors | Downgrade it to "lead only" or reject it; re-run those records |
| Inter-reviewer agreement below 90% on approve/reject | Run a second calibration and tighten the criteria wording |
| All within limits | Import the next batches |

---

## 6. Quality and risk controls

### 6.1 Error definitions and thresholds that stop importing

| Severity | Definition | Examples |
|---|---|---|
| **S1 critical** | Could harm a person or breach law | Wrong person (identity mix-up); a claim on a prohibited topic; sensitive data published (party, religion, health, age); a suppressed person or organisation published; an invented claim with no source |
| **S2 material** | A fact is wrong | Wrong title, role, organisation, date, licence status; the source does not support the claim; the criterion is not actually met |
| **S3 minor** | Presentation | Typo, formatting, a dead link where the fact is still right |

These are rules people apply. With a named person starting each import, there is no automatic pause to build.

| Trigger | Response |
|---|---|
| **Any S1 in published content** | Unpublish the profile at once (410). **Stop importing that tier and source** until the root cause is fixed and the supervisor signs off. If S1 errors in individuals look systemic, use the kill switch (REQ-193). If an organisation batch looks bad, bulk unpublish it (REQ-227). |
| Weekly S2 rate above 2% for T3, or above 3% for T1/T2 | Stop importing that tier. Fully re-review the affected batch. Fix guidelines or prompts. |
| QA sample size | 5% of profiles reviewed that week, at least 20 per week, always including at least 10 individuals |
| Reject rate above 25% in a batch | Stop importing from that source or prompt version until it is fixed |
| Valid correction requests above 1% of published profiles in a week | Review the source and prompt; audit the affected batch |
| Removal requests above 2% of individual profiles in a month | Supervisor and Legal review the inclusion criteria for that class |
| Review backlog above 2 weeks of capacity | Workload warning (REQ-206). Add reviewer hours. Do not import more organisations as live. |

The earlier cap on unreviewed profiles, the automatic pause and the AI takedown are removed (decision 25).

### 6.2 Corrections log
Every change to a published profile writes an entry (REQ-164): what changed, old and new value, reason, who made it, and the source. Corrections are resolved within 7 days (REQ-158).

### 6.3 Removal and suppression
- **Targets (unchanged):**
  - Acknowledge within 1 business day.
  - Hide within 2 business days of a valid request from the subject (REQ-158). Hiding means a 410 and noindex, removal from the sitemap and internal search, and a CDN purge (REQ-209).
  - Add the subject to the permanent suppression list (REQ-207).
- **The suppression list is checked three times** (seed, basic verification, import), so a suppressed entity can never come back through a new import.
- **The objection right is real.**
  - NDPA s.36 requires us to stop unless we show overriding legitimate grounds.
  - POPIA s.11(3) requires a stop on reasonable grounds.
  - **Default:** a subject who objects is removed and suppressed. The Editor-in-Chief may escalate only office-holders (I1/I5) to Legal.
- **How a requester's identity is checked** is still open (wireframe finding 15). With no accounts (D-034), the requester's email is verified by a single-use signed link (REQ-233). Proposed default: a request from the subject's official work email, or from the organisation's domain, is accepted. Anything else goes to staff judgement.
- **Search engines:** after a removal, staff file search-engine removal requests (N21).

### 6.4 Legal risks

| Risk | What applies | Control |
|---|---|---|
| **Organisations public before a human checks** | Publication happens when the page goes live. Organisation facts carry less data-protection risk, but defamation and wrong-fact risk remain. The label may help or may read as an admission; I am not assuming it reduces liability. | No named person on organisation records; label; noindex; Legal questions 1 and 2 in 6.5; decisions 12 and 24 |
| **Accuracy duty** | The NDPA has a data-accuracy principle. I recall it as s.24, but could not re-read it: the readable copy of the Act is a scanned image. **Unverified.** | Individuals are reviewed before public. Legal to confirm for organisations that name no one. |
| Lawful basis for individuals (NDPA s.25) | Legitimate interests fail if the subject "would not have a reasonable expectation" of the processing (s.25(2)(c)). The journalism exemption (s.3(2)(d)) still leaves s.25 in force. | Professional, already-public facts only (REQ-139). A legitimate interests assessment (GAID Schedule 8). A disclosure page (REQ-130, 170). An easy objection route. |
| Sensitive data (s.30, s.65) | Political affiliation, religion, ethnicity and health are sensitive, with no "made public" exception | Excluded from the schema and scanned for (REQ-140, 141). No party field for office-holders. |
| DPIA (s.28; GAID) | Required for likely high risk; GAID requires one before deploying AI (summaries read, not the GAID text) | DPIA before the first individual is published |
| Registration as a data controller of major importance (s.44, s.65; GAID tiers) | GAID tiers are reportedly over 200, over 1,000 and over 5,000 subjects in 6 months, with fees of about ₦100k to ₦1m (unverified). 1,000 profiles would likely qualify. **The run holds data on about 600 people in the staging database from day one.** | Legal confirms the tier, registration, the DPO (s.32) and the audit-return duty |
| Cross-border transfer (s.41–43) | Anthropic's addendum has no Nigerian mechanism | Legal records the basis before individuals are drafted. **Organisation pages may name people incidentally,** so Legal should say whether run A needs the same basis. |
| Defamation (civil; criminal defamation remains in the Criminal Code and Penal Code in some states) | No higher threshold for public figures; truth is a defence, with the burden on us | Source per claim, neutral-content checklist, no allegations, fast correction (REQ-142, 163, 158) |
| Cybercrimes Act s.24 (amended 2024) | Narrowed. Exact amended text not read. | The same controls; Legal to confirm |
| Noindex is not privacy | The page is public. Google says the rule "must not be blocked by a robots.txt file" to work, and that "some search engines might interpret the `noindex` rule differently". | Treat every labelled page as fully public; REQ-217; fast removal |
| Other countries | Ghana: every controller registers (Act 843 s.27). Kenya: registration above thresholds (s.18). POPIA: objection stops processing. | Per-country Legal clearance before collecting people or importing anything from that country |
| Copyright and terms of use | Compilations and government works are protected; site terms are contracts | Facts only, cited; terms checked per source; no mirroring |

Penalties for context: NDPA s.48 sets a maximum of ₦10m or 2% of annual gross revenue for major-importance controllers. The NDPC fined MultiChoice ₦766.2m in 2025.

### 6.5 What we need from Incspired Legal, by batch

**Questions for Legal, to go with the 13 November request (send by 9 November):**
1. May organisation profiles go public before human review, given that they name no person and carry the label? Does it matter that their summaries are AI-drafted?
2. Does the label ("Compiled automatically from public sources. Human review pending.") help, hurt or make no difference if an organisation profile is wrong? Please approve or change the wording.
3. Does sending organisation pages to the AI, when they may mention people incidentally, need the same transfer basis as individuals?
4. Holding data on about 600 people in a private staging database before any review: does this change the DPIA, registration or notification position?

| Before | Legal item | Blocks |
|---|---|---|
| Any individual's data goes to the AI (13 Nov) | AI transfer basis (s.41/43) and choice of inference location; confirmation of the Anthropic processor terms (REQ-169) | Run B |
| First **organisation** published | Terms and Privacy pages; wording for the "how we compile" page (REQ-130), the AI disclosure (REQ-170) and the label (REQ-216); questions 1–3 | Organisations going live |
| First **individual** approved as public | Legitimate interests assessment; DPIA; registration tier, DPO and audit-return duty; whether and how subjects are told (REQ-159); sign-off of the neutral-content checklist; a written position on D-016; question 4 | Individuals going public (decision 7) |
| First **office-holder** approved | Confirm the office-holder field set and that party affiliation is excluded | I1 profiles |
| First **non-Nigerian** batch | Per-country position: Ghana registration, Kenya threshold, POPIA | Run C; batches B3, B5 |
| Any **licensed** dataset (NGX, Big Deal) | Contract review: display rights, attribution, share-alike | That source |
| Images or logos | Personality rights, trademark | Decision 6 |

### 6.6 What stays exactly as it was
- **The kill switch** (REQ-193), alongside bulk unpublish by batch (REQ-227), because the kill switch covers unclaimed individuals only and labelled organisations would otherwise have no equivalent.
- **Three suppression checks** and the permanent list (REQ-207).
- **Removal SLA** (REQ-158).
- **S1 rules:** any S1 unpublishes that profile at once and stops importing that tier and source.

---

## 7. WordPress tooling needed (input for the Gate 2 architecture revision)
These feed the WordPress architecture (D-031). "Proposed" items are not yet in `docs/requirements.md`. They become new REQs only if you approve them (no scope creep without approval).

### 7.1 First task and priority input for the architecture revision: T-0, basic import path (REQ-237)
**The staging data needs somewhere to go.** Acquisition can finish in a day, but nothing can be reviewed until WordPress can take the records. This is the first acquisition task to build and a priority input for the Gate 2 revision.

**Scope:** one import path into the profile post type, by CSV or REST (decision 26).

**Done when:**
1. A staff member with a named role can dry-run a file and see a diff, then import it.
2. Records are created or updated by stable ID, so running the same file twice changes nothing.
3. Sources, per-field provenance, claims, the verification report, batch ID and content hash are stored with each record.
4. WordPress recomputes each hash and refuses a mismatch.
5. The suppression check runs again at import.
6. State is set by record type (3.3), with a setting to import everything as not public.
7. A reviewed field is never overwritten; a change is flagged instead.
8. A whole batch can be undone.
9. 1,000 records import within the host's limits, in chunks or by WP-CLI (Q26).
10. Each import is in the audit log. Tests cite REQ-237.

**The architecture revision must decide:** the profile post type and how provenance is stored (T-3: queryable, not buried in post meta), CSV or REST, and how imports run on cPanel.

**To be filed as a GitHub issue** when `gh` is available.

### 7.2 Tooling list

| ID | Tool | Must do | REQ |
|---|---|---|---|
| **T-0** | **Basic import path (first task)** | See 7.1 | **REQ-237** |
| T-1 | Source registry | Admin screen with name, entity types, URL, access method, terms summary, robots check date, approver, status. The pipeline reads the approved list from an export of this, or from a file until it exists. | REQ-136, 137 |
| T-2 | Wider import adapters | Wikidata re-sync and other adapters. Later than T-0. | REQ-134, 135, 145–147, 161, 207 |
| T-3 | Provenance per field | Source URL, retrieval date, confidence, method, source hash, passage ID. Queryable for reports, so it should not be buried in post meta. | REQ-142, 143 |
| T-4 | AI draft record | Claims with passage quotes, log of dropped claims, verification report, model, prompt version, internal AI-assisted flag | REQ-166–168, 214 |
| T-5 | Review queue | Filters by tier, batch, source and country. **Claim beside source,** with the verification report. Neutral-content checklist. Reason codes and error severity. Approver ≠ importer enforced. Per-reviewer workload and backlog warning. | REQ-148, 163, 206, 222 |
| T-6 | Duplicate and merge queue | Human merge with an audit entry; never automatic for individuals | REQ-146 |
| T-7 | Publication states | The states and transitions in REQ-210. Unpublish gives a 410, noindex, sitemap removal and purge. Kill switch. Bulk unpublish by batch. | REQ-193, 208–210, 227 |
| T-8 | Corrections, removal and suppression | Corrections log; removal workflow with deadline timers; permanent suppression list checked at every import | REQ-156–158, 164, 207, 226 |
| ~~T-9~~ | ~~Pause and cap~~ | **Removed** (decision 25) | — |
| T-10 | **Proposed:** review timer | Records active seconds per review, per reviewer | (new; extends REQ-149) |
| T-11 | Hash check | Part of T-0 | REQ-221, 237 |
| T-12 | **Proposed:** QA audit queue | Random sample of reviewed profiles each week for the second checker; result feeds the error-rate dashboard | (new) |
| T-13 | **Proposed:** inclusion criterion field | Criterion code plus evidence URL, carried on each record, shown to the reviewer, required for approval | (new) |
| T-15 | Dashboard | Imported, public, reviewed and awaiting review, by tier, source and country; throughput, oldest waiting, error rates, reasons | REQ-149, 171, 228 |
| T-16 | Sensitive-pattern scan | Includes Nigerian party names and acronyms, religion and health terms | REQ-141 |
| T-17 | Export | All profiles with provenance, in open formats | REQ-131 |
| T-18 | **Label and noindex** | Label on page, cards and share previews; "Reviewed on <date>"; `X-Robots-Tag` and meta noindex; no sitemap, feed or schema.org while labelled, **including from any SEO plugin**; listing rules; state-by-surface test matrix | REQ-216–218 |
| ~~T-19, T-20, T-21~~ | ~~AI service roles, AI takedown, monitor~~ | **Removed from acquisition.** Monitoring and takedown are a future item (REQ-238). | — |
| T-22 | Audit log for imports | Who imported, batch, file hash, record counts, states set | REQ-228 |
| T-23 | **No public accounts** | Front-end registration closed; no public login link; wp-login limited to staff; the only users are staff. Test that the site exposes no registration or login route. | REQ-229 |
| T-24 | **Request to claim and claim verification** | Public form with evidence upload and email verification; staff screen with evidence, method, rival claims; only staff set Claimed. | REQ-230, 231 |
| T-25 | **Correction request** | Form and staff handling through the review queue; version history records requester and approver. Sensitive fields need a second approver (Q57). | REQ-232 |
| T-26 | **Single-use signed links** | Purpose-bound, single-use, expiring, hashed tokens for nominator, nominee, intake, consent and claim email verification. | REQ-233 |
| T-27 | **Staff roles and two-factor** | Seven roles with a tested capability matrix; two-factor enforced; same-day deactivation. | REQ-235, 047 |
| T-28 | **Offline score entry** | Awards manager screen for scores, conflicts, shortlist and results (flagged). | REQ-234 |

**Workload note (D-034):** with no owner accounts, every claim, correction and enquiry change lands on staff. Claims and corrections already sit at the top of the review queue (REQ-226); plan about 5 to 10 minutes per claim or correction on top of the review estimates in 4.2. The sales prospect list in section 9 can still point prospects to the claim form.

**Build order that unblocks acquisition soonest:** T-0, T-5 and T-3 first (records in, reviewers working, not public), then T-7 and T-18 (organisations can go live), then T-8 and T-1, then the rest.

---

## 8. Human tasks, owners and dates
Owner "You" = the supervisor. Dates are ceilings; earlier is better.

| # | Task | Owner | Needed by | Blocks |
|---|---|---|---|---|
| H1 | Decide the items in section 12 | You | Mon 9 Nov | Everything |
| H2 | Name the two reviewers and their weekly hours (Q4) | You | Mon 9 Nov | Review |
| H3 | Open the Anthropic API account; **check its tier and limits in the Console**; set the organisation's data settings; decide on zero data retention (Q28) | You | Before H0, target Tue 10 Nov | The run |
| H4 | **Approve all sources** for the run (named approver per REQ-137), after agents prepare the entries | You | Tue 10 Nov | **The run: H0** |
| H5 | Send Legal the questions in 6.5 | You | Mon 9 Nov | Organisations going live |
| H6 | Legal: AI transfer basis | Incspired Legal | Fri 13 Nov (already requested, Q5) | Run B |
| H7 | Name the Editor-in-Chief (REQ-174), who handles disputes and the named-award list | You | Fri 13 Nov | Publishing |
| H8 | Approve the Gate 2 architecture revision, with the import path as its priority input | You | As soon as it is ready | R0 |
| H9 | Legal: Terms, Privacy, disclosure and label wording | Incspired Legal | Fri 20 Nov | Organisations going live |
| H10 | Legal: legitimate interests assessment, DPIA, registration tier, DPO, notification position | Incspired Legal | Fri 20 Nov | First individual going public |
| H11 | Reviewer onboarding (2 h) and calibration (10 profiles) | You and reviewers | R0 | Review |
| H12 | Start each import, after a dry run; decide on each next batch | You or Editor-in-Chief | From R0 | Publishing |
| H13 | Pilot audit of the 50 reviewed profiles | You | End of R0 week 1 | Plan update |
| H14 | Ask NGX for a reference-data licence quote (optional) | You | Thu 12 Nov | Optional O1 route |
| H15 | Legal: office-holder field set | Incspired Legal | Fri 27 Nov | Office-holders going public |
| H16 | Third reviewer onboarded, if the pilot says so | You | Mon 30 Nov | Section 4.3 targets |
| H17 | Legal: per-country positions (Ghana, Kenya, South Africa) | Incspired Legal | Fri 11 Dec | Run C; B3, B5 |
| H18 | Weekly: sign off the QA audit | You or Editor-in-Chief | Every Friday | Next imports |

---

## 9. The sales prospect list is separate and never published
The brief (§9.2) asks for a funnel: 500 qualified prospects, then 300 contacted, 150 engaged, 100 receiving an offer, and 10 paying. That is a **sales list**, not part of the directory.

**How the two relate:**
- **They overlap.** Many prospects will be organisations or people who also qualify for the directory. A published unclaimed profile helps a sales conversation ("your profile exists; you can claim it").
- **The directory may guide which segments sales targets.** It must never guide who gets a profile.

**Why the prospect list stays separate and is never published:**
1. **Different purpose and lawful basis.** The directory publishes professional facts under legitimate interests. Prospecting is direct marketing and needs its own basis and opt-out. Mixing them breaks purpose limitation under the NDPA.
2. **Different data.** Prospect records hold contact details, notes and deal status. REQ-140 forbids these on profiles and D-010 keeps them off public pages.
3. **Different sources.** Sales may use introductions, networks and licensed tools whose terms forbid republication. Those sources are not approved for the directory.
4. **Credibility.** Inclusion, review, labels and awards must never depend on commercial interest (D-003, REQ-199). Reviewers do not see prospect status, and prospect status is never an inclusion criterion.

**Rules:**
- The prospect list lives in its own store (REQ-093, 176), managed through CSV and later CaromHQ (D-011).
- **Prospect to profile:** only by entering this pipeline from step 1, with an approved source and a met criterion, like anyone else.
- **Profile to prospect:** sales may see that a public profile exists. Contact data comes from sales' own lawful sources, never from the pipeline's working store.
- **Telling subjects that a profile exists** (REQ-159) is a notice, not a sales email. If Legal allows it, it is sent separately and contains no offer.

---

## 10. Cost estimate and top risks

### 10.1 Cost (first 1,000 profiles, including the pilot)

| Item | Basis | Estimate |
|---|---|---|
| AI drafting (Claude Opus 5.5, US$4 input / US$20 output per million tokens) | About 11k input and 2.5k output tokens per profile, so about US$0.09, on 960 records including spares, plus some re-runs | **About US$110. Budget cap: US$250** |
| Batch API | Would save about US$55, but a batch can take up to 24 hours | Not used for the main run |
| Wikidata, GLEIF, regulator lists, official sites | Free | US$0 |
| NGX reference data (optional) | Price by quote; not published | Unknown. Only if decision 9 chooses it. |
| Africa: The Big Deal (optional) | About US$1,500 a year enterprise; publication licence unconfirmed | Not in the base case |
| OpenCorporates, Crunchbase, CAC–NIBSS API | Not recommended | US$0 |
| Reviewer time | About 194 h review + 10 h QA + 8 h onboarding and calibration ≈ **215 reviewer-hours** | 215 × hourly rate. Illustration only: at ₦3,000, ₦6,000 or ₦10,000 an hour, that is ₦0.65m, ₦1.3m or ₦2.15m. **You supply the real rate.** |
| Claims and corrections (D-034) | 5–10 minutes each, volume unknown | On top of the above |
| Supervisor time | About 4–6 h a week (approvals, imports, QA sign-off) for 8–14 weeks | Internal |
| NDPC registration and compliance audit (if Legal says it applies) | GAID fees reportedly ₦100k to ₦1m by tier, plus the fee of a licensed compliance organisation (unverified) | ₦0.1m to ₦1m+ |
| Hosting and storage | Already covered by the architecture cost (section 9) | Negligible |

Prices were read on 2026-11-07 from the official Anthropic pricing page. **Reviewer time is about 90% of the cost.**

### 10.2 Top risks

| # | Risk | Likelihood / impact | Mitigation |
|---|---|---|---|
| 1 | **Up to 500 organisation profiles are public on basic verification alone.** It proves the source says it, not that it is right. There is no audit, monitor or cap behind it now. | Medium / high | No named person; label; noindex; review organisations first; first 100 before the rest (decision 24); bulk unpublish by batch |
| 2 | **The import path and review queue are late.** Data can be ready in a day and then wait. The WordPress build has not started. | High / high | T-0 first; priority input for Gate 2; import as not public so review can start early |
| 3 | **Review capacity:** 2 reviewers at the D-015 level reach about 765 by 31 January | High / high | Decision 3 |
| 4 | **Legal objection or NDPC exposure:** registration tier, DPIA, transfer, sensitive data; also personal data held in staging from day one | Medium / high | Individuals not public until approved; organisations name no one; kill switch; no party field |
| 5 | **Approved sources cannot fill the quotas.** Many sources are manual-only or still unread, and some sites are down. | High / medium | Decision 21; 20% spares; run report lists shortfalls by class |
| 6 | **Stale official data passes every check** (the AU page shows a former Chair; NUC vice-chancellor names) | High / medium | Reviewer checks the role is current; retrieval date per field; refresh cycle (REQ-160). Organisations carry no leadership until reviewed. |
| 7 | **Wrong-person errors** for individuals (common Nigerian names) | Medium / severe | The name + organisation + role rule (1.5); no auto-merge; human approval before public; S1 rules |
| 8 | **Problems on live profiles go unnoticed** with no monitor | Medium / medium | Report button, correction form, refresh cycle; monitoring as a future item (REQ-238) |
| 9 | **Review fatigue** lowers quality at volume | Medium / high | Review in focused 2-hour blocks; weekly QA audit; agreement checks; S2 thresholds that stop imports |
| 10 | **"Human review pending" on a brand built on credibility** (brief 3.4, 11.3, 15.4) | Medium / medium | Indexable lists show only reviewed profiles (REQ-218); no sponsorship or awards while labelled (REQ-226); review organisations first |
| 11 | **Anthropic account limited at first** (Evaluation tier numbers are not published) | Low / medium | Check before H0; the run needs only about 10k input tokens a minute to finish in 18 hours |
| 12 | **Prompt injection or invented claims** from fetched pages | Low / high | Exact-quote check in code; allowlisted fields; human review |
| 13 | **Minors** via youth-programme sources | Low / severe | Cohorts at least 5 years old only; no age field; not public until approved |
| 14 | **Prospect data leaking into profiles** | Low / high | Separate store; reviewers blind to prospect status; profiles created only through the pipeline (section 9) |

---

## 11. One-page summary

**Goal:** 1,000 published profiles: 500 organisations, 200 entrepreneurs, 300 notable individuals. 850 are Nigerian; about 150 come from Ghana, Kenya, South Africa and pan-African bodies, after Legal clears each country.

**Model (D-033 as amended):** the pipeline never publishes. AI gathers, does basic verification, stores in a private staging database, and exports. A named staff member imports into WordPress, and reviewers work there.

**Notability is a pass/fail test.** Each profile needs one named criterion (O1–O7, E1–E2, I1–I6) proven by an evidence URL from an approved source. The reviewer applies it. Commercial interest is never a criterion.

**Basic verification, seven checks only:** approved source; URL returned success; exact quote for every AI claim (unsupported claims dropped); allowlisted fields only; sensitive-term scan; suppression check; duplicates flagged. Each record carries a short report and a content hash.

**State on import (you may change these):**
- **Organisations:** live, labelled "Compiled automatically from public sources. Human review pending.", noindex. No named person.
- **Individuals and entrepreneurs:** not public until a reviewer approves.
- **After human approval:** "Reviewed on <date>", indexable.

**Track 1, acquisition: about 6.5 hours for all 1,000, under 12 in the worst ordinary case,** starting straight after source approvals. Fetch limits add minutes. Anthropic's Start tier is no constraint. **What can really hold it up:** your source approvals, Legal's AI transfer basis for individuals (by 13 November), and whether the approved sources can fill the quotas.

**Track 2, review in WordPress, separate and later:** about 190 reviewer-hours. Starting 23 November if the import path exists:

| Reviewers | All organisations reviewed | 1,000 public and reviewed |
|---|---|---|
| 2 × 7.5 h/week (current) | 3 Jan | 28 Feb (about 765 by 31 Jan) |
| **2 × 15 h/week (recommended)** | **13 Dec** | **17 Jan** |
| 2 × full-time | 6 Dec | 20 Dec |

**First task:** a basic WordPress import path (REQ-237, T-0), the priority input for the architecture revision. Without it the data has nowhere to go.

**Sources:**
- **Recommended:** Wikidata (CC0), CBN lists (reuse allowed with attribution), PenCom, NUC, State House, organisations' own sites, Ventures Platform, Anzisha (older cohorts).
- **Not used as sources:** NGX (bans scraping and aggregation; use issuer pages), FMDQ, CAC for agents (reviewers may check it by hand), OpenCorporates, Crunchbase, Briter, D&B, YC, Techstars, MDCN, all social platforms.

**Controls kept:** kill switch; three suppression checks; removal within 2 business days with 410; S1 rules. No party affiliation, religion or health data, ever.

**Removed from acquisition:** second AI audit, AI publishing, review packs, quick look, cap and automatic pause. **Future item:** AI monitoring and takedown as a separate WordPress feature.

**Cost:** AI about US$110 (cap US$250); licences US$0 in the base case; about 215 reviewer-hours, about 90% of the cost.

---

## 12. Decisions needed from you
Reply in one message, for example "Accept all recommendations except 24: ...". Each is also logged in `docs/questions.md`. Numbers keep their earlier meaning.

**From this correction (answer first):**

| # | Q | Decision | Options | Recommendation |
|---|---|---|---|---|
| 12 | Q46 | **AI-drafted organisation text goes live unreviewed.** D-017, REQ-163 and REQ-168 require a human to approve each AI draft first. Your new state on import does not fit them for T2. | (a) As you wrote: all organisations live; amend those three for organisations only; (b) organisations live, but the AI summary stays hidden until a reviewer approves; no amendment needed; (c) only T1 live; T2 waits for review | **(b).** Same speed, no wording amended, and no AI text goes public unchecked. I have written (a) in the documents because you specified it. |
| 22 | Q63 | **Legal's AI transfer basis blocks individuals' data** (REQ-169), so "all 1,000 in one run" depends on it. | (a) Run organisations straight after source approvals, individuals when Legal confirms; (b) wait and run everything together; (c) send individuals' data without it (amends D-017) | **(a)** |
| 23 | Q64 | **Which WordPress takes the import?** Under D-029 the demo/staging site is public and indexable. | (a) One site, importing everything as not public until 5.4 passes; (b) a private staging WordPress first, which amends D-029 | **(a)** |
| 24 | Q65 | **All 500 organisations at once?** With no cap, the first error rate would arrive after everything is live. | (a) First 100 live, reviewed (about 3 working days at 2 × 15 h/week), then the rest; (b) all 500 on the first import | **(a)** |
| 25 | Q66 | **Cap and automatic pause.** Control 5 in D-033's first version; you did not list them under "keep" or "remove". I removed them as the simpler reading, along with instant hide on removal requests. | (a) Confirm removed; people apply the S1 and S2 rules at each import; (b) keep a cap; (c) keep both | **(a)**, with decision 24 doing the cap's job |
| 26 | Q67 | **CSV or REST for the import path** | (a) CSV (or JSON) files uploaded or run by a named staff member, so the pipeline holds no WordPress credentials; (b) REST, with a token that can only create not-public records | **(a)** first; it makes "never publishes" true by construction |
| 3 | Q31 | **Reviewer hours and review order** | Hours: (a) 2 × 7.5 h/week: 28 Feb; (b) 2 × 15 h/week: 17 Jan; (c) 3 × 7.5 h/week: 31 Jan; (d) 2 full-time: 20 Dec. Order: organisations first, or individuals after T1 (1,000 public two weeks sooner at 2 × 15 h/week; organisations reviewed five weeks later). | **(b), organisations first** |
| 21 | Q55 | **Manual-only sources** (CBN, PenCom, NAICOM, SEC, NCC, State House and others). Agents cannot gather from them as coded, and people typing them in does not fit the 24 hours. | (a) Agents read each one's terms and robots rules before H0; you approve re-coding where allowed; the rest are left out of the run; (b) use only sources already coded P, A or X | **(a)** |

**Carried over, still open:**

| # | Q | Decision | Options | Recommendation |
|---|---|---|---|---|
| 7 | Q35 | First individual goes public | (a) Wait for Legal's written assessment and DPIA; (b) publish under D-016 and use the kill switch if Legal objects | **(a).** Review can run meanwhile; approvals simply wait. |
| 15 | Q49 | **What counts.** D-012 and REQ-191 target "reviewed" profiles; REQ-104 and "1,000 published" could mean public or reviewed. | (a) Report both; target = public; D-012 stays reviewed; (b) target = reviewed | **(a)** |
| 20 | Q54 | **Listings** (REQ-218). Noindex on a profile does not stop its name being indexed through a list page. | (a) Indexable lists show reviewed only; labelled profiles found by search, direct link or a noindex view; (b) labelled profiles appear in all lists, and those pages lose indexing | **(a)**, since organisations-first review fills the lists by 13 December |
| 1 | Q29 | Split and countries: 500 / 200 / 300 by type; 850 Nigeria / 150 others. **Amends D-010 and D-025 (Nigeria only).** | (a) As proposed; (b) Nigeria only for the first 1,000; (c) another split | **(a)** |
| 5 | Q33 | Approve the sources, now **all** those for the run, with you as the named approver (REQ-137) | Approve all recommended / some / reject | **All recommended,** once the unread terms are read |
| 6 | Q34 | Images | (a) No photos on unclaimed individuals and no logos in the first 1,000 until Legal advises; (b) allow Commons images under D-024 now | **(a)** |
| 8 | Q36 | Public office-holders (I1) | (a) Include current federal ministers, governors, principal officers and agency heads, with no party field; (b) exclude politicians from the first 1,000 | **(a)** |
| 9 | Q37 | Listed companies | (a) Build from issuer pages plus Wikidata, and ask NGX for a quote in parallel; (b) wait for an NGX licence | **(a)** |
| 10 | Q38 | Budget and rates | AI cap US$250; supply the reviewer hourly rate | Approve the cap; give the rate |
| 11 | Q39 | AI model and processing location | (a) Claude Opus 5.5, global inference; (b) Opus 5.5, US-only inference (1.1x); (c) a cheaper model (Sonnet 5.5) | **(a)** unless Legal prefers (b) for the transfer record |

**Closed by this correction:** 2 (sampling), 4 (review packs), 13 (quick look), 14 (scope), 16 (cap), 17 (pause thresholds), 18 (takedown limits), 19 (staging database: private store).

Also needed (already open): reviewer names (Q4), the Editor-in-Chief's name (REQ-174), the Anthropic account owner (Q28), and Q56–Q62 from D-034.
