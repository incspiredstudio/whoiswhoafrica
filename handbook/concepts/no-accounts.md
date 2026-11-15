# The no-accounts model

Nobody outside the team has an account on the public site, with one exception: Profile owners, the people behind approved claims (D-071). This page explains what replaces accounts, who does have one, and how account-free flows stay safe.

## The rule

The public site has no registration, login, password reset or account area for visitors, nominators, nominees or judges. WordPress sign-up is closed and there is no default role. The only WordPress users are staff and Profile owners.

A Profile owner cannot sign up. Staff create the account when they approve a claim with **Approve and create account**. The owner signs in on the public site at `/account/`, from a "Profile owner sign in" link in the footer. There is no sign-in link in the header.

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

Staff only: Reviewer, Editor and Administrator, plus any role the Administrator creates. Staff sign in through the normal WordPress login. Two-factor sign-in is available and recommended for every staff member, but not enforced. There is no role selector on the sign-in page. See [Roles and what each can do](/concepts/roles).

### Profile owners

| Property | Rule |
|---|---|
| Role | `wiw_profile_owner`, with no wp-admin capabilities |
| Created by | Staff, from an approved claim. No public sign-up. |
| First sign-in | A "Set your password" email |
| Where they sign in | `/account/` on the public site, with the login lockout and, when configured, Turnstile. Errors never say whether an email has an account. |
| wp-admin | Any wp-admin address redirects to `/account/`. No admin bar. |
| What they can do | See their profiles and their status, edit descriptive text, links, contact details and the photo (every edit goes to staff review), change their name and password, sign out |
| Two-factor | Optional |
| Account pages | Noindex, not in the sitemap, never cached |

See [Your profile owner account](/visitor/your-account) and [Claims](/staff/claims#profile-owner-accounts).

## Bot and abuse protection

1. Every public form has a bot challenge and is rate-limited per address and per email or link.
2. Forms use a time-based token instead of a standard WordPress nonce, because public pages may be cached.
3. Responses to public forms are uniform, so they do not reveal what exists.

## Checking who a requester is

A correction, removal or report counts as valid only after the requester's email is verified by a signed link. Staff then judge validity and check identity by hand. An official work email or the organisation's own domain is accepted. Anything else goes to staff judgement.

## Hardening that follows

- Looking up users is blocked: the author query, the users route in the REST interface, user sitemaps and login error messages, including on the Profile owner sign-in.
- XML-RPC and application passwords are off.
- There is no public REST route. Core REST is limited to signed-in staff.
- A test asserts that no registration route is exposed to visitors, and that the only public sign-in is the Profile owner sign-in at `/account/`.
