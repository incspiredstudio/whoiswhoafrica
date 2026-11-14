# Launch checklist

The site is ready to go live only when every item below is true. These are the things that are easy to forget because they sit outside the code. Each item has an owner and the evidence that shows it is done.

## The checklist

| # | Item | Owner | Done when |
|---|---|---|---|
| 1 | Backups set up in cPanel | Administrator | Daily database and weekly file backups run, with a copy kept off the server ([Backups](/operations/backups)) |
| 2 | A restore has been tested | Administrator | A backup was restored to a copy of the site and the site worked. Repeat every quarter. |
| 3 | Host limits checked | Administrator | PHP 8.3 or newer, upload limit 64 MB, memory, cron, inodes and disk are recorded |
| 4 | Two-factor on for staff | Administrator | Every staff member has been asked to switch it on; the Setup checklist shows who has. Recommended, not enforced. |
| 5 | Security settings applied | Administrator | Setup checklist all Ready, guard installed, registration off |
| 6 | Cache plugin configured | Administrator | Public pages are cached and staff pages are not |
| 7 | Email works | Administrator | FluentSMTP connected, SPF, DKIM and DMARC set, and a test message reaches an inbox ([Email](/start/email)) |
| 8 | Weekly plugin review scheduled | Administrator | A recurring weekly review of the custom plugins is on the calendar |
| 9 | Updates routine agreed | Administrator | Weekly updates after a backup, security fixes within 48 hours ([Updates](/operations/updates)) |
| 10 | Launch safeguards pass | Reviewer and Administrator | Nothing unreviewed is public, and the sitemap lists only public profiles |
| 11 | Demo content removed | Administrator | Every demo part removed on the Setup screen; no sample profile, photograph, winner or year remains |
| 12 | Asset licences confirmed | Brand | Every photograph, video and font on the live site has a licence on file; profile photos carry an open licence and a credit ([Photos and credits](/staff/photos-and-credits)) |
| 13 | Legal pages approved | Legal | The privacy notice, terms and the complaints and takedown pages carry final wording |
| 14 | Turnstile keys set | Administrator | Every public form shows the Turnstile check and a test message arrives ([Turnstile](/start/turnstile)) |
| 15 | Contacts recorded | Administrator | The contact sheet is filled in ([Who to contact](/operations/contacts)) |

An item is done only when its evidence exists, not when someone says it is. Add an item whenever the team says a task must be done before the site counts as finished.

## Checks on the live host

Run these once, when the site is first set up on the host. Write each result down. A failure leads to a fix or a move to a small VPS.

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

## Go-live steps

The step-by-step for opening the site to the public is on [Go live](/start/going-live). If the site was built somewhere else first and must be moved to the live host, the development team does the move.

## Rollback

| Layer | How |
|---|---|
| Code | Upload the previous WHOISWHO bundle's zips ([Updates](/operations/updates#if-an-update-breaks-something)) |
| Database | Restore the backup taken before the change ([Backups](/operations/backups#restore-from-a-backup)). Database changes add before they remove, so most updates need no database rollback. |
| A bad import | Undo the batch ([Import data](/staff/importing#undo-a-batch)) |
| A bad publication | Bulk unpublish by filter, then restore by batch |
