# WHOISWHO: Acquisition plan for the first 1,000 published profiles

Status: **Proposed v2, 2026-11-08: rewritten for publish first (D-033).** This is a plan only. Nothing has been fetched, scraped or imported. Terms and licences were checked online on 2026-11-07 (section 2.6 lists what was read). This is not legal advice; legal points are for Incspired Legal to confirm.

Relies on decisions D-002, D-010, D-012 to D-017, D-024, D-029, D-031, D-033 and requirements REQ-132 to REQ-176 and REQ-191 to REQ-228. Codes are explained in `docs/GUIDE.md`.

**What changed in v2.** D-033 reverses the order: the AI pipeline publishes, humans review afterwards, and AI monitors and takes down. Sections 3, 4, 5 and 6 are rewritten. Sections 1.1, 1.7, 2.1, 7, 8, 10, 11 and 12 are updated to match. Other affected documents are listed in `docs/change-review-2026-11-08-publish-first.md`.

**D-034 (no public accounts, 2026-11-08).** There are no owner accounts. "Claim" is a request-to-claim form that staff verify and set to Claimed; owner edits are correction requests staff apply; refresh reminders are emails with a link to the form; the requester check in 6.3 uses a verified email link. Tooling T-23 to T-28 and the workload note are in sections 3.1, 6.3 and 7. Legal and the staff-role questions are Q56–Q62.

**Still in conflict with recorded decisions (need your ruling, see section 12):**
- D-017, REQ-163 and REQ-168 require human approval of every AI draft before publication (decision 12).
- D-013 says "human review first" for individuals (decision 13).
- D-012 and REQ-191 target *reviewed* profiles live (decision 15).
- D-010 and D-025 say Nigeria only. This plan includes about 150 profiles from other African countries, as you asked (decision 1).

---

## 1. Inclusion criteria and the split of the 1,000

### 1.1 How the test works
A record is included only if it meets **one named criterion** (code below) **and** that criterion is backed by **an evidence URL from an approved source**. The automatic checks confirm that both are present. The person at the quick look or full review answers one question: "Does the evidence page show this criterion is met, for this exact entity, today?" No rejects with reason code `NOT_NOTABLE` and, if the profile is already live, unpublishes it. Popularity, press coverage, commercial interest and sales-prospect status are never criteria. The criterion code and evidence URL are stored on the record (tooling item T-13).

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

| Tier | What it is | Count in the 1,000 | AI drafted? | Before going live (D-033; details in 3.3) |
|---|---|---|---|---|
| **T1** | Organisation built only from structured registry or regulator fields: name, type, licence category, regulator, city, website, year founded. The text is templated, not AI. No named person. | 200 | No (the AI audit still runs) | Automatic checks + AI audit |
| **T2** | Organisation with an AI-drafted summary from its official site or annual report | 300 | Yes | Quick look, until decision 12 |
| **T3** | Entrepreneur or individual, whether AI-drafted or not | 500 | Usually | Quick look, unless decision 13 changes it |

---

## 2. Source map

### 2.1 How to read this
- **Status:** No source is approved yet. Under REQ-137, a named person must approve each one. The "Recommendation" column is my proposal for your approval.
- **Verified** = the terms, robots or list page was read on 2026-11-07. **Partly** = part was read, or the rest came from secondary sources. **Unverified** = the page could not be read.
- **Access codes:** M = manual (a person reads the page and enters facts with the URL). P = polite fetch of single pages by an identified agent, honouring robots.txt. A = API. X = export or download file.
- **Effect of publish first (decision 21):** M means a person reads the page, so the pipeline cannot gather from it automatically and the monitor cannot check it. Until an M source's terms and robots rules have been read and it is re-coded P, A or X, records depending on it are amber. Many sources below are M today, including the T1 pilot sources CBN and PenCom.
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

## 3. Pipeline (publish first, D-033)

### 3.1 Steps and who does what

| # | Step | Agent or code does | Human does | Controls (REQ) |
|---|---|---|---|---|
| 0 | **Source approval** | Drafts the source-registry entry: URL, entity types, access method, terms quote, robots check date, risk, and whether the monitor may fetch it | Supervisor approves or rejects (named approver). Legal reviews any source marked "Legal check". | REQ-136, 137, 138 |
| 1 | **Seed list** | Builds candidate lists from approved sources only. Each candidate carries a criterion code, evidence URL, country and type, against batch quotas. Suppression check (first of three). | Supervisor sets the quotas per batch | REQ-147, 192, 207 |
| 2 | **Fetch** | Fetches only approved sources. Identifies itself (`WHOISWHO-bot/1.0 (+https://wiwi.africa/bot; contact address)`), re-checks robots.txt each run, waits at least 5 seconds between requests per host, never logs in or gets around a block, and stores each page with a hash and retrieval date. Wikidata calls follow the WDQS limits. | Enters facts by hand from sources that agents may not fetch (decision 21) | REQ-138, 142 |
| 3 | **Entity resolution and de-duplication** | Matches on, in order: Wikidata QID, RC number, regulator licence number, LEI, official domain. Individuals are matched on name + organisation + role from one page. A probable duplicate makes the record amber. | A reviewer decides every probable duplicate. Individuals are never auto-merged. | REQ-145, 146 |
| 4 | **Field allowlist and sensitive scan** | Keeps only allowlisted fields (REQ-139 for individuals). Drops forbidden data (REQ-140), including party affiliation. Scans free text for phone numbers, emails, addresses, ages, party names, religion and health words. | — | REQ-139, 140, 141 |
| 5 | **Draft** | T1: fills a fixed template from structured fields, with no AI. T2/T3: the AI receives numbered source passages and returns claims, each with a passage ID and an exact quote. Code drops any claim whose quote is not found in the stored passage, whose field is outside the allowlist, or that matches the prohibited-topic list. Model and prompt version are recorded. | — | REQ-165–167 |
| 6 | **Store** | Writes the version to the staging database with a content hash. Nothing here is public. | — | REQ-211, 221 |
| 7 | **Automatic checks** | Every field has a source, retrieval date and confidence. Source links return HTTP 200. Criterion evidence is present. Suppression check (second of three). | — | REQ-142, 143, 212 |
| 8 | **AI audit** | A separate model run, on every record, checks each claim against the stored passages. It can lower a record's colour but never raise it. | — | REQ-213 |
| 9 | **Colour and route** | Code sets green, amber or red (3.2) and routes by tier (3.3). | — | REQ-214 |
| 10 | **Human before go-live, where the tier requires it** | Shows the quick-look screen or the full review screen, with a timer | Quick look for green individuals and entrepreneurs. Full review for amber. A decision on red. | REQ-215, 222 |
| 11 | **Publish** | Code re-runs the gate: no pause, under the cap, suppression check (third of three), hash matches. AI Publisher sets Published – review pending. The label and noindex apply at once. | A named human opens each ramp stage (section 6.6). No one presses publish per record. | REQ-212, 216, 217, 219, 225 |
| 12 | **Human review after go-live** | Orders the queue: S1 flags, sample, claims and corrections, then oldest. Hides the AI audit from the reviewer on sampled records. | The reviewer checks identity, the criterion, every claim against its source, and the neutral-content checklist. Approve gives "Reviewed on <date>", indexing and a sitemap entry. Reject gives a 410. **Approver ≠ importer.** | REQ-148, 163, 222 |
| 13 | **Monitor and AI takedown** | Runs the continuous checks (6.8). May unpublish with a reason code (6.7). Never edits facts. | A human reviews every AI takedown | REQ-220, 221, 223 |
| 14 | **Corrections, claims and removals** | Sends refresh reminders by email with a link to the correction form (no owner login, D-034). Hides a pending profile at once on a removal request. | Reviewers verify claims and set Claimed, and apply correction requests in wp-admin (REQ-230–232). The Editor-in-Chief handles disputes. | REQ-156–161, 164, 174, 226, 229–232 |

### 3.2 The gate: green, amber, red
Code sets the colour, not the model. Numbers are defaults until the pilot.

| Colour | Any of these | What happens |
|---|---|---|
| **Green** | Every check and the AI audit pass | Routed by tier (3.3) |
| **Amber** | A medium-confidence field; the audit is unsure; a probable duplicate; sources disagree; an individual's current role is not stated on an official page fetched in the last 30 days; the first 10 records from a new source; a current public office-holder (I1, I5); a source the monitor cannot fetch | Waits for full human review. Goes live as Reviewed, never as pending. |
| **Red** | A suppressed match; sensitive or prohibited content; an unsupported claim; a low-confidence or unsourced field; no criterion evidence; an unapproved source; a possible minor | Never published until a human clears it. Usually rejected or sent back for a redraft. |

