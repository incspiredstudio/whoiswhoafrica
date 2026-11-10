# Awards and nominations

The awards recognise people and organisations, chosen by independent judges, offline, against criteria published before nominations open. The site takes nominations when they are open, then publishes the nominees and the winners.

## What it does

- Publishes the awards overview, rules, categories, nominees and winners.
- Takes nominations through a six-step form with email verification, only while the Nominations page is open.
- Lets staff keep an optional, staff-only Contact email on a nomination and send a nomination email or a winner email to it from the Nominations screen (no link in the email).
- Gives every nomination a stage (Nominated or Winner) and a status (Live or Not live). Nothing is public until staff set it Live.
- Lets staff add a profile to nominations, switch each one between Nominated and Winner, switch it Live or Not live, and remove a nomination, all from the Nominations screen. Judging and scoring happen offline.
- Plays a short confetti animation on the winners page.

## Who uses it and how

- As a visitor, I can read the rules and see the categories, nominees and winners.
- As a nominator, I can nominate someone for free with no account, once my email is verified.
- As an Administrator, I can open or close nominations from a toggle on the Nominations screen.
- As an Editor or Administrator, I can add, rename, reorder and remove award categories.
- As an Editor or Administrator, I can add a profile to nominations, set it Nominated or Winner, and switch it Live or Not live.
- As an Editor, I can remove a nomination, and prepare the winner email for a winner.

## The pieces

### Theme

- Awards overview, category page, nominees page, nominee page, winners page (with filters in the URL), rules page.
- Nominate page: closed state, open state with the six-step form, and the guidance section shown either way (how it works, who is eligible, criteria, categories, what to have ready, short answers).
- The expired-or-used page for the nominator's verification link.
- Home page awards marquee (category names) and nominees block.
- Confetti on the winners page, skipped for reduced-motion visitors.

### Plugin

- `Awards` module: editions, categories (edited on the Award categories screen), nominations with a stage (Nominated or Winner) and a status (Live or Not live).
- Nominations flag (the only feature flag), a toggle on the Nominations screen, audited with who, when and an optional reason.
- Signed-link service for nominator verification.
- Duplicate-nomination warning, rate limits, free-text scan for contact and sensitive data.
- Live and Winner together are what shows a winner. There is no separate announcement step and no flag for it.
- Hard boundary: no code path from commercial records into awards.

### Pages and screens

- Public: `/awards`, `/awards/category/:i`, `/awards/nominees`, `/awards/nominee/:ref`, `/awards/winners`, `/awards/rules`, `/nominate`, `/nominate/verify`, `/nominate/done`.
- Staff: Nominations (with the open or closed toggle, add profile, stage and status controls, remove), Nomination detail, Award categories. There is no separate Feature flag screen or Shortlist and winners page (D-065).

### Third-party plugins

None. Bot challenge on the form.

### Data

- Awards tables (editions, categories, nominations with stage and status). Not live nominations are private. Never joined to commercial tables.
- Winners and nominees pages read from the reviewed profile and its sourced facts.

## Business rules

- One switch controls whether nominations are open. On: the Nominate page and menus show the form. Off: they show "Nominations are closed" with links to the nominees or winners once published, and no sign-up form (REQ-194, 259). Later stages run outside the site for now; only published results appear.
- Only an Administrator can turn the switch on or off. Each change is audited with who, when and an optional reason; no approval reference is needed (REQ-194).
- Anyone may nominate. The nominator's email is verified by a single-use link. There is no public voting (REQ-196).
- Nominator details are never public. A nomination is Not live, and so private, until staff set it Live (REQ-064, 198).
- The public says "nominees" and "winners". Live nominees appear on the Nominees page and Live winners on the Winners page (REQ-198, 33).
- The site does not email nominees or ask them to accept. A nominee who objects uses "Request removal" like anyone else; a valid removal hides the nomination and adds the name to the suppression list (REQ-147, 207).
- Judging and scoring happen offline, outside the site (D-064). The site stores no judge, conflict or score data.
- Every nomination starts Nominated and Not live. Staff switch the stage between Nominated and Winner, and the status between Live and Not live. Each change stores the staff member who made it in the audit log (REQ-202). Nothing depends on a score.
- Staff add a profile to nominations by picking an existing profile and a category. There is no free-text name (REQ-267).
- A category that has nominations cannot be removed (REQ-266). Eligibility and judging-criteria text are page copy edited in the page editor (REQ-260).
- No Awards screen or export shows payment, subscription, sponsorship or customer data (REQ-183, 199).
- A pending-review profile cannot appear on a nominee page (REQ-226).
- The winners page lists every edition, newest first, with filters by year, group, category, type and country, and search by name, category or citation (REQ-246).

