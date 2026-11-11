# Pipeline

Code for seeding, fetching, drafting, basic verification, staging and export, plus `migrations/` for the staging database.

- `run_first_1000.py`: the 2026-11-09 all-Africa test run. Stages: `seed`, `enrich`, `select`, `sites`, `select` again, `images`, `logos`, `finalise`. Run `images` and `logos` one at a time.
- Deceased notable people: run `seed_deceased`, then `enrich`, `select`, `images` and `finalise`.
- Living leaders and sector organisations (brief section 4): run `seed_leaders`, `enrich_leaders`, `select_leaders`, `sites_leaders`, `images`, `logos` and `finalise`. `finalise` always rebuilds every run's records, this one included.
- `reword.py`: rewords each record's stored facts into a bio plus Achievement, Influence and Legacy, using DeepSeek. Run `draft` with `DEEPSEEK_API_KEY` set in the environment (never in a file). It resumes, skipping records already drafted from unchanged facts. `finalise` re-applies the drafts after rebuilding the records. Use `show <record_id>` to see the facts and the draft. Without an API: `export` writes the records still lacking text as batches of 50 to `acquisition/data/claude_batches/`, with the writing rules; a Claude session writes `out_NNN.json` per batch, and `import <file>` checks and stores it.
- `breakdown.py`: writes `acquisition/docs/data-breakdown.md` (counts only). Run it after every run.
- `build_table.py`: builds the filterable table at `acquisition/data/directory.html`.

Run from the repo root: `python -I acquisition/pipeline/run_first_1000.py <stage>`. See `acquisition/STATUS.md`.

There is no AI drafting step or contract export yet.

WordPress schema changes do not belong here. Output format: `docs/contracts/import-format.md`.
