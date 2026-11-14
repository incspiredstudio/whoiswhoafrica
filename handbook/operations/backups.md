# Backups

Backups let the site recover from a bad change, a hacked account or a host failure. This page says what to back up, how to do it in cPanel, and how to prove a restore works. The recovery targets are to lose at most 24 hours of data and to be back within 8 hours.

## What is backed up

| Item | Frequency | Where |
|---|---|---|
| Database | Daily | The host's automatic backups, plus a downloaded copy kept off the server |
| Files (uploads and private files) | Weekly | The host's automatic backups, plus a downloaded copy |
| A manual backup | Before every import, bundle update, bulk unpublish or other risky change | Downloaded from cPanel |

Most cPanel hosts take automatic backups (look for **JetBackup** or **Backup** in cPanel). Check with your host how many days they keep, and do not rely on them alone: keep your own downloaded copy as well.

## The backup reminder

Until a backup is recorded, Administrators see a reminder on the **Dashboard** and on **WHOISWHO > Setup** (not on other screens): "No backup has been recorded for this site yet. Turn on backups in cPanel (Backup or JetBackup), then click "I have set up backups"."

1. Turn on the host's automatic backups in cPanel (**Backup** or **JetBackup**), and check how many days they keep.
2. Press **I have set up backups**. The reminder goes away for everyone, and the confirmation is written to the audit log (`backup.host_confirmed`).

**Remind me in 30 days** hides it for you only. The site cannot see cPanel's backups itself, so the button is your confirmation that they are on.

## Take a backup before risky work

The quickest backup is the database, which holds every profile, story and setting.

1. In cPanel, open **Backup** (in the Files section).
2. Under **Download a MySQL Database Backup**, click the name of the site's database. A `.sql.gz` file downloads.
3. Save it in the team's backup folder with the date and what you are about to do in the name, for example `2026-11-20-before-import.sql.gz`.

For a full backup (database and all files), also under **Backup**:

1. Press **Download a Full Account Backup**.
2. Backup Destination: **Home Directory**. Enter your email to be told when it is ready, and press **Generate Backup**.
3. When the email arrives, return to **Backup**, click the new file under "Backups Available for Download" and save it off the server.
4. Delete the backup file from the home directory afterwards, so it does not fill the disk.

If your host has **JetBackup** instead, use its **Full Backup** or **Database Backups** pages the same way.

## Restore from a backup

Restoring overwrites the current site with the backup, so anything done since is lost. Ask the development team first if you are unsure.

**Database only (most cases):**

1. In cPanel, open **Backup**.
2. Under **Restore a MySQL Database Backup**, choose the `.sql.gz` file and press **Upload**.
3. Open the site and wp-admin and check.

**With JetBackup:** open **JetBackup > Database Backups** (or **Full Backups**), pick the date and press **Restore**.

A full account restore is done by the host: open a ticket with them and name the backup date.

## Test a restore

A backup that has not been restored is not a backup.

1. Choose a recent database backup and the matching files.
2. Restore them to a copy of the site (a staging subdomain), not the live one. The development team can set the copy up.
3. Sign in, open a profile, a story and the Nominations screen, and check the sitemap.
4. Write the date, the time it took and any problem in the team's log.
5. Repeat every quarter, and once before launch.

## Keep backups safe

Backups hold personal data: names, emails and messages from forms.

- Keep downloaded backups in one agreed place with restricted access, encrypted where possible.
- Do not email backups or put them on personal drives.
- Delete copies older than the retention the team agreed.

## Audit log protection

The audit log is append-only with a hash chain, so any alteration is detectable. The off-server backups mean tampering shows up even if the server itself is compromised.
