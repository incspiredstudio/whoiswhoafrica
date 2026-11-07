# WHOISWHO: Acquisition plan for the first 1,000 published profiles

Status: **Proposed, 2026-11-07.** This is a plan only. Nothing has been fetched, scraped or imported. Terms and licences were checked online on 2026-11-07 (section 2.6 lists what was read). This is not legal advice; legal points are for Incspired Legal to confirm.

Relies on decisions D-002, D-010, D-012 to D-017, D-024, D-029, D-031 and requirements REQ-132 to REQ-176 and REQ-191 to REQ-209. Codes are explained in `docs/GUIDE.md`.

**Conflicts with recorded decisions (need your ruling, see section 12):**
- D-010 and D-025 say Nigeria only. This plan includes about 150 profiles from other African countries, as you asked.
- D-015 and REQ-148 say 100% human review with no sampling. You asked for a tiered policy with sampling. Section 4.4 proposes one and shows it saves only 2–5% of review time at this mix. My recommendation is to keep 100% review for the first 1,000.

---

## 1. Inclusion criteria and the split of the 1,000

### 1.1 How the test works
A record is included only if it meets **one named criterion** (code below) **and** that criterion is backed by **an evidence URL from an approved source**. The reviewer answers one question: "Does the evidence page show this criterion is met, for this exact entity, today?" Yes publishes; no rejects with reason code `NOT_NOTABLE`. Popularity, press coverage, commercial interest and sales-prospect status are never criteria. The criterion code and evidence URL are stored on the record (tooling item T-13).

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

### 1.7 Review tiers (used throughout this plan)

| Tier | What it is | Count in the 1,000 | AI used? |
|---|---|---|---|
| **T1** | Organisation built only from structured registry or regulator fields: name, type, licence category, regulator, city, website, year founded. The text is templated, not AI. | 200 | No |
| **T2** | Organisation with an AI-drafted summary from its official site or annual report | 300 | Yes |
| **T3** | Entrepreneur or individual, whether AI-drafted or not | 500 | Usually |

---

## 2. Source map

### 2.1 How to read this
- **Status:** No source is approved yet. Under REQ-137, a named person must approve each one. The "Recommendation" column is my proposal for your approval.
- **Verified** = the terms, robots or list page was read on 2026-11-07. **Partly** = part was read, or the rest came from secondary sources. **Unverified** = the page could not be read.
- **Access codes:** M = manual (a person reads the page and enters facts with the URL). P = polite fetch of single pages by an identified agent, honouring robots.txt. A = API. X = export or download file.
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

## 3. Pipeline

### 3.1 Steps and who does what

| # | Step | Agent does | Human does | Controls (REQ) |
|---|---|---|---|---|
| 0 | **Source approval** | Drafts the source-registry entry: URL, entity types, access method, terms quote, robots check date, risk | Supervisor approves or rejects (named approver). Legal reviews any source marked "Legal check". | REQ-136, 137, 138 |
| 1 | **Seed list** | Builds candidate lists from approved sources only. Each candidate carries a criterion code, evidence URL, country and type, against batch quotas. Runs the suppression check (first of three). | Supervisor sets the quotas per batch | REQ-147, 192, 207 |
| 2 | **Fetch** | Fetches only approved sources. Identifies itself (`WHOISWHO-bot/1.0 (+https://wiwi.africa/bot; contact address)`), re-checks robots.txt each run, waits at least 5 seconds between requests per host, never logs in or gets around a block, and stores each page with a hash and retrieval date. Wikidata calls follow the WDQS limits. | — | REQ-138, 142 |
| 3 | **Entity resolution and de-duplication** | Matches on, in order: Wikidata QID, RC number, regulator licence number, LEI, official domain. Individuals are matched on name + organisation + role from one page. Probable duplicates go to a merge queue. | A reviewer decides every probable duplicate. Individuals are never auto-merged. | REQ-145, 146 |
| 4 | **Field allowlist and sensitive scan** | Keeps only allowlisted fields (REQ-139 for individuals). Drops forbidden data (REQ-140), including party affiliation. Scans free text for phone numbers, emails, addresses, ages, party names, religion and health words. | — | REQ-139, 140, 141 |
| 5 | **Draft** | T1: fills a fixed template from structured fields, with no AI. T2/T3: the AI receives numbered source passages and returns claims, each with a passage ID and an exact quote. Code drops any claim whose quote is not found in the stored passage, whose field is outside the allowlist, or that matches the prohibited-topic list. Model and prompt version are recorded. | — | REQ-165–168, 163 |
| 6 | **Automated pre-checks** | Every field has a source, retrieval date and confidence, and low-confidence fields are blocked. Source links return HTTP 200. Suppression check (second of three). Criterion evidence is present. | — | REQ-142, 143 |
| 7 | **Human review** | Shows each claim beside its source passage. Records review time per profile. | The reviewer checks identity, the criterion, every claim against its source, and the neutral-content checklist. Decision: approve, reject (reason code) or return. **Approver ≠ drafter or importer.** | REQ-148, 149, 163, 168, 206 |
| 8 | **QA audit** | Draws a random sample of approved profiles (section 6.1) | The other reviewer, or the supervisor, re-checks the sample | REQ-149 |
| 9 | **Publish** | Prepares the batch. Suppression check (third of three). | A **human** (Editor-in-Chief or supervisor) bulk-publishes a batch only after its gate passes. Agents never publish. | REQ-208, 209 |
| 10 | **After publishing** | Watches link rot and source changes (it flags changes and never overwrites). Sends refresh reminders. | Reviewers handle corrections and removals. The Editor-in-Chief handles disputes. | REQ-156–161, 164, 174 |

