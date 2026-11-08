# Change review: no login or signup on the public site (D-034, 2026-11-08)

Status: **Decision recorded (D-034). Requirements, questions, the acquisition plan and GUIDE are changed. Architecture, ADRs and CLAUDE.md are listed in section 4 and not yet changed.** Rulings in section 5 are open (Q56–Q62).

## 1. What was decided

No login or signup for anyone on the public website. Only staff use wp-admin, with two-factor. Judges do not access the site for now.

| Area | Before | Now (D-034) |
|---|---|---|
| Claiming a profile | Owner creates a login, submits a claim (D-022, D-030) | "Request to claim" form with evidence, no account. Staff verify and set Claimed (REQ-230, 231) |
| Editing a profile | Owner edits in an owner area (REQ-015, 128) | Correction request. Staff apply it in wp-admin through the usual review (REQ-232) |
| Correct, remove, report | Forms | Forms (unchanged) |
| Nominator and nominee | Email link | Single-use signed email links, no account (REQ-233) |
| Customer intake | Invitation link, drafts | Single-use signed link; staff may enter it instead (REQ-013, 085) |
| Judging | Judge accounts and scoring portal (D-019, REQ-035, 201) | Offline. Awards manager enters scores and results in wp-admin (REQ-234) |
| Registration | Anyone could register (REQ-107) | Closed (REQ-229) |
| Staff roles | Admin, Editor, Reviewer, Sales/Support, Finance, Judge | Administrator, Editor-in-Chief, Editor, Reviewer, Sales/Support, Finance, Awards manager (REQ-235). Judge is not a role |
| Staff security | MFA (REQ-047) | Two-factor enforced before first use, recovery codes, lockout |

## 2. Requirements affected (all listed)

| REQ | Change | What |
|---|---|---|
| 019 | **Withdrawn** | Owner sees status and view count. Replaced by REQ-236 (on request) |
| 035 | **Withdrawn** | Judge accounts. Replaced by REQ-234 |
| 107 | **Withdrawn** | Register with email verification. Replaced by REQ-229 |
| 110 | **Withdrawn** | User deactivates account. Staff leavers: REQ-235 |
| 128 | **Withdrawn** | Owner previews draft. Staff preview in wp-admin (REQ-084) |
| 201 | **Withdrawn** | Judge accounts see assigned nominations only. Replaced by REQ-234 and REQ-037 |
| 111 | Already withdrawn (D-030) | Organisation accounts. D-030 itself is now superseded |
| 013 | Amended | Intake by signed link, not an account |
| 015 | Amended | Owner edits become correction requests; second approver for sensitive fields (Q57) |
| 018 | Amended | Owners ask staff to switch enquiries off; routing (Q62) |
| 036 | Amended | Conflicts recorded per judge, entered by the Awards manager |
| 037 | Amended | Score-entry and results screens show no commercial data |
| 047 | Amended | Two-factor enforced for all human staff |
| 060 | Amended | Owners choose public contact details by asking staff |
| 083 | Amended | Seven staff roles; no Judge |
| 108 | Amended | Staff login only |
| 109 | Amended | Visitor and staff roles only |
| 122 | Amended | Rate limits per IP and per email or link token |
| 152 | Amended | Claimed set only by staff after a request-to-claim |
| 160 | Amended | Yearly prompts are emails with a link to the correction form |
| 173 | Amended | Owners ask for the sources used; staff send them |
| 175 | Amended | Consent via signed-link page |
| 196 | Amended | Nominator verified by signed link |
| 197 | Amended | Nominee link is single-use and signed; no account |
| 200 | Amended | Judges are names on a panel, not users |
| 229–236 | **New** | No public accounts; request to claim; claim verification; correction request; signed links; offline judging; staff roles; information on request |

**Checked and unchanged:** REQ-106 and 195 (an email sign-up for "nominations open soon" is a mailing list, not an account; confirm), REQ-014, 084, 089, 094, 124, 125, 156–158, 174, 226. REQ-094 already lists the claim and nominee emails; its notification list needs "single-use link" wording at the next requirements edit.

## 3. Decisions affected

| Decision | Effect |
|---|---|
| D-019 | Amended: no judge accounts or scoring portal; the rest (configurable rules, flags, judge wall from commercial data) stands |
| D-022 | Amended: claim verification by form and staff. Evidence rules (domain email or document) stand |
| D-030 | Superseded: no owner logins |
| D-015, D-033 | Not changed. Staff-applied corrections go through review; claims and corrections still rise to the top of the queue (REQ-226) |
| D-005, D-028, D-031, D-013 | No conflict |

## 4. Documents affected

