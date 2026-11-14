#!/usr/bin/env bash
# Self-test for deploy/build-release (M0-INF-12; REQ-273, REQ-056). Builds a tiny fake site with --skip-composer and
# --skip-assets, so it runs offline in seconds. Run: bash deploy/tests/build-release.test.sh
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT="$HERE/../build-release"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0
FAILS=0

ok() { PASS=$((PASS + 1)); echo "ok   - $1"; }
bad() { FAILS=$((FAILS + 1)); echo "FAIL - $1"; }
check() { if "${@:2}" >/dev/null 2>&1; then ok "$1"; else bad "$1"; fi; }
check_not() { if "${@:2}" >/dev/null 2>&1; then bad "$1"; else ok "$1"; fi; }

# A minimal site the script accepts.
make_site() {
	local d="$1"
	mkdir -p "$d/wp-content/themes/whoiswho" "$d/wp-content/themes/whoiswho-child" "$d/wp-content/plugins/whoiswho-core/src" "$d/wp-content/plugins/whoiswho-core/tests" "$d/wp-content/mu-plugins/whoiswho-guard" "$d/bin" "$d/dist" "$d/config"
	printf '/*\nTheme Name: WHOISWHO\n*/\n' > "$d/wp-content/themes/whoiswho/style.css"
	printf '/*\nTheme Name: WHOISWHO Child\nTemplate: whoiswho\n*/\n' > "$d/wp-content/themes/whoiswho-child/style.css"
	echo '<?php' > "$d/wp-content/plugins/whoiswho-core/whoiswho-core.php"
	echo '<?php' > "$d/wp-content/plugins/whoiswho-core/tests/FooTest.php"
	echo '<?php' > "$d/wp-content/mu-plugins/whoiswho-guard.php"
	echo '<?php' > "$d/wp-content/mu-plugins/whoiswho-guard/Guard.php"
	echo '<?php' > "$d/bin/wiw.php"
	echo '{}' > "$d/dist/manifest.json"
	echo '{}' > "$d/config/plugin-allowlist.json"
	echo '<?php' > "$d/index.php"
	echo '<?php' > "$d/wp-config.php"
	echo '{}' > "$d/composer.json"
	echo '{}' > "$d/composer.lock"
	# Things that must never ship.
	echo 'SECRET=1' > "$d/wp-content/themes/whoiswho-child/.env"
	echo 'SECRET=1' > "$d/wp-content/plugins/whoiswho-core/local.env"
}

build() { bash "$SCRIPT" --source "$1" --out "$2" --version "$3" --skip-composer --skip-assets --no-archive; }

# 1. A good site builds, with both themes, the stamp and nothing forbidden.
SITE="$WORK/good"; make_site "$SITE"
OUT="$WORK/out1"
if build "$SITE" "$OUT" 1.2.3 >"$WORK/log1" 2>&1; then ok "a complete site builds"; else bad "a complete site builds"; cat "$WORK/log1"; fi
A="$OUT/whoiswho-1.2.3"
check "the artifact has the parent theme" test -f "$A/wp-content/themes/whoiswho/style.css"
check "the artifact has the child theme" test -f "$A/wp-content/themes/whoiswho-child/style.css"
check "the artifact has the plugin and the guard" test -f "$A/wp-content/plugins/whoiswho-core/whoiswho-core.php" -a -f "$A/wp-content/mu-plugins/whoiswho-guard.php"
check "the artifact has built assets" test -f "$A/dist/manifest.json"
check "the version stamp says the version" grep -q '"version": "1.2.3"' "$A/release.json"
check "the version stamp has a commit and a time" grep -Eq '"built_at": "[0-9]{4}-' "$A/release.json"
if [ -z "$(find "$A" \( -name '.env' -o -name '.env.*' -o -name '*.env' \))" ]; then ok "no .env file in the artifact"; else bad "no .env file in the artifact"; fi
check_not "no tests folder in the artifact" test -d "$A/wp-content/plugins/whoiswho-core/tests"
check_not "no composer files left in the artifact" test -f "$A/composer.json"

