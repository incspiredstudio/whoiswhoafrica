# Home hero media

The four clips behind the home page title (`hero-1` to `hero-4`) and their still posters. The theme reads them from this folder
(`wiw_home_hero_videos()` in the parent theme's `inc/home.php`); they are not in the WordPress media library.

## Files

| File | What |
|---|---|
| `hero-N.mp4` | H.264 high profile, 540x960, 24 fps, no audio track, `+faststart`, a keyframe every 2 s. Always present; the fallback. |
| `hero-N.webm` | AV1 in WebM, same picture, only for clips where it is clearly smaller (2, 3 and 4). Listed first, so only browsers that can decode it fetch it. |
| `hero-N-360.{avif,webp,jpg}`, `hero-N-540.{avif,webp,jpg}` | First frame of the clip. The 540 JPEG is the `<img>` fallback; clip 1's poster is the page's largest contentful paint. |

## Size (bytes)

| Clip | Original | MP4 now | WebM now |
|---|---|---|---|
| 1 | 1,423,641 | 495,271 | none (MP4 is smaller at the same look) |
| 2 | 2,872,143 | 823,927 | 515,645 |
| 3 | 538,354 | 315,589 | 223,362 |
| 4 | 370,302 | 216,247 | 101,577 |
| Total | 5,204,440 | 1,851,034 | about 1.34 MB for a browser that takes the WebM (clip 1 stays MP4) |

## Regenerate

The originals stay in `prototype/theme/video/` and are never copied into the theme. From the repository root:

```
FFMPEG_BIN=/path/to/ffmpeg/bin bash site/bin/optimise-hero-video.sh [SRC_DIR] [OUT_DIR]
```

`FFMPEG_BIN` is the folder with `ffmpeg` (leave it unset when `ffmpeg` is on the PATH); it needs libx264, libsvtav1, libwebp and
libaom-av1. The per-clip quality values (CRF) are at the top of the script. They were chosen by SSIM against the original (0.87 to
0.98) and by comparing frames side by side on the darkened hero. Lower CRF means better quality and bigger files.

## Loading (home.js)

Every `<video>` is `muted loop playsinline preload="none"` with no `autoplay` and no `controls`, so nothing is fetched at load.
After the page has loaded, `home.js` starts clip 1, then each next clip only once the one before it is playing. Reduced motion, Data
Saver and 2G connections leave the posters only. Clips pause when the hero leaves the screen or the tab is hidden.

## Licences

The owner has not yet confirmed the licences for these clips (issue #8). Nothing here claims they are licensed. Do not launch with
them until that is settled.
