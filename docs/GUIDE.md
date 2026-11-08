# How to read this repo: guide and glossary

Put this file at `docs/GUIDE.md`. It explains the codes, symbols and files you will see. Claude Code reads it too, so it always uses the same terms.

## 1. The big picture
The repo turns the product brief into a built product in stages. A human approves at three **gates**. Everything else is done by Claude Code agents.

| Stage | What happens | Output files | Your gate |
|---|---|---|---|
| 1. Review the brief | Extract requirements, find gaps, ask questions | `requirements.md`, `questions.md`, `decisions.md` | Gate 1: answer the questions |
| 2. Architecture | Choose tech, design the system, security, hosting | `architecture.md`, `adr/`, `.env.example` | Gate 2: approve the stack |
| 3. Plan | Milestones, tasks, tracking | `plan.md` | Gate 3: approve the plan |
| 4. Issues | Create GitHub issues and the project board | GitHub issues, `traceability.md` | none |
| 5. Build waves | Parallel agents build, review, merge | code, pull requests | review merges when asked |
| 6. Verify | Independent audit against requirements | `verify-*.md` | read go / no-go |
| 7. Ship | Deploy and hand over | `runbook.md`, `handover.md` | say "go" for production |

## 2. Codes and what they mean
| Code | Stands for | Where it lives | Example |
|---|---|---|---|
| **REQ-###** | A numbered requirement: one testable statement about what the product must do. Numbers never change. | `requirements.md` | REQ-179 admin commercial register |
| **C#** | **C**ontradiction or gap found in the brief | `questions.md` (top table) | C8: "10 subscriptions or NGN 5M" |
| **Q#** | A **Q**uestion Claude Code needs you to answer | `questions.md` | Q2: publish individuals without consent? |
| **D-###** | A **D**ecision recorded from your answers. Each links to its C# or Q# and the REQs it affects. | `decisions.md` | D-013 = your answer to Q2 |
| **N#** | A non-software work item (brand, legal, sales, events, awards rules, accounts and DNS). Tracked but not built by Claude Code. If a decision removes one it is marked Withdrawn, like a REQ. | section 9 of `requirements.md` ("Non-software work") | N8: award categories, eligibility, judging panel and governance |
| **ADR** | Architecture decision record: one short note per big tech choice, with the reason | `docs/adr/` | "Why we chose this database" |
| **M#** (M0, M1...) | A **M**ilestone in the plan. M0 is always the foundation. | `plan.md` | M1: public site and directory |
| **#123** | A GitHub issue or pull request number | GitHub | `Closes #42` |
| **[implied]** | A requirement the brief never states but needs to work | `requirements.md` | |
| **[Mx-y]** | Task y of milestone x, in an issue title | GitHub | [M1-3] |

## 3. Symbols and terms
- **§** = section of the brief. "§9.3" means section 9.3.
- **BLOCKING** question: a wrong guess would force rework, so it must be answered or a default accepted. **ASSUMABLE**: Claude Code can proceed on its default.
- **Status values in questions.md:** Answered (you gave an answer), Default accepted (you agreed with Claude Code's suggestion), Pending - team lead (waiting on someone else), Open (nobody has answered).
- **Withdrawn:** a requirement removed by a decision. It keeps its number and stays in the file for traceability, but is not built.
- **Gate:** a stop where Claude Code waits for you.
- **Wave:** one round of agents building several issues in parallel.
- **Orchestrator:** the Claude Code session that picks issues and launches builder agents. It doesn't write feature code.
- **Builder / reviewer / security reviewer:** the agents. Builders write code. A separate reviewer checks it. Only the reviewer marks work done.
- **Traceability table** (`traceability.md`): one row per REQ showing issue, pull request, test, and whether it was verified. It proves nothing was missed.
- **Feature flag:** a switch that keeps a built feature hidden until you turn it on (for example nominations).
- **Kill switch:** one switch that unpublishes all unclaimed individual profiles immediately.
- **Suppression list:** people who asked to be removed, so they are never republished.
- **Staging:** the test copy of the site before production.
- **Preview:** in this project the test site is public and indexable, so only human-approved profiles appear on it.
- **Provisional theme:** temporary styling until the approved brand assets arrive. All brand values sit in one config file.

## 4. Profile and listing labels (never mix these up)
| Label | Meaning | Can it be bought? |
|---|---|---|
| **Unclaimed** | WHOISWHO created the profile from public sources; the person has not claimed it | no |
| **Claimed** | The person or organisation took control of the profile | no |
| **Verified** | Staff checked it against a checklist | **never** |
| **Featured** | Chosen by the editors | **never** |
| **Sponsored** | Paid placement or content, always labelled and never implies verification | yes |

## 5. Files at a glance
| File | Purpose | Edited by |
|---|---|---|
| `docs/spec.md` | The original brief (cleaned copy). Source of truth for intent. | you |
| `docs/requirements.md` | What the product must do (REQ numbers) | Claude Code |
| `docs/questions.md` | Contradictions and questions, with a Status column | Claude Code, from your answers |
| `docs/decisions.md` | Every decision (D numbers) | Claude Code |
| `docs/architecture.md`, `docs/adr/` | Tech choices and design | Claude Code |
| `docs/plan.md` | Milestones, tasks, timeline | Claude Code |
| `docs/wireframe-inventory.md`, `prototype/` | Every screen and flow, and the clickable wireframe. **Since D-034 there are no login, signup, owner-area or judge screens; only staff use wp-admin.** | Claude Code |
| `docs/design-inspiration.md` | Visual references and open design questions, for the later design phase only | Claude Code |
| `docs/traceability.md` | REQ to issue to test to verified | Claude Code |
| `docs/verify-*.md` | Audit results with go / no-go | Claude Code |
| `STATUS.md` | Where the project is right now; every session reads it first | Claude Code |
| `CLAUDE.md` | The standing rules every session follows | you and Claude Code |
| `.env` / `.env.example` | Secrets (never committed) / the list of needed variables | you / Claude Code |

## 6. How to answer a question
Reply in one message: "Accept all defaults except Q2: ..., Q5: ...". Claude Code writes the decisions and updates the Status column. If you don't know, say "Pending - <person>" and name who will decide.

## 7. Rules that always apply
- Speed first: finish as early as possible; the brief's dates are ceilings.
- Claude Code builds everything; you approve at gates and handle outside work (accounts, legal, content, sales).
- No payment processing on the website; payments are offline and admins record them.
- No login or signup on the public website for anyone (D-034). Only staff use wp-admin, with two-factor. Claims, corrections and nominations use forms and single-use signed email links. Judging is offline.
- Never commit secrets or read `.env` files.
- Nothing publishes without human review.
- New ideas found during the build become new issues, not extra scope.

## 8. Where to look for what
- "What did we decide about X?" → `decisions.md`.
- "Is X being built?" → `requirements.md` for the REQ, then `traceability.md` for its status.
- "What is blocked?" → `STATUS.md` and the Pending items in `questions.md`.
- "What's next?" → `STATUS.md`, or ask Claude Code for a status summary.
