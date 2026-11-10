# Guard

The must-use plugin `whoiswho-guard`. Its single purpose is to make it impossible for a profile that is not public to appear on any surface, and to harden the install.

## Responsibility

A must-use plugin loads before ordinary plugins and cannot be deactivated from wp-admin. That is why the safety rules live here and not in `whoiswho-core`. If the core plugin or a third-party plugin misbehaves, the guard still holds.

## Content types, taxonomies and tables

None. The guard owns no data. It registers the non-public custom post status `wiw_hidden` and the filters below.

## Admin screens

None.

## Capabilities

None of its own. It enforces other modules' capabilities where a filter must override them (for example, fixed capabilities).

## Public read models and endpoints

The guard emits all SEO and crawl output itself, from the read models, so no other plugin can add an entry for a hidden record.

| Output | Rule |
|---|---|
| `sitemap.xml`, `robots.txt` | Built from public states only. Pending-review profiles have no sitemap entry |
| Canonical URLs, meta description, Open Graph tags | From the read model |
| Structured data (Person or Organization) | Only for reviewed profiles |
| `X-Robots-Tag: noindex` and robots meta | On Published, review pending pages |
| Security headers | Set for every response |

## Jobs, hooks and events

| Hook | Behaviour |
|---|---|
| `wp_insert_post_data` | Refuse any change to a profile's post status or publication state that does not come from `Review\StateMachine`, and log the refusal |
| REST API | Core REST requires a signed-in staff user. There is no public REST route |
| Feeds, core sitemaps, oEmbed | Turned off for profiles (and for all hidden content) |
| Author and attachment archive pages | Not public |
| Preview, `?p=ID`, `?page_id=ID` shortlinks | Return the same answer as the real page: 404 or 410 for anything not public |
| Core search | Does not return profiles. Search runs through the plugin's index |
| Registration and application passwords | Closed and off |

## Business rules

- **Hardening.** File editing and file modification are disabled in production. Automatic plugin updates are off: updates ship through the build pipeline. `wp-config.php` sits outside the web root. Directory listing is off. PHP does not run in uploads (server configuration).
- **No SEO plugin.** One would generate its own sitemap, feeds and schema, and could leak a hidden record.
- **Every plugin is re-tested.** Adding any plugin means re-running the visibility matrix.

## Tests that must exist

- `test_REQ_208_visibility_matrix_bare_install` (one profile per state, every surface: page, lists, search, `sitemap.xml`, core sitemaps, feeds, REST, oEmbed, shortlinks, attachment pages, author archives, previews, share image, cache headers).
- The same matrix again after each third-party plugin is added.
- `test_REQ_210_direct_status_write_is_refused_and_logged`
- `test_REQ_217_pending_profile_has_noindex_and_no_sitemap_entry`
- `test_REQ_229_rest_requires_staff_login`
- `test_registration_closed_and_application_passwords_off`

## Depends on

Review (`StateMachine`, the state values), Directory (read models). The guard calls them; they do not call the guard.

## Requirement ids covered

REQ-044, 046, 121, 208, 210, 217, 229.
