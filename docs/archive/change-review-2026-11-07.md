# Brief update: change review (2026-11-07)

Status: **Resolved 2026-11-09 (D-052).** Brand stays WHOISWHO; legal name whoiswhoafrica; `WIWI - VENTURE BRIEF (1).md` is the most current brief and is now `docs/spec.md`; no crawler name. Q40–Q45 are answered in questions.md. Text below is the original review.

Compared: old = `docs/spec.md` (identical to the untracked `WHOISWHO - VENTURE BRIEF.md`); new = `WIWI - VENTURE BRIEF (1).md`. Method: both files normalised (embedded base64 images removed, whitespace, escapes and bold markers ignored), then line-diffed. I also decoded the one embedded image and looked at it, because it changes what the document shows.

## 1. Result in one paragraph

**The only substantive change is the name.** The brand "WHOISWHO" became "WIWI" in 73 lines, the domain line in section 2.1 changed, a title with a long-form name and a pronunciation was added, an image was added, and a closing wordmark was removed. Every number, date, role, target, feature, risk and process sentence is unchanged. The updated brief does not touch payments, tiers, publishing rules, the CMS or the sprint target.

## 2. Every change, by group

### Scope and features
None added, removed or modified.

### Numbers and targets
None. Still: 10 paid subscriptions or NGN 5 million by 31 January 2027 (section 8, 9, 17), same cover-block target text ("N5 Million").

### Dates
None. Sprint 4 November 2026 to 31 January 2027, 20 November and 20 December checkpoints unchanged.

### Roles and responsibilities
None. Venture CEO, Incspired shared services, team principles unchanged (only the product name inside those sentences changed, listed below).

### Risks
None.

### Brand and terminology

| Type | Where | Old | New |
|---|---|---|---|
| Added | Title block, line 1 | "WHOISWHO" | "Who Is Who In Africa (wiwi.africa) pronounced (We We Africa)." (long-form name, domain and pronunciation) |
| Modified | 2.1 Brand Name | "WHOISWHOINAFRICA.COM" | "WIWI.AFRICA" |
| Modified | 7.2 Maiden Edition | "WHOISWHO IN AFRICA: THE MAIDEN EDITION" | "WIWI IN AFRICA: THE MAIDEN EDITION" |
| Modified | 5.1 to 5.6 headings | "WHOISWHO Profiles / Directory / Editorial & Media / Awards / Events & Networking / Intelligence & Insights" | "WIWI Profiles" and so on |
| Modified | Section 3 and 11.2 headings | "WHAT WHOISWHO STANDS FOR", "Words Associated With WHOISWHO" | "WIWI" in both |
| Modified | Body text (the 73 changed lines include the title, the domain line and the edition name above) | "WHOISWHO is an African discovery, recognition and business intelligence platform" and similar | "WIWI is …" |
| Removed | Closing lines (section 19) | stand-alone "WHOISWHO" wordmark above the tagline | removed; tagline "INFLUENCE. ACHIEVEMENT. LEGACY." stays |

Where the 73 name changes fall: Introduction (3) and central idea (2); 2.1 to 2.7 (6); section 3 (4); section 4 (2); section 5 and 5.1 to 5.6 (10); section 6 (1); 7.1, 7.2, 7.5 (5); sprint section 8 and phases 1 to 3 (8); section 10 (4); section 11 (6); section 12 (3); section 13 (4); section 14 (2); section 15 (4); sections 17, 18, 19 (8); title block (1). Total 73.

Not changed: the tagline, the explanation in 2.1 ("The name communicates a simple but powerful question: Who are the people, businesses and institutions worth knowing?"). That sentence explains "who is who"; with the name WIWI it only works through the long form in the new title.

### Other

| Type | Where | Change |
|---|---|---|
| Added | After the cover block | A 624 x 299 PNG replaced the horizontal rule. **It still reads "WHOISWHO"**: "Master brand", the tagline, and six sub-brands (WHOISWHO Profiles, Directory, Editorial, Awards, Events, Insights). |
| Modified | Title block | Second heading "Venture Brand, Business & Execution Brief" went from level 2 to level 1 (formatting only). |

**The new brief contradicts itself:** its text says WIWI, its own image says WHOISWHO.

## 3. Impact on what we have

Effort key: **XS** under 15 minutes, **S** under 2 hours, **M** about half a day. Effort is for agents; your decisions are the real wait. The effect on every item depends on your ruling in section 4: the figures below assume the name changes. If you keep WHOISWHO, only the first four rows apply.

