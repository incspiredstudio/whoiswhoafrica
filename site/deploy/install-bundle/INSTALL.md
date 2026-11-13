# Install the site on a plain WordPress or WordPress Studio

Bundle version @VERSION@ (build @COMMIT@). You do not need to edit any file or type any command: everything is done from the WordPress admin.

Files in this folder:

| File | What it is | How it is installed |
|---|---|---|
| `whoiswho.zip` | Parent theme, with its built CSS and JavaScript inside | Appearance > Themes > Add New > Upload Theme |
| `whoiswho-child.zip` | Child theme: the brand values. This is the theme to activate | Appearance > Themes > Add New > Upload Theme |
| `whoiswho-core.zip` | The core plugin. It carries Action Scheduler, Two Factor and User Role Editor inside it, so nothing else is downloaded | Plugins > Add New > Upload Plugin |
| `whoiswho-guard-mu-plugin.zip` | Safety net that keeps unreviewed records out of public pages. Recommended, not required | Unzip into `wp-content/mu-plugins/` (WordPress cannot upload this kind of plugin; see step 4) |
| `wiw.php` | Optional command line runner | Only for people who use a terminal; copy next to `wp-load.php` |
| `SHA256SUMS.txt` | Checksums of the zips | `sha256sum -c SHA256SUMS.txt` |

You need WordPress 6.5 or newer, PHP 8.3 or newer, and a MySQL or MariaDB database (the plugin's tables use database triggers, so a site on SQLite, which is WordPress Studio's default, cannot host it: create the site with a MySQL or MariaDB database). Make a backup first if the site holds anything you want to keep.

## Steps, in this order

1. **Upload the themes.** Appearance > Themes > Add New > Upload Theme: upload `whoiswho.zip`, then `whoiswho-child.zip`. You can leave them inactive for now.
2. **Upload the plugin.** Plugins > Add New > Upload Plugin: upload `whoiswho-core.zip` and click **Activate**.
3. **Follow the Setup screen.** Right after activation WordPress opens **Setup** (it is also in the admin menu, and a box on the Dashboard reminds you). It creates the database tables by itself and switches on readable web addresses (permalinks), then shows a checklist with a ready, recommended or needs-attention label for each step, what to do when it is not ready, and a button where one click can fix it:
   1. **Database ready:** press *Run database setup* if it needs attention (this works on any site that has no tables yet, including a live host; a live site that already has its tables is only ever changed by the deploy script).
   2. **Pretty web addresses:** press *Fix permalinks* if it needs attention.
   3. **Site theme active:** press *Activate child theme*.
   4. **Guard plugin installed:** see step 4 below.
   5. **Two-factor sign-in set up for you:** open your profile (top-right menu, then Profile) and switch on an authenticator app or the email code.
   6. **Email settings:** read-only. Passwords are never shown in the admin. A demo does not need mail.
   7. **Demo content:** press *Import demo content* to fill every page with invented profiles, stories, events and awards. A progress bar shows it working (about a minute); keep the page open, and if it stops press *Try again*. *Remove demo content* deletes only what the demo added and puts back the settings it changed.
4. **Install the guard (recommended).** WordPress cannot upload must-use plugins. Unzip `whoiswho-guard-mu-plugin.zip` so that you have `wp-content/mu-plugins/whoiswho-guard.php` and the folder `wp-content/mu-plugins/whoiswho-guard/` (use your host's File Manager or an FTP program; create `mu-plugins` if it does not exist; in WordPress Studio, "Open site folder" shows `wp-content`). Until it is there the Setup checklist shows a "Recommended" line and the theme's own notice appears in wp-admin; the site still works.
5. **Settings.** Settings > General: site title, time zone `Lagos` (WAT), date format. Staff with the awards capability open or close nominations on the Nominations screen. Email goes through the host's SMTP settings in the site's secrets file (see `docs/runbook.md` in the repository).
6. **Optional: page cache.** The core plugin loads WP Super Cache only when `WP_CACHE` is true in `wp-config.php`. If you want it, install WP Super Cache from wordpress.org (folder `wp-content/plugins/wp-super-cache`) first. It is not in the bundle because it does not work from inside another plugin's folder.

Until the plugin is active and the child theme is switched on, wp-admin shows a red notice saying what is missing (with an Activate button when the plugin is uploaded but not active), and visitors see a short "getting this site ready" page (HTTP 503) instead of an error.

## Before you go live

Remove the demo content on the Setup screen (*Remove demo content*): the site's launch checks flag anything still marked as sample content.

## Optional: the command line

Nothing below is needed. If you prefer a terminal, copy `wiw.php` next to `wp-load.php` and run, from the WordPress folder:

```
php wiw.php migrate        # create or update the tables (the Setup screen does this for a new install)
php wiw.php sample:seed    # the demo content, as the Setup screen's button does (local and staging sites only)
php wiw.php sample:remove  # remove it
```

On a live production host that already has its tables, the deploy script is the only thing that changes the database (`WIW_DEPLOY_MIGRATE=1 php wiw.php migrate`); the Setup screen refuses to.

## If something is wrong

- **A red notice about PHP, an extension or the database:** it says what to ask your host for (a newer PHP, the "mbstring" extension, or a MySQL or MariaDB database that allows triggers). Nothing is half-installed: fix it and press *Run database setup* on the Setup screen.
- **Holding page and a red notice:** the core plugin is missing, inactive, or did not start (it needs PHP 8.3 and WordPress 6.5). Follow the notice.
- **Pages under /directory/ or /awards/ show the home page or a 404:** press *Fix permalinks* on the Setup screen, or Settings > Permalinks > Save.
- **Demo import stops:** press *Try again*. It carries on from the step that failed and never makes anything twice.
- **Update the site later:** upload the newer zips (WordPress offers "Replace current with uploaded") and activate again; the database changes are forward-only and safe to repeat.
