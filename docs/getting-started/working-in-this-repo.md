# Working in this repo

This page summarises the rules in `CLAUDE.md` that govern who may edit what and how changes are recorded. If this page and `CLAUDE.md` disagree, `CLAUDE.md` wins.

## Layout and ownership

| Folder | Holds | Written by |
|---|---|---|
| `docs/` | Shared product docs | The shared-docs owner named in `STATUS.md` |
| `prototype/` | The clickable wireframe | The wireframe session |
| `acquisition/` | Data acquisition: `docs/`, `pipeline/`, git-ignored `data/`, `exports/` with no personal data | The acquisition session |
| `site/` | The WordPress code (plugins, theme, tests, deploy), created at the first build step | The website build sessions |
| `scripts/`, `.github/`, `CLAUDE.md`, `STATUS.md`, `.gitignore` | Shared tooling and rules | The shared-docs owner |

Rules that follow from this:

- A session writes only inside its own folder.
- A session that does not own the shared docs lists what it needs under "For shared docs" in its folder's status file. The owner applies it.
- One exception: the wireframe session may regenerate `docs/wireframe-inventory.md`, edit `docs/wireframe-findings.md`, and edit the inventory script and the smoke test.
- `docs/contracts/import-format.md` is the only interface between acquisition and the website. Neither side reads the other's folders or data.
- Sessions running at once use separate git worktrees.

## Working rules

| Rule | Detail |
|---|---|
| Every requirement has an ID | REQ-### in `docs/requirements.md`. Every issue, PR and test cites REQs |
| No scope creep | A discovery becomes a new issue labelled `type:discovered` |
| Never guess on a blocking unknown | Write it to `docs/questions.md` (or to the status file) and stop |
| Tests first | Only a separate reviewer step marks work done |
| No secrets | Never commit secrets, never read `.env*`, never force-push |
| No personal data | Anything fetched, drafted or staged stays in `acquisition/data/` |
| Scope tags | Every REQ, D, C and Q has a scope: SITE, ACQUISITION or BOTH. Never renumber |

## Branches and commits

| Item | Convention |
|---|---|
| Branch | `area/issue-N-short-slug`, one branch and one PR per issue |
| Commit | Imperative mood, cites REQ, for example `feat: ... (REQ-252)` |
| PR body | `Closes #N`, the REQ list and the test evidence |

Commit or push only when asked. Do not skip hooks.

## Status files

| At the end of a session | Update |
|---|---|
| Shared-docs owner | `STATUS.md` |
| Any other session | Its folder's status file: `prototype/STATUS.md` or `acquisition/STATUS.md` |

Then commit. The status file says what was done, what is open and what is next, so the next session can start without reading everything.

## Changing a rule

1. Record the ruling as a new decision (D-###) with its date and what it changes. Never reuse a number.
2. Amend or withdraw the affected REQs. A withdrawn REQ keeps its number and stays in the file.
3. Update the questions list if a question was answered or raised.
4. Update the prototype and the inventory if a screen changes, then run the checks.

## Website rules (for the build)

These apply once `site/` exists:

- Public templates read profiles and stories only through the `PublicProfile` and `PublicStory` read models.
- Only `Review\StateMachine` writes a profile's publication state.
- Every staff action on profiles, claims, labels, the register, imports and bulk unpublish goes through `AuditLogger`.
- Escape all output, verify nonces, check a named `wiw_*` capability on every admin action, and use `$wpdb->prepare`.
- No brand values outside the theme tokens file. No payment code (D-005).
- Only the Administrator changes roles or capabilities.

The technical design is in Architecture.

## Stack in one line

WordPress on a cPanel host, a custom plugin `whoiswho-core`, a must-use plugin `whoiswho-guard`, a theme `whoiswho`, MariaDB or MySQL, server-rendered PHP templates, email through cPanel SMTP, media in the WordPress library.