| Document | What is affected | Effect | Effort |
|---|---|---|---|
| docs/spec.md | Whole file | Replaced by the new brief; the old one archived. After that, spec.md says WIWI while decisions.md says WHOISWHO until you rule. | XS |
| decisions.md | D-001 | States the brand is WHOISWHO and the edition is "WHOISWHO in Africa: The Maiden Edition". In conflict with the new brief (section 4, X1 to X3). | XS |
| decisions.md | D-004, D-031 and others | Mention the name only in passing. No change of substance. | none |
| questions.md | C1 | The answer no longer matches the brief. The brief now names the same domain but a different brand. | XS |
| questions.md | New items | One new contradiction pair and questions Q40 to Q45 (section 5). | XS |
| requirements.md | REQ-002, 130, 151 (and the struck text of REQ-048) | Hard-code "WHOISWHO" in the requirement wording. Safer wording: "the brand name (from the theme config)". | XS |
| requirements.md | REQ-052, 190 | Already require brand values in one config. They do not yet name the brand name, long-form name, edition name or pronunciation as config values. Extend them; **no new REQ and no renumbering**. | XS |
| requirements.md | REQ-204 (domain wiwi.africa) | Unchanged: the brief now agrees with the domain. | none |
| requirements.md | Withdrawals | None needed. | none |
| architecture.md, ADRs, .env.example | 2 mentions; `APP_NAME`, `MAIL_FROM_NAME` | Site title and mail sender name. In WordPress these are the site title and the sender setting. architecture.md is not yet revised for WordPress, so do this in that revision. | XS |
| WordPress decision (D-031) | Whole | Not affected. | none |
| The plan | Not written yet | Must read the brand name from one config value. No work now. | none |
| docs/wireframe-inventory.md, wireframe-findings.md | 1 mention | Regenerate. | XS |
| prototype | `tokens.js` has the name and edition; but "WHOISWHO" is also typed directly in about 12 places (data.js, views-awards, views-owner, views-public, views-staff1, index.html, README) | These break REQ-190 today regardless of the ruling. Route all through the brand token; add the long-form name and pronunciation to About and the footer. | S |
| docs/acquisition-plan.md | Title and the crawler identity "WHOISWHO-bot/1.0 (+https://wiwi.africa/bot …)" | The crawler name should match the brand before the first fetch (pilot starts 10 Nov). The contact URL on wiwi.africa stays. Q29 to Q39 are otherwise unaffected. | XS |
| GUIDE.md, CLAUDE.md, STATUS.md, questions.md titles | Headings use the name | Cosmetic; change only after the ruling. | XS |
| Legal (N9), Terms and Privacy | Controller and trading name in the notices; due 21 Nov (REQ-100) | Legal needs to know which name goes in them. If the name changes, clearance of the new name is a question for Legal; I have not checked it. | external |
| Brand assets (N19) | Logo and master-brand image | The image in the brief still shows the old name, so assets may be stale or in progress. | external |

Size of change overall: about half a day of agent work if the name changes, under an hour if it does not. No effect on scope, effort estimates, the sprint target, costs, or the acquisition plan's tiers and volumes.

## 4. Conflicts with your recorded decisions: for your ruling, not resolved

| # | Your decision | The new brief says | Notes |
|---|---|---|---|
| X1 | D-001: brand is WHOISWHO ("Who Is Who In Africa"); domain wiwi.africa | Brand "WIWI", long form "Who Is Who In Africa", pronounced "We We Africa", domain wiwi.africa | The domain agrees. The brand name does not. Your precedence rule says D-001 stands until you say otherwise. |
| X2 | D-001: edition is "WHOISWHO in Africa: The Maiden Edition" | "WIWI IN AFRICA: THE MAIDEN EDITION" | Follows X1. |
| X3 | Sub-product names in the brief (section 5) | "WIWI Profiles", "WIWI Directory", "WIWI Awards" and so on | Follows X1. |
| X4 | Not a conflict with a decision, but inside the brief: text says WIWI, its master-brand image says WHOISWHO | | Cannot be resolved by me. The author needs to confirm which is current. |
| X5 | Not a conflict: 2.1 still explains the name as the question "Who are the people…worth knowing?" | | That reads as "Who Is Who", not "WIWI". Copy question for the author. |

**No conflict** with D-005 (no payments), D-028 (no tiers), D-031 (WordPress), D-013 (publishing individuals), D-026 (speed first), or any other decision: the new brief changed none of those subjects.

## 5. Proposed edits (apply only after approval)

Default assumption: **you have not changed D-001 yet.** Rows marked "if WIWI" apply only if you rule that way.

