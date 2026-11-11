# Events

Events are their own content type, separate from stories. They list conferences, launches, ceremonies and other happenings with the date, place and a link to register. A story can link to one event, so the event shows at the end of the story.

## What it does

- Lets Editors publish an Event with when, where, organiser and an external registration link.
- Gives events their own pages: an Events list at `/events` and an Event page at `/events/:slug`.
- Adds an "Events" link to the header and the footer, and an Events section to the Home page.
- Moves an event from upcoming to past on its own, by date.
- Lets a story link one event, shown as a Related event card at the end of the story. The Event page lists the stories that link to it.
- Gives each published event Event structured data.

## Who uses it and how

- As a visitor, I can see what is coming up from the Home page, the header or the footer.
- As a visitor, I can browse upcoming events and past events, and filter by city and industry.
- As a visitor, I can open an event, read the details and use the registration link.
- As a visitor reading a story, I can see the related event at the end and open it.
- As an Editor, I can create an event with its dates, venue or Online, organiser, industry and registration link, and publish or schedule it.
- As an Editor, I can link an event to a story from the story editor.
- As an Administrator, I can give the events capability to another role, for example a Reviewer.

## The pieces

### Theme

- Templates: the Events list (`archive-event`) and the Event page (`single-event`).
- An "Events" link in the header and the footer.
- An Events section on the Home page: the next three upcoming events and an "All events" link.
- Event card, Event details block and Related event card patterns.
- A Related stories block on the Event page.
- Event structured data on the Event page (see SEO, sharing and sitemaps).

### Plugin

- `Content` module: the `wiw_event` post type with its fields, the `PublicEvent` read model that computes upcoming or past, and the `related_event_id` field on stories.
- Capability `wiw_manage_events`.
- The sitemap lists published events only.

### Pages and screens

- Public: [Events](/pages/public/events) at `/events` and `/events/:slug`; the end of [Stories](/pages/public/stories) (Related event); the Events section on [Home](/pages/public/home).
- Staff: the [Events](/pages/admin/events) screen, and a Related event field in the story editor.

### Third-party plugins

None.

### Data

Fields on an event: start date and time, optional end, venue or Online, city, country, organiser, industry, summary or body, image, registration link, price note. A story has an optional `related_event_id`. Proposed names are set when the plugin is built.

## Business rules

- An event is not a story. It follows the same review, scheduling, draft and removal rules as other published content (REQ-262).
- Upcoming or past is computed from the end date, or the start date if there is no end. A past event stays published, shows "Past event" and has no registration button.
- The registration link is external and informational. The site takes no registration or payment for events (D-005).
- The price note is text only. No page states a price the site collects.
- Events cannot be Sponsored. Paid placement is for stories only (REQ-263).
- Events are not in site search at launch. Search covers profiles and stories (REQ-254).
- A draft, scheduled or removed event returns 404 and has no structured data or sitemap entry.
- A story shows its Related event only while that event is published. The Event page lists only published stories that link to it (REQ-265).
- `wiw_manage_events` is held by Editors and Administrators by default, and the Administrator can grant it to any role (REQ-244).

## Acceptance criteria

- [ ] An Editor can create, schedule and publish an event with start, venue or Online, city, country, organiser, industry, registration link and price note.
- [ ] `/events` lists upcoming events first, soonest first, then past events, and can be filtered by city and industry.
- [ ] `/events/:slug` shows the details block and body. A past event shows "Past event" and no registration button, and is not unpublished.
- [ ] The header and the footer each have an "Events" link that opens `/events`.
- [ ] The Home page has an Events section with the next three upcoming events and an "All events" link, and the section is absent when no event is upcoming.
- [ ] The Stories page has no Events filter and no upcoming-events strip.
- [ ] A story can link one event; its page shows a Related event card at the end. Unlinking removes the card.
- [ ] The Event page lists the published stories that link to it, and omits drafts.
- [ ] A published event has Event structured data that matches the visible details; a draft or scheduled event has none.
- [ ] Events do not appear in site search results.
- [ ] An event cannot be selected in the Sponsored stories screen.
- [ ] Without `wiw_manage_events`, the Events screen returns 403; granting it to a Reviewer opens it.
- [ ] The registration link opens the external address in a new tab, and the site collects no payment or registration.

## Depends on

- Stories (the Related event link and templates).
- Home and curation (the Events section on the Home page).
- SEO, sharing and sitemaps.
- Roles, audit and admin (the capability).

## Out of scope or later

- Ticketing, payment or registration on the site.
- Calendar file downloads and recurring events.
- Event reminders by email.
- Finding events through site search.

## Requirement ids covered

REQ-262, 264, 265; also REQ-026, 027.
