# Get listed and enquiries

The Get listed page and the contact form are how people reach the team. Both create a record for staff; nothing is paid or bought on the site.

## What it does

- Presents "Get listed" as an enquiry form with explanatory text, not a shop.
- Creates a Support inbox item of type Enquiry for staff from each Get listed submission and sends an acknowledgement.
- Provides a contact form that creates a general item in the Support inbox.
- Relays enquiries sent to a profile owner without exposing the owner's email or phone.
- Lets staff invite a customer to fill in their own profile, or ask a person to consent to a complimentary listing, through single-use links.

## Who uses it and how

- As an organisation, I can ask to be listed, sponsor a story, ask for media work or sponsor the awards.
- As a visitor, I can send the team a message.
- As a visitor, I can send an enquiry to a profile's owner.
- As an Editor, I can work enquiries in the Support inbox, set an owner and status, and create a customer when a deal is agreed.
- As an Administrator, I can issue an invitation or consent link to a customer.

## The pieces

### Theme

- `/subscribe` (Get listed): side text listing what the team can do, and the form (name, organisation, interested in, email or phone, anything else, consent tick).
- `/contact`: name, email, message, bot challenge.
- Enquiry form on each profile.
- Done pages with a reference such as ENQ-0004; expired-or-used link page.
- Customer profile form (`/invite/:token`) and consent page (`/consent/:token`).

### Plugin

- `Commerce` module: customers with stable external IDs and change timestamps (REQ-205). Enquiries live in the Support inbox.
- Single-use signed link service for invitations and consent.
- Profile intake form with drafts, validation, image type and size limits, and a free-text scan for contact details and sensitive words (REQ-013, 141).
- Enquiry relay with rate limits.
- Capabilities `wiw_support_inbox`, `wiw_receive_enquiries`.

### Pages and screens

- Public: `/subscribe`, `/contact`, profile enquiry form, `/invite/:token`, `/consent/:token`.
- Staff: Support inbox (Enquiry items), Customers (with the Issue invitation or consent link action).

### Third-party plugins

A bot challenge. No other plugin.

### Data

- Enquiry items (name, organisation, interest, contact, status, owner, notes, time) and customers (external ID, name, segment, contact).
- Consent records for complimentary listings.

## Business rules

- No packages, tiers, cart, checkout or prices on the site. Every listing has the same format. Money is agreed offline (REQ-177).
- An enquiry is not a profile. Nothing is published because someone sent an enquiry.
- Forms are protected by a bot challenge and rate-limited per IP and per email or link token (REQ-081, 122).
- Enquiry relay never shows the owner's email or phone. Staff can switch enquiries off for a profile on the owner's request, logged (REQ-018).
- A complimentary listing needs the customer's recorded consent on a single-use signed-link page before publication (REQ-175).
- Each link is bound to one purpose and one record, works once, expires, is stored hashed and carries no personal data in the URL (REQ-233).
- Customer-submitted profiles are public only after a human approves them (REQ-014).

## Acceptance criteria

- [ ] Submitting the Get listed form creates a Support inbox item of type Enquiry with the entered fields and a reference, and sends an acknowledgement.
- [ ] The Get listed form refuses a submission without name, contact or consent.
- [ ] No page on the site shows a price, package or checkout.
- [ ] Submitting the contact form creates a general Support inbox item and shows "Message sent".
- [ ] A profile enquiry reaches staff and never exposes the owner's email or phone in the page or the confirmation.
- [ ] After the rate limit, further submissions from one IP or email are refused with a try-later message.
- [ ] An invitation link opens the profile form once; a second use shows "Link expired or already used".
- [ ] The profile form blocks a submission containing a phone number, email, age or sensitive word in free text.
- [ ] A submitted customer profile is not public until a reviewer approves it.
- [ ] A complimentary listing cannot be published without a stored consent record.
- [ ] An Enquiry item can be assigned an owner and status, and an Editor can create a customer from it.

## Depends on

- Sponsored content and the commercial register (conversion to arrangements).
- Email and notifications; Roles, audit and admin.
- Directory and profiles (enquiry form, intake).

## Out of scope or later

- Online payment, checkout or card data (not allowed anywhere on the site).
- A CaromHQ integration (CSV import and export now; a live link only if CaromHQ exposes an API).
- Response targets for enquiries and contact messages (none are specified).

## Requirement ids covered

REQ-013, 014, 018, 039, 081, 122, 141, 175, 176, 177, 178, 205, 233. First paying customers' arrangements and support process by 20 Dec 2026 (REQ-101).
