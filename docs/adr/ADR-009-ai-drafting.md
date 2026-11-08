# ADR-009 AI drafting: superseded; it lives in the acquisition pipeline

Status: **Superseded** · Date: 2026-11-08 · REQs: REQ-165 to 170, 142, 166 to 169 (all ACQUISITION or BOTH) · Original decision of 2026-11-06 withdrawn

**Context.** The first draft put an `AiDrafter` interface, claim verification and an AI feature flag inside the Laravel site. Since then the pipeline was separated (D-033 as amended): it gathers, drafts and does basic verification, stores records in a private staging database and exports them. It never publishes and holds no website credential. AI drafting has no flag on the website (D-037, REQ-165).

**Decision.** There is no AI code, API key or AI provider account on the website. The site's only part is verifying what it imports: allowlisted fields, per-field provenance, the verification report, the recomputed content hash and the live suppression list (architecture.md 3.4). The design of drafting and verification is in `acquisition/docs/acquisition-plan.md` and the interface is `docs/contracts/import-format.md`.

**Why.** One side owns each risk, and the site stays small.

**Rejected.** Keeping an AI service inside WordPress.

**Consequences.** If AI monitoring or takedown ever returns, it is a separate item (REQ-238) with its own ADR.
