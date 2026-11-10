# Get listed and contact

These pages are how a person or organisation reaches the team: an enquiry about being listed or working with WHOISWHO, and a general contact form. Both create a record for staff and nothing is paid on the site.

Routes: `/subscribe` (V24, V25), `/contact` (V26, V27), `/invite/:token`, `/consent/:token` (O22, O23). Requirements: REQ-018, REQ-039, REQ-081, REQ-122, REQ-177 (requirements). Decisions: D-005, D-028 (decisions).

## Get listed (`/subscribe`)

The header's "Get listed" button leads here. The page was called "Subscribe / Get Featured" in the brief. It is now "Get listed", because Featured applies only to stories (D-055).

The page is an **enquiry form with some explanation, not a shop**. There are no packages, tiers, cart or checkout, and every listing has the same format (REQ-177, D-005, D-028). Money is agreed offline between the team and the customer.

The side text lists what the team can do: create or improve a listing, sponsored stories (always labelled), media and editorial work, and awards sponsorship.

| Field | Notes |
|---|---|
| Your name | Required |
| Organisation | Optional |
| Interested in | Listing a profile, Sponsored story, Media or editorial work, Awards sponsorship, Not sure |
| Email or phone | Required |
| Anything else | Free text |
| Consent | A tick that the visitor has read the privacy notice and agrees to be contacted about this enquiry |

The form has a bot challenge and is rate-limited per IP and per email (REQ-081, REQ-122). Submitting it creates an item of type **Enquiry** in the Support inbox and sends the visitor an acknowledgement with a reference such as `ENQ-0004`. The visitor lands on "Enquiry received: Our team will contact you. Terms are agreed offline; nothing is charged on this site."

An enquiry is not a profile. Nothing is published because someone sent an enquiry. If a listing is agreed, the team either creates the profile on the customer's behalf or sends the customer a single-use invitation link.

### Invitation and consent links

Two pages are reached only from an email link, not from the menu. Each link works once, expires, is bound to one purpose and carries no personal data in the address (REQ-233).

| Link | Purpose |
|---|---|
| `/invite/:token` (O23) | A customer whose arrangement was agreed offline starts their profile. No account or password. A draft is held against the link, and a form (O06) collects the profile details. Uploaded images are checked for size, type and scan (O07). The customer submits, a reviewer checks everything, and only then is it public (O09). |
| `/consent/:token` (O22) | A person is asked to agree to a complimentary listing. Consent is recorded with a timestamp. The profile is not public until they agree and a reviewer approves it. |

An expired or already used link shows "Link expired or already used. For your security, each link works once and expires. Nothing was changed." with a button to ask the team for a new link (O24).

The profile form scans text for contact details and sensitive information such as phone numbers, emails, ages, health, religion, politics and allegations, and asks the customer to remove them before submitting.

## Contact (`/contact`)

A short form: name, email and message, with a bot challenge. The page says a person reads every message, and that the quickest way to change a profile is the correction form on that profile.

The side panel points to common alternatives: find a profile to correct or remove it, Get listed, complaints and takedown, and your data rights.

Sending a message creates a general item in the Support inbox and shows "Message sent: A member of staff will reply. The message is logged." (V27, REQ-039).

## Enquiries about a profile

A different form lives on each profile: "Send an enquiry" relays a message to the profile's owner through staff. The owner's email and phone are never shown. After several enquiries in a short time the visitor is asked to try later. If a profile owner has asked for enquiries to be switched off, the page says so instead of showing the button (REQ-018, V43, V47). See [Directory and profiles](/pages/public/directory-and-profiles).

## Which staff screens feed it

| Public form | Staff screen | Who |
|---|---|---|
| Get listed | Support inbox, type Enquiry; customers and the commercial register for agreed deals (A14, A15) | Editor, Administrator |
| Contact and profile enquiries | Support inbox (A12) | Editor, Administrator |
| Invitations and consent links | Customers (A14), issued with "Issue invitation or consent link" (A45) | Administrator |

Default roles: the Support inbox is an Editor capability, and the commercial register, customers and exports are Administrator only. The Administrator can regrant capabilities to other users.

## Rules to remember

- Paying never changes how a profile is ranked, labelled, nominated or judged (REQ-183).
- Only stories can be Sponsored. A paid story placement is an enquiry like any other and is arranged offline. A paid listing is a subscription, never a Sponsored profile (D-055, D-062).
- No public page states prices, packages or commercial terms. The public commercial terms page was dropped (REQ-120).

## Open points

- The requirements set response targets for corrections, removals and claims (REQ-158, REQ-256). They set none for Get listed enquiries or contact messages.