| Document | Status | What is needed | Effort |
|---|---|---|---|
| `docs/requirements.md` | Changed | Done | — |
| `docs/decisions.md` | Changed | D-034 added; D-019, D-022 amended; D-030 superseded | — |
| `docs/questions.md` | Changed | Q56–Q62 added (Open); summary counts updated | — |
| `docs/acquisition-plan.md` | Changed | Header note; step 14; requester check (6.3); T-23 to T-28; workload note | — |
| `docs/GUIDE.md` | Changed | Files table and rules | — |
| `docs/design-inspiration.md` | New | Part A of the instruction | — |
| `docs/wireframe-inventory.md`, `prototype/` | See section 7 | Login, signup, owner and judge screens removed; new screens added | — |
| `docs/architecture.md` | **Not changed** | Has an owner area, `/register`, `/login`, a judge Filament panel, a `judge` role and owner login policy (sections around lines 10, 20–22, 42, 63, 124, 134–135, 181–184, 214–216, 235). It is being rewritten for WordPress at Gate 2 (D-031), so these should simply not be carried over | S, inside Gate 2 |
| `docs/adr/ADR-004` (admin and judge console) and any auth ADR | **Not changed** | Superseded by D-031 and D-034; to be rewritten with Gate 2 | S |
| `CLAUDE.md` | **Not changed** | The stack line still says Laravel (D-031). Add "no public accounts" under Rules when Gate 2 rewrites the Commands section | XS |
| `docs/spec.md` | Not changed | The brief is the original intent. Page 12.2 lists a "Subscription plans" page and the admin roles list in the brief includes Judge; both already differ from decisions (D-028, D-034) | — |
| `docs/wireframe-findings.md` | See section 7 | Rows on owners, judges and accounts (6, 11, 12, 13, 16, 17, 23) are superseded | XS |
| `docs/plan.md`, `docs/traceability.md` | Do not exist yet | Plan must list the six new tooling items and the Awards manager score screen | — |

## 5. Needs your decision (Q56–Q62, all Open, defaults proposed)

| Q | Question | Recommended default |
|---|---|---|
| Q56 | Confirm owners only ever use forms and email; staff do all edits and lookups | Yes; revisit after the first 100 claims |
| Q57 | Who approves a correction to name, title, organisation, awards or achievements | A second staff member |
| Q58 | Life of single-use links | 14 days; nominator and claim verification 48 hours |
| Q59 | How staff check a claimant without an account | Domain email or document for organisations; document plus call-back or official email for individuals |
| Q60 | How judges score without a portal, and how scores are kept consistent | Sheets or a meeting; the Awards manager enters, a second person checks |
| Q61 | Role holders at launch; Editor-in-Chief separate from Administrator | Seven roles; Administrator limited to two named people; names needed |
| Q62 | Where enquiries to a claimed profile go; who sends view counts and sources | Forward to a contact address staff hold; staff send on request |

## 6. Conflicts and consequences to rule on

These are listed, not resolved.

1. **Subscriber value.** The brief sells profiles to subscribers (the 10 paid subscriptions). With no owner area, a subscriber cannot see a dashboard, view count or enquiries themselves. REQ-236 gives them this on request. If subscribers expect self-service, this is a sales problem, not a build one. Rule whether that is acceptable.
2. **Staff workload.** Every claim, correction and enquiry setting lands on staff. The acquisition plan's review estimates (4.1) do not include this; I added 5 to 10 minutes per claim or correction as a note, not as a recalculation. The 1,000 profiles may produce a few hundred claims. Capacity (Q4 reviewer names) matters more now.
3. **Customer intake (REQ-013).** A long form with drafts and image uploads works through a single-use link, but an expired link loses the draft unless staff reissue it (Q58). The fallback is staff entering it (REQ-085).
4. **Judging confidentiality and audit.** Offline scoring means paper or spreadsheet scores outside the platform until staff enter them. The entry is audited but the original is not. Q60.
5. **wp-admin is still a public URL.** WordPress has no way to hide the login completely; hiding the path reduces noise only. Two-factor, lockout, rate limiting and IP rules (Cloudflare) carry the security (REQ-047, 235). This is the one remaining login surface.
6. **Mailing list.** REQ-106 and 195 keep an email sign-up for "nominations open soon". It is a list, not an account. Confirm.
7. **D-033 (publish first).** A claim or correction moves a pending profile to the top of the review queue (REQ-226). Nothing changes, but claim requests now arrive as forms, so spam and abuse rates (a rival requesting removal) matter more; REQ-226 already rate-limits.
8. **Brief text.** The brief mentions judge portals only in passing (spec lines near 753 and 1219 talk about identifying judges). No contradiction with the brief's intent, but its admin roles list includes a Judge role that D-034 removes.

## 7. Wireframe

Done in `prototype/` (look unchanged). Smoke test: 4,716 checks, 0 failures (Node with a stubbed DOM, not a real browser).

- **Removed:** public login, signup, verify, forgot and reset (O01–O04); the whole owner area `/account/*`; the judge portal `/judge/*` (J01–J07); the owner and judge roles.
- **Added or changed:** request to claim with evidence and an emailed confirmation link (V42, V51); correction request (V39); staff claim verification (A08, A09); corrections handling with a second approver (A11, A49); score entry (A51–A53, `/admin/scores`); judging panel and conflicts (A31); roles and permissions matrix (A54); staff handling of owner requests (A55, A56); single-use signed-link pages for nominator, nominee, customer intake and consent.
- **Staff sign-in** (`/staff/signin`) stays as the wp-admin preview.
- **Flows:** still 19; flows 4, 5, 6, 8, 12, 13, 15 and 19 rewritten.
- **Inventory:** now 130 entries (101 SPECIFIED, 29 IMPLIED). `docs/wireframe-findings.md` has new findings 26–37 and updated rows.
- The localStorage key is now `wiw-wireframe-v4`, so old saved state is ignored. Hard-coded brand strings in the prototype now go through `W.brand`.
- **Still not in the wireframe:** the D-033 publish-first states (REQ-210–228), and AI Publisher and AI Monitor roles in the matrix.
- **Unclear points it found:** Sales/Support can open claim verification (maybe too wide); no screen to revoke or reissue a link; no path to reopen a rejected customer profile; no minimum judge count or tie-break; a conflict recorded after scoring deletes that judge's score.
