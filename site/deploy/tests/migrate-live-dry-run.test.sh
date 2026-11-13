#!/usr/bin/env bash
# Self-test for deploy/migrate-live (M0-INF-13; REQ-056, REQ-273, REQ-095). Uses a fake release, a fake target and stub
# commands for php, mysqldump and mysql, so it runs offline in seconds and touches no database.
# Run: bash deploy/tests/migrate-live-dry-run.test.sh
set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT="$HERE/../migrate-live"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
PASS=0
FAILS=0

ok() { PASS=$((PASS + 1)); echo "ok   - $1"; }
bad() { FAILS=$((FAILS + 1)); echo "FAIL - $1"; }
has() { grep -q -- "$2" <<<"$3" && ok "$1" || { bad "$1"; echo "       wanted: $2"; }; }
hasnt() { grep -q -- "$2" <<<"$3" && { bad "$1"; echo "       unwanted: $2"; } || ok "$1"; }

# Stubs: record every call in $LOG.
LOG="$WORK/calls.log"
mkdir -p "$WORK/bin"
cat > "$WORK/bin/php" <<'EOF'
#!/usr/bin/env bash
echo "php $* [WIW_DEPLOY_MIGRATE=${WIW_DEPLOY_MIGRATE:-}]" >> "$STUB_LOG"
case "$*" in *smoke-check.php*) cat "$STUB_SMOKE_OUT"; exit "$(cat "$STUB_SMOKE_RC")";; esac
exit 0
EOF
cat > "$WORK/bin/mysqldump" <<'EOF'
#!/usr/bin/env bash
echo "mysqldump $*" >> "$STUB_LOG"
echo "-- dump"
EOF
cat > "$WORK/bin/mysql" <<'EOF'
#!/usr/bin/env bash
echo "mysql $*" >> "$STUB_LOG"
cat > /dev/null
EOF
chmod +x "$WORK/bin/"*
export STUB_LOG="$LOG" STUB_SMOKE_OUT="$WORK/smoke.out" STUB_SMOKE_RC="$WORK/smoke.rc"
echo "PASS  Child theme whoiswho-child is active" > "$STUB_SMOKE_OUT"; echo 0 > "$STUB_SMOKE_RC"

# A minimal release and target.
REL="$WORK/release"; TGT="$WORK/public_html"; SEC="$WORK/home/wiw-secrets.env"
mkdir -p "$REL/wp-content/themes/whoiswho" "$REL/wp-content/themes/whoiswho-child" "$REL/bin" "$REL/deploy" "$TGT" "$WORK/home"
printf '/*\nTheme Name: P\n*/\n' > "$REL/wp-content/themes/whoiswho/style.css"
printf '/*\nTheme Name: C\nTemplate: whoiswho\n*/\n' > "$REL/wp-content/themes/whoiswho-child/style.css"
echo '<?php' > "$REL/bin/wiw.php"; echo '<?php' > "$REL/deploy/smoke-check.php"; echo '<?php' > "$REL/wp-config.php"
echo '{"version":"t1"}' > "$REL/release.json"
printf 'DB_NAME=wiwdb\nDB_USER=wiwuser\nDB_PASSWORD=pw\nDB_HOST=localhost\nBACKUP_PASSPHRASE=pass-phrase\n' > "$SEC"
echo "old" > "$TGT/old.txt"
SQL="$WORK/site.sql"; echo "-- sql" > "$SQL"
mkdir -p "$WORK/uploads"; echo x > "$WORK/uploads/a.jpg"

run() { PATH="$WORK/bin:$PATH" bash "$SCRIPT" "$@" 2>&1; }

# 1. Dry run prints each step and changes nothing.
: > "$LOG"
OUT="$(run --dry-run --release "$REL" --target "$TGT" --secrets "$SEC" --db-import "$SQL" --uploads "$WORK/uploads" --dump-dir "$WORK/dumps" --url https://example.test)"; RC=$?
[ "$RC" -eq 0 ] && ok "dry run exits 0" || { bad "dry run exits 0 (got $RC)"; echo "$OUT"; }
for n in "1/9" "2/9" "3/9" "4/9" "5/9" "6/9" "7/9" "8/9" "9/9"; do has "dry run prints step $n" "STEP $n" "$OUT"; done
has "dry run says nothing was changed" "dry run: nothing was changed" "$OUT"
[ ! -e "$TGT/wp-config.php" ] && ok "dry run leaves the target untouched" || bad "dry run leaves the target untouched"
[ ! -s "$LOG" ] && ok "dry run runs no command" || { bad "dry run runs no command"; cat "$LOG"; }
has "the live-host checklist is printed" "SMTP sends" "$OUT"
hasnt "the passphrase is never printed" "pass-phrase" "$OUT"

