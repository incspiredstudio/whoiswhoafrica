# The Setup checklist

The Setup screen is the one place that tells you whether the site is ready. It opens by itself after you activate the WHOISWHO plugin, and it stays in the admin menu under **WHOISWHO > Setup**. A box on the Dashboard reminds you until you hide it. Only an Administrator can open it.

## How to read it

The screen is titled "Set up your site" and says how many steps are ready, for example "5 of 10 steps are ready". Each line has one of three labels:

| Label | Meaning | What to do |
|---|---|---|
| **Ready** | This step is done | Nothing |
| **Recommended** | The site works, but this step makes it safer or more complete | Do it before going live |
| **Needs attention** | Something is missing and part of the site will not work | Follow the "What to do" line under it |

Many lines have a button that fixes the problem in one click. After fixing something outside the page, for example in cPanel, reload the Setup screen.

## The lines, one by one

| Line | When it is ready | If it is not |
|---|---|---|
| Database ready | "The plugin's tables are in place and up to date." | On a new site, press **Run database setup**. It is safe to press again. After a plugin update on a site that already has data, the button is **Update the database**: take a backup first, tick **I have a backup**, then press it. If it still fails, the line says why; usually the database user lacks permission to create tables and triggers (see [Troubleshooting](/operations/troubleshooting#setup-checklist-red-items)). |
| Pretty web addresses (permalinks) | Web addresses read like `/directory/` | Press **Fix permalinks**. |
| Site theme active | The child theme is active | Press **Activate child theme**. If it says the themes are not installed, upload both theme zips first ([Install](/start/install#_1-upload-the-two-themes)). |
| Guard plugin installed (recommended) | "The guard is running." | Copy the guard into `wp-content/mu-plugins` with cPanel File Manager ([Install](/start/install#_3-copy-the-guard-into-mu-plugins)). |
| Two-factor sign-in for staff (recommended) | Every staff account has two-factor switched on; the line lists who has it on and who does not yet | Advice only. Two-factor is recommended for every staff account but not required ([Two-factor](/staff/users-and-two-factor#two-factor-sign-in)). |
| Email | FluentSMTP is active and connected to an email account (or your host set an email account for the site) | Press **Open Required plugins** to install FluentSMTP, or **Open FluentSMTP settings** to connect it ([Email](/start/email)). A demo does not need email. |
| Spam protection for forms (optional) | Cloudflare Turnstile is on | Optional. The built-in spam check is enough for most sites. To use Turnstile, paste its keys into the form on this line ([Turnstile](/start/turnstile)). |
| FluentSMTP plugin active | FluentSMTP is installed and switched on | Press **Open Required plugins**, then **Install** and **Activate** ([Required plugins](/start/required-plugins)). |
| wpDiscuz plugin active | wpDiscuz is installed and switched on | Press **Open Required plugins**, then **Install** and **Activate** ([Required plugins](/start/required-plugins)). |
| Demo content (optional) | Shows whether demo content is on the site | Optional. See [Demo content](/start/demo-content). |

## Hide the Dashboard reminder

When every line you need is Ready, press **Hide the Dashboard reminder** at the bottom of the Setup screen. The screen itself stays in the menu, so you can come back to it at any time, for example after an update.

## A red notice at the top of every admin page

If the site could not finish setting itself up, a red notice says "The site could not finish setting itself up" with a list of what is wrong and a link **Open the Setup screen**. The usual causes are an old PHP version, a missing PHP extension, or a database that is not MySQL or MariaDB. Each line says what to ask your host for. See [Troubleshooting](/operations/troubleshooting).