### 3.2 Rules for the AI drafting step
- **Model.** Default to Claude Opus 5.5. At about US$0.10 per profile (section 10.1), quality matters more than cost.
- **Data use and retention.** Under Anthropic's Commercial Terms, Anthropic "may not train models on Customer Content". API inputs and outputs are deleted within 30 days by default, and zero data retention is available on request.
- **Transfer basis.** Anthropic's data processing addendum covers EU, UK and Swiss transfers, not Nigeria. Our NDPA s.41/43 transfer basis must be recorded by us and confirmed by Legal before any individual's data is sent (REQ-169). Inference location defaults to "global"; US-only costs 1.1x.
- **Fetched text is data, never instructions.** Prompt injection in a source page cannot add a claim, because every claim must quote the stored passage exactly.
- **Allowed fields only.** The prompt asks for allowlisted fields only and forbids praise, superlatives, and the topics in REQ-167.
- **Sites that block AI crawlers by name** (such as CAC). Their text is never fetched by an agent or passed to the model. People may consult them manually.

### 3.3 Where the data lives before WordPress is ready
- **Pipeline code** goes in `scripts/acquisition/` (infra area).
- **Fetched pages, drafts and personal data are never committed to git.** They live in a working store outside the repo (a git-ignored local folder plus an encrypted backup), and move into WordPress once the import tool exists.
- **The pilot can be reviewed before WordPress exists** using a *review pack* (decision 4):
  - Each profile is a static page showing claims beside sources, plus a decision sheet.
  - The pack records a **content hash** for each approved profile.
  - Later, the WordPress import accepts an approved profile only if its hash matches, so what goes live is exactly what was reviewed (tooling item T-11).
  - This lets review run ahead of the build without breaking REQ-148.

---

## 4. Capacity and timeline

### 4.1 Assumptions (pre-pilot; the pilot replaces these)

| Item | Estimate | Note |
|---|---|---|
| T1 full review | 3 min | Check structured fields against the regulator list |
| T2 review | 8 min | Fields plus 4–8 AI claims against the source |
| T3 review | 12 min | Identity, criterion, claims, neutral-content check |
| Rework and rejects | +15% | Reviewing records that are then rejected or returned |
| Corrections, removals and QA audit | +10% | Ongoing work after publishing |
| **Effective minutes per profile** | **T1 3.8 · T2 10.1 · T3 15.2** | Estimate × 1.15 × 1.10 |
| T1 under sampling | 1.1 | 20% sample plus batch sign-off |
| Pilot speed | 1.5× slower | Learning curve and measurement |
| "D-015 reviewer" | 7.5 focused hours/week (450 min) | About 40 profiles a week at the blended mix, matching D-015 |
| "Full-time reviewer" | 25 focused hours/week (1,500 min) | The rest of the week goes on meetings, corrections and admin |
| Holidays | 21 Dec – 3 Jan at half capacity | Two weeks count as one |

Agents and AI are not the constraint: drafting 1,000 profiles takes days of machine time. **Human review is the constraint.**

Total review load for the 1,000:

| Tier | Count | Effective min | Minutes |
|---|---|---|---|
| T1 | 200 | 3.8 | 760 |
| T2 | 300 | 10.1 | 3,030 |
| T3 | 500 | 15.2 | 7,600 |
| **Total** | **1,000** | **11.4 average** | **11,390 (≈ 190 hours)**, plus about 4 hours of pilot slowness |

Individuals (T3) are two-thirds of the load. That is why sampling registry organisations saves little (section 4.4).

### 4.2 Weekly throughput (profiles approved per week)

| Reviewers | T1 only | T2 only | T3 only | Typical mixed batch |
|---|---|---|---|---|
| 2 × D-015 (900 min/week, the current commitment) | 237 | 89 | 59 | 73–83 |
| 2 × 15 h/week (1,800 min) | 474 | 178 | 118 | 146–166 |
| 2 × full-time (3,000 min) | 789 | 297 | 197 | 243–278 |

