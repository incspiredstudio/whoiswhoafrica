# Runbook outline

The runbook is the written set of procedures for running the site. This page lists what it must contain, and the order each procedure follows. Keep the full runbook with the site's code, and update it whenever a procedure changes.

## Sections

| Section | What it holds |
|---|---|
| Host record | PHP version, memory, cron, inodes, disk, SMTP and TRIGGER privilege, as found on the host |
| Deploy | Build a release, copy it, run migrations through the deploy script, run the smoke checks |
| Rollback | Code, database, bad import and bad publication |
| Backup and restore | Schedule, off-server copy, quarterly restore test and its results |
| Incidents | Detection, containment, notice, and a dated entry for each incident without personal data |
| Bulk unpublish | When to use it, the filter, the reason, and how to restore a batch |
| Updates | The weekly update routine and the 48-hour security routine |
| Key rotation | Steps for SMTP credentials, signing keys, the backup passphrase and the WordPress salts |
| Staff joiners and leavers | Create an account, ask them to switch on two-factor, grant capabilities, deactivate the same day on leaving ([Users, roles and two-factor](/staff/users-and-two-factor)) |
| Moving to a VPS | The fallback host: Nginx or Caddy, PHP-FPM, MariaDB and a supervised worker |

## Incident procedure

1. **Contain.** Hide affected profiles with bulk unpublish if public content is at risk. Revoke sessions or links if access is at risk.
2. **Assess.** Use the audit log, the purge log and the email log to see what happened, who was affected and when it started.
3. **Notify.** If personal data is involved, tell the regulator and the affected people within the legal deadline.
4. **Recover.** Fix the cause, restore from backup if needed, and verify the sitemap and public pages.
5. **Record.** Add a dated entry to the incident section with what happened, what was done and what changes. Include no personal data.

## Rollback

1. Decide which layer failed: code, database, import or publication.
2. Code: redeploy the previous release. Database: restore the pre-deploy dump. Import: undo the batch. Publication: bulk unpublish by filter, then restore by batch.
3. Run the smoke checks.
4. Record what happened in the runbook.

## Monitoring

Failed-job alerts and a backup check appear in wp-admin. An uptime monitor is a later, optional addition.

## Routine checks

| When | Check |
|---|---|
| Weekly | Update review, vulnerability scan, custom plugin review |
| Monthly | Failed jobs, bounced email, new staff accounts and capability grants |
| Quarterly | Restore test, review of the processor register and the lawful basis register |
