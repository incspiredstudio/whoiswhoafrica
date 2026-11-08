# Acquisition

Gathers data for the first 1,000 profiles and exports it for import into WordPress. **The pipeline never publishes** (D-033 as amended). Only the acquisition session writes here (see `CLAUDE.md`).

| Folder | Holds | In git? |
|---|---|---|
| `docs/` | Plan, change reviews, review guidelines, approved source list | Yes |
| `pipeline/` | Pipeline code and staging-database schema migrations | Yes |
| `data/` | Staging database, fetched pages, drafts, backups, real export bundles | **Never** |
| `exports/` | Schemas and sample exports with no personal data | Yes |

**The only interface with the website is `docs/contracts/import-format.md`.**