### 4.3 Batches and weeks to each milestone

**Batch plan** (every batch is Nigeria-only until Legal clears the other countries):

| Batch | Cumulative | T1 | T2 | T3 | Minutes | What goes in |
|---|---|---|---|---|---|---|
| Pilot | 50 | 15 | 15 | 20 | ≈ 770 | Section 5 |
| B1 | 100 | 20 | 20 | 10 | 430 | Banks, PFAs, universities, listed companies, startups |
| B2 | 250 | 40 | 40 | 70 | 1,620 | Reaches about 150 organisations and about 100 individuals, the D-012 target. First public office-holders. |
| B3 | 500 | 60 | 90 | 100 | 2,657 | First non-Nigerian organisations, if cleared |
| B4 | 1,000 | 65 | 135 | 300 | 6,171 | Mostly individuals and entrepreneurs |

Each batch is split into weekly lots of 50–150 so that each lot can pass its gate (section 6.1) separately.

**Calendar:**
- The pilot runs 10–18 November.
- Production week 1 starts **Monday 23 November**. Week 4 ends 20 December (end of brief Phase 2). Week 8 ends 17 January. Week 10 ends 31 January (sprint end). Week 12 ends 14 February.

**When each milestone is reached** (counted as *approved*; *published* = approved once the WordPress publication gate, 410 and kill switch pass on staging):

| Milestone | 2 × D-015 (current) | 2 × 15 h/week | 2 × full-time |
|---|---|---|---|
| 100 | Week 1 (by 29 Nov) | Week 1 | Week 1 |
| 250 | Week 3 (by 13 Dec) | Week 2 (by 6 Dec) | Week 1 |
| 500 | Week 7 (by 10 Jan) | Week 3 (by 13 Dec) | Week 2 |
| 1,000 | **Week 14 (by 28 Feb)**. About 775 by 31 Jan. | **Week 8 (by 17 Jan)** | Week 4 (by 20 Dec) |

### 4.4 Tiered review policy (proposed; needs a change to D-015 and REQ-148)

| Tier | Policy |
|---|---|
| T3 (all individuals and entrepreneurs) | **Full review, always.** |
| T2 (any AI-drafted content) | **Full review, always.** |
| T1 (structured registry organisations, no AI) | Full review until the **sampling gate** is passed. Then a 20% random sample per lot (at least 10 records) plus automated checks. |

**T1 sampling rules:**
- **Gate:** at least 100 T1 records from **the same source**, fully reviewed in a row, with at most 1 material (S2) error and no critical (S1) error. That gives a 95% upper bound of about 3% on the error rate.
- **Failure in a sampled lot:** more than 2% S2 errors, or any S1 error, sends that whole lot to full review. Sampling is suspended for that source until it passes another 100 clean records.
- **Records never sampled:** any T1 record that changed since its last review, or that has a duplicate flag.

**What it saves:**
- At this mix it saves about **270 minutes (2.4%)**, because the gate is passed only once about 100 of the 200 T1 records have been fully reviewed.
- Even if all 200 T1 records were sampled, the saving would be only about 530 minutes (4.7%).

**Recommendation:** keep 100% review (D-015) for the first 1,000 and do not build the sampling mode yet. Reconsider once registry organisations are most of the incoming volume. This is decision 2.

### 4.5 Reviewers needed to reach 1,000 by week 4, 8 and 12
Remaining load after the pilot: 10,878 minutes with full review, 10,612 with tiered review. Usable weeks: week 4 = 4.0, week 8 = 7.0, week 12 = 11.0 (holidays fall in weeks 5 and 6).

| Deadline | D-015 reviewers (7.5 h/week), full review | D-015 reviewers, tiered | Full-time reviewers, full review | Full-time reviewers, tiered |
|---|---|---|---|---|
| Week 4 (20 Dec) | **7** (6.04 needed) | **6** (5.90) | **2** (1.81) | **2** (1.77) |
| Week 8 (17 Jan) | **4** (3.45) | **4** (3.37) | **2** (1.04; one is just short) | **2** (1.01) |
| Week 12 (14 Feb) | **3** (2.20) | **3** (2.14) | **1** (0.66), but keep 2 for cover and the reviewer ≠ drafter rule | 1 (0.64), keep 2 |

**Cheapest way to meet the 31 January sprint end:** the two existing reviewers each give about 2 days (15 focused hours) a week from 23 November. That reaches 1,000 approved by about 17 January. Alternatively, add a third reviewer at the D-015 level and a fourth from week 7.

**Sensitivity:**
- If the pilot shows T3 takes 25 minutes instead of 12, the total load rises to about 17,400 minutes. Every column above then rises by about 60%.
- The pilot result is the number that matters.

---

## 5. Pilot: 50 profiles end to end

