# Build guide

This section is for developers and agents who will build the WordPress site. It summarises the architecture in plain steps and points to the full architecture document for detail.

**Status:** the architecture is proposed and awaits the Gate 2 approval. No application code exists yet. Anything unproven is marked "verify" in the architecture.

## What is being built

One WordPress site with the product rules in three code packages:

| Package | Kind | Job |
|---|---|---|
| `whoiswho-core` | Plugin | The product: profiles, review, import, awards, sponsored story deals, enquiries, corrections, requests and reports, capabilities, audit log |
| `whoiswho-guard` | Must-use plugin | Closes the surfaces where WordPress could leak a non-public profile, locks core REST to staff, sets headers and hardening. An Administrator cannot deactivate it |
| `whoiswho` | Theme | Server-rendered PHP templates with brand values in one tokens file. No page builder; staff edit page copy in the native block editor (D-060) |

The site is built and tested locally and then migrated to the existing cPanel host (D-050). No Node server, message broker, Redis, payment gateway or front-end framework is needed.

## Where the code will live

Website code lives in `site/`, created at milestone M0 and written only by the website build sessions. Other folders:

| Folder | Owner |
|---|---|
| `docs/` | Shared product docs; one session edits them at a time (the owner named in `STATUS.md`) |
| `prototype/` | The wireframe session |
| `acquisition/` | The acquisition session |
| `site/` | The website build |

The import contract (`docs/contracts/import-format.md`) is the only interface with acquisition.

## Areas and ownership

An agent edits only its own area. A need in another area becomes an issue for the owner.

| Area | Owns |
|---|---|
| Plugin | `site/wp-content/plugins/whoiswho-core/**`, `site/wp-content/mu-plugins/**`, `site/bin/`, unit, integration and architecture tests. Only this area writes migrations |
| Theme | `site/wp-content/themes/whoiswho/**`, the tokens file, assets, UI specs in `tests/e2e` |
| Infra | `.github/`, `deploy/`, `scripts/`, `composer.json` and lockfile, the `wp-config` template, `docs/runbook.md`, `.env.example` |
| QA | `tests/e2e`, `tests/Visibility`, `tests/Security`, traceability and verify reports. Writes no feature code |

## The pipeline

Work moves through stages, with a human gate after the question list, the architecture and the plan.

| Stage | Output |
|---|---|
| Review the brief | Requirements, questions, decisions (Gate 1) |
| Architecture | Architecture, ADRs (Gate 2) |
| Plan | Milestones (Gate 3) |
| Issues | GitHub issues, traceability |
| Build waves | Code and pull requests; parallel agents |
| Verify | An independent audit against requirements |
| Ship | Runbook and handover; production only on your "go" |

## The working rules

- Every requirement is a REQ number, and every issue, pull request and test cites it.
- No scope creep: discoveries become issues labelled `type:discovered`.
- Never guess on a blocking unknown. Write it to the questions file and stop.
- Only a separate reviewer step marks work done. Tests come first.
- Never commit secrets, never read `.env*`, never force-push, never commit personal data.
- One branch and one pull request per issue ("Closes #N").
- Speed: deadlines in the brief are ceilings, not targets. Parallelise. Only external lead times and human approvals are real constraints.

## The first steps (M0, in order)

1. Scaffold `site/` with a pinned WordPress, the three packages, a local environment and the lint, analysis, test and build tools.
2. CI with every gate passing on empty suites.
3. A repeatable go-live migration script (needed only at go-live).
4. Platform core: roles and capabilities, enforced two-factor, closed registration, the audit logger, settings, the Nominations flag, the personal-data catalogue.
5. Core data and the choke point: the profile type, the state machine, the status guard, the guard plugin, the public read model, and the visibility test matrix.
6. Theme shell: tokens, layout, components, a CI check against brand values outside the tokens file.
7. Architecture tests for module boundaries and single writers.
8. The import path: contract validator, hash check, dry run, chunked apply, undo, result.
9. Cache purge and the 410 handler, then bulk unpublish.

After M0, parallel waves cover directory pages, the review queue, the enquiries, corrections and requests screens and forms, signed links, sponsored story deals, stories, awards, the capability screen, email templates, analytics and the runbook.

## Pages in this section

| Page | Covers |
|---|---|
| [Stack](/build/stack) | Technology per layer and why |
| [Data model](/build/data-model) | Content types, taxonomies, custom tables |
| [Roles in code](/build/roles-in-code) | Capabilities, locked ones, the audit logger, architecture tests |
| [Security](/build/security) | Threats, secrets, validation, rate limits |
| [Testing and CI](/build/testing-and-ci) | Test approach and merge gates |
| [Deployment](/build/deployment) | Local build, migration to cPanel, rollback |
| [Decisions guide](/build/) | How decisions, requirements, questions and ADRs relate, plus the ADR list |

## Changes since the architecture was written

The architecture is dated 2026-11-08 to 09. Later decisions change some of it:

- **D-053:** prefer native WordPress features; profile state may be a registered field instead of a custom table unless measured queries need one (Q82).
- **D-059:** Verified is retired, the staff mark is Claimed, and claim reminders, home curation, story comments and the nominations switch are added.

Where this section differs from the architecture document, this section follows the later decision.
