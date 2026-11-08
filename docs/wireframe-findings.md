# Wireframe findings: unclear requirements and decisions

Found while designing screens (2026-11-07; rows marked D-034 were updated 2026-11-08 when public accounts were removed). Each row says what the wireframe assumed so it could be built. Please confirm or correct, then the documents can be fixed. Nothing here is decided.

| # | Where | What was unclear | Wireframe assumed | Screens |
|---|---|---|---|---|
| 1 | REQ-022, D-008 | Does an arrangement that has ended (for example a one-month sponsored post) still count as a "paid subscription" once it ends? | Yes, if it was Confirmed and not complimentary. | A15, A02 |
| 2 | REQ-022, D-028 | Do all arrangement types count toward the 10 (sponsored post, awards sponsorship), or only profile-type ones? Already flagged in STATUS. | All types count. | A02 |
| 3 | REQ-180 | Which revenue stream each arrangement type maps to. | Editable default: sponsored profile = subscription; sponsored post and media package = media/editorial; awards sponsorship = sponsorship; other = subscription. | A47 |
| 4 | REQ-022, REQ-083 | Who is the "named confirmed-by admin": the Finance role, or Admin only? | Finance or Admin can be named. | A16 |
| 5 | REQ-202 | Which roles are the named approvers for screening, shortlist and final approval. | Screening: Editor-in-Chief, Awards manager or Admin; shortlist: Editor-in-Chief or Admin; final approval: Admin standing in for the Venture CEO. The Awards manager enters scores and results but cannot shortlist. (Updated for D-034.) | A29 |
| 6 | REQ-015 vs D-015 | Edits to name, title, organisation and achievements re-enter review. Do all other edits go live without review, even though D-015 says 100% human review before anything publishes? | **Superseded by D-034.** Owners have no login. Every owner change is a correction request applied by staff. Changes to name, title, organisation and achievements go to the review queue and need a second person (default, Q57); bio changes are applied by staff directly with a history entry. | V39, A11, A49
| 7 | REQ-208 | What a visitor sees at the URL of an unreviewed profile. | 404 (reveals nothing). Staff and the owner see a banner. | V13, V15 |
| 8 | REQ-198, D-021 | Which feature flag controls the public shortlist and nominee pages. | The judging flag. It also gates score entry (A52). (Unchanged by D-034.) | V19, V20 |
| 9 | D-021 | What happens if a nominee never responds to the invitation. No time limit or reminder rule. | Stays at "Nominee invited"; no timeout designed. | A29 |
| 10 | spec 7.4, REQ-030, REQ-031 | The brief says "public nominations and curated nominations" but the requirements only describe the public form. | Staff can add curated nominations in the queue. | A28 |
| 11 | REQ-037, REQ-201 | What judges may see: the nominator's identity? the nominee's public profile? | **Superseded by D-034.** Judges see nothing; they have no access. The score-entry screen shows the evidence text only, nominator hidden, no commercial data (REQ-037). | A52
| 12 | REQ-107 vs D-030, REQ-013 | Can anyone self-register, or only invited and claiming owners? | **Superseded by D-034.** There is no self sign-up for anyone. Only staff have accounts. | A01, A34
| 13 | REQ-003, REQ-139 | A stub has only the REQ-139 fields. What may a claimed owner of a stub add (career history, education, media appearances)? | **Superseded by D-034.** There is no owner editor. A claimed owner can ask for changes to name, title, organisation, achievements and biography through the correction request; adding career history or education is not possible without staff doing it by hand. | V39
| 14 | REQ-018 | Who receives enquiries for an unclaimed profile that has no owner. | They go to the staff inbox. For claimed profiles staff forward them to the contact address held for the owner. (Updated for D-034.) | V43, A12 |
| 15 | REQ-158 | "A valid removal request from the subject": how is the requester's identity checked? | Not designed; staff judge validity by hand. | V40, A11 |
| 16 | REQ-047 | MFA is required for staff. For judges and profile owners? | **Superseded by D-034.** Two-factor is required for every staff role. Judges and owners have no account, so the question no longer applies. | A01, A54
| 17 | REQ-068 | How is "under 18" checked? | The 18+ checkbox stays on the customer intake form (O06) and is self-declaration only. The request-to-claim and correction forms do not ask. (Updated for D-034.) | O06
| 18 | REQ-007 | "Industry", "sector" and "professional category" are listed as separate filters. | One industry list plus profession; sector and category not separated. | V03 |
| 19 | spec 12.2 vs D-028 | The brief lists a "Subscription plans" page; D-028 and REQ-010 say there are no plans. | No such page; Get Featured is an enquiry form with informational copy. | V24 |
| 20 | REQ-154, Q11 | Who may set Featured, and on what grounds (still marked Q11). | Anyone with profile access (reviewer, editor, Editor-in-Chief, admin) with a written reason; to be narrowed. | A06 |
| 21 | REQ-159, Q5 | Whether and how a subject is told that a stub exists. | A disabled control on the review screen, waiting for Legal. | A04 |
| 22 | REQ-063, D-023 | Retention for claim documents and enquiries is not in the accepted defaults. | Marked "sample / not decided" on the Compliance screen. | A37 |
| 23 | REQ-175, REQ-179 | The consent for a complimentary profile is a field on the arrangement and also a landing page for the customer. Which is the record of truth? | The single-use consent page (O22) sets the arrangement's consent field. The link works once. (Updated for D-034.) | O22, A16 |
| 24 | REQ-146 | Merge of two individuals "by a human": which role. | Reviewer. | A21 |
| 25 | REQ-206 | What "capacity" is for the backlog warning. | 2 reviewers at about 40 a week = 80. | A02 |
| 26 | REQ-152, 230, 231 | How a domain-email claim is checked. The brief and D-022 say 'domain email' but not who compares the domain. | The claimant confirms the email by link; staff compare its domain with the profile's website by eye. No automatic check. | V42, V51, A09 |
| 27 | REQ-231, 174 | Which roles may verify claims. | Reviewer, Sales/Support and Editor-in-Chief can open the queue; when a rival request exists only the Editor-in-Chief or Administrator can approve. Sales/Support verifying claims may be too wide. | A08, A09 |
| 28 | REQ-232 | Is the requester's email verified for every correction request? | Only for owner requests (the person or an organisation representative). A visitor's plain correction needs no confirmation (REQ-157). | V39, V51 |
| 29 | REQ-233, 013 | How long a single-use link lives and who can reissue it. | 14 days by default (Q58). No screen for staff to revoke or reissue a link; the expired page tells people to contact us. | O24 |
| 30 | REQ-013 | What a customer can do after submitting through the intake link. | Nothing: the link is used up. Later changes go through the correction request. Re-opening a rejected profile is not designed (the rejection email points to a used link). | O06, O09 |
| 31 | REQ-234 | Who enters scores, and may anyone else read them. | Awards manager and Administrator only. The Editor-in-Chief cannot open the scores screens, but does the shortlist step on the nomination screen. | A51, A52 |
| 32 | REQ-200, 234 | Scoring scale and tie rules. | Whole numbers 0 to 10 per criterion, weights from Awards setup, average of the judges whose scores are in. No tie-break or minimum number of judges. | A52 |
| 33 | REQ-036, 234 | What happens to a score already entered when a conflict is recorded later. | The score is deleted and the action is logged. | A31, A53 |
| 34 | REQ-018, 060, 236 | How staff check a request to switch enquiries off or hide contact details really comes from the owner. | Only the confirmed email on the request; staff note who asked. Same open question as #15. | A55, A56 |
| 35 | REQ-219, 235 | The two non-human roles from D-033 (AI Publisher, AI Monitor). | Not in the roles matrix; the wireframe has not been updated for D-033. | A54 |
| 36 | REQ-229, 106 | Is the 'Nominations open soon' email sign-up an account? | No. It is a consent record and an unsubscribe link (V22, V45), nothing more. | V22, V45 |
| 37 | REQ-160 | Yearly prompt to claimed owners. | It is an email with a link to the correction request (V39); there is no 'confirm still correct' screen any more. | A39, V39 |
