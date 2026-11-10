# Platform quality

Performance, accessibility, availability, backups, monitoring and delivery checks that every other feature depends on.

## What it does

- Keeps public pages fast, accessible and usable on a mid-range phone.
- Keeps the site available, backed up and monitored.
- Runs automated checks on every change so features cannot regress.
- Measures visits and journeys without tracking individuals.

## Who uses it and how

- As a visitor on a mid-range Android phone over 4G, I can open a profile page quickly.
- As a visitor who uses a screen reader or keyboard, I can use every public page.
- As an Administrator, I can restore the site from a backup and read an operations runbook.
- As a developer, I can see a failing check before a change is merged.

## The pieces

### Theme

- Responsive layouts usable at 360 px; support for the latest two versions of Chrome, Safari, Edge, Firefox and Android Chrome.
- WCAG 2.1 AA: contrast, focus states, labels, keyboard use, reduced-motion support.
- Image handling: resized, modern formats, lazy loading.

### Plugin

- Guard plugin hardening: HTTPS, security headers, form rate limits, file type checks for uploads, no file editing in production.
- Monitoring hooks for form-submission failures and errors, with no personal data in logs.
- Daily view counts for the staff report only.

### Pages and screens

- Public: every page.
- Staff: Dashboard weekly report; error and uptime monitoring outside the site.

### Third-party plugins

- Backups are set up in cPanel (D-060). A backup plugin only if cPanel cannot do it. They count as done only when they run and a restore has been tested (see the [launch checklist](/build/launch-checklist)).
- Privacy-friendly analytics (cookieless and first-party by default).
- Optional uptime monitor, later.

### Data

- Backups of the database and uploads, daily. Nothing else.

## Business rules

- Profile and directory pages reach Largest Contentful Paint of 2.5 seconds or better on a mid-range Android phone over throttled 4G (REQ-041, an assumed target).
- Public pages meet WCAG 2.1 AA (REQ-043, assumed) and work at 360 px (REQ-040).
- Images are served resized and in modern formats; a CDN may cache them later (REQ-042, 080).
- All traffic is HTTPS, with security headers, form rate limits and dependency scanning (REQ-046).
- Availability is 99.5% a month or better (REQ-045, assumed). Design for at least 50 concurrent users and 100,000 page views a month (REQ-054).
- Backups run daily; a restore is tested before launch; recovery point 24 hours and recovery time 8 hours (REQ-049).
- Errors, uptime and form failures are monitored; logs hold IDs only (REQ-050).
- Uploaded files are type-checked and scanned (REQ-127).
- Privacy-friendly analytics measure visits, journey completion and conversions (REQ-078).
- A staging environment mirrors production and automated tests run on every pull request (REQ-056).
- An operations runbook documents deploy, rollback, backup restore and incident handling (REQ-095).
- Media is stored in the WordPress media library on the host, originals kept (REQ-080).

## Acceptance criteria

- [ ] On a throttled-4G mid-range Android profile, a profile page reaches Largest Contentful Paint at or under 2.5 seconds in the agreed test.
- [ ] An automated accessibility check (axe) reports no violations on the home, directory, profile, story, nominate and form pages at 360, 768 and 1280 px.
- [ ] No public page scrolls horizontally at 360 px.
- [ ] Keyboard-only use reaches every interactive control with a visible focus state.
- [ ] Reduced-motion visitors get no auto-advancing motion.
- [ ] Every response sets HTTPS-only and the agreed security headers.
- [ ] An uploaded file with a disallowed type or over the size limit is refused.
- [ ] A backup is taken daily and a documented restore has been run successfully before launch.
- [ ] CI runs unit, integration, architecture and end-to-end tests, linting and static analysis on every pull request, and a high-severity dependency finding fails the build.
- [ ] A uptime or form-failure event reaches the person named in the runbook.
- [ ] Analytics load no non-essential cookie before consent.

## Depends on

- All public features.
- Privacy and data rights (cookies and analytics consent).
- SEO, sharing and sitemaps (rendering and caching).

## Out of scope or later

- An object store or CDN offload (not used at launch).
- Load testing beyond the stated concurrency target.
- A paid uptime or error-tracking service (optional, later).

## Requirement ids covered

REQ-040, 041, 042, 043, 045, 046, 049, 050, 054, 055, 056, 078, 080, 095, 127. Production operating by 31 Jan 2027 (REQ-103).