### 5.1 Composition (Nigeria only)
- **15 T1 organisations:** 8 CBN-licensed banks, 4 PFAs, 3 NUC universities.
- **15 T2 organisations:** 5 listed companies (from their own IR pages), 6 startups (Ventures Platform or Google accelerator), 4 public institutions or foundations.
- **10 entrepreneurs (E1):** founders of pilot startups or companies.
- **10 individuals:** 3 ministers (I1), 3 bank CEOs (I2), 2 vice-chancellors (I3), 2 academy fellows (I3).

### 5.2 Schedule

| Date | Step | Owner |
|---|---|---|
| Mon 9 Nov | Approve this plan and decisions 1–11; name the reviewers | You |
| Tue 10 Nov | Approve pilot sources (2.2–2.4, marked "Approve for pilot"); Anthropic API account live (Q28) | You |
| Tue 10 Nov | Reviewer onboarding (2 h) using `docs/review-guidelines.md` (agent drafts it on 9 Nov) | You and reviewers |
| 10–11 Nov | Agents seed, fetch, resolve and draft the organisations (T1/T2) and build review packs | Agents |
| Wed 11 Nov | Calibration: both reviewers review the same 10 profiles and compare | Reviewers |
| Fri 13 Nov | Legal confirms the AI transfer basis (REQ-169). **Individual drafting waits for this.** | Incspired Legal |
| 13–14 Nov | Agents draft the 20 T3 profiles | Agents |
| 12–18 Nov | Pilot review. 20 profiles (10 organisations, 10 individuals) are reviewed by **both** reviewers to measure agreement. | Reviewers |
| 18–19 Nov | Supervisor re-checks all 50 approved profiles against their sources (a 100% audit in the pilot) | You |
| Thu 19 Nov | Agent produces the pilot report (5.3) | Agents |
| Fri 20 Nov | This plan is updated to v2 with measured numbers (brief Phase 1 exit: "a clear acquisition plan") | Agents, then your approval |

Publishing the pilot depends on WordPress staging passing REQ-208, 209 and 193. If that is not ready by 18 November, the pilot is published when it is, from the hash-locked approved set. The review measurements stand either way.

### 5.3 What the pilot measures

| Measure | How | Feeds |
|---|---|---|
| Review minutes per profile, by tier | Timer in the review pack or queue (median and 90th percentile) | Section 4 capacity |
| First-pass approval rate; reject reasons | Reason codes | Draft quality, source choice |
| Draft error rate by field and severity | Reviewer findings | Prompt and template fixes |
| **Post-approval error rate** | Supervisor's 100% audit of approved profiles | Section 6 thresholds, sampling gate |
| AI claim-drop rate; claims left unsupported | Verifier logs | Drafting prompt |
| Source quality: stale, wrong, link rot, by source | Errors traced to source | Source approvals (2.x) |
| Disambiguation failures | Duplicate and identity errors | Matching rules |
| Inter-reviewer agreement | Double-reviewed 20 | Guidelines, calibration |
| AI cost and agent time per profile | Usage logs | Section 10 cost |

### 5.4 Decision rules after the pilot

| Result | Action |
|---|---|
| Any S1 error survives review | Fix the cause. Re-pilot 25 profiles of that type before scaling it. |
| Post-approval S2 rate above 3% (organisations) or 2% (individuals) | Revise guidelines and prompts. Run a 25-profile second round for that tier. |
| Median T3 review above 20 min | Choose one: cut T3 fields to the core REQ-139 set; raise reviewer hours; or move volume from T3 to T2. |
| A source causes more than 10% of errors | Downgrade it to "lead only" or reject it |
| Inter-reviewer agreement below 90% on approve/reject | Run a second calibration and tighten the criteria wording |
| All within limits | Start B1 on 23 November at the measured rates |

---

## 6. Quality and risk controls

### 6.1 Error definitions and thresholds that pause publishing

| Severity | Definition | Examples |
|---|---|---|
| **S1 critical** | Could harm a person or breach law | Wrong person (identity mix-up); a claim on a prohibited topic; sensitive data published (party, religion, health, age); a suppressed person or organisation published; an invented claim with no source |
| **S2 material** | A fact is wrong | Wrong title, role, organisation, date, licence status; the source does not support the claim; the criterion is not actually met |
| **S3 minor** | Presentation | Typo, formatting, a dead link where the fact is still right |

| Trigger | Automatic response |
|---|---|
| Any S1 in published content | Unpublish the profile at once (410). **Pause publishing for that tier and source** until the root cause is fixed and the supervisor signs off. If S1 errors in individuals look systemic, use the kill switch (REQ-193). |
| Weekly QA audit S2 rate above 2% for T3, or above 3% for T1/T2 | Pause publishing for that tier. Fully re-review the affected lot. Fix guidelines or prompts. |
| QA sample size | 5% of profiles approved that week, at least 20 per week, always including at least 10 individuals |
| Draft rejection rate above 25% in a lot | Stop drafting from that source or prompt version until it is fixed |
| Valid correction requests above 1% of published profiles in a week | Review the source and prompt; audit the affected lot |
| Removal requests above 2% of individual profiles in a month | Supervisor and Legal review the inclusion criteria for that class |
| Review backlog above 2 weeks of capacity | Workload warning (REQ-206). Add reviewer hours or slow the drafting. |

