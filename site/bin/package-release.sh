#!/usr/bin/env bash
# Build the install bundle (REQ-046, REQ-056): the zip files for installing the site on a plain WordPress or WordPress Studio.
#
#   bin/package-release.sh [--out DIR] [--skip-assets]      (npm run package does the same)
#
# Writes, into build/install-bundle/ (git-ignored) or --out:
#   whoiswho-install-bundle/whoiswho.zip              the parent theme, with its built assets inside it
#   whoiswho-install-bundle/whoiswho-child.zip        the child theme (brand values; the theme to activate)
#   whoiswho-install-bundle/whoiswho-core.zip         the core plugin, with Action Scheduler, Two Factor and User Role Editor in bundled/
#   whoiswho-install-bundle/whoiswho-guard-mu-plugin.zip   the must-use plugin: unzip into wp-content/mu-plugins (not uploaded in wp-admin)
#   whoiswho-install-bundle/wiw.php                   the command line runner: copy it into the WordPress folder (beside wp-load.php)
#   whoiswho-install-bundle/INSTALL.md, SHA256SUMS.txt
# No tests, dev tools, .env files, wp-config or secrets go in. The page cache plugin (WP Super Cache) is optional and is not bundled
# (it must live in wp-content/plugins/wp-super-cache; see INSTALL.md).
# Needs: tar, php with the zip extension, and (unless --skip-assets) node. Run "composer install" and "npm ci" first.
# Environment: WIW_PHP names the php command (default "php").
# Exit codes: 0 built, 1 a rule failed, 2 bad usage.

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE="$(cd "$HERE/.." && pwd)"
OUT=""
SKIP_ASSETS=0
PHP="${WIW_PHP:-php}"

while [ $# -gt 0 ]; do
	case "$1" in
		--out) OUT="$2"; shift 2 ;;
		--skip-assets) SKIP_ASSETS=1; shift ;;
		*) echo "package-release: unknown option $1" >&2; exit 2 ;;
	esac
done

fail() { echo "package-release: FAILED: $1" >&2; exit 1; }

[ -n "$OUT" ] || OUT="$SOURCE/build/install-bundle"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"

PLUGINS="$SOURCE/wp-content/plugins"
THEMES="$SOURCE/wp-content/themes"
MU="$SOURCE/wp-content/mu-plugins"

[ -f "$PLUGINS/whoiswho-core/whoiswho-core.php" ] || fail "the whoiswho-core plugin is missing"
[ -f "$THEMES/whoiswho/style.css" ] || fail "the parent theme is missing"
[ -f "$THEMES/whoiswho-child/style.css" ] || fail "the child theme is missing"
[ -f "$MU/whoiswho-guard.php" ] || fail "the whoiswho-guard mu-plugin is missing"
for slug in action-scheduler two-factor user-role-editor; do
	[ -d "$PLUGINS/$slug" ] || fail "$slug is not installed in wp-content/plugins: run composer install first"
done

VERSION="$(sed -n 's/^ \* Version: *//p' "$PLUGINS/whoiswho-core/whoiswho-core.php" | head -n 1 | tr -d '\r')"
[ -n "$VERSION" ] || fail "cannot read the plugin version"
COMMIT="$(git -C "$SOURCE" rev-parse --short HEAD 2>/dev/null || echo unknown)"

# Built assets (the theme's CSS and JavaScript). The theme zip carries them in its own dist/ folder.
if [ "$SKIP_ASSETS" -eq 0 ]; then
	(cd "$SOURCE" && npm run build) || fail "the asset build failed (run npm ci first)"
fi
[ -f "$SOURCE/dist/manifest.json" ] || fail "dist/manifest.json is missing: run npm run build (or drop --skip-assets)"

STAGE="$OUT/stage"
BUNDLE="$OUT/whoiswho-install-bundle"
rm -rf "$STAGE" "$BUNDLE"
mkdir -p "$STAGE" "$BUNDLE"

# copy_tree <from> <to>: a folder without tests, dev files, version control or secrets.
copy_tree() {
	local from="$1" to="$2"
	mkdir -p "$to"
	(cd "$from" && tar --exclude='./node_modules' --exclude='./tests' --exclude='./.github' --exclude='.git' --exclude='.env' --exclude='.env.*' --exclude='*.env' --exclude='*.log' --exclude='.DS_Store' --exclude='./wp-config.php' -cf - .) | (cd "$to" && tar -xf -)
}

# The core plugin and the third-party plugin code it loads from bundled/ (Platform\ThirdParty).
copy_tree "$PLUGINS/whoiswho-core" "$STAGE/whoiswho-core"
rm -rf "$STAGE/whoiswho-core/bundled"
for slug in action-scheduler two-factor user-role-editor; do
	copy_tree "$PLUGINS/$slug" "$STAGE/whoiswho-core/bundled/$slug"
done

# The themes. The parent carries dist/ so it works wherever it is installed.
copy_tree "$THEMES/whoiswho" "$STAGE/whoiswho"
rm -rf "$STAGE/whoiswho/dist"
copy_tree "$SOURCE/dist" "$STAGE/whoiswho/dist"
copy_tree "$THEMES/whoiswho-child" "$STAGE/whoiswho-child"

# The must-use plugin: a loader file and its folder, to be unzipped into wp-content/mu-plugins.
mkdir -p "$STAGE/mu"
cp "$MU/whoiswho-guard.php" "$STAGE/mu/whoiswho-guard.php"
copy_tree "$MU/whoiswho-guard" "$STAGE/mu/whoiswho-guard"

# Safety scan: nothing that must not ship.
BAD="$(find "$STAGE" \( -name '.env' -o -name '.env.*' -o -name '*.env' -o -name 'wp-config.php' -o -name '*.log' -o -name '.git' -o -name 'node_modules' -o -name 'phpunit*.xml*' \) -not -name '.env.example' 2>/dev/null | head -n 5)"
[ -z "$BAD" ] || fail "a file that must not ship is in the bundle: $BAD"
[ ! -d "$STAGE/whoiswho-core/tests" ] || fail "tests are in the core plugin"
find "$STAGE/whoiswho-core/bundled" -maxdepth 2 -name tests | grep -q . && fail "tests are in a bundled plugin"

"$PHP" "$HERE/zip-dir.php" "$STAGE" "$BUNDLE/whoiswho.zip" whoiswho
"$PHP" "$HERE/zip-dir.php" "$STAGE" "$BUNDLE/whoiswho-child.zip" whoiswho-child
"$PHP" "$HERE/zip-dir.php" "$STAGE" "$BUNDLE/whoiswho-core.zip" whoiswho-core
"$PHP" "$HERE/zip-dir.php" "$STAGE/mu" "$BUNDLE/whoiswho-guard-mu-plugin.zip" whoiswho-guard.php whoiswho-guard

cp "$SOURCE/bin/wiw.php" "$BUNDLE/wiw.php"
sed "s/@VERSION@/$VERSION/g; s/@COMMIT@/$COMMIT/g" "$SOURCE/deploy/install-bundle/INSTALL.md" > "$BUNDLE/INSTALL.md"
(cd "$BUNDLE" && { sha256sum ./*.zip 2>/dev/null || shasum -a 256 ./*.zip; } | sed 's# \./# #' > SHA256SUMS.txt)

rm -rf "$STAGE"
echo "package-release: built version $VERSION ($COMMIT) into $BUNDLE"
ls -l "$BUNDLE"
