# Wireframe findings: unclear requirements and decisions

Found while designing screens (2026-11-07). Each row says what the wireframe assumed so it could be built. Please confirm or correct, then the documents can be fixed. Nothing here is decided.

| # | Where | What was unclear | Wireframe assumed | Screens |
|---|---|---|---|---|
| 1 | REQ-022, D-008 | Does an arrangement that has ended (for example a one-month sponsored post) still count as a "paid subscription" once it ends? | Yes, if it was Confirmed and not complimentary. | A15, A02 |
| 2 | REQ-022, D-028 | Do all arrangement types count toward the 10 (sponsored post, awards sponsorship), or only profile-type ones? Already flagged in STATUS. | All types count. | A02 |
| 3 | REQ-180 | Which revenue stream each arrangement type maps to. | Editable default: sponsored profile = subscription; sponsored post and media package = media/editorial; awards sponsorship = sponsorship; other = subscription. | A47 |
| 4 | REQ-022, REQ-083 | Who is the "named confirmed-by admin": the Finance role, or Admin only? | Finance or Admin can be named. | A16 |
| 5 | REQ-202 | Which roles are the named approvers for screening, shortlist and final approval. | Screening: Editor-in-Chief or Admin; shortlist: Editor-in-Chief or Admin; final approval: Admin standing in for the Venture CEO. | A29 |
| 6 | REQ-015 vs D-015 | Edits to name, title, organisation and achievements re-enter review. Do all other edits go live without review, even though D-015 says 100% human review before anything publishes? | Other edits go live at once. | O06, O11 |
| 7 | REQ-208 | What a visitor sees at the URL of an unreviewed profile. | 404 (reveals nothing). Staff and the owner see a banner. | V13, V15 |
| 8 | REQ-198, D-021 | Which feature flag controls the public shortlist and nominee pages. | The judging flag. | V19, V20 |
| 9 | D-021 | What happens if a nominee never responds to the invitation. No time limit or reminder rule. | Stays at "Nominee invited"; no timeout designed. | A29 |
| 10 | spec 7.4, REQ-030, REQ-031 | The brief says "public nominations and curated nominations" but the requirements only describe the public form. | Staff can add curated nominations in the queue. | A28 |
| 11 | REQ-037, REQ-201 | What judges may see: the nominator's identity? the nominee's public profile? | Evidence text only; nominator hidden; no commercial data. | J04 |
| 12 | REQ-107 vs D-030, REQ-013 | Can anyone self-register, or only invited and claiming owners? | Self sign-up exists, but profile access comes through an invitation or an approved claim. | O01, O23 |
| 13 | REQ-003, REQ-139 | A stub has only the REQ-139 fields. What may a claimed owner of a stub add (career history, education, media appearances)? | Claimed owners can add the full individual fields; stubs show only REQ-139 fields. | V09 |
| 14 | REQ-018 | Who receives enquiries for an unclaimed profile that has no owner. | They go to the staff inbox. | V43, A12 |
| 15 | REQ-158 | "A valid removal request from the subject": how is the requester's identity checked? | Not designed; staff judge validity by hand. | V40, A11 |
| 16 | REQ-047 | MFA is required for staff. For judges and profile owners? | Judges: yes (wireframe asks for a code). Owners: no. | J01, O03 |
| 17 | REQ-068 | How is "under 18" checked? | Self-declaration only. | O01, O06 |
| 18 | REQ-007 | "Industry", "sector" and "professional category" are listed as separate filters. | One industry list plus profession; sector and category not separated. | V03 |
| 19 | spec 12.2 vs D-028 | The brief lists a "Subscription plans" page; D-028 and REQ-010 say there are no plans. | No such page; Get Featured is an enquiry form with informational copy. | V24 |
| 20 | REQ-154, Q11 | Who may set Featured, and on what grounds (still marked Q11). | Anyone with profile access (reviewer, editor, Editor-in-Chief, admin) with a written reason; to be narrowed. | A06 |
| 21 | REQ-159, Q5 | Whether and how a subject is told that a stub exists. | A disabled control on the review screen, waiting for Legal. | A04 |
| 22 | REQ-063, D-023 | Retention for claim documents and enquiries is not in the accepted defaults. | Marked "sample / not decided" on the Compliance screen. | A37 |
| 23 | REQ-175, REQ-179 | The consent for a complimentary profile is a field on the arrangement and also a landing page for the customer. Which is the record of truth? | The landing page sets the arrangement's consent field. | O22, A16 |
| 24 | REQ-146 | Merge of two individuals "by a human": which role. | Reviewer. | A21 |
| 25 | REQ-206 | What "capacity" is for the backlog warning. | 2 reviewers at about 40 a week = 80. | A02 |
