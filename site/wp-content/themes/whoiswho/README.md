# whoiswho (parent theme)

Structure and components. No brand values: colours, fonts and the logo live in the child theme `whoiswho-child` (the active theme) and its tokens file. The parent is never activated alone; if it is, an admin notice says so.

## Rules

- Templates read profiles and stories only through the plugin read models (`PublicProfile`, `PublicStory`). They never call `get_post_meta` or `WP_Query` on the profile type, and they never decide visibility. Visibility is decided in the plugin and the guard.
- No colour, font or brand name literal in this theme. Use tokens (`var(--wiw-...)`) defined by the child.
- Escape all output (`esc_*`, `wp_kses` with an allowlist). No jQuery on the front end.
- Folders: `templates/`, `template-parts/`, `patterns/`, `inc/` (loaded from `functions.php`), `assets/`, `emails/`.
