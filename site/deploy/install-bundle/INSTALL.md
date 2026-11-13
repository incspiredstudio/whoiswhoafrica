# Install the site on a plain WordPress or WordPress Studio

Bundle version @VERSION@ (build @COMMIT@). Files in this folder:

| File | What it is | How it is installed |
|---|---|---|
| `whoiswho.zip` | Parent theme, with its built CSS and JavaScript inside | Appearance > Themes > Add New > Upload Theme |
| `whoiswho-child.zip` | Child theme: the brand values. This is the theme to activate | Appearance > Themes > Add New > Upload Theme |
| `whoiswho-core.zip` | The core plugin. It carries Action Scheduler, Two Factor and User Role Editor inside it, so nothing else is downloaded | Plugins > Add New > Upload Plugin |
| `whoiswho-guard-mu-plugin.zip` | Must-use plugin that keeps unreviewed records out of public pages | Unzip into `wp-content/mu-plugins/` (must-use plugins cannot be uploaded in wp-admin) |
| `wiw.php` | Command line runner | Copy next to `wp-load.php` in the WordPress folder |
| `SHA256SUMS.txt` | Checksums of the zips | `sha256sum -c SHA256SUMS.txt` |

You need WordPress 6.5 or newer, PHP 8.3 or newer, MariaDB or MySQL, and a new WordPress site. The plugin's tables use MariaDB/MySQL features (triggers), so a WordPress Studio site that runs on its default SQLite database cannot host it: use a MySQL or MariaDB database (not verified on Studio itself). Make a backup first if the site holds anything you want to keep.

## Steps, in this order

1. **Set the environment type.** Open `wp-config.php` and add, above the line "That's all, stop editing":
   `define( 'WP_ENVIRONMENT_TYPE', 'local' );` (use `'staging'` on a staging site).
   WordPress Studio and wp-env usually set this already. Without it WordPress treats the site as production, and production never creates the plugin's tables by itself and refuses `sample:seed`.
2. **Upload the themes.** Upload `whoiswho.zip`, then `whoiswho-child.zip`. Activate **WHOISWHO Child** (the parent theme is never activated on its own).
3. **Install the guard.** Unzip `whoiswho-guard-mu-plugin.zip` into `wp-content/mu-plugins/` so you have `wp-content/mu-plugins/whoiswho-guard.php` and the folder `wp-content/mu-plugins/whoiswho-guard/`. Create `mu-plugins` if it does not exist. In WordPress Studio, "Open site folder" shows `wp-content`.
4. **Upload and activate the core plugin.** Upload `whoiswho-core.zip`, then click Activate. On activation the plugin gives the site pretty permalinks (`/%postname%/`) if it still has the plain ones, creates its database tables (non-production sites only), and creates its roles.
5. **Check.** Until steps 3 and 4 are both done, wp-admin shows a red notice on the Dashboard, Plugins and Themes screens saying what is missing (with an Activate button when the plugin is uploaded but not active), and the public site shows a short "getting this site ready" page (HTTP 503) instead of an error. When both are in place the notices disappear and the site is live.
6. **Sign in.** Two-factor sign-in is required: the first sign-in sends each user to set up an authenticator app or an email code.
7. **Settings.** Settings > General: site title, time zone `Lagos` (WAT), date format. Settings > Permalinks: leave "Post name". Staff with the awards capability open or close nominations on the Nominations screen of the admin menu. Email goes through the host's SMTP settings in the site's secrets file (see `docs/runbook.md` in the repository); a demo site does not need mail.
8. **Optional: demo content.** From the WordPress folder run:
   ```
   php wiw.php migrate
   php wiw.php sample:seed
   ```
   `migrate` normally prints "Nothing to apply" because step 4 already ran it (run it if the notice "database changes waiting" shows). `sample:seed` creates fictional profiles, stories, events and award categories so every public page can be seen, and takes about a minute. It refuses on a production-type site and exits 1. Remove the demo content with `php wiw.php sample:remove`.
9. **Optional: page cache.** The core plugin loads WP Super Cache only when `WP_CACHE` is true in `wp-config.php`. If you want it, install WP Super Cache from wordpress.org (folder `wp-content/plugins/wp-super-cache`) first. It is not in the bundle because it does not work from inside another plugin's folder.

## First install on a live production host

Production is deliberately never migrated from the browser. After step 4, run once from the WordPress folder:
`WIW_DEPLOY_MIGRATE=1 php wiw.php migrate`
Do not run `sample:seed` there.

## If something is wrong

- **Holding page and a red notice:** the core plugin is missing, inactive, or did not start (it needs PHP 8.3 and WordPress 6.5). Follow the notice.
- **Notice "database changes waiting":** deactivate and activate the core plugin again, or run `php wiw.php migrate`.
- **Pages under /directory/ or /awards/ show the home page or a 404:** Settings > Permalinks > Save.
- **Update the site later:** upload the newer zips (WordPress offers "Replace current with uploaded") and activate again; migrations are forward-only and safe to repeat.
