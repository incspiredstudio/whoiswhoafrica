# The no-accounts model

Nobody outside the team has an account on the public site. This page explains what replaces accounts, who does have one, and how the account-free flows stay safe.

Requirements: REQ-018, 047, 081, 122, 229, 232, 233, 235. Decisions: D-034, D-044, D-048. Records: ADR-004. Architecture: section 3.7 and 6.2.

## The rule

The public site has **no registration, login, password reset or account area** for visitors, profile owners, nominators, nominees or judges (REQ-229). WordPress front-end registration is closed: no self sign-up, no default role, no user-facing login link.

The only WordPress users are staff.

## Why

- Nothing to log in to means a much smaller attack surface at the front of the site.
- There are no passwords or profiles of visitors to protect, and less personal data to hold.
- Judges do not use the site. Judging and scoring happen offline (D-034, D-064).

## Who does have an account

| Role | Default scope |
|---|---|
| Reviewer | Review queue, duplicates, profile list (view, assign existing terms), read-only sources and suppression list |
| Editor | Everything a Reviewer does, plus editing, publishing, stories, awards, the support inbox, claims and curation |
| Administrator | Everything, including imports, exports, the commercial register, the audit log, users and capabilities (managed in WordPress) |

These are default bundles of named capabilities. The Administrator can grant or revoke single capabilities per user, and can create more roles (REQ-244). Staff sign in through the normal WordPress login, with a maintained two-factor plugin enforced for every staff account before first use (REQ-047). There is no role selector on sign-in (D-048).

A person who edits a claimed profile is an ordinary staff user, not a public account. See [Trust and labels](/concepts/trust-and-labels).

## What replaces accounts

### Forms

Anyone can do these with no account:

| Form | Lands in |
|---|---|
| Enquiry to a profile | Relayed by email; the owner's address is never shown |
| Correction, removal, report | The support inbox as a typed item with a status |
| Claim this profile | The Claims screen |
| Contact | The support inbox |
| Get listed (enquiry) | Support inbox, type Enquiry |
| Nominate | Nominations (private) |
| Data request | Data requests, with a due date |
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

A test asserts that no registration or login route is exposed to visitors ([build: testing](/build/testing-and-ci)). The prototype's smoke test checks the same thing: routes such as `/login`, `/signup`, `/account` and `/judge` do not exist.

## Open points

- Whether a profile owner's edits need a login is answered by D-059: they are made by an assigned staff user and approved by a Reviewer or Administrator.
- A fourth "Profile manager" role is not shipped by default; the Administrator can create one (Q93).
