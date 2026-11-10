# Third-party plugins

Every outside plugin the platform plans to use, what each replaces, and where the choice is still open.

## Policy

- **Few and pinned.** Every plugin is locked to a version. Updates ship through the build pipeline, and file modification is disabled in production, so nothing updates from wp-admin.
- **Use WordPress first.** A plugin is added only where WordPress does not already do the job.
- **Vetting rule.** The rule for allowing a plugin (maintenance, size, licence, data handling) is still an open question; see [open questions](/open-questions). Until it is answered, every plugin below needs the visibility matrix re-run and a privacy check before it is approved.
- **Plugins that touch personal data** go on the processor register.

## The list

| Purpose | Plugin | Status | Replaces | Risks |
|---|---|---|---|---|
| Staff two-factor sign-in | A maintained two-factor plugin, enforced for every staff role, with recovery codes | Chosen kind; product not yet named | Custom code | WordPress has none built in. Test enforcement and lockout locally |
| Background jobs | Action Scheduler, used as a library inside `whoiswho-core` | Chosen | A custom job table | Shared hosts cannot run daemons, so it runs on WP-Cron with a time budget |
| Story comments | wpDiscuz | Chosen | Core comments | Personal data and defamation risk. Needs the settings in [Content](/plugins/content). Add to the PII catalogue and processor register |
| Spam checking | Akismet or an equivalent, with a honeypot and rate limits in the plugin | Proposed | Custom spam filters | Akismet sends data to a third party. Confirm before use |
| Per-user capability screen | A maintained role-editor plugin | Decided (Q86) | Custom screen | A plugin with a wide surface; the guard plugin still enforces the non-grantable list |
| Page editor | The native block editor with theme patterns | Decided (Q98) | Editing copy in code | None; built into WordPress |
| Backups | The host's nightly backups, plus a backup plugin or export if needed | Proposed | Manual backups | Test a restore into a local copy before launch and monthly |
| Mail delivery | The host's SMTP through `wp_mail`, with SPF, DKIM and DMARC. A small SMTP plugin only if configuration cannot be done without one | Proposed | A mail provider | Hosts cap messages per hour; check before bulk nominee mail |
| Bot challenge | Cloudflare Turnstile, if Cloudflare is added | Optional | reCAPTCHA | Only exists if Cloudflare is in front |
| Edge cache and firewall | Cloudflare, at go-live if wanted | Optional | A page-cache plugin | Not a dependency. Without it there is no edge cache and the origin's 410 is the answer |
| Page-cache plugin | None planned | Not planned | | A cache plugin is a leak risk for hidden content |
| SEO plugin | None. The guard emits all markup | Excluded | | A plugin could publish a sitemap or schema entry for a hidden record |

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
