# ADR-012 CI/CD and deployment to cPanel

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-056, 095

**Context.** One human supervisor, many agents, shared hosting, WordPress with pinned plugins.

**Decision.** GitHub Actions runs the gates on every PR: PHP coding standards (WordPress ruleset), static analysis, PHPUnit on the WordPress test suite with architecture tests, asset build, Playwright and axe, dependency audit, secret scan. It builds an artifact (pinned WordPress core, plugins and theme from the lockfile, built assets), auto-deploys staging, and deploys production after manual approval. Release directories with an atomic `current` symlink; the secrets file, `wp-config.php` and uploads are shared symlinks; forward-only expand/contract migrations run by the plugin's migrate command; an automatic pre-migration dump in production. Updates to core and plugins ship only through the pipeline (`DISALLOW_FILE_MODS` in production). Fallback: cPanel Git Version Control with `.cpanel.yml`.

**Why.** Fast rollback and no build tools needed on the host.

**Rejected.** Manual FTP (error-prone). Updating plugins from wp-admin (unreviewed code in production).

**Consequences.** Needs SSH and a symlink-following document root (checked in spike S1).
