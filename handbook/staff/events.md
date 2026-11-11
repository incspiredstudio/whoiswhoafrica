# Events

Events have their own content type and their own item in the admin menu. Staff create, schedule, publish and unpublish them, and can link one to a story. Events cannot be Sponsored, and the site takes no registration or payment.

## Who does what

| Work | Where | Capability | Default roles |
|---|---|---|---|
| Create, edit, schedule, publish and unpublish an event | Events | `wiw_manage_events` | Editor, Administrator |
| Link an event to a story | The Related event field in the story editor | `edit_posts`, and `wiw_manage_events` to see the event list | Editor, Administrator |

The Administrator can grant the events capability to any role, or take it from an Editor. Every change is audited.

## Create an event

1. Open Events and choose Add new.
2. Fill in the fields.
3. Save as a draft, schedule it, or publish it.

| Field | Notes |
|---|---|
| Title | Required |
| Start date and time | Required |
| End date and time | Optional. If empty, the event is upcoming until its start. |
| Venue | A place, or Online |
| City and country | Required unless Online |
| Organiser | Name of who runs it |
| Industry | One of the existing industry terms |
| Summary or body | The text shown on the event page |
| Image | From the media library |
| Registration link | An external address. Informational only. |
| Price note | Text only, for example "Free" or "From USD 50 at the door" |

## The Events list

The list shows each event's title, start date, city, status (Draft, Scheduled or Published) and whether it is upcoming or past. Filter by status, city and industry.

## What saving does

- Saving a published event refreshes its public page, the Events list and the Home page section.
- Unpublishing makes the event page "not found" and removes it from the sitemap.
- The Home page shows the next three upcoming events automatically. Events are not chosen by curation.

## Link an event to a story

1. Open the story in the editor.
2. In Related event, pick one published event.
3. Save. The story page shows a Related event card at the end, and the event page lists the story.

Unlinking, or unpublishing either side, removes the card.

## What is not here

- Events cannot be Sponsored. The Sponsored stories screen lists stories only.
- No registration or payment is taken.
- Events are not in site search.
