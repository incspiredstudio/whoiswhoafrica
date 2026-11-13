#!/usr/bin/env bash
# Re-encode the home hero clips and make their still posters (issue "Optimise the home hero videos and loading", REQ-001, REQ-041).
#
#   bash site/bin/optimise-hero-video.sh [SRC_DIR] [OUT_DIR]
#
# SRC_DIR  originals hero-1.mp4 .. hero-4.mp4 (default prototype/theme/video; they never go into the theme)
# OUT_DIR  where the theme serves them (default site/wp-content/themes/whoiswho-child/assets/home)
# FFMPEG_BIN  folder holding ffmpeg and ffprobe when they are not on PATH (needs libx264, libsvtav1, libwebp, libaom-av1)
#
# Per clip: an H.264 MP4 (high profile, faststart, no audio, a keyframe every 2 s) and, only when it is clearly smaller at the same
# look, an AV1 WebM; a first-frame poster at 360 and 540 px wide in AVIF, WebP and JPEG. The CRF values below were tuned by SSIM and
# by eye on the darkened hero (see the README in OUT_DIR). Lower CRF = better quality and bigger files.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SRC="${1:-$ROOT/prototype/theme/video}"
OUT="${2:-$ROOT/site/wp-content/themes/whoiswho-child/assets/home}"
if [ -n "${FFMPEG_BIN:-}" ]; then FF="$FFMPEG_BIN/ffmpeg"; else FF="ffmpeg"; fi

# clip number : H.264 CRF : AV1 CRF (0 = no WebM for that clip, the MP4 is smaller at the same look)
CLIPS=("1:38:0" "2:38:62" "3:36:58" "4:36:60")

mkdir -p "$OUT"
for spec in "${CLIPS[@]}"; do
	IFS=: read -r n h264 av1 <<<"$spec"
	in="$SRC/hero-$n.mp4"
	[ -r "$in" ] || { echo "missing $in" >&2; exit 1; }

	"$FF" -v error -y -i "$in" -an -c:v libx264 -profile:v high -preset slow -crf "$h264" \
		-g 48 -keyint_min 48 -sc_threshold 0 -pix_fmt yuv420p -movflags +faststart "$OUT/hero-$n.mp4"

	rm -f "$OUT/hero-$n.webm"
	if [ "$av1" != "0" ]; then
		"$FF" -v error -y -i "$in" -an -c:v libsvtav1 -crf "$av1" -preset 6 -g 48 -pix_fmt yuv420p "$OUT/hero-$n.webm"
	fi

	# First frame of the clip, native size and a 360 px wide copy, three formats each.
	"$FF" -v error -y -i "$in" -frames:v 1 "$OUT/.frame-$n.png"
	for w in 360 540; do
		scale="scale=$w:-2:flags=lanczos"
		"$FF" -v error -y -i "$OUT/.frame-$n.png" -vf "$scale" -q:v 5 "$OUT/hero-$n-$w.jpg"
		"$FF" -v error -y -i "$OUT/.frame-$n.png" -vf "$scale" -c:v libwebp -quality 72 "$OUT/hero-$n-$w.webp"
		"$FF" -v error -y -i "$OUT/.frame-$n.png" -vf "$scale" -c:v libaom-av1 -crf 34 -cpu-used 4 -still-picture 1 "$OUT/hero-$n-$w.avif"
	done
	rm -f "$OUT/.frame-$n.png"
	echo "clip $n done"
done

echo "Sizes (bytes):"
wc -c "$OUT"/hero-* | sort -k2
