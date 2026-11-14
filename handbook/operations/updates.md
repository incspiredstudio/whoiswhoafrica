<!-- verify-after-round-9 -->

# Updates

WordPress, the plugins and the WHOISWHO theme and plugin all receive updates. Updates fix security holes, so apply them promptly, but always with a backup first. This page says how to update each part from wp-admin, without any code.

## The routine

| What | How often | Who |
|---|---|---|
| Security updates (WordPress or any plugin) | Within 48 hours of release | Administrator |
| Other WordPress and plugin updates | Once a week, on a quiet day | Administrator |
| A new WHOISWHO bundle | When the development team sends one | Administrator |
| A look at **WHOISWHO > Setup** after any update | Every time | Administrator |

Before every update, take a backup ([Backups](/operations/backups)). Record each update (date, what, who, any problem) in the team's change log.

## Update WordPress and the wordpress.org plugins

These are WordPress itself, FluentSMTP and wpDiscuz.

1. Take a backup.
2. Open **Dashboard > Updates**.
3. Update WordPress first if offered, then tick the plugins and press **Update Plugins**.
4. Open the site and check Home, a profile, a story and the Contact form.
5. Open **WHOISWHO > Setup** and check that nothing turned red.

Two Factor, User Role Editor and Action Scheduler live inside the WHOISWHO plugin. They are updated when a new WHOISWHO bundle is installed, not from Dashboard > Updates.

## Install a new WHOISWHO bundle

The development team sends a new bundle: the same set of zips as at install, with a higher version number. **Installing a new bundle over the old one is safe.** Your profiles, stories, settings and media are kept, and database changes only ever add, never remove.

1. Take a backup.
2. Read the notes that come with the bundle.
3. **Themes:** open **Appearance > Themes > Add New Theme > Upload Theme**, choose `whoiswho.zip` and press **Install Now**. WordPress says the theme is already installed: press **Replace installed with uploaded** (for the active child theme the button reads **Replace active with uploaded**). Do the same with `whoiswho-child.zip`.
4. **Plugin:** open **Plugins > Add New Plugin > Upload Plugin**, choose `whoiswho-core.zip`, press **Install Now**, then **Replace current with uploaded**. If WordPress offers it, press **Activate Plugin**.
5. **Guard:** only if the notes say the guard changed. In cPanel **File Manager**, open `wp-content/mu-plugins`, delete the old `whoiswho-guard.php` file and the `whoiswho-guard` folder, then upload and extract the new `whoiswho-guard-mu-plugin.zip` as at [install](/start/install#_3-copy-the-guard-into-mu-plugins).
6. Open **WHOISWHO > Setup** and check every line.
7. Check the site as above.

Upload the zips in this order: parent theme, child theme, plugin, guard. Replace; do not delete first. Deleting the WHOISWHO plugin does not delete its data, but it switches the site off until the plugin is back.

### If Setup says the database has changes waiting

On a live site, the Database ready line can say "This is a live site and its database has changes waiting." The new bundle needs a database change that, on a live site, is run by the development team rather than from a button, so an upgrade cannot stop halfway. The public site keeps working meanwhile. Contact the development team ([Who to contact](/operations/contacts)) and tell them the bundle version you installed.

## If an update breaks something

1. Note what you see (a screenshot helps) and the time.
2. For a wordpress.org plugin: open **Plugins** and deactivate the plugin you just updated. If wp-admin will not open, see [White screen](/operations/troubleshooting#white-screen-or-critical-error).
3. For a WHOISWHO bundle: upload the previous bundle's zips the same way (replace with uploaded).
4. If that does not help, restore the backup you took before the update ([Backups](/operations/backups#restore-from-a-backup)).
5. Tell the development team.

## Automatic updates

Leave automatic updates off for plugins and themes, so nothing changes without a backup. WordPress's own small security releases may update automatically; that is fine.

## Before adding any other plugin

Keep plugins to a minimum. Before adding one, ask the development team. A plugin that handles personal data must first be added to the processor register, and some kinds of plugin (sitemaps, SEO, page builders) conflict with the rules that keep unreviewed profiles off public pages.