# 1b. The demo profiles (REQ-208): a local sample/ folder never ships; SAMPLE_BUNDLE adds the named zip under sample/.
SITES="$WORK/sample"; make_site "$SITES"; mkdir -p "$SITES/wp-content/plugins/whoiswho-core/sample"; echo 'local' > "$SITES/wp-content/plugins/whoiswho-core/sample/records.jsonl"
build "$SITES" "$WORK/outs1" 1.0.0 >/dev/null 2>&1 || true
check_not "a local sample folder is not in the artifact" test -e "$WORK/outs1/whoiswho-1.0.0/wp-content/plugins/whoiswho-core/sample"
echo 'zip' > "$WORK/sample-50.zip"
SAMPLE_BUNDLE="$WORK/sample-50.zip" build "$SITES" "$WORK/outs2" 1.0.0 >/dev/null 2>&1 || true
check "SAMPLE_BUNDLE adds sample/sample-50.zip" test -f "$WORK/outs2/whoiswho-1.0.0/wp-content/plugins/whoiswho-core/sample/sample-50.zip"
check "the sample folder is closed to the web" grep -q 'Require all denied' "$WORK/outs2/whoiswho-1.0.0/wp-content/plugins/whoiswho-core/sample/.htaccess"
check_not "the local sample copy is not added" test -e "$WORK/outs2/whoiswho-1.0.0/wp-content/plugins/whoiswho-core/sample/records.jsonl"
check_not "a SAMPLE_BUNDLE that does not exist fails the build" env SAMPLE_BUNDLE="$WORK/missing.zip" bash "$SCRIPT" --source "$SITES" --out "$WORK/outs3" --version 1.0.0 --skip-composer --skip-assets --no-archive

# 2. A missing child theme fails.
SITE2="$WORK/nochild"; make_site "$SITE2"; rm -rf "$SITE2/wp-content/themes/whoiswho-child"
check_not "a missing child theme fails the build" build "$SITE2" "$WORK/out2" 1.0.0

# 3. A missing parent theme fails.
SITE3="$WORK/noparent"; make_site "$SITE3"; rm -rf "$SITE3/wp-content/themes/whoiswho"
check_not "a missing parent theme fails the build" build "$SITE3" "$WORK/out3" 1.0.0

# 4. A child that does not declare Template: whoiswho fails.
SITE4="$WORK/notemplate"; make_site "$SITE4"; printf '/*\nTheme Name: Child\n*/\n' > "$SITE4/wp-content/themes/whoiswho-child/style.css"
check_not "a child without Template: whoiswho fails the build" build "$SITE4" "$WORK/out4" 1.0.0

# 5. A missing lockfile fails (releases install from the lockfile).
SITE5="$WORK/nolock"; make_site "$SITE5"; rm "$SITE5/composer.lock"
check_not "a missing lockfile fails the build" build "$SITE5" "$WORK/out5" 1.0.0

# 6. A bad version label is refused.
check_not "a version with a slash is refused" build "$SITE" "$WORK/out6" "../x"

# 7. A forbidden file added to the built tree is caught: simulate through a theme asset named like a dev tool.
SITE7="$WORK/leak"; make_site "$SITE7"; echo 'x' > "$SITE7/wp-content/themes/whoiswho/phpunit.xml"
check_not "a dev file inside a theme fails the build" build "$SITE7" "$WORK/out7" 1.0.0

# 8. The archive is made when asked.
if bash "$SCRIPT" --source "$SITE" --out "$WORK/out8" --version 2.0.0 --skip-composer --skip-assets >/dev/null 2>&1; then ok "the build with an archive succeeds"; else bad "the build with an archive succeeds"; fi
check "the archive exists" test -f "$WORK/out8/whoiswho-2.0.0.tar.gz"

echo "$PASS passed, $FAILS failed"
[ "$FAILS" -eq 0 ]