### 3.3 What each tier needs before going live

| Tier | Before going live | After going live |
|---|---|---|
| **T1** structured-source organisation, no AI text, **no named person** | Automatic checks + AI audit | Full human review |
| **T2** organisation with AI summary | Green: a 2-minute quick look (**default until decision 12**; D-017 and REQ-168 still say a human approves every AI draft) | Full human review |
| **T3** individuals and entrepreneurs | Green: a quick look (**decision 13** may remove this) | Full human review |
| Any tier, amber | Full human review | Nothing more; it is already reviewed |
| Any tier, red | Human decision | — |

**A record that names a person is never T1.** PenCom's list, for example, gives each MD's name: the T1 record omits it.

**A quick look is not a review.** It takes about 3 minutes and answers four questions: is this the right person, is the criterion met, is the role still current on the evidence page, and is anything prohibited visible at a glance? Claim-by-claim checking comes later. The profile stays labelled and noindex until then.

### 3.4 Rules for the AI steps
- **Model.** Default to Claude Opus 5.5 for drafting and audit. At about US$0.15 per profile for both (section 10.1), quality matters more than cost.
- **Audit independence.** The audit is a fresh run that sees the passages and the draft, not the drafter's reasoning. It is still the same model family, so it may share blind spots; the human review sample measures that (6.1).
- **What exact-quote checking does not prove.** It proves the source says it, not that the source is right. Stale official pages are a known problem (2.5: the AU page shows a former Chair). A recent fetch does not prove the page is current. This is why the quick look asks whether the role is still current, and why disagreement between sources makes a record amber.
- **Data use and retention.** Under Anthropic's Commercial Terms, Anthropic "may not train models on Customer Content". API inputs and outputs are deleted within 30 days by default, and zero data retention is available on request.
- **Transfer basis.** Anthropic's data processing addendum covers EU, UK and Swiss transfers, not Nigeria. Our NDPA s.41/43 transfer basis must be recorded by us and confirmed by Legal before any individual's data is sent (REQ-169). Inference location defaults to "global"; US-only costs 1.1x.
- **Fetched text is data, never instructions.** Prompt injection in a source page cannot add a claim, because every claim must quote the stored passage exactly. It could try to sway an audit verdict or trigger a takedown, so the model holds no WordPress credentials: code acts on structured verdicts, and takedowns are reversible and human-reviewed.
- **Allowed fields only.** The prompt asks for allowlisted fields only and forbids praise, superlatives, and the topics in REQ-167.
- **Sites that block AI crawlers by name** (such as CAC). Their text is never fetched by an agent, passed to the model or monitored. People may consult them manually.

### 3.5 Where the data lives
- **Pipeline code** goes in `scripts/acquisition/` (infra area).
- **Fetched pages, drafts and personal data are never committed to git.**
- **The staging database** (REQ-211) holds every candidate version until it publishes. I read "staging database" as a private store, not the database behind staging.wiwi.africa, which D-029 makes public (decision 19).
- **Before WordPress exists,** the staging database is a git-ignored local store plus an encrypted backup, and the pilot is reviewed in hash-locked *review packs* (decision 4):
  - Each profile is a static page showing claims beside sources, plus a decision sheet.
  - The pack records a **content hash** for each approved profile.
  - WordPress imports an approved profile only if its hash matches (REQ-221), and it goes live as Reviewed.
  - If WordPress is late, reviewers can keep working this way, so their time is not lost.

---

## 4. Capacity and timeline

### 4.1 Assumptions (pre-pilot; the pilot replaces these)

| Item | Estimate | Note |
|---|---|---|
| T1 full review | 3 min | Check structured fields against the regulator list |
| T2 full review | 8 min | Fields plus 4–8 AI claims against the source |
| T3 full review | 12 min | Identity, criterion, claims, neutral-content check |
| Rework and rejects | +15% | Reviewing records that are then rejected or returned |
| Corrections, removals and QA audit | +10% | Ongoing work after publishing |
| **Effective minutes, full review** | **T1 3.8 · T2 10.1 · T3 15.2** | Estimate × 1.15 × 1.10 |
| **Quick look, new** | T3 3 min, T2 2 min (3.45 and 2.3 effective) | Assumption; a key pilot measure |
| **Review after a quick look, new** | T3 10 min, T2 7 min (12.65 and 8.86 effective) | Identity and criterion are already checked |
| **Amber share, new** | T1 10%, T2 15%, T3 20% | Assumption; a key pilot measure |
| **AI takedown reviews, new** | 2% of published × 5 min | Assumption |
| Pilot speed | 1.5× slower | Learning curve and measurement |
| "D-015 reviewer" | 7.5 focused hours/week (450 min) | About 40 profiles a week at the old blended mix, matching D-015 |
| "Full-time reviewer" | 25 focused hours/week (1,500 min) | The rest of the week goes on meetings, corrections and admin |
| Holidays | 21 Dec – 3 Jan at half capacity | Two weeks count as one |
| Review priority | At least 30% of weekly hours go to post-publication review | Keeps the measurement sample on time |

### 4.2 Publish first does not reduce the review load

| Tier | Count | Green | Human before going live | Human after going live | Minutes |
|---|---|---|---|---|---|
| T1 | 200 | 90% | Amber only | 3.8 each (green) | 760 |
| T2 | 300 | 85% | 2.3 look, or 10.1 if amber | 8.86 each (green) | 3,299 |
| T3 | 500 | 80% | 3.45 look, or 15.2 if amber | 12.65 each (green) | 7,960 |
| AI takedown reviews | 1,000 | | | 0.1 each | 100 |
| **Total** | **1,000** | | **about 4,000 min (67 h)** | **about 8,100 min (135 h)** | **12,119 (about 202 h)**, plus about 4 hours of pilot slowness |

Review-first was 11,390 minutes (190 h). **Publish first with quick looks costs about 6% more human time**, because a look plus a later review takes longer than one review. It moves the work; it does not remove it. A third of the load still sits before going live.

| Variant | Total minutes |
|---|---|
| As proposed (T2 and T3 quick look) | 12,119 (202 h) |
| T2 publishes without a look (decision 12) | 11,850 (198 h) |
| T2 and T3 publish without a look (decision 13) | 11,490 (192 h) |

### 4.3 Batches and ramp

Organisations come first, since most can go live with little human time. Every batch is Nigeria-only until Legal clears the other countries.

| Batch | Cumulative | T1 | T2 | T3 | Minutes | What goes in |
|---|---|---|---|---|---|---|
| Pilot | 50 | 15 | 15 | 20 | ≈ 920 | Section 5. Fully reviewed in shadow mode; goes live as Reviewed. |
| B1 | 100 | 35 | 15 | 0 | 303 | Banks, PFAs, universities, listed companies, startups |
| B2 | 250 | 100 | 50 | 0 | 945 | Remaining registry organisations |
| B3 | 500 | 50 | 120 | 80 | 2,807 | First new individuals. First non-Nigerian organisations once cleared (no earlier than 14 Dec). |
| B4 | 1,000 | 0 | 100 | 400 | 7,510 | Mostly individuals and entrepreneurs |

**Ramp (section 6.6):** 50, then 150, then 300, then the cap of pending profiles. A named human opens each stage after the previous stage's sample passes. The model assumes 150 in week 1, 300 in week 2 and the full cap from week 3.

### 4.4 Rewritten timeline

**Calendar:**
- **Pilot, shadow mode:** 10–18 November. Nothing goes live.
- **Plan v2 and go-live sign-off:** 20 November.
- **T0, first public record: Monday 23 November,** if the go-live gate passes (5.5). **This depends on the WordPress build, which is not started** (Gate 2 and Gate 3 are still ahead). Every date below moves with T0, week for week.
- Week 4 ends 20 December (end of brief Phase 2). Week 8 ends 17 January. Week 10 ends 31 January (sprint end). Week 12 ends 14 February.
- **Individuals beyond the pilot start in week 2 or 3,** if Legal has answered (decision 7). The live counts include the 20 pilot individuals; without Legal's answer they are 20 lower.

**When each milestone is reached.** "Live" = public, including pending. "Reviewed" = a human has approved it, and it can be found through search engines.

