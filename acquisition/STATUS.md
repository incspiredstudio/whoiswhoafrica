# Acquisition status

Updated 2026-11-09 by the acquisition session. This session does not own the shared docs.

## Done: all-Africa test run
Run on your instructions of 2026-11-09: skip Legal; notable entrepreneurs and organisations; local SQL database; filterable HTML table; then 10,000 more, all African countries, longer bios, more facts rather than AI.

- **Code:** `pipeline/run_first_1000.py`, `pipeline/build_table.py` (Python standard library only; stages resume).
- **Data, git-ignored:** `data/staging.sqlite`, `data/images/`, `data/directory.html`. The first run's table is kept as `data/first-1000-run1.html`, and its records as the `records_run1` table.
- **Counts:** 10,507 records: 8,950 organisations, 1,557 entrepreneurs, 54 countries plus pan-African bodies. 989 are from the first run and 9,518 are new. **Target was 11,000 in total; 493 short.**

**This is staging data. No person has reviewed it and nothing is published.** It does not replace plan run A or B.

## Differences from the plan
| Plan | This run | Why |
|---|---|---|
| 500 organisations, 200 entrepreneurs, 300 individuals; 850 Nigeria | 8,950 and 1,557, no notable individuals; all African countries | Your instructions; the mix is what the filters allow |
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
- `docs/contracts/import-format.md`: this run's records are not yet in contract format. An export step is still to be written.
