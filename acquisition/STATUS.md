# Acquisition status

Updated 2026-11-11 by the acquisition session. This session does not own the shared docs.

## Done: all-Africa test run
Run on your instructions of 2026-11-09: skip Legal; notable entrepreneurs and organisations; local SQL database; filterable HTML table; then 10,000 more, all African countries, longer bios, more facts rather than AI.

- **Code:** `pipeline/run_first_1000.py`, `pipeline/build_table.py` (Python standard library only; stages resume).
- **Data, git-ignored:** `data/staging.sqlite`, `data/images/`, `data/directory.html`. The first run's table is kept as `data/first-1000-run1.html`, and its records as the `records_run1` table.
- **Counts:** 10,507 records: 8,950 organisations, 1,557 entrepreneurs, 54 countries plus pan-African bodies. 989 are from the first run and 9,518 are new. **Target was 11,000 in total; 493 short.**

**This is staging data. No person has reviewed it and nothing is published.** It does not replace plan run A or B.

## Done: deceased notable Africans (`deceased-2026-11-09`)
On your instruction: 1,000 notable Africans who have died, from any field, not only business.

- **Count:** 999 people from 54 countries and 20 former African states. One more failed the final scan.
- **Ranking:** by the number of Wikipedia language editions. Every country gets its 5 most notable first, and no country gets more than 150.
- **Excluded:**
  - people notable for crime
  - people who died as minors
  - deaths before 1500
  - non-African people
  - citizens only of former states that were not African, such as the Ottoman Empire or Castile
- **Fields kept:** birth and death years, birth and death places, positions held with years, education, notable works and awards.
- **Never read:** party, religion, ethnicity, cause of death and family.
- **Grouping:** each person is grouped by field: politics, sport, literature, royalty and so on.
- **Breakdown:** `docs/data-breakdown.md` holds counts only. Regenerate it after every run with `python -I acquisition/pipeline/breakdown.py`.

## Done: living leaders and sector organisations (`leaders-2026-11-10`)
On your instruction of 2026-11-10: add the brief's groups 1 to 5 (section 4), at most 2,000 profiles.

- **Count:** 1,989 records. 2,000 were selected and 11 failed the final sensitive-term scan.
  - Living people: 549 creative or sports figures (180 of them in sport), 547 public-sector leaders, 437 academics or professionals, 124 business leaders and 25 NGO or community leaders.
  - Organisations: 260 healthcare, 26 chambers of commerce and 21 finance and investment.
  - Total in staging: 13,489.
- **How people were found:**
  - The top 1,500 living people per country by number of Wikipedia editions. Footballers count only with 30 or more editions.
  - Current chief executives, chairs, rectors and heads of the organisations we already hold. Wikidata lists only 337 of these.
- **Grouping:** a person who leads one of our organisations takes that organisation's category. Otherwise their occupations and positions decide the group. Politicians always count as public-sector leaders.
- **Per-country limits:** every country gets 2 people per group first, and no country takes more than 15% of a group. Room left by short groups went first to academics, public sector, creative arts and healthcare, and to sport last.
- **Fields kept (REQ-139):** name, description, title, organisation, positions held with years, boards, memberships and awards.
- **Never read (REQ-140):** birth date, birthplace, education, family, party, religion and ethnicity. Party-like memberships and positions are dropped, and so is anyone whose description names a party.
- **Excluded:**
  - religious and military roles, though heads of state with a military past stay
  - coup leaders
  - traditional rulers and royals
  - activists who lead no organisation, because their activism can reveal political opinion
  - anyone with a sensitive term
  - names containing digits
  - people already in the directory
- **Criteria:** the plan's I1 (public office), I2 (leads a qualifying organisation), I3 (university head) and I6 (award) are recorded, but none is met yet. Wikidata is not the evidence source the plan names for them. Every living person is flagged `living_person_review_required`.
- **Gaps:**
  - Wikidata has few executives and finance firms, and no professional-services firms passed the checks.
  - Business leaders came to 124 against a target of 300, and finance to 21 against 160. The spare room went to the other groups.
