# Build plan: how the issue files are written

The build plan is a set of JSON files in `docs/plan/`, one per milestone. A generator script (`scripts/plan-issues.js`) turns them into GitHub milestones, labels, issues and project items. Nothing in this folder is published on the docs site.

## Milestones

| Milestone | Name | Holds |
|---|---|---|
| M0 | Foundations | Repo scaffold in `site/`, local environment, CI, test harness, guard plugin skeleton, parent and child theme skeleton, tokens, migrations, roles and capabilities, audit logger, settings service, email, platform quality basics |
| M1 | The record | Directory and profiles, publication states and review, import and sources, SEO and sitemaps, corrections, removals and reports |
| M2 | Reading and finding | Search, stories, events, home and curation, newsletter |
| M3 | Trust and revenue | Claims, get listed and enquiries, sponsored stories and deals, privacy and data rights |
| M4 | Recognition | Awards and nominations, comments, editable pages and copy |
| M5 | Launch | Hardening, accessibility and performance passes, the launch checklist, human tasks, migration to the live host |

## File format

Each file is `docs/plan/<milestone-id>-<slug>.json` containing `{ "milestone": "M1", "issues": [ ... ] }`. Each issue:

```json
{
  "key": "M1-DIR-03",
  "title": "Imperative title, 70 characters or fewer",
  "milestone": "M1",
  "area": "plugin",
  "feature": "directory-and-profiles",
  "priority": "M",
  "size": "M",
  "reqs": ["REQ-208", "REQ-217"],
  "depends": ["M0-PLG-02"],
  "summary": "One or two sentences: what this delivers and why.",
  "scope": ["Short bullet of what to build", "Another bullet"],
  "acceptance": ["Testable statement that can be ticked", "Another"],
  "tests": ["test_REQ_208_hides_unreviewed_profiles_from_sitemap"],
  "docs": ["docs/features/directory-and-profiles.md", "docs/plugins/directory.md"],
  "files": ["site/wp-content/plugins/whoiswho-core/src/Directory/**"],
  "notes": "Optional: proposals, open questions, risks"
}
```

## Field rules

| Field | Rule |
|---|---|
| `key` | `<milestone>-<3 to 4 letter feature code>-<two digit number>`, unique across all files, never reused |
| `area` | `plugin`, `theme`, `infra`, `qa` (the four code areas in CLAUDE.md), or `human` for work only a person can do (decisions, licences, legal text, host checks) |
| `size` | `S` under half a day for an agent, `M` up to a day, `L` up to two days. Split anything larger |
| `priority` | `M` must-have, `S` should-have, as in the feature catalogue |
| `reqs` | Every issue cites at least one REQ id that exists in `docs/requirements.md` and is not withdrawn. Human tasks may cite none |
| `depends` | Keys of issues that must be done first. Only real dependencies. No cycles. A key may point to an earlier milestone |
| `acceptance` | Each item is a checkable statement taken from the feature page, the plugin page or the theme page, not invented |
| `tests` | Test names in the form `test_REQ_<n>_<behaviour>`. Tests are written first. Theme and end-to-end items may name a Playwright spec instead |
| `docs` | Repo paths of the pages an agent must read before starting. Always include the feature page and the matching plugin or theme page |
| `files` | The paths the issue may write. Stay inside one area |

## Rules for splitting work

- One issue is one pull request. It stays inside one area, so two agents never edit the same files.
- A plugin domain issue and the theme issue that shows it are separate, joined by `depends`.
- Foundations before features: nothing in M1 or later assumes something not created in M0.
- Do not invent scope. If a feature page is unclear, put the question in `notes` and keep the issue to what the docs state.
- Human tasks (for example confirming licences, Legal text, the host check) are issues with `area: human`, so they are visible and owned.
