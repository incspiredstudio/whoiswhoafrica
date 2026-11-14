<!-- verify-after-round-9 -->

# Install the site on WordPress with MySQL or MariaDB

Bundle version @VERSION@ (build @COMMIT@). You do not need to edit any file or type any command: everything is done in your host's control panel (cPanel) and in the WordPress admin. The team handbook has the same steps with more detail, under "Getting started".

Files in this folder:

| File | What it is | How it is installed |
|---|---|---|
| `whoiswho.zip` | Parent theme, with its built CSS and JavaScript inside | Appearance > Themes > Add New Theme > Upload Theme |
| `whoiswho-child.zip` | Child theme: the brand values. This is the theme to activate | Appearance > Themes > Add New Theme > Upload Theme |
| `whoiswho-core.zip` | The core plugin. It carries Action Scheduler, Two Factor and User Role Editor inside it | Plugins > Add New Plugin > Upload Plugin |
| `whoiswho-guard-mu-plugin.zip` | Safety net that keeps unreviewed records out of public pages. Recommended | Copied into `wp-content/mu-plugins/` with cPanel File Manager (step 4) |
| `wiw.php` | Optional command line runner | Only for developers; not needed |
| `SHA256SUMS.txt` | Checksums of the zips | For checking a download |

## What the host needs

- PHP 8.3 or newer (cPanel: MultiPHP Manager).
- A **MySQL or MariaDB** database whose user has all privileges, including TRIGGER (cPanel: MySQL Databases). Every cPanel host provides this.
- WordPress 6.5 or newer.

**WordPress Studio and other setups that use SQLite are not supported.** The plugin's tables use database triggers, which SQLite does not have. On such a site wp-admin shows a plain "needs MySQL or MariaDB" message and nothing is installed.

Make a backup first if the site holds anything you want to keep.

## Steps, in this order

**0. Raise the upload limit.** The child theme zip is about 4 MB and an import bundle up to 50 MB, while PHP often allows only 2 MB. In cPanel open **MultiPHP INI Editor**, choose your domain, set `upload_max_filesize` and `post_max_size` to `64M` (and `memory_limit` to `256M`), and press **Apply**. Media > Add New Media File then shows the new maximum.

1. **Upload the themes.** Appearance > Themes > Add New Theme > Upload Theme: upload `whoiswho.zip`, then `whoiswho-child.zip`. You can leave them inactive for now.
2. **Upload the plugin.** Plugins > Add New Plugin > Upload Plugin: upload `whoiswho-core.zip` and click **Activate Plugin**.
3. **Follow the Setup screen.** Right after activation WordPress opens **Setup** (also in the admin menu under WHOISWHO, and a box on the Dashboard reminds you). It creates the database tables by itself and switches on readable web addresses, then shows a checklist with a Ready, Recommended or Needs attention label for each step, what to do when it is not ready, and a button where one click can fix it:
   - **Database ready:** press *Run database setup* if it needs attention.
   - **Pretty web addresses:** press *Fix permalinks* if it needs attention.
   - **Site theme active:** press *Activate child theme*.
   - **Guard plugin installed:** see step 4.
   - **Required plugins (FluentSMTP and wpDiscuz):** open **Required plugins** and press *Install*, then *Activate*, for each.
   - **Email settings:** connect your cPanel mail account in **Settings > FluentSMTP** (Other SMTP, your mail server, port 587, TLS, the full email address as username), then send a test from its Email Test tab. A demo does not need mail.
   - **Two-factor sign-in:** recommended, not required. Each staff member opens their profile (top-right menu, then Edit Profile), finds Two-Factor Options and switches on an authenticator app, then generates recovery codes.
   - **Demo content (optional):** press *Import demo content*, tick the parts you want, and keep the page open while the bar fills. *Remove* takes a part away again; *Remove demo content* deletes everything the demo added and puts back the settings it changed.
4. **Install the guard (recommended).** WordPress cannot upload must-use plugins. In cPanel **File Manager**, open `public_html/wp-content` (create a folder `mu-plugins` there if it is missing), open `mu-plugins`, **Upload** `whoiswho-guard-mu-plugin.zip`, then right-click it and **Extract** into the same folder. You should then have `wp-content/mu-plugins/whoiswho-guard.php` and the folder `wp-content/mu-plugins/whoiswho-guard/`. Delete the zip. Until the guard is there the Setup checklist shows a "Recommended" line; the site still works.
5. **Turnstile spam check.** Create a Turnstile widget for your domain in the Cloudflare dashboard and paste its site key and secret key into the Turnstile section of the WHOISWHO Settings menu.
6. **Settings.** Settings > General: site title, time zone (Lagos for West Africa Time), date format. Staff with the awards capability open or close nominations on the Nominations screen.
7. **Optional: page cache.** The core plugin works with WP Super Cache, installed from wordpress.org. Ask your developer before switching it on.

Until the plugin is active and the child theme is switched on, wp-admin shows a red notice saying what is missing (with an Activate button when the plugin is uploaded but not active), and visitors see a short "getting this site ready" page (HTTP 503) instead of an error.

## Before you go live

Remove the demo content on the Setup screen: the site's launch checks flag anything still marked as sample content. Then untick Settings > Reading > "Discourage search engines from indexing this site".

## Update the site later

Take a backup, then upload the newer zips the same way (WordPress offers "Replace ... with uploaded") and activate again. Your content and settings are kept. Open the Setup screen afterwards and check every line. If it says a live site has database changes waiting, contact your developer.

## If something is wrong

- **A red notice about PHP, an extension or the database:** it says what to ask your host for (a newer PHP, the "mbstring" extension, or a MySQL or MariaDB database that allows triggers). Nothing is half-installed: fix it and press *Run database setup* on the Setup screen.
- **"The uploaded file exceeds the upload_max_filesize directive":** raise the limit as in step 0.
- **Holding page and a red notice:** the core plugin is missing, inactive, or did not start (it needs PHP 8.3 and WordPress 6.5). Follow the notice.
- **Pages under /directory/ or /awards/ show the home page or a 404:** press *Fix permalinks* on the Setup screen, or Settings > Permalinks > Save Changes.
- **Emails do not arrive:** send a test from Settings > FluentSMTP > Email Test and read the error; check the host, port 587, TLS and the password.
- **Demo import stops:** press *Try again*. It carries on from the step that failed and never makes anything twice.

## For developers only: the command line

Nothing below is needed. If you prefer a terminal, copy `wiw.php` next to `wp-load.php` and run, from the WordPress folder:

```
php wiw.php migrate        # create or update the tables (the Setup screen does this for a new install)
php wiw.php sample:seed    # the demo content, as the Setup screen's button does (local and staging sites only)
php wiw.php sample:remove  # remove it
```

On a live production host that already has its tables, the deploy script is the only thing that changes the database (`WIW_DEPLOY_MIGRATE=1 php wiw.php migrate`); the Setup screen refuses to.
