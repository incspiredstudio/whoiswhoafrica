# Launch checklist

The project is not done until every item below is true. These are the things that are easy to forget because they sit outside the code. Each item has an owner and the evidence that shows it is done.

| # | Item | Owner | Done when |
|---|---|---|---|
| 1 | Backups set up in cPanel | Administrator | Daily database and weekly file backups run, with a copy kept off the server |
| 2 | A restore has been tested | Administrator | A backup was restored to a copy of the site and the site worked; repeat every quarter |
| 3 | Host limits checked | Administrator | PHP version, memory, cron, inodes and disk are recorded in the runbook (Q87 is still open) |
| 4 | Two-factor on for every staff user | Administrator | No staff account can sign in without it |
| 5 | Security settings applied | Administrator | File editing off, XML-RPC off, registration off, login limits on |
| 6 | Cache plugin configured | Administrator | Public pages are cached and staff pages are not |
| 7 | Email works | Administrator | SPF, DKIM and DMARC are set; a test message from the site reaches an inbox |
| 8 | Weekly plugin review scheduled | Administrator | A recurring weekly review of the custom plugins is on the calendar (D-060) |
| 9 | Updates policy in the runbook | Administrator | Core, plugin and theme updates are tried locally first and applied weekly; security fixes within 48 hours |
| 10 | Launch safeguards pass | Reviewer and Administrator | Nothing unreviewed is public; the sitemap lists only public profiles |
| 11 | Sample content removed | Editor | No sample profile, photograph, winner or year remains on the live site |
| 12 | Asset licences confirmed | Brand | Every photograph, video and font on the live site has a licence on file |
| 13 | Legal pages approved | Legal | The privacy notice, terms and the complaints and takedown pages have no draft note |
| 14 | Go-live migration rehearsed | Administrator | The local site was moved to a staging copy on the host and checked before the switch |
| 15 | Runbook complete | Administrator | Deploy, rollback, restore and incident handling are written down |

## Rules for this list

- Add an item here whenever the team says a task "must be done before we can call it finished".
- An item is done only when its evidence exists, not when someone says it is.
- Items that need a person, not code, are still tracked as issues in the build plan.
