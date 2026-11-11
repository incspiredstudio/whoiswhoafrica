# Corrections, removals and reports

Anyone can ask for a correction, a removal, or report a profile, with no account and no payment. Corrections have their own screen, and removals and reports go to Requests and reports; staff handle each against published deadlines (REQ-270, REQ-271).

## What it does

- Provides three public forms on every profile: Suggest a correction, Request removal, Report.
- Confirms the requester's email by a single-use link before the request counts.
- Lands each correction on the Corrections screen, and each removal and report on the Requests and reports screen, as items with a status, an age and a due date.
- Lets staff fix a profile directly, hide it, or add it to the permanent suppression list.
- Takes a profile down at once on the origin, with a 410 response, and clears it from search, sitemap and cache.

## Who uses it and how

- As a person named on a profile, I can ask for a fact to change and give evidence.
- As the subject or their representative, I can ask for my profile to be removed.
- As any visitor, I can report a profile as inaccurate, defamatory or harmful, a privacy problem or a copyright problem.
- As an Editor, I can resolve a correction by editing the profile, which closes the request.
- As a Reviewer, I can hide a reported profile immediately while it is investigated.
- As an Administrator, I can change the suppression list when needed.

## The pieces

### Theme

- Forms: `/correct/:slug`, `/remove/:slug`, and the Report dialog on the profile.
- "Received" page with a reference, and the "Link expired or already used" page.
- The public Complaints and takedown page with the response promises (see Privacy and data rights).

### Plugin

- Item types: Enquiry (Enquiries), Correction (Corrections), Removal and Report (Requests and reports), alongside data requests (REQ-089, 270, 271).
- Single-use signed link service for email verification (REQ-233).
- Deadline tracking and breach flags (REQ-158).
- Direct profile edit with a required reason, version history and audit (REQ-125, 188).
- Suppression list and the state machine's Removed and suppressed transition.
- Unpublish pipeline: 410, noindex header, sitemap and search removal, cache purge job (REQ-209).
- Bulk unpublish by filter and restore by batch (REQ-193).
- Capabilities `wiw_support_inbox`, `wiw_handle_requests`, `wiw_handle_disputes`, `wiw_unpublish_profiles`, `wiw_bulk_unpublish`, `wiw_view_suppression`, `wiw_edit_suppression`.

### Pages and screens

- Public: correction, removal, report, done, expired-link pages.
- Staff: Corrections, Requests and reports, Profile screen, Profiles list with bulk unpublish, Suppression list, Purge log.

### Third-party plugins

A bot challenge on the public forms. No other plugin.

### Data

- Support items table with requester name, relationship, email, message, status, owner, due date.
- Suppression table. Bulk action table (filter, reason, count, actor).
- Signed links table (hashed token, purpose, subject, expiry, use).

## Business rules

- Acknowledge within one business day. Resolve corrections within 7 days. For a valid removal from the subject or their representative, hide the profile within two business days (REQ-158). Breaches are flagged.
- Requests need no account or payment (REQ-157). The requester's email is verified by a single-use link; until then the item shows "email not confirmed".
- Staff do not edit a separate draft. They edit the profile directly and enter the request reference as the reason.
- A valid removal adds the name to the suppression list automatically. No import can bring it back. There is no remove button on the list; editing it needs `wiw_edit_suppression` (Administrator by default) (REQ-147, 207).
- A removed or suppressed profile returns 410 with noindex, leaves the sitemap and search, and has its cache purged (REQ-209).
- Bulk unpublish needs a reason and a count confirmation, writes one audit row per profile plus one for the batch, and can be restored by batch (REQ-193).
- An unresolved dispute goes to the Administrator (REQ-174).
- Information a subject asks for (status, view count, sources, enquiries) is sent by staff through the inbox, and logged (REQ-236).
- Forms are rate-limited per IP and per email or link token (REQ-122).

## Acceptance criteria

- [ ] A correction form submission creates an item on the Corrections screen with a due date 7 days out.
- [ ] A removal request or a report creates an item on Requests and reports, not on Corrections, with its due date and a Kind column value of Removal or Report; a data-rights form submission appears in the same list as a Data request.
- [ ] On the Corrections screen, Apply opens the profile for a direct edit with a required reason; for a claimed profile the edit waits for approval (REQ-253); Decline requires a reason that is never public.
- [ ] A user without `wiw_handle_requests` gets a 403 on Requests and reports.
- [ ] A removal submission creates an item of type Removal with a countdown to two business days.
- [ ] Until the requester opens the verification link, the item shows "email not confirmed"; the link works once and expires.
- [ ] Opening a used or expired link shows the expired-or-used page and changes nothing.
- [ ] Choosing "Valid: hide and suppress" on a removal makes the profile return 410 with `X-Robots-Tag: noindex` within the same request, removes it from the sitemap and search, queues a cache purge, and adds a suppression entry.
- [ ] After suppression, re-importing the same record does not recreate or republish it.
- [ ] Editing a profile to resolve a correction requires a reason, creates a version, writes an audit row and closes the linked item.
- [ ] A Report lets any visitor choose a reason and creates an item in the inbox; a Reviewer can hide the profile from it.
- [ ] An item past its target shows a breach flag.
- [ ] Bulk unpublish by filter shows the match count, requires a reason, produces 410 for every match, and "Restore this batch" returns them to 200.
- [ ] A Reviewer and an Administrator can bulk unpublish; an Editor cannot unless granted the capability.
- [ ] Only a user with `wiw_edit_suppression` can add or remove a suppression entry by hand.
- [ ] Submitting more than the rate limit from one IP shows a "try later" message and creates no item.

## Depends on

- Directory and profiles (states and unpublish).
- Email and notifications.
- Roles, audit and admin.
- SEO, sharing and sitemaps (410, noindex, sitemap removal).

## Out of scope or later

- A right-of-reply note on a profile (REQ-172, should-have).
- Notifying the subject when a stub is created (REQ-159, waiting on Legal).
- Any owner account.

## Requirement ids covered

REQ-072, 089, 090, 091, 122, 147, 157, 158, 174, 193, 207, 209, 232, 233, 236. Support process working by 20 Dec 2026 (REQ-101).
