#!/usr/bin/env bash
# Put the demo profiles into a staged copy of the core plugin (REQ-208, REQ-056). Called by deploy/build-release and
# bin/package-release.sh after they copy whoiswho-core.
#
#   deploy/add-sample-bundle.sh <staged whoiswho-core folder>
#
# A local sample/ folder (a developer's test copy) never ships: it is always removed first. Then, only when SAMPLE_BUNDLE names a
# .zip (for example SAMPLE_BUNDLE=D:/github/whoiswhoafrica/acquisition/data/exports/sample-50.zip), that file is copied in as
# sample/sample-50.zip with an index.php and a .htaccess that stop the web server from listing or serving it. The Setup screen
# imports it through the normal import path. The bundle itself is never committed to the repository.
# Exit codes: 0 done, 1 SAMPLE_BUNDLE is set but unusable, 2 bad usage.

set -euo pipefail

PLUGIN="${1:-}"
[ -n "$PLUGIN" ] && [ -f "$PLUGIN/whoiswho-core.php" ] || { echo "add-sample-bundle: usage: add-sample-bundle.sh <staged whoiswho-core folder>" >&2; exit 2; }

rm -rf "$PLUGIN/sample"
if [ -z "${SAMPLE_BUNDLE:-}" ]; then
	echo "add-sample-bundle: no SAMPLE_BUNDLE, so the plugin ships without sample profiles (the demo uses invented ones)"
	exit 0
fi
case "$SAMPLE_BUNDLE" in
	*.zip) ;;
	*) echo "add-sample-bundle: SAMPLE_BUNDLE must name a .zip file" >&2; exit 1 ;;
esac
[ -f "$SAMPLE_BUNDLE" ] || { echo "add-sample-bundle: SAMPLE_BUNDLE does not exist: $SAMPLE_BUNDLE" >&2; exit 1; }

mkdir -p "$PLUGIN/sample"
cp "$SAMPLE_BUNDLE" "$PLUGIN/sample/sample-50.zip"
printf '<?php\n// Silence is golden.\n' > "$PLUGIN/sample/index.php"
printf '# The demo bundle is read by the plugin, never served.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n' > "$PLUGIN/sample/.htaccess"
echo "add-sample-bundle: added $(basename "$SAMPLE_BUNDLE") as sample/sample-50.zip"