| Milestone | 2 × 7.5 h/week (current commitment; cap 200) | 2 × 15 h/week (cap 400) | 2 × full-time (cap 400) |
|---|---|---|---|
| 100 live | Week 1 (by 29 Nov) | Week 1 | Week 1 |
| 250 live | Week 1 (by 29 Nov) | Week 1 | Week 1 |
| 500 live | Week 3 (by 13 Dec) | Week 2 (by 6 Dec) | Week 2 (by 6 Dec) |
| 750 live | Week 8 (by 17 Jan) | Week 4 (by 20 Dec) | Week 3 (by 13 Dec) |
| **1,000 live** | **Week 13 (by 21 Feb).** About 865 by 31 Jan. | **Week 7 (by 10 Jan)** | **Week 4 (by 20 Dec)** |
| 100 reviewed | Week 1 | Week 1 | Week 1 |
| 250 reviewed | Week 2 (by 6 Dec) | Week 1 | Week 1 |
| 500 reviewed | Week 7 (by 10 Jan) | Week 4 (by 20 Dec) | Week 2 (by 6 Dec) |
| 750 reviewed | Week 11 (by 7 Feb) | Week 7 (by 10 Jan) | Week 4 (by 20 Dec) |
| **1,000 reviewed and indexable** | **Week 14 (by 28 Feb).** About 745 by 31 Jan. | **Week 8 (by 17 Jan)** | **Week 6 (by 3 Jan)** |
| 100 reviewed individuals (D-012 target) | Week 5 (by 27 Dec) | Week 3 (by 13 Dec) | Week 2 (by 6 Dec) |
| Most pending at once (all / individuals) | 200 / 75 | 236 / 150 | 236 / 150 |

**Week by week at 2 × 15 h/week:**

| Week ending | Live | Reviewed | Pending | Of which individuals |
|---|---|---|---|---|
| 29 Nov | 250 | 250 | 0 | 0 |
| 6 Dec | 500 | 386 | 114 | 34 |
| 13 Dec | 728 | 492 | 236 | 150 |
| 20 Dec | 858 | 623 | 235 | 150 |
| 27 Dec | 908 | 683 | 225 | 150 |
| 3 Jan | 956 | 744 | 212 | 150 |
| 10 Jan | 1,000 | 892 | 108 | 108 |
| 17 Jan | 1,000 | 1,000 | 0 | 0 |

### 4.5 What publish first buys, and the levers

Compared with the review-first plan in the same model and batch order:

| Reviewers | 500 live | 750 live | 1,000 live | 1,000 reviewed |
|---|---|---|---|---|
| 2 × 7.5 h: review first | 27 Dec | 31 Jan | 28 Feb | 28 Feb |
| 2 × 7.5 h: **publish first** | **13 Dec** | **17 Jan** | **21 Feb** | 28 Feb |
| 2 × 15 h: review first | 13 Dec | 27 Dec | 17 Jan | 17 Jan |
| 2 × 15 h: **publish first** | **6 Dec** | **20 Dec** | **10 Jan** | 17 Jan |
| 2 × full-time: review first | 6 Dec | 20 Dec | 27 Dec | 27 Dec |
| 2 × full-time: **publish first** | 6 Dec | **13 Dec** | **20 Dec** | 3 Jan |

**The result:**
- **It brings "live" forward by one to two weeks.** The cap on individuals is what limits it.
- **It does not bring forward search visibility, and mid-way it delays it.** Pending profiles are noindex, so 1,000 *indexable* profiles arrive no earlier. Because quick looks take reviewer time, the middle arrives later: at 2 × 15 h/week, 500 indexable moves from 13 Dec to 20 Dec, and 750 from 27 Dec to 10 Jan.
- **Reviewer hours are still the main lever.** Going from 7.5 to 15 hours a week brings 1,000 live forward by six weeks. Publish first alone brings it forward by one.
- **It adds resilience.** Most organisations keep going live if reviewers stall, and review packs still protect reviewer time if WordPress is late.

**Levers at 2 × 15 h/week:**

| Change | 1,000 live | 1,000 reviewed | Most individuals pending |
|---|---|---|---|
| **As proposed: cap 400 / 150** | **10 Jan** | **17 Jan** | **150** |
| Tighter cap, 300 / 100 | 17 Jan | 17 Jan | 100 |
| Wider cap, 600 / 300 | 27 Dec | 17 Jan | About 300 |
| Publish individuals with no quick look, cap 400 / 150 (decision 13) | 3 Jan | 17 Jan | 150 |
| No quick look and cap 600 / 300 | 20 Dec | 17 Jan | About 300 |
| Quick look takes 5 min, not 3 | 10 Jan | 24 Jan | 150 |
| Legal clears individuals for week 3 (7 Dec) | 10 Jan | 17 Jan | 150 |
| Legal clears individuals for week 5 (21 Dec) | 24 Jan | 31 Jan | 150 |
| Legal clears individuals for week 8 (11 Jan) | 7 Feb | 14 Feb | 150 |

Legal can run until about 7 December without moving the dates. After that, each week's delay costs about a week.

### 4.6 Reviewers needed to reach 1,000 by week 4, 8 and 12

| Deadline | D-015 reviewers (7.5 h/week): 1,000 live | D-015 reviewers: 1,000 reviewed | Full-time: 1,000 live | Full-time: 1,000 reviewed |
|---|---|---|---|---|
| Week 4 (20 Dec) | **6** | **9** | **2** | **3** |
| Week 8 (17 Jan) | **4** | **4** | **1**, keep 2 for cover | **2** |
| Week 12 (14 Feb) | **3** | **3** | **1**, keep 2 for cover | **1**, keep 2 for cover |

**Cheapest way to meet the 31 January sprint end:** the two existing reviewers each give about 2 days (15 focused hours) a week from 23 November: 1,000 live by 10 January and all reviewed by 17 January. Alternatively, 3 reviewers at 7.5 hours reach 1,000 live by 24 January and all reviewed by 31 January.

**Sensitivity:** if the pilot shows T3 takes 25 minutes instead of 12, the total load rises by about half, and every date moves accordingly. The pilot result is the number that matters.

---

## 5. Pilot: 50 profiles in shadow mode

The pilot no longer only times human review. It must show whether the AI audit is good enough to let records go live without a person. **Nothing goes live during the pilot.** The pipeline runs in full and records what it *would* have published; humans review everything without seeing that verdict.

### 5.1 Composition (Nigeria only)
- **15 T1 organisations:** 8 CBN-licensed banks, 4 PFAs, 3 NUC universities.
- **15 T2 organisations:** 5 listed companies (from their own IR pages), 6 startups (Ventures Platform or Google accelerator), 4 public institutions or foundations.
- **10 entrepreneurs (E1):** founders of pilot startups or companies.
- **10 individuals:** 3 ministers (I1), 3 bank CEOs (I2), 2 vice-chancellors (I3), 2 academy fellows (I3).

Ministers are amber under 3.2. They stay in the pilot because they test identity and the party-field rule.

### 5.2 Schedule

| Date | Step | Owner |
|---|---|---|
| Mon 9 Nov | Decide items 1 and 3–21 (section 12); name the reviewers; send Legal the publish-first questions (6.5) | You |
| Tue 10 Nov | Approve pilot sources (2.2–2.4, marked "Approve for pilot"); Anthropic API account live (Q28) | You |
| Tue 10 Nov | Reviewer onboarding (2 h) using `docs/review-guidelines.md` (agent drafts it on 9 Nov) | You and reviewers |
| 10–11 Nov | Agents seed, fetch, resolve, draft and audit the organisations (T1/T2); colours recorded and hidden; review packs built | Agents |
| Wed 11 Nov | Calibration: both reviewers review the same 10 profiles and compare | Reviewers |
| Fri 13 Nov | Legal confirms the AI transfer basis (REQ-169). **Individual drafting waits for this.** | Incspired Legal |
| 13–14 Nov | Agents draft and audit the 20 T3 profiles | Agents |
| 12–18 Nov | For T2 and T3, a quick look first (timed), then full review by the other reviewer, who cannot see the look's result. 20 profiles are reviewed by **both** reviewers to measure agreement. | Reviewers |
| 11–18 Nov | The monitor runs against the 50 stored records, in dry-run mode | Agents |
| 18–19 Nov | Supervisor re-checks all 50 against their sources (a 100% audit) | You |
| Thu 19 Nov | Agent compares the AI's colours with human findings and produces the report (5.3) | Agents |
| Fri 20 Nov | This plan is updated with measured numbers (brief Phase 1 exit: "a clear acquisition plan"); go/no-go on the gate in 5.5 | Agents, then your approval |

Reviewer time in the pilot is about 920 minutes (15 hours) across both reviewers, plus 2 hours each of onboarding.

### 5.3 What the pilot measures

