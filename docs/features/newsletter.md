# Newsletter

Visitors can sign up by email to get new stories. Marketing email needs its own opt-in, and every message has a one-click unsubscribe.

## What it does

- Shows a sign-up form in the stories masthead, a second sign-up block part-way down the stories page, and on the home page where the design calls for it.
- Records the address, sends a confirmation email, and shows "You are subscribed".
- Puts an unsubscribe link in every marketing email.

## Who uses it and how

- As a visitor, I can give my email to get new stories and confirm it from the email I receive.
- As a subscriber, I can unsubscribe in one click from any email.
- As an Editor or Administrator, I can see sign-ups and who has unsubscribed.

## The pieces

### Theme

- Two sign-up form components (masthead and block) with one email field, consent text and a submit button.
- "You are subscribed" done page and the Unsubscribe page (`/unsubscribe`).

### Plugin

- Sign-up handler: validate email, rate limit, bot challenge, record consent with timestamp and policy version (REQ-058, 061).
- Confirmation email and unsubscribe handling, with an unsubscribe link in every message (REQ-061).
- Consent table entry for each sign-up.

### Pages and screens

- Public: sign-up forms on `/stories`, `/unsubscribe`, done page.
- Staff: a Subscribers screen with consent date and unsubscribed list. It is not yet specified.

### Third-party plugins

None decided. How newsletters are actually sent (the cPanel mail account, or a mail provider added later) is a build choice; if a provider is used it must be added to the processor register.

### Data

- Email address, time, consent, policy version, confirmed time, unsubscribed time.

## Business rules

- Marketing email requires its own opt-in and a one-click unsubscribe on every message (REQ-061).
- The consent is stored with a timestamp and the policy version shown at the time (REQ-058).
- A sign-up must be confirmable and reversible from every email (REQ-249).
- The sign-up is rate-limited and protected by a bot challenge (REQ-081, 122).
- Newsletter subscribers are kept in their own Subscribers list with a consent record, apart from enquiries and customers.
- Non-essential analytics and cookies still load only after consent (REQ-062).

## Acceptance criteria

- [ ] Submitting a valid email creates a record with a timestamp and the current policy version.
- [ ] A confirmation email is sent to that address.
- [ ] The visitor lands on the "You are subscribed" page after submitting.
- [ ] Every marketing email contains an unsubscribe link that works without logging in and without further steps.
- [ ] After unsubscribing, the address receives no further marketing email, and the record shows the unsubscribe time.
- [ ] Submitting an invalid or empty email shows an error and creates no record.
- [ ] More than the rate limit from one IP is refused.
- [ ] A bot-challenge failure creates no record.
- [ ] A subscriber appears in exports and data-request exports for that email address.

## Depends on

- Stories (where the forms live).
- Email and notifications.
- Privacy and data rights (consent, retention, requests).

## Out of scope or later

- A subscribers screen with consent date, double opt-in and unsubscribed list.
- Newsletter design, scheduling and sending tools.
- Segments and per-industry newsletters.

## Requirement ids covered

REQ-058, 061, 062, 081, 122, 249.
