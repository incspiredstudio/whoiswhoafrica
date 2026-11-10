# Open questions

These are the questions that still need an answer. Each one has a suggested answer, so silence means we go with the suggestion. Answered questions are not listed here.

2 open: 1 about the product and 1 about the WordPress build.

## Product

### Q102. How does an approved claimant get access to edit their profile, and what can they change?

**Why it matters.** Claiming is only useful if the owner can keep the profile current, and there are no public accounts (D-060, REQ-261).

**Suggested answer.** Staff create an ordinary wp-admin user for the claimant with a limited Profile manager role, two-factor enforced. The user sees only their assigned profiles, can change descriptive fields, links and photograph, cannot change status, slug or arrangements, and every edit goes to review (REQ-253).

## WordPress build

These need an answer before the build plan is final.

### Q87. What are the limits of the live cPanel host (PHP version, memory, execution time, cron, inodes, database size, backups), and who checks them?

**Why it matters.** The site is built locally and imported (D-050); the host may not fit.

**Suggested answer.** Check at import time and record in the runbook. Needed: a current PHP version, at least 256 MB memory, a working cron, enough inodes and disk for the media library. If the host fails, move to a small VPS.

---

Generated from the open items in `docs/questions.md` by `scripts/build-open-questions.js`. Do not edit this page by hand.
