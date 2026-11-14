# Security review of the WHOISWHO WordPress build

- **Issue:** #810
- **Date:** 2026-11-14
- **Reviewer:** security review session (lane B)
- **Code reviewed:** `origin/chore/structured-build-setup` at `70a6d4a` (integration round 8), plus these branches merged locally: `lane-u/no-code-config`, `lane-v/required-plugins`, `lane-w/profile-sections`, `lane-z/add-profile`, `lane-y/symbols-motion`, `lane-d/demo-chooser` and `fix/https-redirect-site-url`. These make up integration round 9.
- **Fixes:** branch `security/review-fixes`, based on `origin/chore/structured-build-setup` at `830f9e4` (integration round 9 merged, PR #812).

## 1. Scope

**In scope (first-party code):**
- `site/wp-content/plugins/whoiswho-core/**`: the custom plugin. That is `src`, `config`, `migrations`, `templates` and the shipped sample bundle.
- `site/wp-content/mu-plugins/whoiswho-guard/**`: the must-use security plugin.
- `site/wp-content/themes/whoiswho/**` and `site/wp-content/themes/whoiswho-child/**`.
- `site/bin/`, `site/deploy/` (install bundle, release packaging, CI install scripts) and `site/wp-config.php`.

**Out of scope:**
- Third-party plugins (FluentSMTP, wpDiscuz, Two Factor, WP Super Cache) and WordPress core. Only the way we configure them is reviewed.
- The hosting platform, DNS and Cloudflare. These are recommendations only, in section 8.
- The acquisition pipeline, which has no runtime path into the website. The import contract is reviewed only as an input format.

## 2. Standards used

| Standard | Version | Used for |
|---|---|---|
| OWASP Top 10 | 2025 | Overall checklist, section 6 |
| OWASP ASVS | 4.0.3, level 2 | Authentication (V2), session management (V3), access control (V4), input validation (V5), files and resources (V12) |
| WordPress Plugin Handbook | Security chapter, current online edition (checked 2026-11) | Sanitising input, escaping late, nonces, capabilities, `$wpdb->prepare`, file uploads |
| WordPress Coding Standards (WPCS) | 3.4.1 | `WordPress.Security.*`, `WordPress.DB.PreparedSQL*`, `WordPress.Security.SafeRedirect`, `WordPress.PHP.DiscouragedPHPFunctions` sniffs |
| WordPress VIP coding standards | Security sniffs, as mirrored by WPCS 3.4.1 | The same sniff families |
| CVSS | 3.1 | Severity reasoning, given as a short vector rationale per finding |

**Platform versions:**
- WordPress core 7.1.4, from `johnpbloch/wordpress-core` in the lockfile.
- PHP 8.3.
- PHPStan 2.3.5 with `szepeviktor/phpstan-wordpress` 2.0.4.
- PHPUnit 9.6.38.

## 3. Method

1. **Automated sweeps:**
   - PHPCS with `--standard=WordPress-Extra`, limited to these sniffs: `WordPress.Security.EscapeOutput`, `NonceVerification`, `ValidatedSanitizedInput`, `WordPress.DB.PreparedSQL`, `PreparedSQLPlaceholders`, `WordPress.Security.SafeRedirect` and `WordPress.PHP.DiscouragedPHPFunctions`. It ran over the plugin, guard, both themes, `bin`, `deploy` and `wp-config.php`.
     - It reported 3 messages, all in CLI-only deploy scripts that print to a terminal.
     - It counted every `phpcs:ignore` that suppresses one of these sniffs: about 118 escape-output, 241 SQL, 6 nonce and 3 sanitise suppressions.
   - The repository's architecture rules in `tests/Arch`: no raw superglobals outside `Platform\Admin\Request` and `FormInput`, no unprepared SQL, no `eval` or shell, admin pages only through `ScreenRegistry` and `ActionGuard`, and only `StateMachine` writes `pub_state`.
   - `composer audit` and `npm audit --audit-level=high`.
2. **Manual review of every suppression** of the security sniffs above. Each was confirmed safe: the value is an integer, a `$wpdb` table name, built from a fixed allowlist or `array_fill` placeholders, or HTML built entirely from escaped parts. Any value that could carry user data counted as a finding.
3. **Manual review of the new code paths** named in the brief:
   - zip upload, piece-by-piece upload and the server import folder;
   - image import;
   - one-click plugin installs;
   - generated site keys and the Turnstile secret;
   - the mail transport;
   - the Update the database button;
   - wpDiscuz settings forcing;
   - demo import and removal;
   - the Add profile screen;
   - the HTTPS redirect change.
4. **Cross-cutting greps:**
   - every `$_GET`, `$_POST`, `$_REQUEST`, `$_FILES` and `$_SERVER` read;
   - `register_rest_route` and its `permission_callback`;
   - `wp_ajax_nopriv_` and `admin_post_nopriv_`;
   - `wp_redirect` and `wp_safe_redirect`;
   - `unserialize` and `maybe_unserialize`;
   - file writes: `file_put_contents`, `fopen`, `mkdir`, `move_uploaded_file`, sideload;
   - `setcookie`;
   - `hash_equals` and `===` comparisons on secrets;
   - token generation;
   - `error_log` and Logger calls, checked against the PII catalogue;
   - in theme templates, `get_post_meta` and `WP_Query` on profiles.
5. **Verification:**
   - Each candidate finding was confirmed by reading the code around it.
   - Where cheap, it was also reproduced with a PHPUnit test that fails before the fix. Those tests are listed against each finding.
   - False positives were dropped.
6. **Fixes:**
   - Written test first, with test names citing the REQ and `issue_810`.
   - Followed by the full gate run in section 9.

## 4. Summary

| ID | Severity | Title | Status |
|---|---|---|---|
| SEC-01 | Medium | Sign-in lockout counted per typed name only: username and email counted separately, no per-address limit | Fixed `e25e2c1` |
| SEC-02 | Low | Zip entries copied up to 1 GB each regardless of the size their header declares; handles not closed on failure | Fixed `48b9cf6` |
| SEC-03 | Low | Sideloaded files skip the guard's upload checks and scanner | Fixed `276dc04` |
| SEC-04 | Low | Exposure filter treats a visitor's admin-ajax request as admin | Fixed `5214770` |
| SEC-05 | Low | Log folder guard written after the first log line | Fixed `b938afd` |
| SEC-06 | Low | Private import folder has no deny-all guard when WordPress sits in a sub-folder of the web root | Fixed `9fa9d55` |
| SEC-07 | Low | Newsletter double opt-in confirmed by a plain GET, so link scanners can confirm | Accepted (recommendation) |
| SEC-08 | Low | Private files in web-reachable folders protected only by `.htaccess` (audit export, server import folder) | Accepted (recommendation) |
| SEC-09 | Low | Per-IP throttles use `REMOTE_ADDR`, which is shared behind a proxy or CDN | Accepted (go-live action) |
| SEC-10 | Low | wpDiscuz guest reply subscriptions send confirmation mail without our rate limit | Accepted (recommendation) |
| SEC-11 | Info | Public CSP allows inline styles; admin CSP is report-only | Accepted |
| SEC-12 | Info | No-code install: plugin uploads stay open for Administrators; the code editors are switched off by the guard (owner decision 2026-11-14, #813) | Fixed (editors) and accepted (uploads) |
| SEC-13 | Info | Two-factor available but not enforced for staff (owner decision 2026-11-14) | Accepted (owner decision) |
| SEC-14 | Info | Version disclosure through `release.json` and `vendor/composer/installed.json` in the web root (release-tarball install) | Accepted (recommendation) |
| SEC-15 | Info | Theme settings screen uses `manage_options`, not a `wiw_*` capability | Accepted |
| SEC-16 | Info | Profile view counts can be inflated; form tokens can be replayed within their hour | Accepted |
| SEC-17 | Info | `WP_DEBUG_LOG` is on outside production and writes `wp-content/debug.log` | Accepted |

There are no Critical or High findings. Both new upload paths were reviewed in detail: the zip and piece uploads, and the image import. Neither allows zip-slip, symlinks, absolute paths or requests to remote URLs (SSRF).

## 5. Findings

### SEC-01: sign-in lockout counted per typed name only (Medium)

- **Location:** `site/wp-content/mu-plugins/whoiswho-guard/Login/Login.php:53-112` before the fix (`key()`, `block_locked()`, `record_failure()`).
- **Severity:**
  - CVSS 3.1 about 5.3 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:L), plus a credential-guessing aid.
  - Anyone can trigger it without signing in.
  - Brute force stays slow, so impact is limited to targeted lockout and a doubled guessing budget.
  - ASVS 2.2.1 asks for anti-automation that works across names and addresses.
- **Description:**
  - Failed sign-ins were counted under an HMAC of the name as typed.
  - An account's username and its email had separate counters, so a guesser got 10 tries per 15 minutes per account instead of 5.
  - There was no limit per client address, so one address could spray passwords across any number of names.
- **Fix:** `e25e2c1`.
  - A name that belongs to an account is counted under the account id, so the username and email share one count.
  - A new per-address counter refuses an address for 15 minutes after 20 failures across any names. The address comes from `REMOTE_ADDR` only and is stored as an HMAC.
  - The per-account lock and the generic error are unchanged.
- **Tests:**
  - `tests/Security/LoginProtectionTest.php::test_REQ_047_username_and_email_share_one_failure_count_issue_810`
  - `::test_REQ_047_one_address_is_limited_across_many_usernames_issue_810`
- **Residual risk:**
  - Anyone who knows a staff username or email can still keep that account locked by sending 5 bad passwords every 15 minutes. This is inherent to account lockout.
  - Mitigations: a WAF rate rule on `wp-login.php` (section 8), and staff signing in with a username that is not published.
  - Behind a proxy without the real client IP restored, the address limit applies to the proxy's address (see SEC-09).

### SEC-02: zip entries copied without a per-entry byte cap (Low)

- **Location:** `site/wp-content/plugins/whoiswho-core/src/Import/Admin/BundleStore.php:242-260` before the fix (`copy_entry()`).
- **Severity:**
  - CVSS about 2.7 (AV:N/AC:L/PR:H/UI:N/S:U/C:N/I:N/A:L).
  - Only a user with `wiw_run_imports` can upload.
  - The impact is disk exhaustion.
- **Description:**
  - `plan()` checks the declared unpacked sizes against a 1 GB cap, but `copy_entry()` streamed up to 1 GB from every entry and only compared the result with the declared size afterwards.
  - A zip whose headers understate the size could write up to 1 GB per entry, and every image entry counts, before being refused.
  - On a failed read, the file handles were not closed in a `finally` block. On Windows the folder could then be left behind.
- **Fix:** `48b9cf6`.
  - Each entry is copied to at most its declared size plus one byte (`BundleStore::copy_limit()`).
  - The handles are closed in `finally`.
- **Test:** `tests/Security/ZipEntrySizeTest.php::test_REQ_092_an_entry_larger_than_its_header_says_is_refused_issue_810`. It uses a crafted zip with a rewritten header.
- **Residual risk:** none beyond the documented caps: 512 MB zip, 1 GB declared unpacked, 5 MB per image.

**What was confirmed safe on the same path:**
- Entry names with `..`, a leading `/`, a drive letter, a backslash or a NUL byte are refused.
- Unix symlink entries are refused.
- Only `manifest.json`, `records.jsonl` and `images/<sha256>.<jpg|jpeg|png|webp>` are extracted, to names we choose, inside a folder with a random 96-bit name.
- The root folder is refused when it sits inside `ABSPATH`.
- The folder is deleted after the import.
- Piece uploads accept only `^[0-9a-f]{32}$` ids and exact offsets, and abandoned pieces are cleared after a day.
- The server import folder lists only names matching `^[A-Za-z0-9][A-Za-z0-9._-]{0,180}\.zip$`. It rejects links, uses `realpath` to confirm the parent folder, and deletes each zip once it is staged.
- All of these actions need `wiw_run_imports` plus a nonce through `ActionGuard`.

### SEC-03: sideloads skip the guard's upload prefilter (Low)

- **Location:** `site/wp-content/mu-plugins/whoiswho-guard/Uploads/Uploads.php:65`.
- **Severity:**
  - CVSS about 3.1 (AV:N/AC:H/PR:L/UI:N/S:U/C:N/I:L/A:N).
  - Core's file-type check and `ImageCheck` still applied, so no executable could get through.
- **Description:**
  - `media_handle_sideload()` fires `wp_handle_sideload_prefilter`, not `wp_handle_upload_prefilter`. The guard's size and type rules and the pluggable malware scanner were therefore skipped for three paths:
    - invitation photos: `Commerce/Invitations/Invitations.php`, reachable by anyone holding an invitation link;
    - profile manager photos: `Trust/Admin/MyProfilesScreen.php`;
    - import images: `Import/Images/ImageImporter.php`.
- **Fix:** `276dc04`. The same `prefilter` is now hooked to `wp_handle_sideload_prefilter`.
- **Test:** `tests/Security/UploadRulesTest.php::test_REQ_127_sideloaded_files_get_the_same_checks_and_scan_issue_810`.
- **Residual risk:** the default scanner is the null scanner. A real scanner (ClamAV or a host service) is a go-live recommendation.

### SEC-04: anonymous admin-ajax counted as admin by the exposure filter (Low)

- **Location:** `site/wp-content/mu-plugins/whoiswho-guard/Exposure/Exposure.php:104`.
- **Severity:**
  - CVSS about 3.7 (AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:N/A:N).
  - Our code has no `wp_ajax_nopriv_*` handler. Exposure would need a third-party handler that queries profiles.
- **Description:**
  - The last-line filter that strips guarded (unpublished) profiles from `WP_Query` results returned early on `is_admin()`.
  - `is_admin()` is also true on `admin-ajax.php` for visitors.
- **Fix:** `5214770`. A visitor's AJAX request is now treated as public. Signed-in admin and AJAX screens are unchanged.
- **Test:** `tests/Visibility/ExposureTest.php::test_REQ_229_anonymous_admin_ajax_queries_never_get_a_guarded_type_issue_810`.
- **Residual risk:** a signed-in non-staff user (a subscriber) on admin-ajax is still treated as admin by this filter. Registration is closed and there is no default role, so no such accounts exist.

### SEC-05: log folder guard written after the first line (Low)

- **Location:** `site/wp-content/plugins/whoiswho-core/src/Platform/Log/FileSink.php:58-68`.
- **Severity:**
  - CVSS about 2.0.
  - Log lines hold ids, not personal data, because `Logger` scrubs them through the PII catalogue.
  - The window is a single request.
- **Description:** on a fresh folder, the first log line was written before `.htaccess` existed.
- **Fix:** `b938afd`. The `.htaccess` (deny all) and an empty `index.php` are written first.
- **Test:** `tests/Unit/LoggerTest.php::test_REQ_050_the_guard_files_exist_before_the_first_line_is_written_issue_810`.
- **Residual risk:** see SEC-08 for servers that ignore `.htaccess`.

### SEC-06: private import folder without deny guards in sub-folder installs (Low)

- **Location:** `site/wp-content/plugins/whoiswho-core/src/Import/Admin/BundleStore.php:89` (`ensure_root()`).
- **Severity:** CVSS about 3.1 (AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:N/A:N). The folder names are 96-bit random and live only for the length of an import.
- **Description:**
  - The default root is `dirname(ABSPATH)/wiw-private/import`, and `ensure_root()` refuses a root inside `ABSPATH`.
  - When WordPress sits in a sub-folder of the document root (for example `public_html/wp/`), the default root lands in `public_html/` without any guard.
- **Fix:** `9fa9d55`. `ensure_root()` now writes a deny-all `.htaccess` and an empty `index.php` into the root.
- **Test:** `tests/Security/ZipEntrySizeTest.php::test_REQ_127_the_private_import_folder_refuses_web_requests_too_issue_810`.
- **Residual risk:** on nginx the guard files have no effect (see SEC-08).

### SEC-07: newsletter confirmation consumed by GET (Low, accepted)

- **Location:** `site/wp-content/plugins/whoiswho-core/src/Content/Newsletter/ConfirmRoute.php:66-97`.
- **Severity:** CVSS about 3.1 (AV:N/AC:L/PR:N/UI:R/S:U/C:N/I:L/A:N).
- **Description:**
  - `GET /confirm/{token}` confirms immediately.
  - Mail security scanners and link prefetchers open links, so an address signed up by a third party can be confirmed without its owner acting.
  - The token itself is sound: 32 random bytes, stored only as a SHA-256 hash, single use, with an expiry.
  - `SignedLinks` already uses the safer pattern: GET shows a button and POST consumes the link.
- **Status:** accepted for launch. The fix touches the plugin route and the theme's confirmation page.
- **Recommendation:** move the confirmation to the same GET-shows-button, POST-confirms pattern before newsletter sends begin (REQ-249).

### SEC-08: private files guarded only by `.htaccess` (Low, accepted)

- **Location:**
  - `src/Platform/Audit/AuditExport.php:67` (`uploads/wiw-private/audit/`);
  - `src/Import/Admin/ServerDrop.php:63` (`uploads/wiw-import/`, by design so that staff can use the cPanel File Manager);
  - `src/Platform/Log/FileSink.php` (`wp-content/wiw-logs`).
- **Severity:**
  - CVSS about 3.7.
  - Audit export file names are guessable (`audit-<first>-<last>.jsonl`).
  - On Apache or LiteSpeed (cPanel) the guards hold. On nginx they do not.
- **Recommendation:**
  - At go-live, confirm that `https://<site>/wp-content/uploads/wiw-private/audit/` and `/wp-content/uploads/wiw-import/` return 403.
  - Later, move the audit export and logs to the private folder outside the web root that the import store already uses (`WIW_PRIVATE_DIR`).

### SEC-09: per-IP throttles behind a proxy (Low, accepted, go-live action)

- **Location:**
  - `src/Compliance/Forms/FormRoutes.php:228`;
  - `src/Platform/Links/LinkRoute.php:102`;
  - `src/Commerce/Invitations/InvitationRoute.php:118`;
  - `themes/whoiswho/templates/search.php:23`;
  - the guard's sign-in limit (SEC-01).
- **Description:**
  - Every limit uses `REMOTE_ADDR` and trusts no forwarded header. That is correct, because nobody can spoof it.
  - Behind Cloudflare or a reverse proxy without the real IP restored, all visitors share a few edge addresses. The per-IP limits (for example 10 form posts per hour) then apply site-wide, so one person could block a public form for everyone.
- **Go-live action:** before turning on Cloudflare, restore the client IP from Cloudflare's published ranges at the web server. On cPanel, use `mod_remoteip` with `RemoteIPHeader CF-Connecting-IP` and `RemoteIPTrustedProxy` for the Cloudflare ranges. Do not trust the header in PHP.

### SEC-10: wpDiscuz guest reply subscriptions (Low, accepted)

- **Location:** `src/Content/Comments/WpdiscuzSettings.php:80-87` (the forced `subscription` settings).
- **Description:**
  - We force `enableGuestsConfirm` on, so a guest's reply subscription needs email confirmation.
  - The subscription form still lets anyone send a confirmation email to any address. That request goes through wpDiscuz's own AJAX handler, outside our comment throttle.
- **Recommendation:**
  - Turn off wpDiscuz's guest subscription bar in its settings, or add the subscription keys to `FORCED` once their option names are verified on the installed wpDiscuz version.
  - Keep wpDiscuz behind the WAF rate rule.

### SEC-11: CSP details (Info, accepted)

- **Location:** `site/wp-content/mu-plugins/whoiswho-guard/Headers/Headers.php:70` and `:122`.
- **Description:**
  - The public CSP allows `style-src 'unsafe-inline'`. Scripts need the per-request nonce.
  - The admin CSP is sent report-only, because core needs inline script and `unsafe-eval` there.
  - Turnstile (`https://challenges.cloudflare.com`) is allowed for `script-src` and `frame-src` only.
- **Recommendation:** keep as is for launch. Revisit inline styles once the theme no longer needs them.

### SEC-12: file editor and plugin uploads in the no-code install (Info, owner decision)

- **Location:** `site/wp-content/mu-plugins/whoiswho-guard/Hardening/Hardening.php:101-136`.
- **Description:**
  - With our `wp-config.php` template, `DISALLOW_FILE_EDIT` is always true and `DISALLOW_FILE_MODS` is true in production.
  - In the no-code install the host's stock `wp-config.php` is used, so neither constant is set. Administrators can then:
    - edit theme and plugin PHP in the admin;
    - upload plugins (the Required plugins screen needs this).
  - The guard shows a notice that names the constants. That is developer wording for a non-coder.
- **Status (2026-11-14):** the owner chose option (a) (#813). The guard now removes `edit_plugins`, `edit_themes` and `edit_files` through `map_meta_cap`, and the notice is a plain, dismissible Dashboard note. Plugin uploads stay open for Administrators; residual risk: a stolen Administrator account could upload a plugin, limited by the login lockout, two-factor (reminded to every staff member, #814) and few Administrator accounts. The options considered were:
  - (a) Keep plugin installs, but have the guard remove `edit_plugins`, `edit_themes` and `edit_files` through `map_meta_cap`. This closes the code editor without a constant. Then reword the notice in plain language.
  - (b) Set both constants through the host (cPanel's WordPress Toolkit or a one-line `wp-config.php` edit by the host) after the required plugins are installed.

### SEC-13: two-factor optional (Info, owner decision)

- **Description:**
  - Two-factor is available through the Two Factor plugin and recommended, but not enforced, by owner decision on 2026-11-14 (REQ-047, REQ-235 amended).
  - ASVS L2 (2.1.x, 2.7, 2.8) expects multi-factor for privileged accounts.
- **Recommendation:** every Administrator and Editor turns two-factor on before go-live. The Setup checklist shows who has it.

### SEC-14: version disclosure (Info)

- **Description:** a release-tarball install puts `release.json` (version and commit) and `vendor/composer/installed.json` (package versions) under the web root.
- **Recommendation:** deny `release.json`, `/vendor/` and `/bin/` at the web server or WAF.
  - `bin/wiw.php` and `bin/zip-dir.php` already refuse non-CLI requests.
  - The deploy scripts are not shipped.

### SEC-15: theme settings capability (Info)

- **Location:** `site/wp-content/themes/whoiswho/inc/settings.php:20`.
- **Description:**
  - The theme settings screen uses `manage_options`, not a named `wiw_*` capability.
  - It is fully guarded (`ScreenRegistry`, `ActionGuard`, nonce) and in practice limited to Administrators.
- **Recommendation:** move it to a `wiw_*` capability when the capability catalogue is next revised.

### SEC-16: view counts and form token replay (Info)

- **Description:**
  - `src/Directory/Views/ViewCounter.php` counts every non-bot GET.
  - `src/Platform/Forms/FormToken.php` tokens can be reused within their hour.
  - Per-IP and per-email throttles bound the abuse.
- **Recommendation:** none for launch. Do not show raw view counts to customers as a metric.

### SEC-17: debug log outside production (Info)

- **Location:** `site/wp-config.php:75`.
- **Description:** `WP_DEBUG_LOG` is true whenever `WIW_ENV` is not `production`, which writes `wp-content/debug.log`.
- **Status:** production is the default, so this affects only staging and local hosts.
- **Recommendation:** never run a public staging site with `WIW_ENV` other than production, or deny `debug.log` at the web server.

## 6. Industry standard checklist: OWASP Top 10 2025

| Item | Status for this code | Evidence |
|---|---|---|
| A01 Broken Access Control (includes SSRF) | **Pass** after SEC-04 | <ul><li>Every admin screen goes through `ScreenRegistry`, and every action through `ActionGuard`: a named `wiw_*` capability, then a nonce, deny by default. The architecture rule `AdminOnlyThroughRegistries` enforces this, and per-screen authorisation matrix tests cover the new screens (Add profile, Required plugins, Setup, Import).</li><li>Plugin installs also need core's `install_plugins` and `activate_plugins`.</li><li>There are no `register_rest_route` routes, and the guard requires sign-in for all REST.</li><li>There are no nopriv AJAX or admin-post handlers.</li><li>Public templates read only the `PublicProfile` and `PublicStory` read models, which the architecture test `TemplatesDoNotReadProfilesTest` checks.</li><li>No code fetches a URL taken from input. The only outbound calls are `plugins_api` (wordpress.org) and the Turnstile verify endpoint. Plugin package URLs must be `https` on `downloads.wordpress.org` or `wordpress.org`. Import images are read from the bundle and never fetched.</li><li>All redirects use `wp_safe_redirect`, and `return` targets are allowlisted.</li></ul> |
| A02 Security Misconfiguration | **Pass with go-live actions** | <ul><li>The guard sends HSTS (HTTPS only), nosniff, `X-Frame-Options: DENY`, Referrer-Policy, Permissions-Policy and a nonce-based CSP.</li><li>XML-RPC, application passwords, registration and user enumeration are off.</li><li>Debug display is off. Secrets come from a file outside the web root.</li><li>The HTTPS redirect applies once the site address is https.</li><li>See SEC-08, SEC-11, SEC-12, SEC-14 and SEC-17.</li></ul> |
| A03 Software Supply Chain Failures | **Pass** | <ul><li>`composer audit`: no advisories.</li><li>`npm audit --audit-level=high`: 0 high or critical. There are 3 moderate, all in `minimatch` through `eslint`, a development dependency that is not shipped.</li><li>Third-party plugins come only from wordpress.org through the slug allowlist (`config/plugin-allowlist.json`). Auto-updates are off, so updates are a deliberate step.</li></ul> |
| A04 Cryptographic Failures | **Pass** | <ul><li>Generated keys are `bin2hex(random_bytes(32))`, stored in non-autoloaded options, never echoed. A constant or the secrets file wins.</li><li>Signed links and newsletter tokens use 32 random bytes, stored only as SHA-256, with an expiry and single use.</li><li>Every secret comparison uses `hash_equals`.</li><li>Lockout keys are HMACs with `wp_salt`.</li><li>The Turnstile secret is a password field and is never echoed. It is stored with autoload off.</li><li>Cookies are secure once the site address is https.</li></ul> |
| A05 Injection | **Pass** | <ul><li>Every variable in SQL goes through `$wpdb->prepare`. Dynamic SQL fragments are table names or allowlists (all 241 suppressions reviewed). The `NoUnpreparedSql` architecture rule enforces this.</li><li>Output is escaped late. The escape suppressions are markup built only from escaped parts.</li><li>There is no `eval`, `unserialize` of input, `extract`, shell call or variable include.</li><li>Mail headers are fixed: Reply-To comes from a setting validated by `is_email`, and From from configuration or the home host.</li></ul> |
| A06 Insecure Design | **Pass with notes** | <ul><li>Protections are layered: the read models, the exposure filter, the state machine as the only writer of `pub_state`, and the audit log for staff actions.</li><li>Every public form has Turnstile or a honeypot, a signed time token, and per-IP and per-email throttles with uniform replies.</li><li>Demo removal keeps items that were edited after import.</li><li>The import zip is extracted under names we choose.</li><li>Notes: SEC-07, SEC-10 and SEC-16.</li></ul> |
| A07 Authentication Failures | **Pass with owner decision** after SEC-01 | <ul><li>Lockout per account and per address, with generic errors.</li><li>Strong passwords required for staff, plus a 60-minute idle timeout in the admin.</li><li>Sessions are revoked when a role or capability changes.</li><li>Two-factor is optional by owner decision (SEC-13).</li></ul> |
| A08 Software or Data Integrity Failures | **Pass** | <ul><li>Import images are checked against their sha256 (`hash_equals`), their real type (`getimagesize`), size, and the licence allowlist.</li><li>The contract version is checked, and records are validated before apply.</li><li>Plugin packages come only from wordpress.org over https.</li><li>Neither the release build nor the import uses unserialize on external data.</li></ul> |
| A09 Security Logging and Alerting Failures | **Pass** | <ul><li>`AuditLogger` records staff actions on profiles, labels, imports, setup, the Turnstile keys and the database update.</li><li>The lockout event carries the account id only.</li><li>The Logger scrubs personal data through the PII catalogue, and mail errors are scrubbed of addresses and credentials.</li><li>See SEC-05 and SEC-08 for where logs are kept.</li></ul> |
| A10 Mishandling of Exceptional Conditions | **Pass** after SEC-02 | <ul><li>Import failures discard the staged folder, and handles are closed in `finally` (SEC-02).</li><li>The database update stops at the failing step and can be resumed.</li><li>Error messages shown to staff are plain text, with no stack traces and no personal data.</li><li>Unknown or expired tokens give one uniform answer.</li></ul> |

## 7. Dependency audit (2026-11-14)

- **`composer audit`** (in `site/`): "No security vulnerability advisories found."
- **`npm audit --audit-level=high`** (in `site/`): exit 0. It found 3 moderate advisories (GHSA-g3hv-75wg-m249, `minimatch` ReDoS), pulled in through `eslint` and `@eslint/config-array`, which are development tools only and not in the release. Recommendation: `npm audit fix` in a routine dependency update.

## 8. Recommendations for go-live

1. **WAF and Cloudflare:**
   - Put the site behind Cloudflare, or the host's WAF, with the managed WordPress rules.
   - Add a rate rule on `POST /wp-login.php`, `/wp-admin/admin-ajax.php` and the public form endpoints.
   - Restore the real client IP at the web server first (SEC-09).
   - Keep Turnstile keys saved on the Setup screen, so that every public form has the bot challenge.
2. **Backups:**
   - Take daily off-site backups of the database and `wp-content/uploads` (cPanel JetBackup or the host's equivalent), keep at least 14 days, and test one restore before launch.
   - Take a backup before every "Update the database" press. The screen already asks for this.
3. **File permissions:**
   - Folders 755 and files 644. The secrets file 600, kept outside `public_html`.
   - `wp-config.php` 640 or 600 where the host allows it.
   - The private import folder (`wiw-private`) outside `public_html`.
   - After launch, confirm that `/wp-content/uploads/wiw-import/`, `/wp-content/uploads/wiw-private/` and `/wp-content/wiw-logs/` return 403 (SEC-08).
4. **Keep the guard:** `whoiswho-guard` is a must-use plugin and must stay in `wp-content/mu-plugins/`. Removing it turns off the headers, the sign-in protection, the upload rules and the exposure filter.
5. **Two-factor for staff:** every Administrator and Editor enables the Two Factor plugin (an authenticator app) before go-live (SEC-13).
6. **`DISALLOW_FILE_EDIT`:**
   - Once the required plugins are installed, ask the host to set `define( 'DISALLOW_FILE_EDIT', true );` in `wp-config.php`, which cPanel's WordPress Toolkit can do.
   - Ideally also set `DISALLOW_FILE_MODS`, lifted only for planned updates.
   - Until the owner decides SEC-12, this is the way to close the admin code editor.
7. **Scanner:** connect a real malware scanner through the `wiw_guard_file_scanner` filter (for example, the host's ClamAV), so that uploads and sideloads are scanned.
8. **Monitoring:**
   - Point an uptime monitor at the health endpoint with the `X-Health-Token` header.
   - Review the audit log weekly.

## 9. Gate results on `security/review-fixes`

All gates were run in `site/` on 2026-11-14, on top of `830f9e4`.

| Gate | Result |
|---|---|
| `composer lint` (PHPCS, WordPress standards, `COMPOSER_PROCESS_TIMEOUT=0`) | 0 errors, 950 of 950 files |
| `composer analyse` (PHPStan 2.3.5) | `[OK] No errors` |
| `vendor/bin/phpunit` on an empty `wiw_test_b` (recreated first) | OK: 1733 tests, 14660 assertions, 3 skipped, 12:28 |
| `vendor/bin/phpunit tests/Arch -d memory_limit=1G` | OK: 29 tests, 713 assertions, 1 skipped |
| `node scripts/check-brand-values.js` | OK |
| `composer audit` | No advisories |
| `npm audit --audit-level=high` | 0 high or critical (3 moderate, development only) |

`Performance\SearchAt10000Test`, which is known to be timing-flaky, passed in this run and was not changed. The 1000-record import benchmark took 74.1 s in total over 69 requests of about 1 s each, peaking at 514 MB of memory.

**Note on line endings:** this worktree has `core.autocrlf=true`, so checking out a branch writes CRLF files and PHPCS then reports `Generic.Files.LineEndings` on every file. The lint above ran after re-checking out `site/` with `core.autocrlf=false`. The committed files are LF, as `.gitattributes` requires.