| Measure | How | Feeds |
|---|---|---|
| **AI audit miss rate:** S1 and S2 errors that humans found in records the gate marked green | AI colour against human and supervisor findings | Whether automatic publishing is safe; thresholds in 6.1 |
| **Quick-look catch rate:** how many S1 and S2 errors a look finds compared with full review | Look decisions against full review | Whether the look is worth its 3 minutes (decision 13) |
| Green, amber and red shares by tier | Gate logs | 4.1 assumptions, the green share behind every date |
| False amber and red | Human found nothing wrong | Gate tuning |
| Monitor false alarms | Dry-run findings against stored records | Takedown rules (6.7) |
| Minutes per profile: quick look, full review, review after a look | Timer in the review pack or queue (median and 90th percentile) | Section 4 capacity |
| First-pass approval rate; reject reasons | Reason codes | Draft quality, source choice |
| Draft error rate by field and severity | Reviewer findings | Prompt and template fixes |
| AI claim-drop rate; claims left unsupported | Verifier logs | Drafting prompt |
| Source quality: stale, wrong, link rot, by source | Errors traced to source | Source approvals (2.x) |
| Disambiguation failures | Duplicate and identity errors | Matching rules |
| Inter-reviewer agreement | Double-reviewed 20 | Guidelines, calibration |
| AI cost and agent time per profile | Usage logs | Section 10 cost |

**What 50 records cannot show.** Zero errors in 30 green records still leaves a 95% upper bound of about 10%; zero in 100 leaves about 3%. The pilot can catch a bad pipeline. It cannot prove a good one. That is why the first 100 live records of each tier get full human review right away (6.1) and the ramp starts small.

### 5.4 Decision rules after the pilot

| Result | Action |
|---|---|
| Any S1 error in a record the gate marked green | That tier does not publish automatically. Fix the cause and re-pilot 25 profiles of that type. |
| S2 in green records above 3% (organisations) or 2% (individuals) | Revise the prompts and gate. Run a 25-profile second round for that tier. |
| Quick look misses an S1 error that full review finds | Lengthen the look or make that case amber; re-test before individuals go live |
| Green share below 50% in a tier | Rework the gate or sources; reset the date model, because amber costs full review before going live |
| Median T3 review above 20 min | Choose one: cut T3 fields to the core REQ-139 set; raise reviewer hours; or move volume from T3 to T2 |
| Monitor false alarms above 10% of records | Hold automatic takedown for that code; flag to a human instead |
| A source causes more than 10% of errors | Downgrade it to "lead only" or reject it |
| Inter-reviewer agreement below 90% on approve/reject | Run a second calibration and tighten the criteria wording |
| All within limits | T0 on 23 November, if the gate in 5.5 passes |

### 5.5 Go-live gate (T0)
Publish first needs all its safeguards working before the first record goes out, not after. **All of these must pass on the live environment:**
1. Publication states and permitted transitions (REQ-210) and the publish gate (REQ-212).
2. Label, noindex, sitemap exclusion and listing rules, with the state-by-surface test matrix passing (REQ-208, 216–218).
3. 410, purge and restore (REQ-209); kill switch (REQ-193); bulk unpublish of pending (REQ-227).
4. AI service roles proven unable to edit (REQ-219), and AI takedown and its human queue working (REQ-220, 221).
5. Monitor running with a heartbeat (REQ-223); pause and cap firing in a drill (REQ-224, 225).
6. Removal, correction and suppression workflows (REQ-156–158, 207); audit log (REQ-228).
7. Legal items for organisations (6.5); pilot rules in 5.4 passed; supervisor sign-off.

**Individuals need extra items:** the Legal items for individuals (6.5, decision 7), and the quick-look result from the pilot.

**Fire drill:** before T0, the team triggers each of these on the live site with sample data and times them: an S1 takedown, a pause, the kill switch and a removal.

---

## 6. Quality and risk controls

### 6.1 Error definitions, sampling and thresholds that pause publishing

| Severity | Definition | Examples |
|---|---|---|
| **S1 critical** | Could harm a person or breach law | Wrong person (identity mix-up); a claim on a prohibited topic; sensitive data published (party, religion, health, age); a suppressed person or organisation published; an invented claim with no source |
| **S2 material** | A fact is wrong | Wrong title, role, organisation, date, licence status; the source does not support the claim; the criterion is not actually met |
| **S3 minor** | Presentation | Typo, formatting, a dead link where the fact is still right |

**Human review sample.** Every record is reviewed eventually; the sample sets the order and supplies the error rate:
- **Burn-in:** the first 100 records of each tier go to the front of the queue.
- **After that:** a random 20% of each lot, at least 20 records, including at least 10 individuals where the lot has them.
- **Deadline:** 3 business days. The reviewer does not see the AI audit on sampled records.
- **Checking the reviewers:** 5% of records reviewed that week, at least 10, are re-checked by the other reviewer or the supervisor.

**Proposed thresholds (all settings; decision 17).** The S2 numbers keep the earlier 2% and 3% limits, turned into rules that a small sample can apply:

| Trigger | Automatic response |
|---|---|
| **Any S1** found by anyone or anything | AI takedown of that profile at once (410). **Pause that tier and source.** S1 rules are unchanged. |
| **Two S1 in 7 days,** or S1 from two sources | **Global pause.** Supervisor decides on the kill switch (REQ-193) or bulk unpublish (REQ-227). |
| **Individuals (T3):** 3 or more S2 in the last 100 reviewed | Pause T3. Reviewers clear the affected lot first. Fix guidelines or prompts. |
| **T1 or T2, each separately:** 4 or more S2 in the last 100 reviewed | Pause that tier; same steps |
| **Any tier, early:** 2 or more S2 in its first 30 reviewed, or in the first 30 after a restart | Pause that tier |
| **One source:** more than 10% of its last 20 reviewed records have an S1 or S2 error | Pause that source |
| **No evidence:** a sample overdue by more than 2 business days | Pause that tier until it is reviewed. No measurement, no publishing. |
| **Oldest pending** older than 21 days | Pause that tier, so review cannot quietly fall behind |
| **Monitor heartbeat** missed for 48 hours | Global pause |
| **AI takedowns:** more than 20 in 24 hours or 5% of live in 7 days | Global pause. Alert the supervisor; it may be a bug. Above this limit, new non-S1 findings are flagged rather than taken down. |
| Humans overturn more than 20% of the last 30 AI takedowns | Alert; tune that reason code. No pause. |
| Amber plus red above 40% of a lot | Pause that source until the cause is known |
| Valid correction requests above 1% of published profiles in a week | Alert; audit the source and prompt |
| Removal requests above 2% of individual profiles in a month | Pause T3. Supervisor and Legal review the inclusion criteria for that class. |

**Resuming:** only a named human, with a recorded reason. Publishing restarts one ramp stage lower. A pause stops new publishing only. It does not unpublish anything except the S1 profile; the supervisor can do that separately with bulk unpublish.

**How these rules behave (chance one 100-record or 30-record window fires):**

| Rule | True error rate → chance it fires |
|---|---|
| T3: 3+ in 100 | 1% → 8% · 2% → 32% · 5% → 88% |
| T1/T2: 4+ in 100 | 1.5% → 6% · 3% → 35% · 6% → 86% |
| T3: 2+ in first 30 | 1% → 4% · 5% → 45% · 8% → 70% |
| T1/T2: 2+ in first 30 | 1.5% → 7% · 6% → 54% · 8% → 70% |

So these rules rarely stop a good pipeline and usually stop a bad one, but a rate just over the limit can run for a while before they fire. That is why the cap also exists.

### 6.2 Corrections log
Every change to a published profile writes an entry (REQ-164): what changed, old and new value, reason, who made it, and the source. Corrections are resolved within 7 days (REQ-158). The AI never makes these changes.

### 6.3 Removal and suppression
- **Targets (unchanged):**
  - Acknowledge within 1 business day.
  - Hide within 2 business days of a valid request from the subject (REQ-158). Hiding means a 410 and noindex, removal from the sitemap and internal search, and a CDN purge (REQ-209).
  - Add the subject to the permanent suppression list (REQ-207).
- **New: a removal request on a pending profile hides it at once** (REQ-226), since no human has yet vouched for it. It is reversible, and rate-limited so it cannot be used to knock out rivals in bulk.
- **The suppression list is checked three times** (seed, pre-check, publish), and the monitor re-checks live profiles against it (6.8), so a suppressed entity can never come back through a new import.
- **The objection right is real.**
  - NDPA s.36 requires us to stop unless we show overriding legitimate grounds.
  - POPIA s.11(3) requires a stop on reasonable grounds.
  - **Default:** a subject who objects is removed and suppressed. The Editor-in-Chief may escalate only office-holders (I1/I5) to Legal.
- **How a requester's identity is checked** is still open (wireframe finding 15). With no accounts (D-034), the requester's email is verified by a single-use signed link (REQ-233). Proposed default: a request from the subject's official work email, or from the organisation's domain, is accepted. Anything else goes to staff judgement.
- **Search engines:** after a removal, staff file search-engine removal requests (N21). Pending profiles should rarely need this, since they are noindex.

