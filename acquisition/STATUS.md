# Acquisition status

Updated 2026-11-09 by the acquisition session. This session does not own the shared docs.

## Done: first 1,000 test run (`first-1000-2026-11-09`)
Run on your instruction of 2026-11-09: skip Legal, 1,000 notable entrepreneurs and organisations, local SQL database, filterable HTML table.

- **Code:** `pipeline/run_first_1000.py`, `pipeline/build_table.py` (Python standard library only; stages resume).
- **Data, git-ignored:** `data/staging.sqlite`, `data/images/`, `data/first-1000.html`.
- **Counts:** 600 organisations, 400 entrepreneurs. 850 Nigeria; the rest Ghana, Kenya, South Africa and pan-African bodies.

**This is staging data. No person has reviewed it and nothing is published.** It does not replace plan run A or B.

## Differences from the plan
| Plan | This run | Why |
|---|---|---|
| 500 organisations, 200 entrepreneurs, 300 individuals | 600 and 400, no notable individuals | Your request named entrepreneurs and organisations; the 600/400 split is my choice |
| Legal basis before any individual's data is processed, including AI (REQ-169, Q35, Q63) | Skipped for local collection, on your instruction | No AI API was used, and nothing was published |
| No photos or logos until Legal advises (Q34) | Collected, on your instruction | Each stores licence, credit and source |
| Entrepreneurs meet E1 or E2 from a primary source | 1 meets E1; 399 marked `LEAD` | Wikidata alone cannot prove E1 or E2 |
| AI-drafted bios with quote checks | Template bios from Wikidata descriptions and fields | No Anthropic API key; no prose copied from Wikipedia |
| Regulator lists (CBN, PenCom, NUC, State House) | Not used | Coded manual-only; terms not read |
| Suppression check against the list | Passes, but no list exists yet | WordPress does not exist to supply it |
| Source approval per REQ-137 | Wikidata, Commons and organisations' own sites, on your instruction ("sources specified in the acquisition plan") | No per-source sign-off record beyond this |

## Before any of this goes public
- Legal: everything in plan 6.5 still applies.
- Human review of every record.
- 270 logos come from organisations' own sites and are not licensed. They need permission or removal.
- Exclusion rules are keyword rules and will have misses, so some religious, political or entertainment figures may remain. Check `flags` and the `LEAD` criterion.

## Decisions needed
- Is the 600/400 split right, with no notable individuals?
- Should this run be kept, re-run under the plan, or used only as a seed list for it?

## For shared docs
The shared-docs owner should apply these:
- Record the 2026-11-09 instruction to skip Legal for local collection as a decision, stating what it does not cover (publication). Affects REQ-169, Q34, Q35, Q63.
- Add to `acquisition/docs` or the plan's research log: `query.wikidata.org/robots.txt` disallows `/sparql` for crawlers, although the WDQS manual documents scripted use under a User-Agent policy.
- `docs/contracts/import-format.md`: this run's records are not yet in contract format. An export step is still to be written.
