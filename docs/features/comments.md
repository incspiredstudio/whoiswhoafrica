# Comments

Readers can comment on stories without an account. Every comment is held for a person to approve before it appears.

## What it does

- Adds a comments section to every story page.
- Takes a name, an email and a consent tick, and nothing else.
- Holds each comment for moderation and publishes it only when approved.
- Provides spam protection and keeps commenter emails private.

## Who uses it and how

- As a reader, I can leave a comment on a story with my name and email.
- As a reader, I see "Thank you. Your comment will appear once it is approved."
- As an Editor, I can approve, edit or remove comments in the WordPress comments screen.
- As an Administrator, I can include comments in a data export or erase request.

## The pieces

### Theme

- Comments block on the story template: list of approved comments (avatar initial, name, time, text) and the "Leave a comment" form.
- A note under the form: comments are checked before they appear.
- Styling of the wpDiscuz output to match the brand tokens.

### Plugin

- Integration glue in the plugin: turn off Gravatar, remove comments on profiles and pages, force moderation on, and add commenter data to the PII catalogue and the data-rights export and erase.

### Pages and screens

- Public: the comments section on `/stories/:slug`.
- Staff: the native WordPress comments screen (moderation queue) and wpDiscuz settings.

### Third-party plugins

- wpDiscuz for the comment form, threading and moderation.
- Akismet or an equivalent filter for spam.

### Data

- wpDiscuz's own tables. They must be listed in the PII catalogue, the processor register, retention schedule and the export and erase flow.

## Business rules

- No account or login to comment (REQ-229, 258).
- Name, email and consent are required. The email is never shown and is used only to say when the comment is approved.
- All comments are held for moderation. A comment appears only after approval by a user with the core `moderate_comments` capability (Editor and Administrator by default).
- Spam is handled by a honeypot, rate limits and Akismet or an equivalent.
- Gravatar is off. Notification emails to commenters need an opt-in.
- Comments exist on stories only. Profiles have none.
- Comments are personal data and follow the retention and data-request rules.

Proposed defaults still to be confirmed: Editors moderate; comments close 90 days after publication; comments are kept while the story is public and erased with the commenter's request; no comments on Sponsored stories until Legal agrees.

## Acceptance criteria

- [ ] A story page shows the comments section; a profile page and an ordinary page do not.
- [ ] Submitting without a name, a valid email or the consent tick is refused.
- [ ] A submitted comment does not appear to other visitors until approved.
- [ ] After approval, the comment appears with the commenter's name and no email.
- [ ] No Gravatar request is made by the page.
- [ ] A user with `moderate_comments` can approve and remove comments; a Reviewer without it cannot.
- [ ] A spam-like submission (honeypot filled, or flagged by the filter) is not published.
- [ ] More than the rate limit from one IP in a short time is refused.
- [ ] A data export for an email address includes that address's comments; an erase request removes or anonymises them.
- [ ] A comment containing the commenter's email never appears in the public HTML.

## Depends on

- Stories.
- Privacy and data rights (export, erase, retention).
- Roles, audit and admin (`moderate_comments`).

## Out of scope or later

- Comments on profiles, awards pages or ordinary pages.
- Commenter accounts, reactions and voting.
- Email notifications to commenters beyond an opt-in.

## Requirement ids covered

REQ-258; also REQ-059, 062, 229. Open items: who moderates, how long comments stay open, retention, and whether Sponsored stories take comments.
