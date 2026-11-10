# Awards

The awards recognise people and organisations, chosen by independent judges against criteria that are published before nominations open. The public site shows the rules, takes nominations when they are open, then publishes the nominees and the winners.

Routes: `/awards`, `/awards/category/:i`, `/awards/nominees`, `/awards/nominee/:ref`, `/awards/winners`, `/awards/rules`, `/nominate`, `/nominate/verify`, `/nominate/done` (V18 to V22, V33, V55, N01 to N05). Requirements: REQ-030 to REQ-038, REQ-064, REQ-106, REQ-194 to REQ-199, REQ-246, REQ-250, REQ-259 (requirements). Decisions: D-009, D-019, D-021, D-037, D-055, D-059 (decisions).

## Two ideas to hold on to

**The public says "nominees" and "winners".** A nomination has a stage (Nominated or Winner) and a status (Live or Not live). A nominee page appears only when staff set the nomination Live (REQ-198, D-055, D-066).

**One switch decides whether nominations are open.** The Nominations flag is the only feature flag. It defaults to off, only the Administrator can change it, from a toggle on the Nominations screen, and each change is audited (REQ-194, D-065). What happens after nominations close (screening and judging) is run outside the site, and only what staff set Live appears (REQ-259, D-059).

## Awards overview (`/awards`)

The overview shows the edition ("WHOISWHO in Africa: The Maiden Edition"), where it stands, the categories and how judging works.

| Block | Content |
|---|---|
| Hero | A statement, a button that follows the stage, and "Read the rules". Counts of groups, categories and, once published, nominees. |
| Where we are | A five-step public timeline: Nominations open, Nominees announced, Judging, Winners announced, Awards presentation. The current step is marked "Now". |
| Categories | Five groups in their brand colours, each listing its categories. Each category links to its page, with a nominee count once nominees are public. The list is marked as a proposal until confirmed before nominations open. |
| How judging works | The eligibility text and the judging criteria, as plain text page copy edited in the page editor. Judging and scoring happen offline. |
| Supported by | Awards sponsors, if any, with a note that sponsors do not choose, score or influence nominees or winners. |

## Stages

The prototype derives a public stage from the switch and from what staff have published:

| Stage | How it is reached | Main button |
|---|---|---|
| Winners announced | At least one Live winner | See the winners |
| Nominees announced | At least one Live nominee | See the nominees |
| Nominations open | Switch on | Nominate someone |
| Not open, nothing published | Switch off | "Get notified" |

**Known mismatch.** REQ-106 and REQ-195 still describe a "Nominations open soon" page with an email sign-up while the switch is off. REQ-259 (D-059) replaced that: when the switch is off the Nominate page says "Nominations are closed" and has no sign-up, and the prototype's Nominate page follows it. The awards overview's stage label ("Nominations open soon") and its "Get notified" button, and the same wording on the home page, still use the older text and point at the closed page. The requirement text and those labels need aligning.

## Rules page (`/awards/rules`)

Eligibility, nominations, judging and "No public voting". Outcomes are decided by judges, not by votes or payments. Judging happens offline and payment never affects nominations or winners. The page carries a draft note: governance and the conflict-of-interest policy come from the Venture CEO. The criteria text matches the overview.

## Categories and nominees

A **category page** (`/awards/category/:i`) shows the group, the winner if winners are published, the nominees, how it is judged and the other categories in the group. If no nominees are public it says "No nominees yet. Nominee pages appear only once nominees are announced, after screening and the nominee's own consent."

The **nominees page** (`/awards/nominees`) lists every public nominee grouped by award group, with tabs to filter by group and counts of people, organisations and categories. Before nominees are published it says "Nominees are not published yet. Nobody is listed without their consent."

A **nominee page** (`/awards/nominee/:ref`) shows the nominee's portrait, category, citation, a short "On the record" extract from the reviewed profile with a link to the full profile, and the other nominees in the category. The nominator is never named. A nominee who is not public returns 404 to visitors, and staff see a preview banner.

## Winners (`/awards/winners`)

The winners page lists the winners of every edition, newest first (REQ-246). A short burst of brand-colour confetti plays once when the page opens, and is skipped for visitors who ask for reduced motion.

| Control | Options |
|---|---|
| Search | Name, category or citation |
| Year, Group, Category, Country | Select lists; year shows a count |
| Winner is | A person or an organisation |
| Sort | Newest first, or Name A to Z |

Filters are in the address so a filtered view can be shared. The filters are one quiet bar with the search field, inline lists, the sort, the number of winners and a Clear link while a filter is on. Twelve winners show per page, grouped by year. A winner who has a public profile links to it. Winners are published through the normal publish step; there is no flag for announcements (REQ-038, D-037). The years and winners in the prototype are sample data, and the years shown (2026 to 2028) are unconfirmed.

## Nominating (`/nominate`)

**When it is closed**, the page says "Nominations are closed", with links to the winners or nominees if published, the rules and the overview. When it is open, the form appears. Either way the same guidance sits below (REQ-250): how it works, who can be nominated, the criteria, the categories, what to have ready and short answers (cost, anonymity, self-nomination, several nominations, a nominee who says no). The guidance adds no data collection. While closed, the Nominate links are removed from the menus and the nav label reads "Nominations closed".

**The form has six steps** (N01):

1. **Your details.** Name, email (verified, never public), relationship to the nominee, privacy tick.
2. **Verify email.** A single-use link is sent. No account is created (N02, N12, REQ-233).
3. **Nominee.** Name, whether a person, business or institution, and an optional nominee email. If given, it is stored as the nomination's staff-only Contact email, never shown publicly. A duplicate-nomination warning appears if the name is already nominated or suppressed; the visitor can continue and staff merge duplicates without revealing who nominated whom (N03).
4. **Category and evidence.** A category, a short evidence statement and an optional link. Evidence containing contact details or sensitive words (phone numbers, emails, ages, health, religion, politics, allegations) is blocked with a message (N11).
5. **Conflict declaration.** No conflict, or a description of the relationship (N10).
6. **Review and submit.** A summary, a bot challenge and submit. The visitor gets a reference and "Nomination submitted" (N04).

A visitor who sends too many nominations from one device sees "Too many nominations from this device" (N05, REQ-122). There is no public voting (REQ-196).

## What happens to a nomination

A nomination is private until staff set it Live. The site does not email the nominee or ask them to accept; there is no nominee response page (D-066). Staff add a nomination's profile, check it, and switch it Live. A nominee who objects uses "Request removal" on their profile, which hides the nomination and adds the name to the permanent suppression list (REQ-147, REQ-207).

## Which staff screens feed it

| Public thing | Staff screen |
|---|---|
| Nominations open or closed | The toggle on Nominations (A28) |
| Nominations, their stage (Nominated or Winner) and status (Live or Not live) | Nominations (A28, A29) |
| Categories | Award categories (A30) |
| Eligibility and judging-criteria text | Page copy, edited in the page editor (REQ-260) |

## Prototype notes

Everything is sample data: the nominees, citations and winners are fictional and the portraits are stock placeholders. The Design review page has a switch that moves the sample data between the stages so each state can be seen.
