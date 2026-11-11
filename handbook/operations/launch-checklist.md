# Launch checklist

The site is ready to go live only when every item below is true. These are the things that are easy to forget because they sit outside the code. Each item has an owner and the evidence that shows it is done.

## The checklist

| # | Item | Owner | Done when |
|---|---|---|---|
| 1 | Backups set up in cPanel | Administrator | Daily database and weekly file backups run, with a copy kept off the server |
| 2 | A restore has been tested | Administrator | A backup was restored to a copy of the site and the site worked. Repeat every quarter. |
| 3 | Host limits checked | Administrator | PHP version, memory, cron, inodes and disk are recorded in the runbook |
| 4 | Two-factor on for every staff user | Administrator | No staff account can sign in without it |
| 5 | Security settings applied | Administrator | File editing off, XML-RPC off, registration off, login limits on |
| 6 | Cache plugin configured | Administrator | Public pages are cached and staff pages are not |
| 7 | Email works | Administrator | SPF, DKIM and DMARC are set, and a test message from the site reaches an inbox |
| 8 | Weekly plugin review scheduled | Administrator | A recurring weekly review of the custom plugins is on the calendar |
| 9 | Updates policy in the runbook | Administrator | Core, plugin and theme updates are tried locally first and applied weekly. Security fixes within 48 hours. |
| 10 | Launch safeguards pass | Reviewer and Administrator | Nothing unreviewed is public, and the sitemap lists only public profiles |
| 11 | Sample content removed | Editor | No sample profile, photograph, winner or year remains on the live site |
| 12 | Asset licences confirmed | Brand | Every photograph, video and font on the live site has a licence on file |
| 13 | Legal pages approved | Legal | The privacy notice, terms and the complaints and takedown pages carry final wording |
| 14 | Go-live migration rehearsed | Administrator | The local site was moved to a staging copy on the host and checked before the switch |
| 15 | Runbook complete | Administrator | Deploy, rollback, restore and incident handling are written down |

An item is done only when its evidence exists, not when someone says it is. Add an item whenever the team says a task must be done before the site counts as finished.

## Checks on the live host

Run these once, when the site is first set up on the host. Write each result into the runbook. A failure leads to a fix or a move to a small VPS.

| Check | Why |
|---|---|
| PHP version, memory and time limits | Imports and the plugin need them |
| A database and database user | Required |
| SMTP sends, plus SPF, DKIM and DMARC | Deliverability |
| The uploads folder is writable | Media |
| Cron or WP-Cron runs jobs | Purge, email, imports, reminders |
| A 1,000-record import in chunks | Throughput on shared hosting |
| The "gone" response, and purge by address if a CDN is added | Immediate unpublish |
| TRIGGER privilege for audit-log protection | Audit-log immutability |
| A restore test | Backups work |

## Go-live migration

The finished local site is moved to the live host after the owner approves.

1. Build a release containing WordPress core (pinned), the plugins and both themes from the lockfile, the production autoloader, built assets and a version stamp.
2. Copy the code to the host by SSH, rsync or the cPanel file manager.
3. Import the database and the uploads.
4. Set `wp-config.php` and the secrets file outside the web root.
5. Run the plugin's forward-only migrations through the deploy script.
6. Check that the child theme is active and the parent theme is present.
7. Run the smoke checks, including the launch safeguards in item 10.
8. Switch the domain.

Production runs migrations only through the deploy script, never by hand.

## Rollback

| Layer | How |
|---|---|
| Code | Redeploy the previous release |
| Database | Restore the pre-deploy dump. Migrations add before they remove, so most releases need no database rollback. |
| A bad import | Undo the batch |
| A bad publication | Bulk unpublish by filter, then restore by batch |