### 6.4 Legal risks

| Risk | What applies | Control |
|---|---|---|
| **Publishing before a human checks (new)** | **Publication happens when the page goes live.** The label may help or may read as an admission that we published unchecked. Whether it reduces liability is a question for Legal; I am not assuming it does. | Tiers, quick look for individuals, cap, fast takedown. Legal question 2 in 6.5. |
| **Accuracy duty (new)** | The NDPA has a data-accuracy principle. I recall it as s.24, but could not re-read it today: the readable copy of the Act is a scanned image. **Unverified.** | Legal to confirm whether "live, pending review" meets it (question 3 in 6.5) |
| **Solely automated decisions (new)** | The NDPA has a right about decisions based solely on automated processing. I recall it as s.37. **Unverified, same reason.** It may or may not cover an AI publishing or taking down a profile. | Humans review every takedown and every profile. Legal question 4 in 6.5. |
| Lawful basis for individuals (NDPA s.25) | Legitimate interests fail if the subject "would not have a reasonable expectation" of the processing (s.25(2)(c)). The journalism exemption (s.3(2)(d)) still leaves s.25 in force. **Publish first makes this harder**, because AI-compiled pages that no human has checked are further from what a person might expect. | Professional, already-public facts only (REQ-139). A legitimate interests assessment (GAID Schedule 8) that covers publish first. A disclosure page (REQ-130, 170). An easy objection route. |
| Sensitive data (s.30, s.65) | Political affiliation, religion, ethnicity and health are sensitive, with no "made public" exception | Excluded from the schema and scanned for (REQ-140, 141). No party field for office-holders. A hit is red before publishing and an S1 takedown after. |
| DPIA (s.28; GAID) | Required for likely high risk; GAID requires one before deploying AI (summaries read, not the GAID text) | DPIA before the first individual is published, **covering automatic publishing and AI takedown** |
| Registration as a data controller of major importance (s.44, s.65; GAID tiers) | GAID tiers are reportedly over 200, over 1,000 and over 5,000 subjects in 6 months, with fees of about ₦100k to ₦1m (unverified). 1,000 profiles would likely qualify. | Legal confirms the tier, registration, the DPO (s.32) and the audit-return duty |
| Cross-border transfer (s.41–43) | Anthropic's addendum has no Nigerian mechanism | Legal records the basis before individuals are drafted. **The audit and monitor also send data**, so the basis must cover them. |
| Defamation (civil; criminal defamation remains in the Criminal Code and Penal Code in some states) | No higher threshold for public figures; truth is a defence, with the burden on us | Source per claim, audit, neutral-content checks, no allegations, fast takedown and correction (REQ-142, 163, 158, 220). **Human checking now comes after publication.** |
| Cybercrimes Act s.24 (amended 2024) | Narrowed. Exact amended text not read. | The same controls; Legal to confirm |
| Noindex is not privacy (new) | The page is public. Google says the rule "must not be blocked by a robots.txt file" to work, and that "some search engines might interpret the `noindex` rule differently". Scrapers may ignore it altogether. | Treat every pending page as fully public; REQ-217 surfaces; quick takedown |
| Other countries | Ghana: every controller registers (Act 843 s.27). Kenya: registration above thresholds (s.18). POPIA: objection stops processing. | Per-country Legal clearance before any batch from that country |
| Copyright and terms of use | Compilations and government works are protected; site terms are contracts. **Continuous monitoring is repeated automated access.** | Facts only, cited; terms checked per source, including for monitoring; no mirroring |

Penalties for context: NDPA s.48 sets a maximum of ₦10m or 2% of annual gross revenue for major-importance controllers. The NDPC fined MultiChoice ₦766.2m in 2025.

### 6.5 What we need from Incspired Legal, by batch

**New questions for Legal, to go with the 13 November request (send by 9 November):**
1. Does automatic publication of AI-compiled personal data, reviewed by a human afterwards, fit legitimate interests under s.25?
2. Does the label ("Compiled automatically from public sources. Human review pending.") help, hurt or make no difference if a pending profile is wrong? Please approve or change the wording.
3. Does publishing before human review meet the accuracy principle?
4. Do automatic publishing or AI takedowns count as solely automated decisions? Does the human review after each one satisfy any duty that applies?
5. May organisations publish without a person looking, given that their records hold no named person?
6. Does the quick look for individuals change your answers? Would publishing individuals with no human look change them?

| Before | Legal item | Blocks |
|---|---|---|
| Any individual's data goes to the AI (13 Nov) | AI transfer basis (s.41/43), covering drafting, audit and monitoring, and choice of inference location; confirmation of the Anthropic processor terms (REQ-169) | T3 drafting in the pilot |
| First **organisation** published | Terms and Privacy pages; wording for the "how we compile" page (REQ-130), the AI disclosure (REQ-170) and the label (REQ-216); questions 2 and 5 | T0 |
| First **individual** published | Legitimate interests assessment; DPIA; registration tier, DPO and audit-return duty; whether and how subjects are told (REQ-159); sign-off of the neutral-content checklist; a written position on D-016; questions 1, 3, 4, 6 | Publishing T3 (decision 7) |
| First **office-holder** batch | Confirm the office-holder field set and that party affiliation is excluded | I1 profiles |
| First **non-Nigerian** batch (B3) | Per-country position: Ghana registration, Kenya threshold, POPIA | Non-Nigerian profiles |
| Any **licensed** dataset (NGX, Big Deal) | Contract review: display rights, attribution, share-alike | That source |
| Images or logos | Personality rights, trademark | Decision 6 |

### 6.6 Cap on pending profiles (proposed; decision 16)
- **Total:** the lower of **400** or **two weeks of measured review capacity.** That is about 200 at 2 × 7.5 h/week and 400 at 2 × 15 h/week or more.
- **Individuals and entrepreneurs:** at most **150,** or the same proportion of a lower cap (75 at a cap of 200).
- **Why two weeks:** if something is badly wrong, reviewers could re-check every pending record within two weeks. It also limits the worst case: at the limit error rates, 150 individuals at 2% is about 3 people with a wrong fact live at any time, and 250 organisations at 3% is about 8.
- **Ramp:** 50, 150, 300, then the cap. A named human opens each stage once the previous stage's sample passes. No new individuals go live before the 300 stage.
- **At the cap,** records wait in the staging database. Reviewing one frees one.
- **Raising it:** a named human may raise the individuals' limit once 200 individuals have been reviewed with no S1 and S2 within the limit. Section 4.5 shows what that buys: 600 / 300 brings 1,000 live forward by two weeks, for twice the exposure.

### 6.7 AI takedowns
The monitor may unpublish, reversibly, with a reason code. It never edits facts. Everything goes in the audit log. The public 410 page never shows or implies the reason, so a takedown cannot itself become a defamatory statement.

| Reason code | Class | Applies to pending | Applies to reviewed, claimed, verified or sponsored | Who may restore |
|---|---|---|---|---|
| `SENSITIVE_DATA`, `PROHIBITED_TOPIC`, `SUPPRESSED_MATCH`, `IDENTITY_DOUBT` | S1 | Takedown | Takedown | Human only |
| `SOURCE_CONTRADICTS`, `ROLE_ENDED`, `SOURCE_CONFLICT`, `NOT_ACTIVE` | Fact | Takedown | Flag to a human, due in 1 business day | Human only |
| `SUBJECT_REQUEST` | Request | Takedown at once | Flag; REQ-158 applies | Human only |
| `SOURCE_WITHDRAWN` (source approval removed) | Compliance | Takedown | Flag | Human only |
| `SOURCE_DEAD` (all sources for a key field gone for 7 days) | Technical | Takedown | Flag | AI, if the source recovers, the hash matches and the whole gate passes again |

**Republishing:** always a version with a matching content hash, after a full pass of the publish gate. A record corrected after a takedown is a new version and goes through the whole pipeline.

**Human review of every takedown:** S1 class within 1 business day, others within 2. Outcomes: uphold, overturn or correct. Overturn rates are tracked by reason code. Decision 18 covers the choices in this table.

### 6.8 Continuous checks on published profiles

