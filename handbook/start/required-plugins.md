# Required plugins

WHOISWHO uses two free plugins from wordpress.org that are not inside the install bundle. The **Required plugins** screen installs and activates them with one click each. Only an Administrator can open it.

| Plugin | What it does on WHOISWHO | Required? |
|---|---|---|
| **FluentSMTP** | Sends the site's email through your cPanel mail account, so messages arrive instead of landing in spam | Required |
| **wpDiscuz** | The comment form on stories | Required |
| **Two Factor** | A second sign-in step for staff. It already comes inside the WHOISWHO plugin, so the screen shows it as "Included with the site plugin". | Recommended (optional) |

The WHOISWHO plugin also carries Action Scheduler (background jobs) and User Role Editor (per-user permissions) inside it. You do not install those.

## Install them

1. In wp-admin, open **Site settings > Required plugins**. The **Site settings** menu sits near the top of the left menu, below Compliance; it is not the WordPress Settings menu lower down. While a required plugin is missing, a notice at the top of every admin page has a **Set up the plugins** button that links here too.
2. Each plugin shows its status: **Not installed**, **Installed, not active** or **Active**.
3. Press **Install FluentSMTP**. Wait until the page reloads.
4. Press **Activate FluentSMTP**.
5. Do the same for wpDiscuz.
6. Open **WHOISWHO > Setup** and check that the plugin lines are Ready.

You can hide the notice for 7 days with its **Remind me in 7 days** button. It comes back while a plugin is still missing.

## If Install asks for FTP details

Some hosts do not let WordPress write plugin files by itself. Then the screen says your host asks for its file-transfer (FTP) details. Next to each Install button there is also a link **Install with WordPress**, which uses the normal WordPress plugin installer.

1. Follow the **Install with WordPress** link, or open **Plugins > Add New Plugin**.
2. Search for "FluentSMTP", press **Install Now**, then **Activate**.
3. Search for "wpDiscuz" and do the same.

If WordPress asks for FTP details there as well, ask your host to fix the file permissions of the site, or ask them for the FTP login.

## What WHOISWHO sets in wpDiscuz for you

When wpDiscuz is activated, WHOISWHO applies the site's comment rules to it. You do not need to change wpDiscuz settings.

| Rule | Setting |
|---|---|
| Comments only on stories | Profiles, events and pages take no comments |
| No account needed | Guests give a name, an email and tick the consent box |
| Every comment is held | Nothing appears until a staff member approves it |
| No Gravatar | No profile pictures are loaded from outside the site |
| Commenter emails stay private | An email is never shown on the public page |

If someone changes these in the wpDiscuz settings, the WHOISWHO rules still apply underneath. Moderation is described in [Stories](/staff/stories#comments).

## Next

[Set up email in FluentSMTP](/start/email).
