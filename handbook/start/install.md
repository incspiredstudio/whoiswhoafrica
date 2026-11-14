# Install the site

This page installs WordPress and WHOISWHO on a cPanel host. Follow the sections in order. You need your cPanel login and the install bundle (the folder of zips the development team sends you).

## What the host needs

| Need | Why | How to check in cPanel |
|---|---|---|
| PHP 8.3 or newer | The WHOISWHO plugin will not start on an older PHP | Software > **MultiPHP Manager**: the PHP version next to your domain |
| A MySQL or MariaDB database | The plugin keeps its records in database tables protected by triggers | Databases > **MySQL Databases** or **Manage My Databases** |
| A database user with all privileges, including TRIGGER | Without TRIGGER the plugin cannot create its protected tables | Databases > MySQL Databases > Privileged users: the user should show **ALL PRIVILEGES** |
| WordPress 6.5 or newer | The plugin needs it | wp-admin > Dashboard > Updates |
| An SSL certificate (https) | Sign-in and forms must be encrypted | Security > **SSL/TLS Status** |
| Email accounts on the domain | The site sends confirmation and invitation emails | Email > **Email Accounts** |

::: warning Not supported
**WordPress Studio and any other setup that uses SQLite are not supported.** WHOISWHO needs MySQL or MariaDB, which every cPanel host provides. If you try it on a SQLite site, wp-admin shows the message "needs MySQL or MariaDB" and nothing is installed. See [Troubleshooting](/operations/troubleshooting#needs-mysql-or-mariadb).
:::

### Choose PHP 8.3

1. In cPanel, open **MultiPHP Manager** (in the Software section).
2. Tick your domain.
3. In the PHP Version list on the right, choose **PHP 8.3** (or a newer one if offered).
4. Press **Apply**.

### Install WordPress

Most cPanel hosts have a one-click WordPress installer, called **WordPress Manager**, **Softaculous** or **WP Toolkit**. It creates the MySQL or MariaDB database and its user for you.

1. In cPanel, open the WordPress installer.
2. Choose your domain, with `https://`, and leave the folder (directory) empty so the site is at the domain itself.
3. Enter the site name (WHOISWHO), an administrator username that is not "admin", a strong password and your work email.
4. Install. Note the wp-admin address it gives you.

If you install WordPress by hand instead, create the database and the user in **MySQL Databases**, and when you add the user to the database tick **ALL PRIVILEGES**.

## Raise the upload limit

PHP on many hosts only accepts uploads up to 2 MB. The child theme zip is about 4 MB, and an import bundle can be up to 50 MB, so raise the limit before uploading anything.

1. In cPanel, open **MultiPHP INI Editor** (in the Software section).
2. Stay on the **Basic Mode** tab and choose your domain in the list.
3. Set these values:

| Setting | Value |
|---|---|
| `upload_max_filesize` | `64M` |
| `post_max_size` | `64M` |
| `memory_limit` | `256M` |
| `max_execution_time` | `120` |

4. Press **Apply** at the bottom.
5. Check it worked: in wp-admin open **Media > Add Media File**. The Upload Media screen says "Maximum upload file size: 64 MB".

If your host does not offer MultiPHP INI Editor, ask their support to raise `upload_max_filesize` and `post_max_size` to 64M. As a fallback you can unzip a theme or plugin on your computer and upload the folder with cPanel **File Manager** into `wp-content/themes` or `wp-content/plugins`.

## Install the themes and the plugin

Do these in wp-admin, in this order.

### 1. Upload the two themes

1. Open **Appearance > Themes** and press **Add New Theme**, then **Upload Theme**.
2. Choose `whoiswho.zip` (the parent theme) and press **Install Now**. Do not activate it.
3. Go back and do the same with `whoiswho-child.zip` (the child theme). You can activate it now, or let the Setup screen do it for you later.

The child theme is always the active one. Never activate the parent on its own.

### 2. Upload and activate the plugin

1. Open **Plugins > Add New Plugin** and press **Upload Plugin**.
2. Choose `whoiswho-core.zip` and press **Install Now**.
3. Press **Activate Plugin**.

WordPress then opens the **Setup** screen. It creates the database tables and switches on readable web addresses by itself. Leave it open; you come back to it after the next step.

### 3. Copy the guard into mu-plugins

The guard is a must-use plugin: a safety net that keeps unreviewed records off public pages whatever else is installed. WordPress cannot upload must-use plugins from wp-admin, so you copy it with cPanel **File Manager**.

1. In cPanel, open **File Manager**.
2. Open the folder of your site (usually `public_html`), then `wp-content`.
3. If there is no folder called `mu-plugins`, press **+ Folder**, name it `mu-plugins` and press **Create New Folder**.
4. Open `mu-plugins`.
5. Press **Upload** and choose `whoiswho-guard-mu-plugin.zip` from the bundle. Wait for the bar to reach 100%, then go back to File Manager.
6. Right-click the zip in `mu-plugins` and choose **Extract**. Extract it into the same folder (`.../wp-content/mu-plugins`).
7. Check what you see in `mu-plugins`: a file called `whoiswho-guard.php` and a folder called `whoiswho-guard`. If they are inside an extra folder, move them up one level.
8. Delete the zip from `mu-plugins`.
9. In wp-admin, open **Plugins** and choose the **Must-Use** filter at the top. The guard is listed.

### 4. Basic WordPress settings

1. **Settings > General**: site title, the time zone (Lagos for West Africa Time) and the date format.
2. **Settings > Reading**: leave "Discourage search engines from indexing this site" ticked until you [go live](/start/going-live).

## Next

Open **WHOISWHO > Setup** and work down the [Setup checklist](/start/setup-checklist).

## Check the files (optional)

The bundle has a file `SHA256SUMS.txt` with a fingerprint of each zip. The development team can check these for you if a download looks damaged.
