# CLAUDE.md

## Session
- Start: read STATUS.md; run `gh issue list --state open`; read only the docs needed.
- End: update STATUS.md and commit.

## Pipeline
review-spec -> architect -> plan -> issues -> build waves -> verify -> ship.
Human gates after: the question list, the architecture, the plan.

## Rules
- Every requirement is REQ-### in docs/requirements.md; every issue, PR and test cites REQs.
- No scope creep: discoveries become new issues labelled `type:discovered`.
- Never guess on blocking unknowns: write them to docs/questions.md and stop.
- Only a separate reviewer step marks work done. Tests first.
- Never commit secrets, never read `.env*`, never force-push.
- One branch + PR per issue ("Closes #N").
- Speed: finish as early as possible; deadlines in the brief are ceilings, not targets; parallelise; only external lead times and human approvals are real constraints.
- Terms and ID codes (REQ, C, Q, D, N, M, ADR) are explained in docs/GUIDE.md; use them consistently.
