# Third-party plugins

Every outside plugin the platform plans to use, what each replaces, and where the choice is still open.

## Policy

- **Few and pinned.** Every plugin is locked to a version. Updates ship through the build pipeline, and file modification is disabled in production, so nothing updates from wp-admin.
- **Use WordPress first.** A plugin is added only where WordPress does not already do the job.
- **Vetting rule (owner answer, Q81).** A plugin is allowed only if it is actively maintained, small, under an open licence, and handles no personal data without an entry in the processor register. The visibility matrix is re-run for each one.
- **Plugins that touch personal data** go on the processor register.

## The list

| Purpose | Plugin | Status | Replaces | Risks |
|---|---|---|---|---|
| Staff two-factor sign-in | Two Factor 0.17.0, enforced for every staff role | Approved (owner) | Custom code | WordPress has none built in. Test enforcement and lockout locally |
| Background jobs | Action Scheduler, used as a library inside `whoiswho-core` | Chosen | A custom job table | Shared hosts cannot run daemons, so it runs on WP-Cron with a time budget |
| Story comments | wpDiscuz 7.7.0 (tested), **required**, installed from the Required plugins screen | Chosen | Core comments | Personal data and defamation risk. The plugin sets it to the comment rules (see "wpDiscuz settings" below). In the PII catalogue and processor register |
| Spam checking | A honeypot and rate limits in the plugin. **No Akismet** | Decided (owner) | Custom spam filters | Akismet would send commenter data to a third party, so it is not used |
| Per-user capability screen | User Role Editor 4.67, usable only by the Administrator | Approved (owner, Q86) | Custom screen | A plugin with a wide surface; the guard plugin still enforces the non-grantable list |
| Page editor | The native block editor with theme patterns | Decided (Q98) | Editing copy in code | None; built into WordPress |
| Backups | Deferred by owner decision (see the launch checklist) | Deferred | Manual backups | Not done until they run and a restore is tested |
| Mail delivery | FluentSMTP 2.4.5 (tested), **required**, installed from the Required plugins screen; the owner connects the mail account on its settings screen. `WIW_SMTP_*` constants still work where a developer prefers them | Owner to set up | A paid mail provider | Free tiers cap messages; check before bulk nominee mail. SPF, DKIM and DMARC still apply. FluentSMTP keeps a log of sent mail, including the links inside each message, by default. Until the plugin switches that off, turn off "Log All Emails" in its settings |
| Bot challenge | Cloudflare Turnstile, if Cloudflare is added | Optional | reCAPTCHA | Only exists if Cloudflare is in front |
| Edge cache and firewall | Cloudflare, at go-live if wanted | Optional | The page-cache plugin | Not a dependency. Without it there is no edge cache and the origin's 410 is the answer |
| Page-cache plugin | WP Super Cache 3.1.5, kept swappable | Approved (owner, Q82) but **off by default** until the CSP-nonce question (issue #719) is settled | Nothing | A cache plugin is a leak risk for hidden content and can serve a stale Content-Security-Policy nonce. The origin's 410 and the visibility matrix must pass with it on before it is switched on. Q82: keep the `wiw_profile_state` table rather than relying on post meta |
| SEO plugin | None. The guard emits all markup | Excluded | | A plugin could publish a sitemap or schema entry for a hidden record |

## Required plugins (no-code install)

The owner installs the outside plugins from the admin, without a file manager or a command line: **Settings (the site's menu group) > Required plugins**, Administrators only (`wiw_run_setup`, plus WordPress's own `install_plugins` and `activate_plugins`). Code: `whoiswho-core/src/Platform/Plugins/`.

| Plugin | wordpress.org slug | Status on the screen |
|---|---|---|
| FluentSMTP | `fluent-smtp` | Required |
| wpDiscuz | `wpdiscuz` | Required |
| Two Factor | `two-factor` | Recommended (optional). Shown as "Included with the site plugin" while whoiswho-core loads its own copy |

- **Install** asks wordpress.org for the plugin (`plugins_api()`) and installs it with core's `Plugin_Upgrader`, the same path as Plugins > Add New. Only a `https://downloads.wordpress.org` package is accepted, and only the slugs above. **Activate** uses `activate_plugin()`. Each success is written to the audit log (`plugins.install`, `plugins.activate`).
- **Hosts that ask for FTP details:** the screen says so and offers "Install with WordPress", core's own install screen, which asks for the details.
- **Hosts where file changes are switched off** (`DISALLOW_FILE_MODS`, as in the repo's production `wp-config` template): WordPress removes `install_plugins`, so the screen explains in plain words and shows no Install button. Such a site gets the plugins through its deploy instead.
- **Reminder:** while a required plugin is not active, Administrators see a notice on every admin page with a link to the screen. "Remind me in 7 days" hides it for that Administrator only (user meta `wiw_plugins_notice_until`).
- **Setup checklist:** one line per required plugin.
- **Allowlist:** both are in `site/config/plugin-allowlist.json` with the version tested. They are not in `composer.lock`, so the lockfile check does not pin them.

### wpDiscuz settings

`Content/Comments/WpdiscuzSettings` sets wpDiscuz to the comment rules without editing its files. Its global settings (option `wc_options`) are **forced on every read**, so changing them on wpDiscuz's settings screen has no effect; they are also saved once so that screen shows them. Its comment forms are set **once**, when wpDiscuz or whoiswho-core is activated, or on the first admin page load after wpDiscuz creates its form (option `wiw_wpdiscuz_applied` marks it done; delete it to apply again).

| Where | Setting | Value | Why |
|---|---|---|---|
| Thread layout | `showAvatars` | off | No profile pictures, so no Gravatar request |
| Thread layout | `showVotingButtons`, `enableDislikeButton`, `isGuestCanVote` | off | Votes store a hash of the visitor's IP address |
| Login and social | every social login and share button, login link, profile links, activity, subscriptions and follows tabs | off | No public accounts (D-034) |
| Subscription | `enableUserMentioning`, `sendMailToMentionedUsers`, `isFollowActive`, `isNotifyOnCommentApprove` | off | Fewer emails and less personal data |
| Subscription | `isReplyDefaultChecked` off, `enableGuestsConfirm` on | | Reply notifications are opt-in and confirmed by email |
| Content | `wmuIsEnabled`, `wmuIsGuestAllowed`, `enableShortcodes` | off | No visitor uploads |
| Form | `storeCommenterData` | 0 | The commenter's name and email are not kept in a long-lived browser cookie |
| Rating | `enablePostRatingSchema` | off | No schema markup from a plugin (REQ-217) |
| General | `isEnableOnHome`, `showPluginPoweredByLink` | off | |
| Each form | `guest_can_comment` on, `wpdiscuz_form_post_types` = posts (stories) only | | Guests comment without an account; stories only |
| Each form | `enable_post_rating`, `allow_guests_rate_on_post` | off | Star ratings store the visitor's IP address |
| Each form | Name and Email fields required; Website field off | | Name, email and consent, nothing more |
| Option | `wpdiscuz_wizard_completed` = 1 | | Its set-up wizard would only ask for what is set here, so its "complete the wizard" banner is not shown |

Underneath any form, `CommentPolicy` still holds the rules: comments open on stories only and close after 90 days, a guest needs a name, a valid email and the consent tick (also when wpDiscuz posts through `admin-ajax.php`), every visitor comment is held for a moderator, Gravatar is off and the email never reaches public HTML. The consent tick is added to wpDiscuz's form through the `comment_form` action. While wpDiscuz is active the theme's story template shows wpDiscuz's form and thread (`comments_template()`); without it, the theme's own form.

Personal data in wpDiscuz 7.7.0's tables: `wc_comments_subscription` (email) and `wc_follow_users` (names, emails) are exported and erased by email (`CommentPrivacy`); `wc_users_voted` (IP hash) and `wc_users_rated` (IP) stay empty because votes and ratings are off; `wc_phrases` and `wc_feedback_forms` hold none. Recheck these tables when the wpDiscuz version changes.

## Not allowed

| Kind | Why |
|---|---|
| An SEO plugin | See above |
| A public-login, membership or account plugin | There are no public accounts |
| A payment or checkout plugin | The site takes no payments |
| An AI drafting plugin | Drafting belongs to the acquisition pipeline |
| A page builder that outputs the public templates | Public templates are server-rendered theme files. A builder may edit page copy only |

## How a plugin is approved

1. State the job it does and what WordPress already offers for it.
2. Check what personal data it handles, where it sends it, and add it to the processor register if needed.
3. Install it on a local copy and re-run the visibility matrix.
4. Pin the version and add it to the lockfile.
5. Record it on this page with its status.

## Depends on

The plugin policy above is used by the build plan when an issue adds a plugin.

## Requirement ids covered

REQ-047, 075, 081, 108, 205, 217, 255, 258, 260.
