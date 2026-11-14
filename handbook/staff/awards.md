<!-- verify-after-round-9 -->

# Award categories and nominations

Staff run the awards from two screens: Award categories and Nominations. Judging and scoring happen offline, and the site records only the outcome. Every nomination has a stage (Nominated or Winner) and a status (Live or Not live), and nothing else.

## Who does what

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Add, rename, reorder and remove categories | Awards > Award categories | `wiw_manage_awards` | Editor, Administrator |
| Open or close nominations | Awards > Nominations (the switch at the top) | `wiw_toggle_nominations` (fixed) | Administrator. Editors see the state read-only. |
| Add a profile, set stage and status, remove, send emails | Awards > Nominations | `wiw_manage_awards` | Editor, Administrator |
| Export nominations | List export | `wiw_export_data` | Administrator |

## Award categories

1. Open **Awards > Award categories**. Categories sit under five groups: Leadership and Public Impact, Entrepreneurship and Business, Technology and Innovation, Professional Excellence, and Industry and Institutional Recognition.
2. Add a category, rename it or change its order.
3. To remove one, it must have no nominations. If it has any, rename it or move its nominations first.

Every change is audited. The Awards pages, the Nominate guidance and the Home marquee read categories from this screen, so a change needs no page edit. The eligibility and judging-criteria text is page copy, edited in the page editor.

## Open or close nominations

1. Open **Awards > Nominations**. The switch is at the top and defaults to Closed.
2. An Administrator turns it on or off. The change records who, when and an optional reason.
3. **Open:** the Nominate page and menus show the form.
4. **Closed:** they show "Nominations are closed", with links to nominees or winners once published.

This is the only feature switch in the system.

## Add a nomination yourself

1. On **Awards > Nominations**, go to **Add profile to nominations**.
2. Search for the profile by name and pick it, then choose a category. Add an optional note.
3. Optionally enter a Contact email. It is staff-only and never appears publicly, in exports or in logs.
4. Save.

There is no free-text name field. If the person or organisation has no profile yet, use the **Create profile** shortcut in the profile picker: it opens [Add profile](/staff/add-profile) with the name filled in and brings you back to the nomination after saving. A profile pending review can be added but cannot be set Live until it is reviewed.

## Stage and status

| Setting | Values | Meaning |
|---|---|---|
| Stage | Nominated, Winner | Nominated is the default. Switch to Winner once the offline judging has decided. |
| Status | Live, Not live | Not live is the default. Live puts it on the public site. |

A Live nominee shows on the Nominees page. A Live winner shows on the Winners page. A Not live nomination shows nowhere. Each change records who made it and is audited.

## Make a nomination public

1. Check that the profile behind it is reviewed.
2. Set the status to Live. The nominee page is built from the reviewed profile and its sourced facts.
3. When judging has decided, set the stage to Winner.

## Emails and removal

- Send nomination email is offered when a Contact email exists. The email carries no link and asks nothing of the nominee. Who sent it and when is recorded, and the audit entry holds no address.
- On a winner, Send winner email goes to the same address and is recorded the same way.
- Remove deletes a nomination after a confirmation and is audited.

## Where nominations come from

1. The public form. The nominator's email is verified by a single-use link, and submissions are rate-limited and checked for duplicates. A nomination arrives Nominated and Not live.
2. Staff, using Add profile to nominations.

The site does not email nominees on its own or ask for their consent. A nominee who objects uses Request removal, which hides the nomination and suppresses the name.

## Safeguards

- Payment never affects nominations or winners. No Awards screen or export shows commercial data.
- The nominator is never named publicly.