The pause control is tooling item T-9: a per-source and per-tier switch that blocks bulk publish and is audited.

### 6.2 Corrections log
Every change to a published profile writes an entry (REQ-164): what changed, old and new value, reason, who made it, and the source. The public profile shows "last reviewed" (REQ-144). Corrections are resolved within 7 days (REQ-158).

### 6.3 Removal and suppression
- **Targets:**
  - Acknowledge within 1 business day.
  - Hide within 2 business days of a valid request from the subject (REQ-158). Hiding means a 410 and noindex, removal from the sitemap and internal search, and a CDN purge (REQ-209).
  - Add the subject to the permanent suppression list (REQ-207).
- **The suppression list is checked three times** (seed, pre-check, publish), so a suppressed entity can never come back through a new import.
- **The objection right is real.**
  - NDPA s.36 requires us to stop unless we show overriding legitimate grounds.
  - POPIA s.11(3) requires a stop on reasonable grounds.
  - **Default:** a subject who objects is removed and suppressed. The Editor-in-Chief may escalate only office-holders (I1/I5) to Legal.
- **How a requester's identity is checked** is still open (wireframe finding 15). Proposed default: a request from the subject's official work email, or from the organisation's domain, is accepted. Anything else goes to staff judgement.
- **Search engines:** after a removal, staff file search-engine removal requests (N21).

### 6.4 Legal risks

| Risk | What applies | Control |
|---|---|---|
| Lawful basis for individuals (NDPA s.25) | Legitimate interests fail if the subject "would not have a reasonable expectation" of the processing (s.25(2)(c)). The journalism exemption (s.3(2)(d)) still leaves s.25 in force. | Professional, already-public facts only (REQ-139). A legitimate interests assessment (GAID Schedule 8). A disclosure page (REQ-130, 170). An easy objection route. |
| Sensitive data (s.30, s.65) | Political affiliation, religion, ethnicity and health are sensitive, with no "made public" exception | Excluded from the schema and scanned for (REQ-140, 141). No party field for office-holders. |
| DPIA (s.28; GAID) | Required for likely high risk; GAID requires one before deploying AI (summaries read, not the GAID text) | DPIA before the first individual is published |
| Registration as a data controller of major importance (s.44, s.65; GAID tiers) | GAID tiers are reportedly over 200, over 1,000 and over 5,000 subjects in 6 months, with fees of about ₦100k to ₦1m (unverified). 1,000 profiles would likely qualify. | Legal confirms the tier, registration, the DPO (s.32) and the audit-return duty |
| Cross-border transfer (s.41–43) | Anthropic's addendum has no Nigerian mechanism | Legal records the basis before individuals are drafted |
| Defamation (civil; criminal defamation remains in the Criminal Code and Penal Code in some states) | No higher threshold for public figures; truth is a defence, with the burden on us | Source per claim, neutral-content checklist, no allegations, fast correction (REQ-142, 163, 158) |
| Cybercrimes Act s.24 (amended 2024) | Narrowed. Exact amended text not read. | The same controls; Legal to confirm |
| Other countries | Ghana: every controller registers (Act 843 s.27). Kenya: registration above thresholds (s.18). POPIA: objection stops processing. | Per-country Legal clearance before any batch from that country |
| Copyright and terms of use | Compilations and government works are protected; site terms are contracts | Facts only, cited; terms checked per source; no mirroring |

Penalties for context: NDPA s.48 sets a maximum of ₦10m or 2% of annual gross revenue for major-importance controllers. The NDPC fined MultiChoice ₦766.2m in 2025.

### 6.5 What we need from Incspired Legal, by batch

| Before | Legal item | Blocks |
|---|---|---|
| Any individual's data goes to the AI (13 Nov) | AI transfer basis (s.41/43) and choice of inference location; confirmation of the Anthropic processor terms (REQ-169) | T3 drafting in the pilot |
| First **organisation** published | Terms and Privacy pages; wording for the "how we compile" page (REQ-130) and the AI disclosure (REQ-170) | Any publishing |
| First **individual** published | Legitimate interests assessment; DPIA; registration tier, DPO and audit-return duty; whether and how subjects are told (REQ-159); sign-off of the neutral-content checklist; a written position on D-016 | Publishing T3 (decision 7) |
| First **office-holder** batch (B2) | Confirm the office-holder field set and that party affiliation is excluded | I1 profiles |
| First **non-Nigerian** batch (B3) | Per-country position: Ghana registration, Kenya threshold, POPIA | Non-Nigerian profiles |
| Any **licensed** dataset (NGX, Big Deal) | Contract review: display rights, attribution, share-alike | That source |
| Images or logos | Personality rights, trademark | Decision 6 |

