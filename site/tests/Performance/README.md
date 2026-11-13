# Performance tests

These tests seed fictional, synthetic rows (no personal data), time the real queries on MariaDB, assert a budget and remove their rows. They write their figures as JSON to the system temp folder.

| Test | What it measures | Budget | Last measured (shared Windows dev machine, MariaDB 11.4) |
|---|---|---|---|
| `ListQueryAt10000Test` | Directory list, filter, sort, text search and industry counts at 10,000 profiles | list 500 ms, industry counts 1,000 ms | see issue #270 |
| `SearchAt10000Test` | `SearchService::search` over 10,000 profiles and 400 stories, 10 queries x 5 runs | p95 under 300 ms (proposal in issue #317; the human owner confirms) | p50 131 ms, p95 227 ms, worst single query 325 ms (the two name queries that return the full 20 profiles plus 20 stories; each result is read through `PublicProfile`/`PublicStory`) |
| `ImportThousandRecordsTest` | A 1,000-record import within host limits | see the test | see the test |

Notes for the infra owner:

- The search target is p95 under 300 ms at 10,000 profiles. The measurement above was taken while three other agents were using the same machine, so a quiet CI runner should be faster; if CI shows a p95 above 300 ms, look at the per-result reads in `SearchService` before the query itself.
- Run one test: `composer test -- --filter SearchAt10000`. The figures are in `<system temp>/wiw-search-10000.json`.