## Acceptance criteria

- [ ] With the switch off, `/nominate` shows "Nominations are closed", no form and no sign-up; the guidance section is still shown.
- [ ] With the switch on, `/nominate` shows the six-step form: details, verify email, nominee, category and evidence, conflict declaration, review and submit.
- [ ] An Administrator can toggle Nominations page Open or Closed from the Nominations screen and the change is audited with who, when and an optional reason; an Editor sees the state read-only and a non-Administrator cannot change it.
- [ ] There is no Feature flag screen and no Shortlist and winners page; both functions are on the Nominations screen.
- [ ] "Add profile to nominations" offers only existing profiles and creates a nomination linked to the chosen profile and category.
- [ ] A category with nominations cannot be removed; one without can, and both are audited.
- [ ] A nomination cannot be submitted until the nominator's email link is opened; the link works once.
- [ ] A nomination naming an already nominated or suppressed person shows a duplicate warning but can continue.
- [ ] Evidence containing a phone number, email address or sensitive word is blocked with a message.
- [ ] Too many nominations from one device show "Too many nominations from this device".
- [ ] A nomination and its nominator are not visible on any public page while the nomination is Not live.
- [ ] A new nomination, from the form or added by staff, starts Nominated and Not live.
- [ ] "Add profile to nominations" has an optional Contact email. It is shown only to staff, and never on a public page, in an export or in a log.
- [ ] "Send nomination email" is offered only when a Contact email exists. Sending records who sent it and when, and the audit entry holds no email address.
- [ ] The winner email goes to the same Contact email and is recorded the same way.
- [ ] The Contact email is in the PII catalogue and is removed by retention and by an erasure request.
- [ ] A nominee page returns 404 to visitors while the nomination is Not live, and never names the nominator.
- [ ] Switching a nomination's stage (Nominated or Winner) or status (Live or Not live), or removing it, is recorded in the audit log with the staff member who did it, and needs no score. Removing asks for confirmation.
- [ ] A Live nominee shows on the Nominees page, and a Live winner on the Winners page; a Not live nomination shows nowhere. The site has no screen, table or export for judges, conflicts or scores.
- [ ] No Awards screen or export shows any commercial field (automated test).
- [ ] Creating or ending an arrangement does not change any nomination status.
- [ ] The winners page filters by year, group, category, type and country, searches by name, category or citation, and keeps filters in the URL.
- [ ] Confetti plays once on load and does not play when reduced motion is requested.
- [ ] The home page shows the award categories in the marquee and has no awards button; the Awards and Nominate pages follow the stage.

## Depends on

- Directory and profiles (nominee pages come from reviewed profiles).
- Corrections, removals and reports (suppression list); Email and notifications.
- Roles, audit and admin; Privacy and data rights.
- Home and curation.

## Out of scope or later

- Public voting (not offered).
- Judge accounts, a judging portal, score entry or conflict records (judging is offline, D-064).
- Events, tickets and table sales (manual for now).
- Automating screening or judging inside the site; emailing nominees or collecting their consent through the site.

## Requirement ids covered

REQ-030, 031, 032, 033, 034, 038, 064, 194, 196, 198, 199, 200, 202, 233, 246, 250, 259, 266, 267, 268 (REQ-036, 037 and 234 are withdrawn, D-064; REQ-197 is withdrawn, D-066) (259 replaces the "Nominations open soon" sign-up of REQ-106 and 195). The Nominations flag stays off until an Administrator turns it on.