- **Fixes in the same run:**
  - A stray control character had stopped the Government category matching plain "government".
  - Organisations re-categorised: Government 1,986 → 2,015; a new Healthcare category holds 279.
  - "Director-General" and "chief executive officer" no longer count as military or royalty.
  - Birth years written as "1992 -" are now stripped.
- **Known side effect:** for 865 candidates that earlier runs had also considered, this run's exclusion reason replaced the earlier one in the `exclusions` table.

## Done: Achievement, Influence and Legacy text (`reword.py`)
On your instructions of 2026-11-11: every profile needs Achievement, Influence and Legacy, reworded rather than copied from the source. First with DeepSeek; then, when its balance ran out, written by Claude in this session.

- **Result:** all 13,412 records have a reworded bio plus the three sections.
  - 2,525 were written by DeepSeek V4.1-Flash: 1,631 living people, 893 deceased and 1 entrepreneur. The ¥9.78 balance ran out.
  - 10,887 were written by Claude in this session, by subagents in batches of 50: `reword.py export`, then `import`. No API key was used.
- **Method:** each record's stored facts go to the writer as numbered facts. The writer returns the four fields and lists the facts each one uses. The brief's definitions (section 3) are in the rules.
- **Checks in code:** a failing draft is fixed and imported again.
  - every number and proper name appears in the facts
  - no he or she
  - no hype words
  - no talk of "the directory" or "records"
  - for living people, nothing on birth, family, religion, party or education
  - no 10-word span copied from source prose
  - the existing sensitive-term scan
- **Thin facts:** 9,268 profiles are flagged `ai_text_thin_facts`, most of them organisations and entrepreneurs.
  - Their facts are little more than a name, a type and a country, so the text is accurate but general.
  - More detail needs more sources, not more writing.
- **Review:** all AI text is flagged `ai_text_needs_review`.
- **The two writers differ slightly:** DeepSeek kept deceased people's lifespans, such as "(1944–2026)", but some Claude batches left out birth years.
- **77 records removed:** they were found while writing. Finalise now skips them by QID (`MANUAL_EXCLUDE_QIDS`, with a reason for each in `exclusions`).
  - 35 are not organisations: topics, places, buildings, job titles and Wikimedia category or list pages.
  - 6 are religious or political.
  - 5 are military, security or prison bodies.
  - 31 are not African, for example Elsevier, Huobi, Skytrax and Actiris.
  - The type filters missed them, often because the name is not in English.
- **Spam website text:** 61 organisations' own-site descriptions are gambling, adult or placeholder pages from lapsed domains. The table hides them.
  - They are still in `records.site_description`.
  - Clearing them there would invalidate the drafts' fact hashes, so it waits for the next re-draft.
- **Source-data problems:** the writing agents reported about 140, listed in `data/review/data-issues-2026-11-11.md`. That file is git-ignored because it names people.
  - The main patterns are below.
  - **Wrong country:** organisations with several countries get the country we searched under, not their headquarters (Sonatel under Guinea; African Leadership University under Mauritius). London companies appear under South Africa.
  - **Wrong type:** schools and colleges are typed "University", and several bodies are typed "Open-access publisher".
  - **Template bugs:** "X is a X is …" doubling, and organisations shown as "part of" themselves.
  - **City field:** it often holds a country, region or building instead of a city.
  - **Encoding:** some site text has broken accents ("soci?t?").
- **DeepSeek key:** it was in no file. **Rotate it, because it was pasted into a chat.**