| Document | Item | Current | Proposed | Reason |
|---|---|---|---|---|
| docs/spec-versions/ | new folder | none | `spec-2026-11-07.md` = the old brief | keep history |
| docs/spec.md | whole file | old brief | the new brief (images kept) | the brief is the source document |
| decisions.md | new D-032 | none | "Brief v2 received 2026-11-07. Only change: name WHOISWHO to WIWI, domain line, title, image. D-001 …" with your ruling on X1 to X3 | record the update |
| questions.md | C1 | Answered | add note: "Brief v2 changed the name again; see C14" | answer no longer matches the brief |
| questions.md | C14, C15 | none | C14: brief v2 name WIWI vs D-001 (X1 to X3). C15: brief v2 image says WHOISWHO, text says WIWI (X4 and X5). Both Open. | log the conflicts |
| questions.md | Q40 to Q45 | none | see below | decisions needed |
| requirements.md | REQ-002, 130, 151 | "WHOISWHO" in the wording | "the brand name (theme config)" | one rename point; no renumbering |
| requirements.md | REQ-052, 190 | tagline and brand values | add brand name, long-form name, edition name and pronunciation as config values | covers this change without a new REQ |
| requirements.md | header | "Derived from docs/spec.md (…)" | add "Brief v2 2026-11-07: name only; see D-032" | traceability |
| architecture.md, .env.example | `APP_NAME`, `MAIL_FROM_NAME`, 2 mentions | WHOISWHO | change with the WordPress revision, from the brand config | avoid double edits |
| docs/acquisition-plan.md | crawler name | "WHOISWHO-bot/1.0" | per Q45 before the first fetch | crawler identity |
| prototype | 12 hard-coded names; About and footer | literal name | all from `W.brand`; add long-form name and pronunciation to About and footer; rerun smoke test | REQ-190 |
| wireframe docs | 1 mention | WHOISWHO | regenerate | consistency |
| traceability.md | does not exist yet | none | when created, record "spec v2, 2026-11-07" in its header | traceability |
| STATUS.md | stage and decisions | | add D-032 and the open items | session state |
| if WIWI | requirements, GUIDE, CLAUDE.md, architecture, ADR, acquisition plan, prototype, .env.example | WHOISWHO | WIWI via the brand config; textual find and replace elsewhere; edition name per Q41 | follows the ruling |

### Questions for you

Same format as questions.md. Status is Open until you answer. Q40 should be answered before the crawler pilot (10 Nov) and before Terms and Privacy are published (21 Nov).

| Q | Status | Question | Why it matters | Decision / default | Ref |
|---|---|---|---|---|---|
| Q40 | Open | Is the brand name WHOISWHO (D-001) or WIWI (brief v2)? | Names, copy, crawler identity, legal notices, assets | **Keep WHOISWHO until you rule.** Build with one brand-name config value so a rename costs under an hour. | X1, D-001 |
| Q41 | Open | If WIWI: is "WHOISWHO" retired, and what is the edition called? | Edition name, sub-product names | If WIWI: retire WHOISWHO; use the brief's names ("WIWI in Africa: The Maiden Edition", "WIWI Profiles" and so on); keep "Who Is Who In Africa" as the long form. | X2, X3 |
| Q42 | Open | Which is current in the brief: the text (WIWI) or the master-brand image (WHOISWHO)? | The brief disagrees with itself | Treat the image as stale; ask the brief's author or Brand. | X4 |
| Q43 | Open | Which name does Legal use in Terms, Privacy and commercial terms, and has it been cleared as a name? | Terms and Privacy due 21 Nov (REQ-100) | Legal confirms the trading name before publication; clearance of any new name is Legal's. | N9 |
| Q44 | Open | Show the pronunciation "We We Africa" on About and in brand-voice guidance? | Name clarity | Yes, one line on About and the footer, once Q40 is settled. | X5 |
| Q45 | Open | What is the crawler called and what does it announce: WHOISWHO-bot or WIWI-bot? | Sources check the user agent; it must be fixed before the first fetch | Use the Q40 result; until then keep WHOISWHO-bot. The info page lives on wiwi.africa either way. | acquisition-plan 2.1 |

## 6. After your approval

1. Archive the old brief as `docs/spec-versions/spec-2026-11-07.md`; replace `docs/spec.md` with the new brief.
2. Apply the approved rows above (REQs are never renumbered; any removal is marked Withdrawn).
3. Record D-032 for the spec update with your rulings; update STATUS.md; create or update traceability.md with the spec version.