| Check | What it does | Pending / reviewed | AI? | If it finds a problem |
|---|---|---|---|---|
| **Source link health** | Requests each cited URL, respecting robots.txt, per-host delay and the source's access code | Daily / weekly | No | One or two failures: wait. Three over 72 hours: flag (S3). All sources for a key field gone for 7 days: `SOURCE_DEAD`. |
| **Source content change** | Compares the normalised page hash with the stored one. If changed, checks whether each quoted passage is still there. | Same fetch | Only if a quote has gone | Quote present: log it. Quote gone: AI compares old and new text; a contradiction is `SOURCE_CONTRADICTS` or `ROLE_ENDED`. It never overwrites (REQ-161). |
| **Prohibited-topic scan** | Pattern scan and AI read of the profile text and its cited passages. Also runs when the term list changes. | Weekly / monthly | Patterns, then AI | `PROHIBITED_TOPIC` or `SENSITIVE_DATA`, S1 |
| **Conflicts between sources** | Compares key fields (current role, organisation, active status, identity) across cited sources and Wikidata | Weekly / monthly | Code first; AI only for free text | `SOURCE_CONFLICT` or `IDENTITY_DOUBT` |
| **Suppression re-check (added)** | Compares live profiles with the suppression list | On every list change, and daily | No | `SUPPRESSED_MATCH`, S1 |
| **Heartbeat (added)** | Each run reports in | Each run | No | Missed for 48 hours: global pause |

**What the monitor cannot see:**
- It reads only approved sources that allow automated access. Sources coded M, and sites such as CAC, are not monitored, which is why records depending on them are amber (decision 21).
- It does not search the web or read news (D-014). It will not learn of a scandal or a change of job unless a cited source shows it.
- Being correct on the page does not make the source right.

### 6.9 What stays exactly as it was (control 6)
- **The kill switch** (REQ-193), which also has a new companion: bulk unpublish of pending profiles (REQ-227), because the kill switch covers unclaimed individuals only and pending organisations would otherwise have no equivalent.
- **Three suppression checks** and the permanent list (REQ-207).
- **Removal SLA** (REQ-158).
- **S1 rules:** any S1 unpublishes that profile at once and pauses its tier and source.

---

## 7. WordPress tooling needed (input for the Gate 2 architecture revision)
These feed the WordPress architecture (D-031). "Proposed" items are not yet in `docs/requirements.md`. They become new REQs only if you approve them (no scope creep without approval).

| ID | Tool | Must do | REQ |
|---|---|---|---|
| T-1 | Source registry | Admin screen with name, entity types, URL, access method, terms summary, robots check date, approver, status, whether the monitor may fetch it. The importer refuses any source that is not Approved. | REQ-136, 137 |
| T-2 | Import tool and staging database | CSV/JSON and Wikidata SPARQL adapters. **Dry run** with a diff. Idempotent on external keys (QID, RC, licence number, LEI, domain). Batch ID on every record. Suppression check. Duplicate detection. Never overwrites a reviewed field; flags a change instead. | REQ-134, 135, 145–147, 161, 207, 211 |
| T-3 | Provenance per field | Source URL, retrieval date, confidence, method, source hash, passage ID. Queryable for reports, so it should not be buried in post meta. | REQ-142, 143 |
| T-4 | AI draft and audit store | Claims with passage quotes, a log of dropped claims, audit verdict per claim, model, prompt version, internal AI-assisted flag | REQ-166–168, 213 |
| T-5 | Queues | Quick look. Full review after publication, ordered as in 6.1. Amber and red. **Claim beside source.** Neutral-content checklist. Reason codes and error severity. Approver ≠ importer enforced. Per-reviewer workload. | REQ-148, 163, 206, 215, 222 |
| T-6 | Duplicate and merge queue | Human merge with an audit entry; never automatic for individuals | REQ-146 |
| T-7 | **Publication states and publish gate** | The states and transitions in REQ-210. The gate in code. Unpublish gives a 410, noindex, sitemap removal and purge. Kill switch. Bulk unpublish of pending. | REQ-193, 208–210, 212, 227 |
| T-8 | Corrections, removal and suppression | Corrections log; removal workflow with deadline timers; permanent suppression list checked at every import; pending profiles hide at once | REQ-156–158, 164, 207, 226 |
| T-9 | **Pause and cap** | Automatic and manual pause by tier, source and globally; cap, age limit and ramp stages; all as settings; audited | REQ-224, 225 |
| T-10 | **Proposed:** review timer | Records active seconds per quick look and review, per reviewer | (new; extends REQ-149) |
| T-11 | **Hash lock** | Published version must match the checked hash; review-pack items import only if the hash matches | REQ-221 |
| T-12 | **Reviewer re-check queue** | Random 5% of reviewed profiles each week for the second checker; result feeds the error-rate dashboard | REQ-222 (sample); (new for the re-check) |
| T-13 | **Proposed:** inclusion criterion field | Criterion code plus evidence URL, required by the gate and shown at look and review | (new; REQ-212 relies on it) |
| T-14 | ~~Sampling mode~~ | **Dropped.** Decision 2 is overtaken by D-033. | — |
| T-15 | Dashboard | Live, reviewed and pending by tier, source and country; oldest pending; error rates against thresholds; pause state; takedowns and outcomes; monitor heartbeat | REQ-149, 171, 228 |
| T-16 | Sensitive-pattern scan | Includes Nigerian party names and acronyms, religion and health terms; used before publishing and by the monitor | REQ-141, 223 |
| T-17 | Export | All profiles with provenance, in open formats | REQ-131 |
| T-18 | **Label and noindex** | Label on page, cards and share previews; "Reviewed on <date>"; `X-Robots-Tag` and meta noindex; no sitemap, feed or schema.org for pending, **including from any SEO plugin**; listing rules; state-by-surface test matrix | REQ-216–218 |
| T-19 | **AI service roles** | AI Publisher and AI Monitor with scoped, revocable tokens. Tests prove they cannot edit fields or change settings. | REQ-219 |
| T-20 | **AI takedown and its queue** | Reason codes; reversible; human review with deadlines; restore rules | REQ-220, 221 |
| T-21 | **Monitor** | The six checks in 6.8 with cadence settings and heartbeat. **Must run somewhere that allows continuous jobs:** shared cPanel may not (Q26, ADR-011). A small runner outside WordPress is likely. | REQ-223 |
| T-22 | **Audit log for AI actors** | Service role, reason code and content hash on each action | REQ-228 |
| T-23 | **No public accounts** | Front-end registration closed; no public login link; wp-login limited to staff; the only users are staff and the two service roles. Test that the site exposes no registration or login route. | REQ-229 |
| T-24 | **Request to claim and claim verification** | Public form with evidence upload and email verification; staff screen with evidence, method, rival claims; only staff set Claimed. | REQ-230, 231 |
| T-25 | **Correction request** | Form and staff handling through the review queue; version history records requester and approver. Sensitive fields need a second approver (Q57). | REQ-232 |
| T-26 | **Single-use signed links** | Purpose-bound, single-use, expiring, hashed tokens for nominator, nominee, intake, consent and claim email verification. | REQ-233 |
| T-27 | **Staff roles and two-factor** | Seven roles with a tested capability matrix; two-factor enforced; same-day deactivation. | REQ-235, 047 |
| T-28 | **Offline score entry** | Awards manager screen for scores, conflicts, shortlist and results (flagged). | REQ-234 |

**Workload note (D-034):** with no owner accounts, every claim, correction and enquiry change lands on staff. Claims and corrections already sit at the top of the review queue (REQ-226); plan about 5 to 10 minutes per claim or correction on top of the review estimates in 4.1. The sales prospect list in section 9 can still point prospects to the claim form.

**Build order that unblocks acquisition soonest:** T-7, T-18, T-19 and T-11 first (nothing can go live safely without them), then T-2 with T-3, T-1, T-9, T-20, T-21, T-5, T-8, then the rest. All items in the go-live gate (5.5) come before T0.

---

## 8. Human tasks, owners and dates
Owner "You" = the supervisor. Dates are ceilings; earlier is better.

