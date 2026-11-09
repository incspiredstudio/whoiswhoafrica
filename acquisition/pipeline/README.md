# Pipeline

Code for seeding, fetching, drafting, basic verification, staging and export, plus `migrations/` for the staging database.

- `run_first_1000.py`: the 2026-11-09 all-Africa test run. Stages: `seed`, `enrich`, `select`, `sites`, `select` again, `images`, `logos`, `finalise`. Run `images` and `logos` one at a time.
- Deceased notable people: run `seed_deceased`, then `enrich`, `select`, `images` and `finalise`.
- `breakdown.py`: writes `acquisition/docs/data-breakdown.md` (counts only). Run it after every run.
- `build_table.py`: builds the filterable table at `acquisition/data/directory.html`.

Run from the repo root: `python -I acquisition/pipeline/run_first_1000.py <stage>`. See `acquisition/STATUS.md`.

There is no AI drafting step or contract export yet.

WordPress schema changes do not belong here. Output format: `docs/contracts/import-format.md`.
