# Performance tests

These tests seed fictional, synthetic rows (no personal data), time the real queries on MariaDB, assert a budget and remove their rows. They write their figures as JSON to the system temp folder.

| Test | What it measures | Budget | Last measured (shared Windows dev machine, MariaDB 11.4) |
|---|---|---|---|
| `ListQueryAt10000Test` | Directory list, filter, sort, text search and industry counts at 10,000 profiles | list 500 ms, industry counts 1,000 ms | see issue #270 |
| `SearchAt10000Test` | `SearchService::search` over 10,000 profiles and 400 stories, 10 queries x 5 runs | p95 under 300 ms (proposal in issue #317; the human owner confirms) | p50 131 ms, p95 227 ms, worst single query 325 ms (the two name queries that return the full 20 profiles plus 20 stories; each result is read through `PublicProfile`/`PublicStory`) |
| `concurrency.mjs` (not PHPUnit: a Node script against a served site) | 50 concurrent anonymous visitors walking home, directory, a profile and a story, 4 rounds each; response times and error rate | 0 errors; p95 under 3 s is the proposal for staging (the owner confirms), no budget on the local server | see the runbook, Concurrency baseline |
| `ImportThousandRecordsTest` | A 1,000-record import within host limits | see the test | see the test |

Notes for the infra owner:

- The search target is p95 under 300 ms at 10,000 profiles. The measurement above was taken while three other agents were using the same machine, so a quiet CI runner should be faster; if CI shows a p95 above 300 ms, look at the per-result reads in `SearchService` before the query itself.
- Concurrency: `BASE_URL=http://127.0.0.1:<port> CONCURRENCY=50 ROUNDS=4 node tests/Performance/concurrency.mjs` (needs a served, seeded site; add `INCLUDE_SEARCH=1` for the uncached search page, `WARM=0` to skip the cache warm-up). It exits 1 when the error rate is above `MAX_ERROR_RATE` (default 0) or p95 above `P95_MS` (default 3000).
- Run one test: `composer test -- --filter SearchAt10000`. The figures are in `<system temp>/wiw-search-10000.json`.
