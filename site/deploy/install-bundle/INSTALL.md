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
3. **Follow the Setup screen.** Right after activation WordPress opens **Setup** (it is also the first item of the plugin's own admin menu, and a box on the Dashboard reminds you). It creates the database tables by itself and switches on readable web addresses (permalinks), then shows a checklist with a Ready, Recommended or Needs attention label for each step, what to do when it is not ready, and a button where one click can fix it:
   1. **Database ready:** press *Run database setup* if it needs attention. After a plugin update it may instead say that database changes are waiting: make a backup in your host's control panel, tick *I have a backup*, and press *Update the database*.
   2. **Pretty web addresses:** press *Fix permalinks* if it needs attention.
   3. **Site theme active:** press *Activate child theme*.
   4. **Guard plugin installed (recommended):** see step 4 below.
   5. **Two-factor sign-in for staff (recommended):** optional. The line lists who has it on. Each staff member opens their profile (top-right menu, then Edit Profile), finds Two-Factor Options, switches on an authenticator app or the email code, and keeps the recovery codes.
   6. **Email:** turns green when FluentSMTP is installed and connected (see the next two lines). Connect your cPanel mail account in **Settings > FluentSMTP** (Other SMTP, your mail server, port 587, TLS, the full email address as user name), then send a test from its Email Test tab. A demo does not need email.
   7. **Spam protection for forms (optional):** the public forms already have a built-in spam check. For stronger protection, create a free Turnstile widget for your domain in the Cloudflare dashboard and paste its site key and secret key into the Turnstile form under this line on the Setup screen, then press *Save Turnstile keys*. The secret key is never shown again; the screen only says it is saved.
   8. and 9. **FluentSMTP plugin active** and **wpDiscuz plugin active:** press *Open Required plugins* (or open **Site settings > Required plugins**), then *Install* and *Activate* for each. It needs no file or command. If your host asks for FTP details, the screen links to WordPress's own Add Plugins screen instead.
   10. **Demo content (optional):** tick the parts you want (Profiles, Stories, Comments, Events, Awards, Home page curation, Pages and Settings) and press *Import demo content*. A part that needs another ticks it for you and says why (Awards need Profiles; Comments need Stories). Keep the page open while the bar fills (about a minute); if it stops, press *Try again*. When the plugin zip includes the sample profiles, the Profiles part imports them like any import and publishes them as reviewed by you; otherwise it adds invented profiles. Awards, citations and the home page picks are always invented and never name a sample profile. Each part shows how many items it added and has its own *Remove* button; *Remove demo content* removes every part. Removing deletes only what the demo added and puts back the settings it changed. Anything a person changed after the import is kept and listed, and counts as your own content from then on.
4. **Install the guard (recommended).** WordPress cannot upload must-use plugins. In cPanel **File Manager**, open `public_html/wp-content` (create a folder `mu-plugins` there if it is missing), open `mu-plugins`, **Upload** `whoiswho-guard-mu-plugin.zip`, then right-click it and **Extract** into the same folder. You should then have `wp-content/mu-plugins/whoiswho-guard.php` and the folder `wp-content/mu-plugins/whoiswho-guard/`. Delete the zip. Until the guard is there the Setup checklist shows a "Recommended" line; the site still works.
5. **Settings.** Settings > General: site title, time zone (Lagos for West Africa Time), date format. Staff with the awards capability open or close nominations on the Nominations screen (Awards menu).
6. **Optional: page cache.** The core plugin works with WP Super Cache, installed from wordpress.org (Plugins > Add New Plugin). It is not in the bundle because it does not work from inside another plugin's folder. Ask your developer before switching it on.

Until the plugin is active and the child theme is switched on, wp-admin shows a red notice saying what is missing (with an Activate button when the plugin is uploaded but not active), and visitors see a short "getting this site ready" page (HTTP 503) instead of an error.

## Before you go live

Remove the demo content on the Setup screen (*Remove demo content*, or each part's *Remove* button): the site's launch checks flag anything still marked as sample content. Then untick Settings > Reading > "Discourage search engines from indexing this site".

## Update the site later

Take a backup, then upload the newer zips the same way (WordPress offers "Replace ... with uploaded") and activate again. Your content and settings are kept. Open the Setup screen afterwards and check every line. If it says database changes are waiting, make a backup in your host's control panel, tick *I have a backup*, and press *Update the database*. It applies the changes once and is safe to press again.

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

On a live site the Administrator can also apply waiting database changes from the Setup screen's *Update the database* button, after a backup. A deploy script runs `WIW_DEPLOY_MIGRATE=1 php wiw.php migrate`.
