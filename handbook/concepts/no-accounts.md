# The no-accounts model

Nobody outside the team has an account on the public site. This page explains what replaces accounts, who does have one, and how account-free flows stay safe.

## The rule

The public site has no registration, login, password reset or account area for visitors, profile owners, nominators, nominees or judges. WordPress sign-up is closed, there is no default role and no sign-in link on the public pages. The only WordPress users are staff.

## Why

- Nothing to log in to means a much smaller attack surface.
- There are no visitor passwords or profiles to protect, and less personal data to hold.
- Judges do not use the site. Judging and scoring happen offline.

## What replaces accounts

### Forms

| Form | Lands in |
|---|---|
| Enquiry to a profile | Enquiries, relayed by staff. The owner's address is never shown. |
| Correction | Corrections |
| Removal, report | Requests and reports |
| Claim this profile | Claims |
| Contact, Get listed | Enquiries |
| Nominate | Nominations (private) |
| Data request | Requests and reports |
| Stories newsletter | Subscribers, with consent |

### Single-use signed links

Where staff must be sure the right person is acting, the site emails a link. Links are used for nominator verification, customer intake, consent to a complimentary listing, and email verification of corrections, removals and reports.

| Property | Rule |
|---|---|
| Token | 32 random bytes, stored only as a hash |
| Scope | Tied to one purpose and one record |
| Life | Expires after 14 days, or 48 hours for nominator and requester verification |
| Use | Works once |
| Contents | No personal data in the address |
| Scanners | Opening the link shows a page, and pressing the button consumes it, so email scanners do not use it up |
| After use | Shows "Link expired or already used" |
| Staff | Can revoke or reissue. Every use is audited. |

## Who does have an account

Staff only: Reviewer, Editor and Administrator, plus any role the Administrator creates. Staff sign in through the normal WordPress login with two-factor authentication required before first use. There is no role selector on the sign-in page. A person who edits a claimed profile is an ordinary staff user. See [Roles and what each can do](/concepts/roles).

## Bot and abuse protection

1. Every public form has a bot challenge and is rate-limited per address and per email or link.
2. Forms use a time-based token instead of a standard WordPress nonce, because public pages may be cached.
3. Responses to public forms are uniform, so they do not reveal what exists.

## Checking who a requester is

A correction, removal or report counts as valid only after the requester's email is verified by a signed link. Staff then judge validity and check identity by hand. An official work email or the organisation's own domain is accepted. Anything else goes to staff judgement.

## Hardening that follows

- Looking up users is blocked: the author query, the users route in the REST interface, user sitemaps and login error messages.
- XML-RPC and application passwords are off.
- There is no public REST route. Core REST is limited to signed-in staff.
- A test asserts that no registration or login route is exposed to visitors.
