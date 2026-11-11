# Backups

Backups let the site recover from a bad change, a hacked account or a host failure. This page states what is backed up, how often, and how to prove a restore works.

## What is backed up

| Item | Frequency | Where |
|---|---|---|
| Database | Daily | cPanel backups, plus a copy kept off the server |
| Files (uploads and private files) | Weekly | cPanel backups, plus a copy kept off the server |
| Audit log | Nightly | Copied off the host, so tampering is detectable |
| Pre-deploy database dump | Every production migration | Taken automatically |

The recovery targets are to lose at most 24 hours of data and to be back within 8 hours. Images and backups live on the host, so download a copy regularly.

## Take a backup before risky work

1. Make a database dump before any migration, import or bulk unpublish.
2. Note the time and what you are about to do in the runbook's log.
3. Proceed.

## Test a restore

A backup that has not been restored is not a backup.

1. Choose a recent database backup and the matching files.
2. Restore them to a copy of the site, not the live one.
3. Sign in, open a profile, a story and the Nominations screen, and check the sitemap.
4. Write the date, the time it took and any problem into the runbook.
5. Repeat every quarter, and again before launch.

## Secrets and backups

Backups hold personal data. Keep them encrypted with a passphrase stored outside the web root. Keep the passphrase out of the backup itself.

## Audit log protection

The audit log is append-only with a hash chain, so any alteration is detectable. A periodic off-server copy means tampering shows up even without database-level protection.
