# ADR-009 AI drafting: interface plus mandatory claim verification

Status: Proposed (Gate 2) · Date: 2026-11-06 · REQs: REQ-165 to 170, 142

**Context.** AI drafting is allowed (D-017) but hallucination about real people is the top risk.

**Decision.** An AiDrafter interface calls the Anthropic API server-side. Output must be JSON claims with passage id and verbatim quote; our code verifies the quote against stored source text and drops anything unsupported or on prohibited topics. Flag-controlled; prompt version, model and source hash are stored.

**Why.** Verification happens in code, not by trusting the model.

**Rejected.** Free-form summarising (unverifiable). Self-hosting a model (cost).

**Consequences.** Fewer claims per page; reviewers still approve every profile.
