# Launch checklist

The project is not done until every item below is true. These are the things that are easy to forget because they sit outside the code. Each item has an owner and the evidence that shows it is done.

| # | Item | Owner | Done when |
|---|---|---|---|
| 1 | Backups set up in cPanel (**deferred by owner decision**) | Administrator | Daily database and weekly file backups run, with a copy kept off the server |
| 2 | A restore has been tested (**deferred by owner decision**) | Administrator | A backup was restored to a copy of the site and the site worked; repeat every quarter |
| 3 | Host limits checked | Administrator | PHP version, memory, cron, inodes and disk are recorded in the runbook (Q87 is still open) |
| 4 | Two-factor on for every staff user | Administrator | No staff account can sign in without it |
| 5 | Security settings applied | Administrator | File editing off, XML-RPC off, registration off, login limits on |
| 6 | Cache plugin configured (WP Super Cache 3.1.5, off by default until issue #719 is settled) | Administrator | If switched on, public pages are cached, staff pages are not, and the visibility matrix passes |
| 7 | Email works through a free SMTP service the owner sets up | Administrator | Credentials are only in the secrets file; SPF, DKIM and DMARC are set; a test message reaches an inbox |
| 8 | Weekly plugin review scheduled | Administrator | A recurring weekly review of the custom plugins is on the calendar (D-060) |
| 9 | Updates policy in the runbook | Administrator | Core, plugin and theme updates are tried locally first and applied weekly; security fixes within 48 hours |
| 10 | Launch safeguards pass | Reviewer and Administrator | Nothing unreviewed is public; the sitemap lists only public profiles |
| 11 | Sample content removed | Editor | `php bin/wiw.php content:find-sample` lists nothing and exits zero; see Sample content below |
| 12 | Asset licences confirmed | Brand | Every photograph, video and font on the live site has a licence on file |
| 13 | Legal pages approved (**deferred by owner decision**) | Legal | The privacy notice, terms and the complaints and takedown pages have no draft note |
| 14 | Go-live migration rehearsed | Administrator | The local site was moved to a staging copy on the host and checked before the switch |
| 15 | Runbook complete | Administrator | Deploy, rollback, restore and incident handling are written down |

## Sample content

All seeded demonstration content carries the marker `_wiw_sample` = 1 (post and term meta, with a stable `_wiw_sample_key`, and a registry for award categories, nominations and the settings the seeder changed). Run from `site/`:

- `php bin/wiw.php sample:seed` creates it (a second run finds what the first made).
- `php bin/wiw.php sample:remove` removes what the seeder made.
- `php bin/wiw.php content:find-sample` lists anything marked sample, any title starting "[Sample]" and any page still carrying the legal draft note; it exits non-zero while any remains, so the migration smoke checks can prove none is left.
- `php bin/wiw.php pages:seed` creates the starter pages (About, FAQ and the others).

**Removing sample content is part of go-live** (item 11). Do not seed sample content on the live site.

## Owner decisions that change this list

- Legal pages, backups and restores are deferred by owner decision. They stay on the list because the project is not finished without them, but they do not block the build.
- Email goes through a free SMTP service the owner sets up. Credentials go only in the secrets file, never in code or docs.

## Rules for this list

- Add an item here whenever the team says a task "must be done before we can call it finished".
- An item is done only when its evidence exists, not when someone says it is.
- Items that need a person, not code, are still tracked as issues in the build plan.
