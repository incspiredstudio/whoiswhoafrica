# Events

Events lists what is happening: conferences, launches, ceremonies and other gatherings linked to the people and ideas on the record. Events are their own content type, separate from stories. The site only lists them and links to the organiser's registration page.

Routes `/events` and `/events/:slug`. Requirements: REQ-262, REQ-264, REQ-265 (requirements).

## Where a visitor finds events

| Place | What shows |
|---|---|
| Header | An "Events" link |
| Footer | An "Events" link |
| [Home](/pages/public/home) | An Events section of compact agenda rows: the next three upcoming events and an "All events" link. Absent when no event is upcoming |
| End of a story | A Related event card, when the story links an event |

## The Events list (`/events`)

The Events pages are built as a calendar, so they never read as the story pages. There are no image cards, bylines or reading times.

1. **Header band.** A full-width orange band with the title "Events" in large display type and one line of introduction. Beside it, a charcoal **Next up** block shows the soonest upcoming event: a large date, the title, the place and a "View event" link. The block is absent when no event matches.
2. **Filter bar.** One quiet row: City and Industry as borderless inline lists, the number of events on the right, and Clear only while a filter is on (see [Components](/theme/components)). Choosing a value applies it straight away.
3. **Upcoming**, soonest first, grouped under month headings. Each row is an agenda row: a date block (day and month), the title in display type, the place and organiser, a status tag (Upcoming or Online), a small image on wide screens and an arrow. Hovering a row slides it in and turns it charcoal.
4. **Past events**, below and muted, newest first, grouped by month, with a Past tag.
5. An empty state: "No upcoming events match" with a clear-filters action. When there are no upcoming events the section says so and the past events still show.

## The Event page (`/events/:slug`)

1. **Poster.** A full-width colour block, orange for an upcoming event and charcoal for a past one, with the date in very large type (day, then month and year), the status tag, the title, the place and "Organised by". On wide screens a duotone image fades in on the right.
2. **Details card.** Beside the text on wide screens and sticky as you scroll, above the text on phones: When, Where, Organiser, Cost and the Register button (a link to the organiser's site). A past event shows "This event has ended" and no button.
3. **Text.** The summary as a large introduction, then the body.
4. **Related stories.** A plain list of the stories that link this event. Absent when there are none.
5. **More events.** The next upcoming events as agenda rows.

There is no author, reading time, cover image or comments section on an event.

Only published events appear. Events are not mixed into the Stories page.

## An Event page (`/events/:slug`)

| Block | Shown when |
|---|---|
| Image and title | Always |
| Event details | Always: start, end, venue or Online, city and country, organiser, price note |
| Register | The event is upcoming and has a link. It opens the organiser's site in a new tab. A past event shows "Past event" and no button |
| Body | Always |
| Related stories | One or more published stories link to this event |

The page never takes a registration or a payment (D-005). The price note is text.

## Rules

**Upcoming or past follows the date.** An event is upcoming until its end date, or until its start date if it has no end. Then it is past, stays published, and loses its register button.

**Published only.** A draft, scheduled or removed event returns 404 and is absent from the list, the Home page, the sitemap and the story that linked it.

**Events are never Sponsored.** The Sponsored label and paid placement apply to stories only (REQ-263).

**Not in site search at launch.** Search covers profiles and stories. Visitors find events through the header, the footer, Home and related stories.

**Event structured data.** Each published event page carries Event structured data that matches the visible details (REQ-262).

## Who feeds it

| Content | Fed by |
|---|---|
| Events | The Events screen in the admin (`wiw_manage_events`). See [Events](/pages/admin/events) |
| Related event on a story | The Related event field in the story editor |
| Home Events section | Automatic: the next three upcoming events |

## Prototype notes

The prototype shows events inside Stories until its next pass. In the WordPress build events have their own list and pages as described here.