| # | Task | Owner | Needed by | Blocks |
|---|---|---|---|---|
| H1 | Decide items 1 and 3–21 in section 12 | You | Mon 9 Nov | Everything |
| H2 | Name the two reviewers and their weekly hours (Q4) | You | Mon 9 Nov | Pilot |
| H3 | Name the Editor-in-Chief (REQ-174), who handles disputes and the named-award list | You | Fri 13 Nov | T0 |
| H4 | Open the Anthropic API account; set the organisation's data settings; decide on zero data retention (Q28) | You | Tue 10 Nov | AI drafting |
| H5 | Approve pilot sources (named approver per REQ-137) | You | Tue 10 Nov | Pilot fetch |
| H6 | Reviewer onboarding (2 h) and calibration (10 profiles) | You and reviewers | 10–11 Nov | Pilot review |
| H7 | Send Legal the publish-first questions (6.5) | You | Mon 9 Nov | First individual |
| H8 | Legal: AI transfer basis, covering drafting, audit and monitoring | Incspired Legal | Fri 13 Nov (already requested, Q5) | T3 drafting |
| H9 | Legal: Terms, Privacy, disclosure and label wording; questions 2 and 5 | Incspired Legal | Fri 20 Nov | T0 |
| H10 | Legal: legitimate interests assessment, DPIA, registration tier, DPO, notification position; questions 1, 3, 4, 6 | Incspired Legal | Fri 20 Nov; no date impact up to about 7 Dec | First individual published |
| H11 | Approve the Gate 2 architecture revision for WordPress, including D-033 | You | As soon as it is ready | T0 |
| H12 | Pilot audit of the 50 profiles | You | Thu 19 Nov | Plan update |
| H13 | Approve the plan update, reviewer hours and go/no-go on the gate in 5.5 | You | Fri 20 Nov | T0 |
| H14 | Fire drill on the live site: S1 takedown, pause, kill switch, removal | You and Editor-in-Chief | Before T0 | T0 |
| H15 | Approve B1 and B2 sources (the "after terms read" rows, and any re-coded from M) | You | Fri 20 Nov | B1, B2 |
| H16 | Ask NGX for a reference-data licence quote (optional) | You | Thu 12 Nov | Optional O1 route |
| H17 | Legal: office-holder field set | Incspired Legal | Fri 27 Nov | Office-holders |
| H18 | Third reviewer onboarded, if the pilot says so | You | Mon 30 Nov | Section 4.6 targets |
| H19 | Legal: per-country positions (Ghana, Kenya, South Africa) | Incspired Legal | Fri 11 Dec | B3 non-Nigerian |
| H20 | **Each business day:** clear AI takedowns and S1 flags | Reviewers | Daily from T0 | SLA in 6.7 |
| H21 | Open each ramp stage after its sample passes; lift pauses | You or Editor-in-Chief | As they arise, within 1 business day | Publishing |
| H22 | Weekly: review error rates, cap and oldest pending | You or Editor-in-Chief | Every Friday | Control |

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

### 10.1 Cost (first 1,000 profiles, including the pilot, through 28 February)

| Item | Basis | Estimate |
|---|---|---|
| AI drafting (Claude Opus 5.5, US$4 input / US$20 output per million tokens) | About 11k input and 2.5k output tokens per profile across the drafting and resolution calls, so about US$0.09. Allow +50% for re-drafts and rejects. 800 profiles use AI (T2 + T3). | About US$110 |
| **AI audit (new)** | About 8k input and 1k output per record, so about US$0.05, on all 1,000, +50% for re-runs | About US$80 |
| **AI monitoring (new)** | About US$0.02 per scan: monthly on all 1,000 (US$22), weekly on up to about 240 pending (about US$21 a month at peak), about US$5 a month for changed pages. Assumption: 10% of pages change each month. | Up to about US$50 a month; about US$145 for 3 months. Falls to about US$30 a month once all are reviewed. |
| **AI total** | The Batch API (50% off) would apply to scans and part of the audit | **About US$335. Proposed cap: US$450** (was US$250) |
| **Monitor runner (new)** | Shared cPanel may not allow continuous jobs (Q26). The architecture's fallback VPS is about US$10–20 a month. | US$0–60 for 3 months |
| Wikidata, GLEIF, regulator lists, official sites | Free | US$0 |
| NGX reference data (optional) | Price by quote; not published | Unknown. Only if decision 9 chooses it. |
| Africa: The Big Deal (optional) | About US$1,500 a year enterprise; publication licence unconfirmed | Not in the base case |
| OpenCorporates, Crunchbase, CAC–NIBSS API | Not recommended | US$0 |
| Reviewer time | About 206 h looks, reviews and takedowns + 10 h re-checks + 8 h onboarding and calibration ≈ **225 reviewer-hours** (was 215) | 225 × hourly rate. Illustration only: at ₦3,000, ₦6,000 or ₦10,000 an hour, that is ₦0.68m, ₦1.35m or ₦2.25m. **You supply the real rate.** |
| Supervisor time | About 5–7 h a week (approvals, ramp stages, pauses, error-rate review) for 8–14 weeks | Internal |
| NDPC registration and compliance audit (if Legal says it applies) | GAID fees reportedly ₦100k to ₦1m by tier, plus the fee of a licensed compliance organisation (unverified) | ₦0.1m to ₦1m+ |
| Hosting and storage | Already covered by the architecture cost (section 9) | Negligible |

Prices were read on 2026-11-07 from the official Anthropic pricing page. **Reviewer time is still most of the cost.**

### 10.2 Top risks

| # | Risk | Likelihood / impact | Mitigation |
|---|---|---|---|
| 1 | **A wrong fact about a real person is public before a full human check.** The label may not protect us. | Medium / severe | Quick look; amber rules; cap; S1 takedown; Legal first (decision 7); a choice remains open (decision 13) |
| 2 | **Wrong-person errors** for individuals (common Nigerian names) | Medium / severe | The name + organisation + role rule (1.5); no auto-merge; quick look; `IDENTITY_DOUBT` takedown; S1 pause |
| 3 | **Legal objection or NDPC exposure**, now larger because of automatic publishing | Medium / high | Organisations first; individuals wait for Legal (decision 7); kill switch; no party field; new questions in 6.5 |
| 4 | **WordPress safeguards late.** Publish first cannot start until the gate in 5.5 passes. It is now the critical path to T0, and the build has not started. | High / high | Build order in section 7; review packs keep reviewers productive; organisations can go first |
| 5 | **Review debt:** with profiles already live, review feels less urgent and the backlog ages | High / high | Cap; 21-day pause; sample-overdue pause; weekly review |
| 6 | **Review capacity:** 2 reviewers at the D-015 level reach 1,000 reviewed only by 28 February | High / high | Decision 3 |
| 7 | **Stale official data passes every automatic check,** because the quote is on the page but the page is wrong (AU page, NUC vice-chancellor names) | High / medium | Conflict check between sources; retrieval date per field; quick look asks if the role is current; refresh cycle (REQ-160) |
| 8 | **AI audit shares blind spots with the drafter** | Medium / high | Shadow pilot measures misses; the sample hides the audit from reviewers; thresholds pause |
| 9 | **Most T1 sources are manual-only today,** so little can be gathered or monitored automatically and amber swells | Medium / high | Decision 21; agents read terms and re-code sources where allowed |
| 10 | **False or malicious takedowns** (bug, prompt injection, bulk removal requests) | Medium / medium | Reversible; takedown rate pause; human review of all; rate limits |
| 11 | **Readers and customers see "Human review pending"** on a brand built on credibility (brief 3.4, 11.3, 15.4) | Medium / medium | Indexable lists show only reviewed profiles (REQ-218); no sponsorship or awards on pending (REQ-226); keep pending counts small |
| 12 | **Noindex is not privacy:** pending pages can be copied or indexed by crawlers that ignore the rule | Medium / medium | Treat as fully public; takedown and N21 |
| 13 | **Prospect data leaking into profiles** | Low / high | Separate store; reviewers blind to prospect status; profiles created only through the pipeline (section 9) |
| 14 | **Minors** via youth-programme sources | Low / severe | Cohorts at least 5 years old only; no age field; possible minor is red |

---

## 11. One-page summary

**Goal:** 1,000 published profiles: 500 organisations, 200 entrepreneurs, 300 notable individuals. 850 are Nigerian; about 150 come from Ghana, Kenya, South Africa and pan-African bodies, starting in B3 after Legal clears each country.

**Direction (D-033):** the AI pipeline gathers, drafts, verifies and publishes; humans review afterwards; AI monitors and takes down. Every record still gets a human review.

**Notability is a pass/fail test.** Each profile needs one named criterion (O1–O7, E1–E2, I1–I6) proven by an evidence URL from an approved source. Commercial interest is never a criterion.

**How a record goes live:**
- **Structured-source organisations** with no named person: automatic checks plus AI audit.
- **Individuals and entrepreneurs:** a 3-minute human look first (you may change this).
- **AI-drafted organisations:** a 2-minute look, as the default until you rule.
- **Amber:** waits for full human review. **Red:** not published.

**While pending:** public, labelled "Compiled automatically from public sources. Human review pending." with a correct/remove link, noindex, and left out of the sitemap and indexable lists. After review: "Reviewed on <date>" and indexable.

