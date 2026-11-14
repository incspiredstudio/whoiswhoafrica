"""Builds the brand pattern tile from the four approved Adinkra marks (REQ-273, issue 808).

The brand guide's pattern rule: the marks in varying shades of a single colour, in a clean, balanced and consistent
arrangement. The tile is an alpha-only image (black at varying opacity): the theme uses it as a CSS mask and paints the
colour through a token, so one file serves light, dark and coloured surfaces.

Only these four marks may appear in a pattern: Adinkrahene (mark-star), Dwennimmen (mark-comb), Nkonsonkonson
(mark-link) and Sankofa (mark-spiral). See docs/theme/brand-and-tokens.md, "Approved symbols".

Run from the repository root (needs Python 3 and Pillow):
    python site/build/pattern-tile.py
It writes site/wp-content/themes/whoiswho-child/assets/brand/pattern-marks.webp. The output is deterministic.
"""

from pathlib import Path

from PIL import Image, ImageChops

BRAND = Path(__file__).resolve().parent.parent / "wp-content" / "themes" / "whoiswho-child" / "assets" / "brand"
MARKS = ["star", "comb", "link", "spiral"]

TILE = 640  # pixels; the CSS shows it at 320 px or less, so it stays sharp on 2x screens
CELLS = 4  # 4 x 4 cells, every mark once per row and per column
MARK = 74  # mark size in pixels inside a 160 px cell: generous space keeps the pattern quiet
# Shades of one colour: each row and each column holds every shade once (a Latin square), so no corner looks heavier.
BOX = round(MARK * 1.3)  # room for a wide mark
SHADES = [1.0, 0.55, 0.78, 0.38]


def mark(name: str) -> Image.Image:
    """The mark as an alpha mask of BOX x BOX pixels, trimmed to its drawn area so every mark has the same visual size."""
    src = Image.open(BRAND / f"mark-{name}.webp").convert("RGBA")
    alpha = src.getchannel("A")
    box = alpha.getbbox()
    alpha = alpha.crop(box) if box else alpha
    w, h = alpha.size
    # Size by area, not by the longer side, so the wide, low chain (mark-link) does not look smaller than the round marks.
    scale = min(1.3 * MARK / max(w, h), 0.9 * MARK / (w * h) ** 0.5)
    alpha = alpha.resize((max(1, round(w * scale)), max(1, round(h * scale))), Image.LANCZOS)
    box = BOX
    out = Image.new("L", (box, box), 0)
    out.paste(alpha, ((box - alpha.size[0]) // 2, (box - alpha.size[1]) // 2))
    return out


def main() -> None:
    cell = TILE // CELLS
    masks = {n: mark(n) for n in MARKS}
    alpha = Image.new("L", (TILE, TILE), 0)
    for row in range(CELLS):
        for col in range(CELLS):
            name = MARKS[(col + row) % 4]
            shade = SHADES[(col + 3 * row) % 4]
            m = masks[name].point(lambda v, s=shade: round(v * s))
            # Brick layout: every other row moves half a cell, drawn twice where it crosses the edge so the tile repeats seamlessly.
            x = col * cell + (cell // 2 if row % 2 else 0) + (cell - BOX) // 2
            y = row * cell + (cell - BOX) // 2
            for dx in (0, -TILE):
                if 0 <= x + dx + BOX and x + dx < TILE:
                    region = alpha.crop((x + dx, y, x + dx + BOX, y + BOX))
                    alpha.paste(ImageChops.lighter(region, m), (x + dx, y))
    tile = Image.new("RGBA", (TILE, TILE), (0, 0, 0, 0))
    tile.putalpha(alpha)
    target = BRAND / "pattern-marks.webp"
    tile.save(target, "WEBP", quality=82, method=6)
    print(f"wrote {target} ({target.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