---

## 7. WordPress tooling needed (input for the Gate 2 architecture revision)
These feed the WordPress architecture (D-031). "Proposed" items are not yet in `docs/requirements.md`. They become REQ-210 onwards only if you approve them (no scope creep without approval).

| ID | Tool | Must do | REQ |
|---|---|---|---|
| T-1 | Source registry | Admin screen with name, entity types, URL, access method, terms summary, robots check date, approver, status. The importer refuses any source that is not Approved. | REQ-136, 137 |
| T-2 | Import tool | CSV/JSON and Wikidata SPARQL adapters. **Dry run** with a diff. Idempotent on external keys (QID, RC, licence number, LEI, domain). Batch ID on every record. Suppression check. Duplicate detection. Never overwrites a reviewed field; flags a change instead. | REQ-134, 135, 145–147, 161, 207 |
| T-3 | Provenance per field | Source URL, retrieval date, confidence, method, source hash, passage ID. Queryable for reports, so it should not be buried in post meta. | REQ-142, 143 |
| T-4 | AI draft store | Claims with passage quotes, a log of dropped claims, model, prompt version, internal AI-assisted flag | REQ-166–168 |
| T-5 | Review queue | Filters by tier, batch, source and country. **Claim beside source.** Neutral-content checklist. Reason codes. Approver ≠ drafter enforced. Per-reviewer workload and backlog warning. | REQ-148, 149, 163, 206 |
| T-6 | Duplicate and merge queue | Human merge with an audit entry; never automatic for individuals | REQ-146 |
| T-7 | Bulk publish and unpublish | Publishes only approved items in a lot, and only if the lot's gate passes. Unpublish gives a 410, noindex, sitemap removal and purge. Kill switch. | REQ-193, 208, 209 |
| T-8 | Corrections, removal and suppression | Corrections log; removal workflow with deadline timers; permanent suppression list checked at every import | REQ-156–158, 164, 207 |
| T-9 | **Proposed:** pause switch | Per source and per tier; blocks bulk publish; audited | (new) |
| T-10 | **Proposed:** review timer | Records active review seconds per profile and per reviewer | (new; extends REQ-149) |
| T-11 | **Proposed:** reviewed-hash lock | Import of pre-reviewed (review-pack) items only if the content hash matches the approved hash | (new) |
| T-12 | **Proposed:** QA audit queue | Random sample of approved profiles each week for the second checker; result feeds the error-rate dashboard | (new) |
| T-13 | **Proposed:** inclusion criterion field | Criterion code plus evidence URL, shown to the reviewer, required for approval | (new) |
| T-14 | **Proposed, only if decision 2 = tiered:** sampling mode | Per-source sampling gate, 20% sample, lot fail triggers full review | (new; changes REQ-148) |
| T-15 | Dashboard | Profiles by type, source, status, age, country; throughput, queue age, error rates, reasons | REQ-149, 171 |
| T-16 | Sensitive-pattern scan | Includes Nigerian party names and acronyms, religion and health terms | REQ-141 |
| T-17 | Export | All profiles with provenance, in open formats | REQ-131 |

**Build order that unblocks acquisition soonest:** T-1, T-2 (with T-3 and T-11), T-5, T-7, then the rest.

---

## 8. Human tasks, owners and dates
Owner "You" = the supervisor. Dates are ceilings; earlier is better.