**Controls:**
- **AI takedown:** reversible, with a reason code; it never edits facts; a human reviews every one.
- **Automatic pause:** any S1; individuals 3+ S2 in the last 100; organisations 4+; sample overdue; oldest pending over 21 days; monitor silent for 48 hours.
- **Cap:** 400 pending, 150 of them individuals, or two weeks of review capacity if lower. Ramp 50, 150, 300, 400.
- **Continuous checks:** link health, content change, prohibited topics, source conflicts, suppression, heartbeat.
- **Unchanged:** kill switch, three suppression checks, 2-day removal, S1 rules. No party affiliation, religion or health data, ever.

**Timeline**, if WordPress safeguards pass by 23 November:

| Reviewers | 500 live | 1,000 live | 1,000 reviewed and indexable |
|---|---|---|---|
| 2 × 7.5 h/week (current) | 13 Dec | 21 Feb | 28 Feb |
| **2 × 15 h/week (recommended)** | **6 Dec** | **10 Jan** | **17 Jan** |
| 2 × full-time | 6 Dec | 20 Dec | 3 Jan |

**What publish first buys:** one to two weeks on "live", and resilience if reviewers stall. It does not reduce review (about 202 hours, up 6%), and search visibility arrives no earlier. Reviewer hours remain the main lever.

**Pilot:** 50 profiles, 10–18 November, in shadow mode: nothing goes live. It measures AI audit misses, quick-look catches and amber share, as well as review time. 

**Sources:**
- **Recommended for the pilot:** Wikidata (CC0), CBN lists (reuse allowed with attribution), PenCom, NUC, State House, organisations' own sites, Ventures Platform, Anzisha (older cohorts).
- **Not used as sources:** NGX (bans scraping and aggregation; use issuer pages), FMDQ, CAC for agents (reviewers may check it by hand), OpenCorporates, Crunchbase, Briter, D&B, YC, Techstars, MDCN, all social platforms.
- **New issue:** many are manual-only today, so the pipeline cannot gather or monitor them until re-checked (decision 21).

**Cost:** AI about US$335 (proposed cap US$450); licences US$0 in the base case; about 225 reviewer-hours.

**Legal is on the critical path for individuals, and now has new questions:** AI transfer basis by 13 November; legitimate interests assessment, DPIA, registration tier, notification position and a view on publish first before the first individual goes live.

**Critical path to the first public record:** WordPress safeguards. The build has not started.

---

## 12. Decisions needed from you
Reply in one message, for example "Accept all recommendations except 13: ...". Each is also logged in `docs/questions.md`. Numbers 1–11 keep their earlier meaning.

**These decide the model (answer first):**

| # | Q | Decision | Options | Recommendation |
|---|---|---|---|---|
| 12 | Q46 | **AI-drafted profiles.** D-017, REQ-163 and REQ-168 still require a human to approve each AI draft, claim by claim, before it goes live. You did not list them as amended, and D-033 cannot work with them as they are. | (a) Amend them: claim-by-claim approval moves after publication; AI-drafted organisations (T2) get a quick look; (b) as (a), but T2 goes live with no look; (c) keep them: only T1 publishes first, and T2 and T3 stay review-first | **(a).** (b) moves neither 1,000 date at 2 × 15 h/week and saves only about 4 hours. (c) is simply the old plan with T1 faster. |
| 13 | Q47 | **Individuals.** Your open choice in control 2. D-013 says "human review first", so either way it needs a matching amendment. | (a) Keep as proposed: quick look first; D-013 amended to say so; (b) publish individuals unreviewed too: D-013 amended further | **(a).** (b) brings 1,000 live forward only one week (10 Jan to 3 Jan), saves 10 hours, and removes the only human check against a wrong-person page. Decide after the pilot's catch rate if you prefer. |
| 7 | Q35 | **First individual goes live.** D-016 says Legal is not a build blocker; publish first raises the stakes. | (a) Wait for Legal's written answer, including the new questions (6.5); (b) publish under D-016 and use the kill switch if Legal objects | **(a).** Legal can take until about 7 December without moving a date. |
| 14 | Q48 | **Scope of D-033.** Your model describes the AI pipeline, so I applied it to acquired records only. | (a) Acquired records only: customer submissions, owner edits, complimentary listings and nominees stay approval-first; (b) everything | **(a)** |
| 15 | Q49 | **What counts.** D-012 and REQ-191 target "reviewed profiles live"; REQ-104 and "1,000 published" could now mean live or reviewed. Also, I read "human review samples" as the first part of reviewing every record, not as sampling instead of reviewing. | (a) Report both; target = live; D-012 target stays reviewed; every record gets reviewed; (b) target = reviewed; (c) review samples only, and unsampled records stay noindex for good | **(a)** |
| 3 | Q31 | **Reviewer hours and target date** | (a) 2 × 7.5 h/week: 1,000 live 21 Feb; (b) 2 × 15 h/week: 10 Jan; (c) 3 × 7.5 h/week: 24 Jan; (d) 2 full-time: 20 Dec | **(b)** |

**Values I proposed (accept or change):**

| # | Q | Decision | Options | Recommendation |
|---|---|---|---|---|
| 16 | Q50 | **Cap** (6.6) | (a) 400 pending / 150 individuals, or two weeks of capacity if lower, ramp 50–150–300–400; (b) 300 / 100; (c) 600 / 300 | **(a)**, raising it only after 200 individuals are reviewed cleanly |
| 17 | Q51 | **Pause thresholds** (6.1) | Accept all; or change named rows | **Accept** |
| 18 | Q52 | **AI takedown limits** (6.7). You said AI takes down "any it finds unsuitable". | (a) Pending: any code. Reviewed, claimed, verified, sponsored: S1 only, otherwise flag. AI restores technical codes only; (b) takedown on any code for every profile; (c) AI restores nothing | **(a).** A false takedown of a reviewed or customer profile costs more than a day's wait for a human. |
| 20 | Q54 | **Listings** (REQ-218). Noindex on a profile does not stop its name being indexed through a list page. | (a) Indexable lists show reviewed only; pending found by search, direct link or a noindex view; (b) pending appear in all lists, and those pages lose indexing | **(a).** The directory looks thinner early on, but the lists stay indexable. |
| 10 | Q38 | **Budget and rates** | AI cap US$450 (was US$250); supply the reviewer hourly rate | Approve the cap; give the rate |

**Things only you can settle:**

| # | Q | Decision | Options | Recommendation |
|---|---|---|---|---|
| 19 | Q53 | **"Staging database."** D-029 makes staging.wiwi.africa public, and the architecture says real data only after approval there. | (a) A private database that no public site reads; (b) the database behind staging.wiwi.africa, which means that site shows real unreviewed data | **(a)** |
| 21 | Q55 | **Manual-only sources.** Many sources are coded M, including CBN, PenCom, NAICOM, SEC, NCC and State House. The pipeline cannot gather from them automatically and the monitor cannot check them. | (a) Agents read each one's terms and robots rules; you approve re-coding where allowed; the rest stay amber with people entering facts; (b) use only sources already P, A or X | **(a).** Otherwise T1 may turn out mostly amber. |

**Unchanged from v1, still needed:**

| # | Q | Decision | Options | Recommendation |
|---|---|---|---|---|
| 1 | Q29 | Split and countries: 500 / 200 / 300 by type; 850 Nigeria / 150 others. **Amends D-010 and D-025 (Nigeria only).** | (a) As proposed; (b) Nigeria only for the first 1,000; (c) another split | **(a)**, with non-Nigerian profiles from B3 after Legal |
| 2 | Q30 | ~~Sampling for T1~~ | **Closed:** overtaken by D-033 | — |
| 4 | Q32 | Pilot review tool before WordPress is ready | (a) Hash-locked review packs now; (b) wait for WordPress | **(a)** |
| 5 | Q33 | Approve the pilot sources listed as "Approve for pilot" in 2.2–2.5, with you as the named approver (REQ-137) | Approve all / approve some / reject | **Approve all** |
| 6 | Q34 | Images | (a) No photos on unclaimed individuals and no logos in the first 1,000 until Legal advises; (b) allow Commons images under D-024 now | **(a)** |
| 8 | Q36 | Public office-holders (I1) | (a) Include current federal ministers, governors, principal officers and agency heads, with no party field, and always amber; (b) exclude politicians from the first 1,000 | **(a)** |
| 9 | Q37 | Listed companies | (a) Build from issuer pages plus Wikidata, and ask NGX for a quote in parallel; (b) wait for an NGX licence | **(a)** |
| 11 | Q39 | AI model and processing location, now for drafting, audit and monitoring | (a) Claude Opus 5.5, global inference; (b) Opus 5.5, US-only inference (1.1x); (c) a cheaper model (Sonnet 5.5) | **(a)** unless Legal prefers (b) for the transfer record |

Also needed (already open): reviewer names (Q4), the Editor-in-Chief's name (REQ-174), the Anthropic account owner (Q28).
