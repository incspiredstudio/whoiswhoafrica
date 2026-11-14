# Getting started

This section takes you from an empty hosting account to a working WHOISWHO site. You do not need to write code, edit files such as `wp-config.php` or type commands. Everything is done in your hosting control panel (cPanel) and in the WordPress admin, by uploading files and pressing buttons.

## The order to follow

| Step | Page | About how long |
|---|---|---|
| 1 | [What the host needs](/start/install#what-the-host-needs) | 10 minutes to check |
| 2 | [Raise the upload limit](/start/install#raise-the-upload-limit) | 5 minutes |
| 3 | [Install the themes, the plugin and the guard](/start/install#install-the-themes-and-the-plugin) | 15 minutes |
| 4 | [Work through the Setup checklist](/start/setup-checklist) | 10 minutes |
| 5 | [Install the required plugins](/start/required-plugins) | 5 minutes |
| 6 | [Set up email](/start/email) | 15 minutes |
| 7 | [Add the Turnstile keys](/start/turnstile) | 10 minutes |
| 8 | [Try the demo content (optional)](/start/demo-content) | 5 minutes |
| 9 | [Go live](/start/going-live) | An hour, plus checks by the team |

## What you need before you start

- A cPanel hosting account for the domain, with your cPanel login.
- The WHOISWHO install bundle: a folder with `whoiswho.zip`, `whoiswho-child.zip`, `whoiswho-core.zip`, `whoiswho-guard-mu-plugin.zip`, `INSTALL.md` and `SHA256SUMS.txt`. The development team sends it to you for each release.
- An email account on the domain for the site to send from, for example `noreply@` your domain. You create it in cPanel on the [email page](/start/email).
- A Cloudflare account for the Turnstile spam check. It is free.

## Words you will meet

| Word | Meaning |
|---|---|
| cPanel | The control panel your host gives you for files, databases, email and PHP settings |
| wp-admin | The WordPress admin, at your domain followed by `/wp-admin` |
| Zip | One compressed file that holds a folder. WordPress installs themes and plugins from zips. |
| Theme | Controls how the public site looks. WHOISWHO has a parent theme and a child theme. You activate the child. |
| Plugin | Adds features to WordPress. `whoiswho-core` is the WHOISWHO plugin. |
| Must-use plugin | A plugin that is always on and cannot be switched off in wp-admin. The WHOISWHO guard is one. |

More terms are in the [Glossary](/glossary).
