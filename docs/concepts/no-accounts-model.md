# The no-accounts model

Nobody outside the team has an account on the public site, with one exception: Profile owners, the people behind approved claims (D-071). This page explains what replaces accounts, who does have one, and how the account-free flows stay safe.

Requirements: REQ-018, 047, 081, 122, 229, 232, 233, 235, 253, 261, 274. Decisions: D-034 (amended by D-071), D-044, D-048, D-071. Records: ADR-004. Architecture: section 3.7 and 6.2.

## The rule

The public site has **no registration, login, password reset or account area** for visitors, nominators, nominees or judges (REQ-229). WordPress front-end registration is closed: no self sign-up and no default role.

The only WordPress users are staff and Profile owners. Profile owners are the single exception to the rule (D-071, REQ-274): see [Profile owner accounts](#profile-owner-accounts).

## Why

- Nothing to log in to means a much smaller attack surface at the front of the site.
- There are no passwords or profiles of visitors to protect, and less personal data to hold.
- Judges do not use the site. Judging and scoring happen offline (D-034, D-064).

## Who does have an account

| Role | Default scope |
|---|---|
| Reviewer | Review queue, duplicates, profile list (view, assign existing terms), read-only sources and suppression list |
| Editor | Everything a Reviewer does, plus editing, publishing, stories, awards, enquiries, corrections, requests and reports, claims and curation |
| Administrator | Everything, including imports, exports, the sponsored story money details, the audit log, users and capabilities (managed in WordPress) |

These are default bundles of named capabilities. The Administrator can grant or revoke single capabilities per user, and can create more roles (REQ-244). Staff sign in through the normal WordPress login, with a maintained two-factor plugin enforced for every staff account before first use (REQ-047). There is no role selector on sign-in (D-048).

## Profile owner accounts

A person whose claim is approved can get a customer-style account on the public site, like a shop's "My account" (D-071, REQ-274). It is not a staff account.

| Property | Rule |
|---|---|
| Role | `wiw_profile_owner` ("Profile owner"): `read` and `wiw_own_profiles` only, no wp-admin capabilities |
| Sign-up | None. Staff press "Approve and create account" on a claim; it creates the account (or reuses the one for that email), links the profile and emails a "Set your password" link |
| Sign-in | `/account/`, linked from the footer only ("Profile owner sign in", or "My account" when signed in), never the header. Login lockout, Turnstile when configured, generic errors that never reveal whether an email exists |
| wp-admin | Any `/wp-admin/` request redirects to `/account/`; no admin bar |
| Pages | Sign-in, lost password, My profiles (each with Live or Changes waiting for review), Edit profile, Account details (name and password), Sign out |
| Edits | Descriptive text, links, contact details and the photo. Every save goes to staff review (REQ-253, 261); the owner is emailed when it is approved or returned |
| Cannot change | Status, slug, Claimed, labels, arrangements, or their own email (staff change it in Users) |
| Ownership | One owner can own several profiles. Staff can transfer or remove ownership; every staff action is audited |
| Two-factor | Optional |
| Privacy | Account pages are noindex, out of the sitemap and never page-cached. Owner accounts are in the PII catalogue and covered by personal-data export and erasure |

## What replaces accounts

### Forms

Anyone can do these with no account:

| Form | Lands in |
|---|---|
| Enquiry to a profile | Relayed by email; the owner's address is never shown |
| Correction | The Corrections screen, with a status and a due date |
| Removal, report | The Requests and reports screen, with a status and a due date |
| Claim this profile | The Claims screen |
| Contact | Enquiries |
| Get listed (enquiry) | Enquiries, type Enquiry |
| Nominate | Nominations (private) |
| Data request | Requests and reports, with a due date |
| Stories newsletter | A consented opt-in with one-click unsubscribe (REQ-061) |

### Single-use signed links

Where the system must be sure the right person is acting, it emails a link (REQ-233). Links carry:

- nominator verification
- customer intake
- consent for complimentary profiles
- email verification of correction, removal and report requests

Each link follows the same rules:

| Property | Rule |
|---|---|
| Token | 32 random bytes, stored only as a hash |
| Scope | Bound to one purpose and one record |
| Life | Expires. Default 14 days; 48 hours for nominator and requester verification (Q58) |
| Use | Works once |
| Contents | No personal data in the URL |
| Scanners | A `GET` shows the page, and a `POST` consumes the link, so email scanners that open links do not burn it |
| After use | Shows an "expired or already used" page |
| Staff | Can revoke or reissue; every use is audited |

## Bot and abuse protection

Public forms are protected by a bot challenge and rate-limited per IP and per email or link token (REQ-081, 122). They use a time-based form token instead of a WordPress nonce, because public pages may be cached at the edge and a nonce would be stale. The design names Cloudflare Turnstile for the challenge once Cloudflare is added; before then the plugin's own throttles apply.

## Checking who a requester is

A correction, removal or report counts as valid only after the requester's email is verified by a signed link. Staff then judge whether it is valid and check the requester's identity by hand. The default is to accept an official work email or the organisation's own domain, and send anything else to staff judgement.

## Hardening that follows from this

- User enumeration is blocked (the author query, the users REST route, user sitemaps and login error messages).
- XML-RPC and application passwords are off.
- There is no public REST route; core REST is limited to logged-in staff.

## A test that guards the rule

A test asserts that no registration route is exposed to visitors and that the only public sign-in is the Profile owner sign-in at `/account/` ([build: testing](/build/testing-and-ci)). The prototype's smoke test predates D-071: it checks that routes such as `/login`, `/signup`, `/account` and `/judge` do not exist in the wireframe.

## Open points

- How a profile owner edits is answered by D-071: through a Profile owner account on the public site, never wp-admin, with every edit approved by a Reviewer or Administrator. This replaces the wp-admin assigned user of D-059 and the optional "Profile manager" role (Q93).