| # | Task | Owner | Needed by | Blocks |
|---|---|---|---|---|
| H1 | Decide items 1–11 in section 12 | You | Mon 9 Nov | Everything |
| H2 | Name the two reviewers and their weekly hours (Q4) | You | Mon 9 Nov | Pilot |
| H3 | Name the Editor-in-Chief (REQ-174), who handles disputes and the named-award list | You | Fri 13 Nov | Publishing |
| H4 | Open the Anthropic API account; set the organisation's data settings; decide on zero data retention (Q28) | You | Tue 10 Nov | AI drafting |
| H5 | Approve pilot sources (named approver per REQ-137) | You | Tue 10 Nov | Pilot fetch |
| H6 | Reviewer onboarding (2 h) and calibration (10 profiles) | You and reviewers | 10–11 Nov | Pilot review |
| H7 | Legal: AI transfer basis | Incspired Legal | Fri 13 Nov (already requested, Q5) | T3 drafting |
| H8 | Legal: Terms, Privacy, disclosure wording | Incspired Legal | Fri 20 Nov | First publishing |
| H9 | Legal: legitimate interests assessment, DPIA, registration tier, DPO, notification position | Incspired Legal | Fri 20 Nov | First individual published |
| H10 | Pilot audit of the 50 approved profiles | You | Thu 19 Nov | Plan v2 |
| H11 | Approve plan v2 and reviewer hours | You | Fri 20 Nov | B1 |
| H12 | Ask NGX for a reference-data licence quote (optional) | You | Thu 12 Nov | Optional O1 route |
| H13 | Approve B1 and B2 sources (the "after terms read" rows) | You | Fri 27 Nov | B2 |
| H14 | Legal: office-holder field set | Incspired Legal | Fri 27 Nov | B2 office-holders |
| H15 | Legal: per-country positions (Ghana, Kenya, South Africa) | Incspired Legal | Fri 11 Dec | B3 non-Nigerian |
| H16 | Third reviewer onboarded, if the pilot says so | You | Mon 30 Nov | Section 4.5 targets |
| H17 | Weekly: sign off the QA audit; approve each lot's bulk publish | You or Editor-in-Chief | Every Friday | Publishing |

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
| AI drafting (Claude Opus 5.5, US$4 input / US$20 output per million tokens) | About 11k input and 2.5k output tokens per profile across the drafting and resolution calls, so about US$0.09. Allow +50% for re-drafts and rejects. 800 profiles use AI (T2 + T3). | **About US$110**. Half that with the Batch API (50% off). **Budget cap: US$250**, including pilot iterations. |
| Wikidata, GLEIF, regulator lists, official sites | Free | US$0 |
| NGX reference data (optional) | Price by quote; not published | Unknown. Only if decision 9 chooses it. |
| Africa: The Big Deal (optional) | About US$1,500 a year enterprise; publication licence unconfirmed | Not in the base case |
| OpenCorporates, Crunchbase, CAC–NIBSS API | Not recommended | US$0 |
| Reviewer time | About 194 h review + 10 h QA + 8 h onboarding and calibration ≈ **215 reviewer-hours** | 215 × hourly rate. Illustration only: at ₦3,000, ₦6,000 or ₦10,000 an hour, that is ₦0.65m, ₦1.3m or ₦2.15m. **You supply the real rate.** |
| Supervisor time | About 4–6 h a week (approvals, QA sign-off, publishing) for 8–14 weeks | Internal |
| NDPC registration and compliance audit (if Legal says it applies) | GAID fees reportedly ₦100k to ₦1m by tier, plus the fee of a licensed compliance organisation (unverified) | ₦0.1m to ₦1m+ |
| Hosting and storage | Already covered by the architecture cost (section 9) | Negligible |

Prices were read on 2026-11-07 from the official Anthropic pricing page. **Reviewer time is about 90% of the cost.**

### 10.2 Top risks

| # | Risk | Likelihood / impact | Mitigation |
|---|---|---|---|
| 1 | **Review capacity**: 2 reviewers at the D-015 level reach only about 775 by 31 January | High / high | Decision 3 (hours or a third reviewer); review packs let review start before WordPress is ready |
| 2 | **Wrong-person errors** for individuals (common Nigerian names) | Medium / severe | The name + organisation + role rule (1.5); no auto-merge; S1 pause; QA audit |
| 3 | **Legal objection or NDPC exposure**: registration tier, DPIA, transfer, sensitive data | Medium / high | Organisations publish first; individuals wait for Legal (decision 7); kill switch; no party field |
| 4 | **Source terms**: NGX bans aggregation; CAC blocks bots; many terms still unread | Medium / medium | Issuer pages and Wikidata instead of NGX; humans only on CAC; approval only after terms are read |
| 5 | **WordPress publication gate late**: approved profiles wait | Medium / medium | Hash-locked review packs; T-1, T-2, T-5 and T-7 first in the build order |
| 6 | **Stale official data** (the AU page shows a former Chair; NUC vice-chancellor names) | High / medium | Cross-check on a second official page; retrieval date per field; refresh cycle (REQ-160) |
| 7 | **Review fatigue** lowers quality at volume | Medium / high | Review in focused 2-hour blocks; weekly QA audit; agreement checks; an S2 threshold that pauses publishing |
| 8 | **Prompt injection or invented claims** from fetched pages | Low / high | Exact-quote verification in code; allowlisted fields; human review of every AI draft |
| 9 | **Minors** via youth-programme sources | Low / severe | Cohorts at least 5 years old only; no age field |
| 10 | **Prospect data leaking into profiles** | Low / high | Separate store; reviewers blind to prospect status; profiles created only through the pipeline (section 9) |

---

## 11. One-page summary

**Goal:** 1,000 reviewed, published profiles: 500 organisations, 200 entrepreneurs, 300 notable individuals. 850 are Nigerian; about 150 come from Ghana, Kenya, South Africa and pan-African bodies, starting in B3 after Legal clears each country.

