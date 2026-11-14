# Troubleshooting

Find the problem you see, then follow the steps. If nothing here helps, collect what you saw (a screenshot, the time, the page address) and contact the development team ([Who to contact](/operations/contacts)).

## Email not sending

Symptoms: no confirmation emails arrive, the FluentSMTP test fails, or **Audience > Email log** shows failures.

1. Open **Settings > FluentSMTP** and send a test from the **Email Test** tab. Read the error message.
2. Check the connection settings against [Email](/start/email#_3-connect-the-account-in-fluentsmtp): host `mail.` plus your domain, port `587`, encryption **TLS**, authentication on, username is the full email address.
3. "Authentication failed" or "Could not authenticate": the password is wrong or was changed. In cPanel **Email Accounts**, press **Manage** next to the account, set a new password, save it in the password manager, and paste it into FluentSMTP.
4. "Could not connect" or a timeout: try port `465` with encryption **SSL**. If neither works, ask your host whether outgoing SMTP is allowed and which server name to use.
5. The test arrives but in spam: run cPanel **Email Deliverability** and press **Repair** for SPF and DKIM; check DMARC exists ([Email](/start/email#_2-check-the-domain-s-email-records)).
6. FluentSMTP is not active: open **Site settings > Required plugins** and press **Activate FluentSMTP** (or **Install FluentSMTP** first if it is not installed).

## Upload too large

Symptoms: "The uploaded file exceeds the upload_max_filesize directive" or "The link you followed has expired" when uploading a theme or plugin, or an import bundle that is refused as too big.

1. Raise the limit in cPanel **MultiPHP INI Editor**: `upload_max_filesize` and `post_max_size` to `64M`. See [Raise the upload limit](/start/install#raise-the-upload-limit).
2. Check in **Media > Add New Media File**: it shows the maximum upload size.
3. Import bundles are not affected by this limit: the Import screen sends them in small pieces. A bundle cannot be bigger than 512 MB; ask the acquisition team to split a bigger one. If a bundle upload still fails, use **Or import from the server** on the Import screen ([Import data](/staff/importing)).
4. For a theme or plugin, you can also unzip it on your computer and upload the folder with cPanel **File Manager** into `wp-content/themes` or `wp-content/plugins`.

## White screen or "critical error"

Symptoms: a blank page, or "There has been a critical error on this website".

1. Check your email: WordPress sends the site administrator a message with a special **recovery mode** link. Open it, sign in, and WordPress shows which plugin or theme failed. Deactivate that plugin from there.
2. If only one page is blank, note its address and try again in a private window.
3. If the whole admin is blank and there is no email: in cPanel **File Manager**, open `wp-content/plugins` and rename the folder of the plugin you last installed or updated (for example add `-off` to the end). That switches it off. Reload the site.
4. If the problem started after a WHOISWHO bundle update, upload the previous bundle's zips ([Updates](/operations/updates#if-an-update-breaks-something)).
5. If nothing helps, restore the last backup ([Backups](/operations/backups#restore-from-a-backup)) and contact the development team.

Visitors who see a short "getting this site ready" page instead of the site are seeing the WHOISWHO holding page: the plugin is inactive or the child theme is not active. Sign in to wp-admin and follow the red notice.

## Setup checklist red items

| Line in WHOISWHO > Setup | What it means | What to do |
|---|---|---|
| Database ready: "The plugin's tables have not been created yet." | The first setup did not finish | Press **Run database setup** |
| Database ready: "The database did not allow the plugin to create the tables it needs." | The database user lacks a permission | In cPanel **MySQL Databases**, under "Add User to Database", choose the site's user and database, press **Add**, tick **ALL PRIVILEGES** and press **Make Changes**. Then press **Run database setup**. If that is not possible on your plan, ask the host: the database user needs permission to create tables and triggers. |
| Database ready: "A plugin update brought database changes that are not applied yet." | A new bundle needs a database change | Take a backup, tick **I have a backup** and press **Update the database**. See [Updates](/operations/updates#if-setup-says-the-database-has-changes-waiting). |
| Database ready: a message about PHP | PHP is too old or missing an extension | cPanel **MultiPHP Manager**: choose PHP 8.3 or newer. For a missing extension (for example "mbstring"), cPanel **Select PHP Version** > Extensions, or ask the host. |
| Pretty web addresses | Addresses look like `?p=123` and directory pages do not open | Press **Fix permalinks**, or open **Settings > Permalinks**, choose **Post name** and save |
| Site theme active | The child theme is not active | Press **Activate child theme**. If the themes are missing, upload them ([Install](/start/install)). |
| Guard plugin installed | The guard is not in `mu-plugins` | Copy it with File Manager ([Install](/start/install#_3-copy-the-guard-into-mu-plugins)). Check the file is directly in `mu-plugins`, not in an extra folder. |
| FluentSMTP plugin active, wpDiscuz plugin active | A required plugin is missing or inactive | **Open Required plugins**, then **Install** and **Activate** |
| Email (marked Recommended, not red) | No working email connection | Set up FluentSMTP ([Email](/start/email)) |

## "Security configuration problem: DISALLOW_FILE_MODS is not true"

The guard shows this red notice to Administrators when wp-admin is still allowed to install and change plugin and theme files. A no-code install needs that, to upload the zips and install FluentSMTP and wpDiscuz, so on a site set up from this handbook the notice is expected. It does not stop the site working. Ask the development team whether to lock file changes after launch; if they do, updates are then installed by them.

## "Needs MySQL or MariaDB"

Symptoms: a red notice saying the site uses a SQLite database and the plugin needs MySQL or MariaDB.

This happens on WordPress Studio and other setups that use SQLite instead of a real database server. WHOISWHO cannot run there, and nothing was installed.

1. Install WordPress on a cPanel host (or another host) that gives you a MySQL or MariaDB database. Every cPanel host does. See [Install](/start/install).
2. Install the WHOISWHO bundle there.

There is no setting that changes this on a SQLite site.

## Pages under /directory/ or /awards/ show the home page or "not found"

Open **Settings > Permalinks** and press **Save Changes** without changing anything. Or press **Fix permalinks** on the Setup screen.

## An import stops part way

Open **Imports > Import**, open the batch from **History** and press **Continue**. It continues where it stopped and never imports a record twice. It also carries on in the background; keeping the tab open makes it faster. See [Import data](/staff/importing).

## The demo import stops

Press **Try again** on the Setup screen. It continues from the step that failed and never adds anything twice.

## Someone is locked out by two-factor

An Administrator turns off two-factor for that person on their user profile. See [Lost phone and no recovery codes](/staff/users-and-two-factor#lost-phone-and-no-recovery-codes).

## Forms say the spam check failed

The Turnstile keys are wrong or the domain does not match. See [Turnstile](/start/turnstile#if-the-keys-are-wrong).
