# Updates

WordPress core, plugins and themes change often, and an unreviewed change on the live site is a risk. This page sets the rules for applying updates and for reviewing the custom plugins.

## Rules

1. Updates are tried locally first, then released to the live site through a release. Do not update plugins from wp-admin.
2. File modification and automatic plugin updates are switched off on the live site.
3. Plugins and themes are pinned by a lockfile, and third-party plugins are kept to a minimum.
4. Core, plugin and theme updates are applied weekly. Security fixes are applied within 48 hours.
5. A weekly vulnerability scan and a weekly review of the custom plugins are on the calendar.

## Apply an update

1. Update the dependency locally.
2. Run the tests, the coding-standard check, the static analysis, the asset build, the browser and accessibility tests and the dependency audit.
3. Check the visibility matrix again if a plugin was added or changed. It confirms that no unreviewed profile appears on any page, feed, search, sitemap or preview.
4. Take a database backup.
5. Release to the live site and run the smoke checks.
6. Record the update in the runbook's change log.

## Database changes

Migrations only move forward and add before they remove, so the previous release still runs on the new database. Production runs them only through the deploy script, with a pre-migration dump taken automatically. Only the plugin changes the database structure.

## Plugins in use

| Purpose | Plugin |
|---|---|
| Staff two-factor | A maintained plugin, enforced for every staff role |
| Background jobs | Action Scheduler, used as a library inside the core plugin |
| Story comments | wpDiscuz |
| Per-user capability screen | A maintained role editor plugin |
| Page editor | The native block editor with theme patterns |
| Caching | A cache plugin |

Before adding any plugin, check that it handles no personal data, or add it to the processor register.

## Optional additions

A CDN and firewall in front of the host, an uptime monitor, error reporting and a mail provider can be added later. Each one that touches personal data must be added to the processor register first.
