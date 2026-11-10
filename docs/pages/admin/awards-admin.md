# Awards admin

How staff run the awards from the admin: the Award categories screen, and one Nominations screen where staff open or close nominations, add profiles, set each nomination's stage (Nominated or Winner) and status (Live or Not live), and remove nominations. Judging and scoring happen offline; the site records only the outcome.

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Add, rename, reorder and remove categories | Award categories | `wiw_manage_awards` | Editor, Administrator |
| Open or close nominations | Nominations (the "Nominations page" toggle) | `wiw_toggle_nominations` (fixed) | Administrator; Editors see the state read-only |
| Add a profile, set stage and status, remove, send nomination and winner emails | Nominations, Nomination detail | `wiw_manage_awards` | Editor, Administrator |

There is no separate Feature flag screen and no separate Shortlist and winners page. Both were folded into Nominations (REQ-267, D-065).

## Award categories

This screen holds categories and nothing else (REQ-266).

- Categories are grouped under the five award groups: Leadership and Public Impact, Entrepreneurship and Business, Technology and Innovation, Professional Excellence, and Industry and Institutional Recognition.
- Staff add a category, rename it, change its order and remove it.
- **A category that has nominations cannot be removed.** Rename it instead, or move its nominations first.
- Every change is written to the audit log.
- The public Awards pages, the Nominate guidance and the home marquee read the categories from here, so changing a category needs no page edit.
- The eligibility text and the judging-criteria text are not settings. They are fixed page copy, edited in the page editor like any other page (REQ-260, REQ-200).

## The Nominations screen

One screen runs the awards.

### Nominations page: Open / Closed

There is one feature flag in the whole system: Nominations (REQ-194, D-037).

- It is a toggle at the top of the Nominations screen, and it defaults to closed.
- An Administrator turns it on or off. Every change records who, when and an optional reason in the audit log. No approval reference is needed.
- **Open:** the Nominate page and the menus show the nomination form.
- **Closed:** they show "Nominations are closed", with links to the nominees or winners once those are published. There is no "tell me when nominations open" sign-up (REQ-259, D-059).
- An Editor sees the current state but cannot change it.

### Add profile to nominations

Staff can add a nomination themselves (REQ-267).

1. Choose **Add profile to nominations**.
2. Pick an existing profile and a category. Add an optional note.
3. The nomination is created and linked to that profile.

There are no free-text names. An optional **Contact email** can be entered: it is shown only to staff, never public, and never appears in exports or logs. Retention treats it as personal data (PII catalogue, `whoiswho-core/config/pii.php`). If the person or organisation has no profile yet, create the profile first. A pending-review profile can be added but cannot be set Live until it is reviewed (REQ-226).

### Stage and status

Every nomination has two settings, and nothing else.

| Setting | Values | Meaning |
|---|---|---|
| Stage | Nominated, Winner | Nominated is the default. Staff switch to Winner once the offline judging has decided. |
| Status | Live, Not live | Not live is the default. Live puts it on the public site. |

- A Live nominee shows on the Nominees page. A Live winner shows on the Winners page. A Not live nomination shows nowhere. There is no separate announcement step: Live plus Winner is what shows.
- Each change records the staff member who made it and is written to the audit log (REQ-202). Nothing depends on a score; judging happens offline.
- **Remove** deletes a nomination after a confirmation, and is audited. It replaces the old Ineligible and Declined stages.
- **Send nomination email** is offered on a nomination that has a Contact email. The email carries no link and asks nothing of the nominee. Each send records who sent it and when, and the audit entry holds no address (REQ-268).
- On a winner row, **Send winner email** goes to the same Contact email and is recorded the same way.

### Where nominations come from

1. **The public form.** Anyone may nominate, with no account. The nominator's email is verified by a single-use link (REQ-196, REQ-233). Submissions are rate-limited and checked for duplicates. There is no public voting. The nomination lands Nominated and Not live.
2. **Staff.** Add profile to nominations, above, creates the same kind of nomination.

The site does not email the nominee or ask for their consent. A nominee who objects uses Request removal, which hides the nomination and adds them to the suppression list (REQ-147, REQ-207).

### Making a nomination public

1. Check the profile behind it is reviewed. A pending-review profile cannot be set Live (REQ-226).
2. Switch the status to Live. The nominee page is built from the reviewed profile and its sourced facts (REQ-198).
3. When the offline judging has decided, switch the stage to Winner. The Winners page then lists it.

Nothing is visible to visitors until you set it Live. Publicly there are only nominees and winners (D-055). The winners page plays a short confetti animation when it loads.

## Safeguards

- Payment never affects nominations or winners. There is no data path from commercial records into nominations, stages or winners (REQ-183, REQ-199).
- Each stage or status change records who made it, and every action is written to the audit log.
- Exports of nominations need `wiw_export_data`, which stays with the Administrator (REQ-031).

## WordPress native or custom

| Part | Status |
|---|---|
| Nominations, stage and status, the toggle | Custom plugin |
| Award categories | Custom plugin screen |
| Awards pages | Theme and content |

## Acceptance criteria

- [ ] An Editor or Administrator can add, rename and reorder a category, and the change shows on the Awards page, the Nominate guidance and the home marquee without a page edit.
- [ ] Removing a category that has nominations is refused with a message; removing an empty category works. Both are audited.
- [ ] The Nominations screen shows a "Nominations page: Open / Closed" toggle. An Administrator can change it and the change is audited with who, when and an optional reason. An Editor sees the state but the control is read-only.
- [ ] There is no Feature flag screen and no Shortlist and winners page in the menu.
- [ ] "Add profile to nominations" offers only existing profiles; there is no free-text name field. The new nomination is linked to the chosen profile and category.
- [ ] A staff member can set a nomination's stage to Nominated or Winner and its status to Live or Not live without any score, and each change shows the approver in the audit log.
- [ ] A new nomination starts Nominated and Not live.
- [ ] "Add profile to nominations" has an optional Contact email that is staff-only: never on a public page, in an export or in a log.
- [ ] "Send nomination email" appears only when a Contact email exists, records who sent it and when, and the audit entry has no address. The winner email uses the same address. A nomination cannot be set Live for a pending-review profile.
- [ ] A Live nominee appears on the Nominees page, a Live winner on the Winners page, and a Not live nomination appears nowhere.
- [ ] Removing a nomination asks for confirmation and is audited. There is no Ineligible, Declined, Shortlisted or Finalist stage.
- [ ] No Awards screen or export shows payment, subscription, sponsorship or customer data.
