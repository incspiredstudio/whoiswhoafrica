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

## Differences from the plan
| Plan | This run | Why |
|---|---|---|
| 500 organisations, 200 entrepreneurs, 300 individuals; 850 Nigeria | 8,950 and 1,557, no notable individuals; all African countries | Your instructions; the mix is what the filters allow |
| MVP excludes deceased persons (plan 1.4; REQ-139 has only a current title) | 999 deceased people collected, with birth and death years | Your instruction; REQ-140 (no date of birth) was written for living people, so Legal should confirm it does not apply |
| Legal basis before any individual's data is processed, including AI (REQ-169, Q35, Q63) | Skipped for local collection, on your instruction | No AI API was used, and nothing was published |
| No photos or logos until Legal advises (Q34) | Collected, on your instruction | Each stores licence or credit, and source |
| Entrepreneurs meet E1 or E2 from a primary source | 1 meets E1; 1,556 marked `LEAD` | Wikidata alone cannot prove E1 or E2 |
| AI-drafted bios with quote checks | Template bios from Wikidata descriptions and fields | Your choice; no prose copied from Wikipedia |
| Regulator lists (CBN, PenCom, NUC, State House) | Not used | Coded manual-only; terms not read |
| Suppression check against the list | Passes, but no list exists yet | WordPress does not exist to supply it |
| Source approval per REQ-137 | Wikidata, Commons and organisations' own sites, on your instruction ("sources specified in the acquisition plan") | No per-source sign-off record beyond this |

## Before any of this goes public
- Legal: everything in plan 6.5 still applies.
- Human review of every record.
- 2,355 logos come from organisations' own sites and are not licensed. They need permission or removal.
- Exclusion rules are keyword rules, mostly in English, and will have misses. Check `flags` and the `LEAD` criterion. Non-English text is less well covered.
- Only 3,164 organisation websites loaded. 3,247 list no website and the rest failed or were blocked, so those organisations are not confirmed active.
- 310 records are flagged as possible duplicates. They are not merged (REQ-146).

## Decisions needed
- Bios are short (median 115 characters for organisations, 66 for people). Longer ones need Wikipedia summaries, which amends the plan's "never copy prose" rule, or the AI step.
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