## Differences from the plan
| Plan | This run | Why |
|---|---|---|
| 500 organisations, 200 entrepreneurs, 300 individuals; 850 Nigeria | 8,950 and 1,557, no notable individuals; all African countries | Your instructions; the mix is what the filters allow |
| MVP excludes deceased persons (plan 1.4; REQ-139 has only a current title) | 999 deceased people collected, with birth and death years | Your instruction; REQ-140 (no date of birth) was written for living people, so Legal should confirm it does not apply |
| Legal basis before any individual's data is processed, including AI (REQ-169, Q35, Q63) | Skipped, on your instruction. Since 2026-11-11, records including living people's stored facts are sent to DeepSeek (China) for rewording | Nothing was published. No transfer basis (NDPA s.41–43) is recorded for DeepSeek, and its data-use terms were not reviewed |
| Claude Opus 5.5 drafts from numbered source passages, and each claim quotes its passage exactly (Q39, REQ-165–167) | DeepSeek V4.1-Flash (2,525 records), then Claude in this session (10,887), reword the stored facts. Names and numbers are traced to the facts, but there are no exact quotes | Your choices of writer. Rewording cannot quote exactly |
| No photos or logos until Legal advises (Q34) | Collected, on your instruction | Each stores licence or credit, and source |
| Entrepreneurs meet E1 or E2 from a primary source | 1 meets E1; 1,556 marked `LEAD` | Wikidata alone cannot prove E1 or E2 |
| AI-drafted bios with quote checks | Template bios from Wikidata descriptions and fields | Your choice; no prose copied from Wikipedia |
| Regulator lists (CBN, PenCom, NUC, State House) | Not used | Coded manual-only; terms not read |
| Suppression check against the list | Passes, but no list exists yet | WordPress does not exist to supply it |
| Source approval per REQ-137 | Wikidata, Commons and organisations' own sites, on your instruction ("sources specified in the acquisition plan") | No per-source sign-off record beyond this |

## Before any of this goes public
- Legal: everything in plan 6.5 still applies.
- Human review of every record.
- 61 organisation site descriptions are spam or adult content from lapsed domains. The table hides them, but they must be cleared before any export.
- 2,355 logos come from organisations' own sites and are not licensed. They need permission or removal.
- Exclusion rules are keyword rules, mostly in English, and will have misses. Check `flags` and the `LEAD` criterion. Non-English text is less well covered.
- Only 3,164 organisation websites loaded. 3,247 list no website and the rest failed or were blocked, so those organisations are not confirmed active.
- 310 records are flagged as possible duplicates. They are not merged (REQ-146).

## Decisions needed
- Bios are short where the source facts are thin. AI rewording reads better but cannot add facts. Thin profiles need more sources, such as organisation sites or Wikipedia summaries; using Wikipedia summaries amends the plan's "never copy prose" rule.
- Should this run be kept, re-run under the plan, or used only as a seed list for it?

## For shared docs
The shared-docs owner should apply these:
- Record the 2026-11-09 instruction to skip Legal for local collection as a decision, stating what it does not cover (publication). Affects REQ-169, Q34, Q35, Q63.
- D-010 and Q29: this run covers all African countries. Plan 1.6's split is overtaken for the test data, not yet as policy.
- Add to the plan's research log: `query.wikidata.org/robots.txt` disallows `/sparql` for crawlers, although the WDQS manual documents scripted use under a User-Agent policy.
- Decide whether deceased notable people are in scope (plan 1.4 excludes them; REQ-139 assumes living people). If yes, add a REQ and a criterion code (this run uses `HIST`).
- `docs/contracts/import-format.md`: this run's records are not yet in contract format. An export step is still to be written.
- Living notable people beyond entrepreneurs (run `leaders-2026-11-10`) are collected under plan criteria I1, I2, I3 and I6, using Wikidata as a lead source only. The plan's 300 "individuals" need a REQ that says which groups count, and how creative and sports figures qualify; I6 needs the named-award list.
- Record the new organisation category Healthcare (brief 4.2) alongside the existing categories.
- Q39 and REQ-169: 2,525 records were reworded by DeepSeek (`deepseek-flash`), the rest by Claude in a Claude Code session, not through the Anthropic API. Record it as a decision. Legal needs the transfer basis and a review of DeepSeek's data-use terms before any of this text is published.
- Profile fields: every profile now carries Achievement, Influence and Legacy (brief 3.1–3.3). `docs/contracts/import-format.md` and the profile REQs need these three fields.
