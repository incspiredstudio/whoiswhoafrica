# Events (admin)

How staff create and manage events, and link them to stories. Events have their own admin menu item. The prototype has no Events screen yet, so this page describes the requirement for the build.

## Who does what

| Work | Where | Capability | Default roles |
|---|---|---|---|
| Create, edit, schedule, publish and unpublish an event | Events | `wiw_manage_events` | Editor, Administrator |
| Link an event to a story | The story editor, Related event field | `edit_posts` and `wiw_manage_events` to see the event list | Editor, Administrator |

`wiw_manage_events` is like `wiw_curate_home`: Editors and Administrators hold it by default, and the Administrator can grant it to any role, including a Reviewer, or take it from an Editor (REQ-244). Every change is audited.

## The Events screen

The list shows each event with its title, start date, city, status (Draft, Scheduled, Published) and whether it is upcoming or past. It can be filtered by status, city and industry.

The editor has these fields:

| Field | Notes |
|---|---|
| Title | Required |
| Start date and time | Required |
| End date and time | Optional. If empty, the event is upcoming until its start |
| Venue | A place, or Online |
| City and country | Required unless Online |
| Organiser | Name of who runs it |
| Industry | One of the existing industry terms |
| Summary or body | The text shown on the Event page |
| Image | From the media library |
| Registration link | An external address. Informational only |
| Price note | Text only, for example "Free" or "From USD 50 at the door" |

Saving a published event purges its public page, the Events list and the Home page section. Unpublishing returns 404 on the event page and removes it from the sitemap.

## Linking an event to a story

The story editor has an optional Related event field that picks one published event. The story page then shows a Related event card at the end (REQ-265). The Event page lists the stories that link to it. Unlinking, or unpublishing either side, removes the card.

## What is not here

- Events cannot be Sponsored. The Story sponsors screen lists stories only (REQ-263).
- No registration or payment is taken (D-005).
- Events are not in site search at launch.

## Built from

| Need | Provided by |
|---|---|
| Scheduling, revisions, trash | Native WordPress, on the custom post type `wiw_event` |
| Fields, upcoming or past, the capability | Custom plugin, `Content` module (REQ-262) |