**Notability is a pass/fail test.** Each profile needs one named criterion (O1–O7, E1–E2, I1–I6) proven by an evidence URL from an approved source. Commercial interest is never a criterion.

**Sources:**
- **Recommended for the pilot:** Wikidata (CC0), CBN lists (reuse allowed with attribution), PenCom, NUC, State House, organisations' own sites, Ventures Platform, Anzisha (older cohorts).
- **Not used as sources:**
  - NGX (bans scraping and aggregation): use issuer pages instead.
  - FMDQ.
  - CAC for agents (it blocks AI crawlers): reviewers may check it by hand.
  - OpenCorporates (share-alike, little Nigerian data).
  - Crunchbase, Briter and D&B (no republication).
  - YC and Techstars (no scraping).
  - MDCN (sensitive).
  - All social platforms.

**Pipeline:** agents seed, fetch politely, resolve duplicates, apply the field allowlist, and draft with exact-quote checking. Humans approve sources, review every profile with claims beside sources, audit samples, and press publish.

**Pilot:** 50 profiles, 10–18 November, measuring review time, error rates, source quality and reviewer agreement. Plan v2 on 20 November.

**Capacity:** about 190 reviewer-hours in total, two-thirds of it on individuals.
- At the current commitment (2 reviewers, about 40 profiles a week each): 1,000 by **28 February** (about 775 by 31 January).
- With 2 reviewers at 2 days a week each: **17 January**.
- 2 full-time reviewers: **20 December**.

**Tiered sampling** of registry organisations saves only 2–5%. Recommendation: keep 100% review for now.

**Controls:**
- Any critical error pauses its tier and source.
- QA audit of 5% a week. S2 thresholds of 2% (individuals) and 3% (organisations) pause publishing.
- Removal within 2 business days with 410 and suppression.
- No party affiliation, religion or health data, ever.

**Cost:** AI about US$110 (cap US$250); licences US$0 in the base case; about 215 reviewer-hours, which is about 90% of the cost.

**Legal is on the critical path for individuals:** AI transfer basis by 13 November; legitimate interests assessment, DPIA, registration tier and notification position before the first individual is published.

---

## 12. Decisions needed from you
Reply in one message, for example "Accept all recommendations except 3: ...". Each is also logged as Q29–Q39 in `docs/questions.md`.

| # | Decision | Options | Recommendation |
|---|---|---|---|
| 1 | Split and countries: 500 / 200 / 300 by type; 850 Nigeria / 150 others. **Amends D-010 and D-025 (Nigeria only).** | (a) As proposed; (b) Nigeria only for the first 1,000; (c) another split | **(a)**, with non-Nigerian profiles from B3 after Legal |
| 2 | Review policy for structured registry organisations (T1). **Sampling would amend D-015 and REQ-148.** | (a) Keep 100% review; (b) tiered sampling after the gate (4.4) | **(a)**. The saving is 2–5% and not worth the tooling and trust cost yet. |
| 3 | Reviewer capacity and target date for 1,000 | (a) 2 reviewers × 7.5 h/week: 28 Feb; (b) 2 × 15 h/week: 17 Jan; (c) 3 reviewers × 7.5 h/week, plus a 4th from week 7: about 31 Jan; (d) 2 full-time: 20 Dec | **(b)** |
| 4 | Pilot review tool before WordPress is ready | (a) Hash-locked review packs now; (b) wait for the WordPress review queue | **(a)** |
| 5 | Approve the pilot sources listed as "Approve for pilot" in 2.2–2.5, with you as the named approver (REQ-137) | Approve all / approve some / reject | **Approve all** |
| 6 | Images | (a) No photos on unclaimed individuals and no logos in the first 1,000 until Legal advises; (b) allow Commons images under D-024 now | **(a)** |
| 7 | First individual published | (a) Wait for Legal's written assessment and DPIA (expected around 20 Nov); (b) publish under D-016 and use the kill switch if Legal objects | **(a)**. Organisations publish first, so nothing is lost. |
| 8 | Public office-holders (I1) | (a) Include current federal ministers, governors, principal officers and agency heads, with no party field; (b) exclude politicians from the first 1,000 | **(a)** |
| 9 | Listed companies | (a) Build from issuer pages plus Wikidata, and ask NGX for a quote in parallel; (b) wait for an NGX licence | **(a)** |
| 10 | Budget and rates | AI cap US$250; supply the reviewer hourly rate | Approve the cap; give the rate |
| 11 | AI model and processing location | (a) Claude Opus 5.5, global inference; (b) Opus 5.5, US-only inference (1.1x); (c) a cheaper model (Sonnet 5.5) | **(a)** unless Legal prefers (b) for the transfer record |

Also needed (already open): reviewer names (Q4), the Editor-in-Chief's name (REQ-174), the Anthropic account owner (Q28).