# 2. A real run (against the fake target) dumps first, then migrates through the script, then smokes.
: > "$LOG"
OUT="$(run --production --release "$REL" --target "$TGT" --secrets "$SEC" --db-import "$SQL" --uploads "$WORK/uploads" --dump-dir "$WORK/dumps" --url https://example.test)"; RC=$?
[ "$RC" -eq 0 ] && ok "a real run succeeds" || { bad "a real run succeeds (got $RC)"; echo "$OUT"; }
CALLS="$(cat "$LOG")"
has "the migration runs with the deploy flag" "bin/wiw.php migrate \[WIW_DEPLOY_MIGRATE=1\]" "$CALLS"
DUMP_LINE="$(grep -n '^mysqldump' "$LOG" | head -1 | cut -d: -f1)"; MIG_LINE="$(grep -n 'migrate' "$LOG" | head -1 | cut -d: -f1)"
{ [ -n "$DUMP_LINE" ] && [ -n "$MIG_LINE" ] && [ "$DUMP_LINE" -lt "$MIG_LINE" ]; } && ok "the pre-migration dump is taken before migrating" || bad "the pre-migration dump is taken before migrating"
ls "$WORK/dumps"/*.sql.gz.enc >/dev/null 2>&1 && ok "the dump is written encrypted" || bad "the dump is written encrypted"
[ -f "$TGT/wp-config.php" ] && [ -f "$TGT/wp-content/uploads/a.jpg" ] && ok "code and uploads were copied" || bad "code and uploads were copied"
has "the database import ran" "^mysql " "$CALLS"
has "smoke checks ran" "smoke-check.php" "$CALLS"

# 3. Smoke fails the run when the child theme is not active.
echo "FAIL  Child theme whoiswho-child is active (active theme is whoiswho)" > "$STUB_SMOKE_OUT"; echo 1 > "$STUB_SMOKE_RC"
OUT="$(run --release "$REL" --target "$TGT" --secrets "$SEC" --dump-dir "$WORK/dumps" --url https://example.test)"; RC=$?
[ "$RC" -ne 0 ] && ok "a failing smoke check fails the run" || bad "a failing smoke check fails the run"
has "the failure names the rollback" "ROLLBACK" "$OUT"
echo "PASS  ok" > "$STUB_SMOKE_OUT"; echo 0 > "$STUB_SMOKE_RC"

# 4. Refusals: secrets file inside the web root, missing child theme, missing release.
cp "$SEC" "$TGT/wiw-secrets.env"
OUT="$(run --release "$REL" --target "$TGT" --secrets "$TGT/wiw-secrets.env" --dump-dir "$WORK/dumps")"; RC=$?
[ "$RC" -ne 0 ] && has "a secrets file inside the web root is refused" "outside the web root" "$OUT" || bad "a secrets file inside the web root is refused"
rm -f "$TGT/wiw-secrets.env"
BAD="$WORK/badrel"; cp -r "$REL" "$BAD"; rm -rf "$BAD/wp-content/themes/whoiswho-child"
OUT="$(run --release "$BAD" --target "$TGT" --secrets "$SEC" --dump-dir "$WORK/dumps")"; RC=$?
[ "$RC" -ne 0 ] && has "a release without the child theme is refused" "child theme" "$OUT" || bad "a release without the child theme is refused"
OUT="$(run --target "$TGT" --secrets "$SEC")"; RC=$?
[ "$RC" -eq 2 ] && ok "missing --release is a usage error (exit 2)" || bad "missing --release is a usage error (exit 2, got $RC)"

# 5. Production refuses to migrate when the dump cannot be taken.
cat > "$WORK/bin/mysqldump" <<'EOF'
#!/usr/bin/env bash
exit 3
EOF
: > "$LOG"
OUT="$(run --production --release "$REL" --target "$TGT" --secrets "$SEC" --dump-dir "$WORK/dumps2")"; RC=$?
[ "$RC" -ne 0 ] && ok "a failed dump stops the run" || bad "a failed dump stops the run"
hasnt "no migration after a failed dump" "migrate" "$(cat "$LOG")"

# 6. Rollback: redeploys the previous artifact and restores the dump.
cat > "$WORK/bin/mysqldump" <<'EOF'
#!/usr/bin/env bash
echo "-- dump"
EOF
mkdir -p "$WORK/prev"; cp -r "$REL/." "$WORK/prev/"; echo '{"version":"t0"}' > "$WORK/prev/release.json"
: > "$LOG"
OUT="$(run --rollback --release "$WORK/prev" --target "$TGT" --secrets "$SEC" --restore-dump "$(ls "$WORK"/dumps/*.sql.gz.enc | head -1)")"; RC=$?
[ "$RC" -eq 0 ] && ok "rollback succeeds" || { bad "rollback succeeds (got $RC)"; echo "$OUT"; }
grep -q '"t0"' "$TGT/release.json" && ok "rollback redeployed the previous artifact" || bad "rollback redeployed the previous artifact"
has "rollback restored the dump" "^mysql " "$(cat "$LOG")"

# 7. Releases layout: releases/<timestamp> with a current symlink.
LAY="$WORK/layout"; mkdir -p "$LAY"
OUT="$(run --releases-layout --release "$REL" --target "$LAY" --secrets "$SEC" --dump-dir "$WORK/dumps")"; RC=$?
[ "$RC" -eq 0 ] && ok "releases layout run succeeds" || { bad "releases layout run succeeds (got $RC)"; echo "$OUT"; }
ls -d "$LAY"/releases/*/ >/dev/null 2>&1 && [ -e "$LAY/current" ] && ok "releases/<timestamp> and current exist" || bad "releases/<timestamp> and current exist"

echo
echo "migrate-live self-test: $PASS passed, $FAILS failed"
[ "$FAILS" -eq 0 ]
