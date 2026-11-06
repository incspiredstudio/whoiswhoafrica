#!/usr/bin/env bash
# Usage: scripts/set-status.sh <issue#> "<Todo|In Progress|Review|Done>"
# Warns (never fails hard) if the project board is missing.
warn() { echo "warn: $*" >&2; exit 0; }
[ $# -eq 2 ] || warn "usage: set-status.sh <issue#> \"<status>\""
ISSUE="$1"; STATUS="$2"
command -v gh >/dev/null || warn "gh not installed"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
[ -f "$ROOT/.project-number" ] || warn ".project-number missing; board not set up"
NUM="$(tr -d '[:space:]' < "$ROOT/.project-number")"
OWNER="$(gh repo view --json owner -q .owner.login 2>/dev/null)" || warn "cannot resolve repo owner"
PID="$(gh project view "$NUM" --owner "$OWNER" --format json -q .id 2>/dev/null)" || warn "project $NUM not found"
FJ="$(gh project field-list "$NUM" --owner "$OWNER" --format json 2>/dev/null)" || warn "cannot list fields"
OID="$(echo "$FJ" | jq -r --arg s "$STATUS" '.fields[]|select(.name=="Status")|.options[]|select(.name==$s)|.id')"
[ -n "$FID" ] && [ -n "$OID" ] || warn "Status option '$STATUS' not found on board"
IID="$(gh project item-list "$NUM" --owner "$OWNER" --limit 500 --format json 2>/dev/null | jq -r --argjson n "$ISSUE" '.items[]|select(.content.number==$n)|.id')"
if [ -z "$IID" ]; then
  URL="$(gh issue view "$ISSUE" --json url -q .url 2>/dev/null)" || warn "issue #$ISSUE not found"
  IID="$(gh project item-add "$NUM" --owner "$OWNER" --url "$URL" --format json -q .id 2>/dev/null)" || warn "could not add issue to board"
fi
gh project item-edit --id "$IID" --project-id "$PID" --field-id "$FID" --single-select-option-id "$OID" >/dev/null 2>&1 || warn "item-edit failed"
echo "#$ISSUE -> $STATUS"
