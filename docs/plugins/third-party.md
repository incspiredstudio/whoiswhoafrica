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
| Story comments | wpDiscuz | Chosen | Core comments | Personal data and defamation risk. Needs the settings in [Content](/plugins/content). Add to the PII catalogue and processor register |
| Spam checking | A honeypot and rate limits in the plugin. **No Akismet** | Decided (owner) | Custom spam filters | Akismet would send commenter data to a third party, so it is not used |
| Per-user capability screen | User Role Editor 4.67, usable only by the Administrator | Approved (owner, Q86) | Custom screen | A plugin with a wide surface; the guard plugin still enforces the non-grantable list |
| Page editor | The native block editor with theme patterns | Decided (Q98) | Editing copy in code | None; built into WordPress |
| Backups | Deferred by owner decision (see the launch checklist) | Deferred | Manual backups | Not done until they run and a restore is tested |
| Mail delivery | A free SMTP service the owner sets up, through `wp_mail`; credentials only in the secrets file | Owner to set up | A paid mail provider | Free tiers cap messages; check before bulk nominee mail. SPF, DKIM and DMARC still apply |
| Bot challenge | Cloudflare Turnstile, if Cloudflare is added | Optional | reCAPTCHA | Only exists if Cloudflare is in front |
| Edge cache and firewall | Cloudflare, at go-live if wanted | Optional | The page-cache plugin | Not a dependency. Without it there is no edge cache and the origin's 410 is the answer |
| Page-cache plugin | WP Super Cache 3.1.5, kept swappable | Approved (owner, Q82) but **off by default** until the CSP-nonce question (issue #719) is settled | Nothing | A cache plugin is a leak risk for hidden content and can serve a stale Content-Security-Policy nonce. The origin's 410 and the visibility matrix must pass with it on before it is switched on. Q82: keep the `wiw_profile_state` table rather than relying on post meta |
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
