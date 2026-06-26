#!/usr/bin/env python3
"""Compose BillX GPS horizontal logos from the BILLX wordmark + GPS suffix."""

from __future__ import annotations

import shutil
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
WEB = ROOT / "public" / "branding" / "web"
FAVICON = ROOT / "public" / "branding" / "favicon"
MOBILE = ROOT.parent / "gps_tracker_pro_mobile_billxiot" / "assets" / "branding"

CHARCOAL = (35, 31, 32)
WHITE = (248, 250, 252)
ACCENT = (0, 174, 239)
NAVY = (30, 41, 59)

GPS_GAP = 8
GPS_SCALE = 0.42
PAD_X = 2
PAD_Y = 6


def _font(size: int) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        "C:/Windows/Fonts/segoeuib.ttf",
        "C:/Windows/Fonts/arialbd.ttf",
        "C:/Windows/Fonts/arial.ttf",
    ]
    for path in candidates:
        if Path(path).exists():
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def _recolor_wordmark(im: Image.Image, from_rgb: tuple[int, int, int], to_rgb: tuple[int, int, int]) -> Image.Image:
    src = im.convert("RGBA")
    out = Image.new("RGBA", src.size, (0, 0, 0, 0))
    for x in range(src.width):
        for y in range(src.height):
            r, g, b, a = src.getpixel((x, y))
            if a < 20:
                continue
            if r < 80 and g < 80 and b < 90:
                out.putpixel((x, y), (*to_rgb, a))
            else:
                out.putpixel((x, y), (r, g, b, a))
    return out


def compose(wordmark: Image.Image, *, light: bool) -> Image.Image:
    wordmark = wordmark.convert("RGBA")
    if not light:
        wordmark = _recolor_wordmark(wordmark, CHARCOAL, WHITE)

    gps_text = "GPS"
    font_size = max(11, int(wordmark.height * GPS_SCALE))
    font = _font(font_size)
    probe = ImageDraw.Draw(Image.new("RGBA", (1, 1)))
    bbox = probe.textbbox((0, 0), gps_text, font=font)
    gps_w = bbox[2] - bbox[0]
    gps_h = bbox[3] - bbox[1]

    out_w = PAD_X * 2 + wordmark.width + GPS_GAP + gps_w
    out_h = PAD_Y * 2 + wordmark.height
    canvas = Image.new("RGBA", (out_w, out_h), (0, 0, 0, 0))

    wx, wy = PAD_X, PAD_Y
    canvas.paste(wordmark, (wx, wy), wordmark)

    gx = wx + wordmark.width + GPS_GAP
    # Vertically center GPS with the wordmark (not bottom-aligned).
    gps_cy = wy + wordmark.height // 2
    text_x = gx - bbox[0]
    text_y = gps_cy - (bbox[1] + bbox[3]) // 2
    ImageDraw.Draw(canvas).text((text_x, text_y), gps_text, font=font, fill=(*ACCENT, 255))
    return canvas


def compose_favicon(wordmark: Image.Image, size: int) -> Image.Image:
    """Square favicon: BILLX mark on top, GPS below — readable at 16px."""
    wm = wordmark.convert("RGBA")
    canvas = Image.new("RGBA", (size, size), (*WHITE, 255))

    mark_h = int(size * 0.52)
    mark_w = int(wm.width * (mark_h / wm.height))
    mark = wm.resize((mark_w, mark_h), Image.Resampling.LANCZOS)

    gps_font = _font(max(10, int(size * 0.16)))
    probe = ImageDraw.Draw(Image.new("RGBA", (1, 1)))
    gps_bbox = probe.textbbox((0, 0), "GPS", font=gps_font)
    gps_w = gps_bbox[2] - gps_bbox[0]
    gps_h = gps_bbox[3] - gps_bbox[1]

    gap = max(2, int(size * 0.04))
    total_h = mark_h + gap + gps_h
    top = (size - total_h) // 2

    mx = (size - mark_w) // 2
    canvas.paste(mark, (mx, top), mark)

    draw = ImageDraw.Draw(canvas)
    gps_x = (size - gps_w) // 2 - gps_bbox[0]
    gps_y = top + mark_h + gap - gps_bbox[1]
    draw.text((gps_x, gps_y), "GPS", font=gps_font, fill=(*ACCENT, 255))
    return canvas


def _compose_launcher_stack(
    wordmark: Image.Image,
    size: int,
    *,
    light_mark: bool,
) -> Image.Image:
    """
    BILLX + GPS stacked for launcher icons.

    Content is scaled to ~54% of the canvas so Android adaptive masks
    (circle / squircle) do not crop the mark or GPS text.
    """
    wm = wordmark.convert("RGBA")
    if not light_mark:
        wm = _recolor_wordmark(wm, CHARCOAL, WHITE)

    content_max = int(size * 0.54)
    mark_h = int(size * 0.34)
    mark_w = int(wm.width * (mark_h / wm.height))
    mark = wm.resize((mark_w, mark_h), Image.Resampling.LANCZOS)

    gps_font = _font(max(10, int(size * 0.10)))
    probe = ImageDraw.Draw(Image.new("RGBA", (1, 1)))
    gps_bbox = probe.textbbox((0, 0), "GPS", font=gps_font)
    gps_w = gps_bbox[2] - gps_bbox[0]
    gps_h = gps_bbox[3] - gps_bbox[1]
    gap = max(2, int(size * 0.03))

    stack_w = max(mark_w, gps_w)
    stack_h = mark_h + gap + gps_h
    scale = min(1.0, content_max / stack_w, content_max / stack_h)
    if scale < 1.0:
        mark_h = max(1, int(mark_h * scale))
        mark_w = max(1, int(wm.width * (mark_h / wm.height)))
        mark = wm.resize((mark_w, mark_h), Image.Resampling.LANCZOS)
        gps_font = _font(max(8, int(size * 0.10 * scale)))
        probe = ImageDraw.Draw(Image.new("RGBA", (1, 1)))
        gps_bbox = probe.textbbox((0, 0), "GPS", font=gps_font)
        gps_w = gps_bbox[2] - gps_bbox[0]
        gps_h = gps_bbox[3] - gps_bbox[1]
        gap = max(2, int(size * 0.03 * scale))
        stack_w = max(mark_w, gps_w)
        stack_h = mark_h + gap + gps_h

    layer = Image.new("RGBA", (stack_w, stack_h), (0, 0, 0, 0))
    mx = (stack_w - mark_w) // 2
    layer.paste(mark, (mx, 0), mark)

    draw = ImageDraw.Draw(layer)
    gps_x = (stack_w - gps_w) // 2 - gps_bbox[0]
    gps_y = mark_h + gap - gps_bbox[1]
    draw.text((gps_x, gps_y), "GPS", font=gps_font, fill=(*ACCENT, 255))
    return layer


def compose_launcher_icon(
    wordmark: Image.Image,
    size: int,
    *,
    background: tuple[int, int, int],
    light_mark: bool = True,
) -> Image.Image:
    """Full square launcher bitmap (legacy mipmaps + preview)."""
    layer = _compose_launcher_stack(wordmark, size, light_mark=light_mark)
    canvas = Image.new("RGBA", (size, size), (*background, 255))
    ox = (size - layer.width) // 2
    oy = (size - layer.height) // 2
    canvas.paste(layer, (ox, oy), layer)
    return canvas


def compose_launcher_foreground(wordmark: Image.Image, size: int) -> Image.Image:
    """Transparent adaptive foreground — white mark on navy launcher background."""
    layer = _compose_launcher_stack(wordmark, size, light_mark=False)
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    ox = (size - layer.width) // 2
    oy = (size - layer.height) // 2
    canvas.paste(layer, (ox, oy), layer)
    return canvas


def compose_favicon_dark(wordmark: Image.Image, size: int) -> Image.Image:
    """Dark variant for adaptive / PWA contexts."""
    wm = _recolor_wordmark(wordmark.convert("RGBA"), CHARCOAL, WHITE)
    icon = compose_favicon(wm, size)
    bg = Image.new("RGBA", (size, size), (*NAVY, 255))
    bg.paste(icon, (0, 0), icon)
    # Re-draw GPS in accent on dark bg
    draw = ImageDraw.Draw(bg)
    gps_font = _font(max(10, int(size * 0.16)))
    bbox = draw.textbbox((0, 0), "GPS", font=gps_font)
    gps_w = bbox[2] - bbox[0]
    mark_h = int(size * 0.52)
    gap = max(2, int(size * 0.04))
    gps_h = bbox[3] - bbox[1]
    total_h = mark_h + gap + gps_h
    top = (size - total_h) // 2
    gps_x = (size - gps_w) // 2 - bbox[0]
    gps_y = top + mark_h + gap - bbox[1]
    draw.text((gps_x, gps_y), "GPS", font=gps_font, fill=(*ACCENT, 255))
    return bg


def square_app_icon(horizontal: Image.Image, size: int) -> Image.Image:
    w, h = horizontal.size
    scale = min((size * 0.88) / w, (size * 0.88) / h)
    nw, nh = int(w * scale), int(h * scale)
    resized = horizontal.resize((nw, nh), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), (*WHITE, 255))
    canvas.paste(resized, ((size - nw) // 2, (size - nh) // 2), resized)
    return canvas


def write_favicons(wordmark: Image.Image) -> None:
    FAVICON.mkdir(parents=True, exist_ok=True)
    for size, name in [
        (16, "favicon-16x16.png"),
        (32, "favicon-32x32.png"),
        (180, "apple-touch-icon.png"),
        (256, "favicon-256x256.png"),
    ]:
        compose_favicon(wordmark, size).save(FAVICON / name, optimize=True)

    ico_src = compose_favicon(wordmark, 32)
    ico_src.save(FAVICON / "favicon.ico", format="ICO", sizes=[(32, 32)])


def sync_mobile(light: Image.Image, dark: Image.Image, wordmark: Image.Image) -> None:
    if not MOBILE.exists():
        print(f"Mobile project not found at {MOBILE}, skipping sync.")
        return

    pairs: list[tuple[Image.Image, Path]] = [
        (light, MOBILE / "web" / "logo_en.png"),
        (dark, MOBILE / "web" / "logo.png"),
        (light, MOBILE / "mobile" / "logo-horizontal.png"),
        (dark, MOBILE / "mobile" / "logo-horizontal-dark.png"),
        (light, MOBILE / "mobile" / "splash-logo.png"),
        (dark, MOBILE / "mobile" / "splash-logo-dark.png"),
        (light, MOBILE / "mobile" / "icon-pin.png"),
    ]
    for img, path in pairs:
        path.parent.mkdir(parents=True, exist_ok=True)
        img.save(path, optimize=True)

    icon_dir = MOBILE / "app-icon"
    icon_dir.mkdir(parents=True, exist_ok=True)
    compose_launcher_icon(wordmark, 1024, background=NAVY, light_mark=False).save(
        icon_dir / "icon-1024.png", optimize=True
    )
    compose_launcher_icon(wordmark, 512, background=NAVY, light_mark=False).save(
        icon_dir / "icon-512.png", optimize=True
    )
    compose_launcher_foreground(wordmark, 1024).save(
        icon_dir / "icon-foreground-1024.png", optimize=True
    )
    compose_launcher_icon(wordmark, 512, background=NAVY, light_mark=False).save(
        MOBILE / "mobile" / "app-icon.png", optimize=True
    )


def main() -> None:
    WEB.mkdir(parents=True, exist_ok=True)
    backup = WEB / "logo_billx_only.png"

    if not backup.exists():
        raise SystemExit(f"Missing source wordmark: {backup}")

    wordmark = Image.open(backup).convert("RGBA")

    light = compose(wordmark, light=True)
    dark = compose(wordmark, light=False)

    light.save(WEB / "logo_en.png", optimize=True)
    dark.save(WEB / "logo.png", optimize=True)
    print(f"Wrote {WEB / 'logo_en.png'} ({light.size})")
    print(f"Wrote {WEB / 'logo.png'} ({dark.size})")

    write_favicons(wordmark)
    print(f"Updated favicons in {FAVICON.relative_to(ROOT)}")

    sync_mobile(light, dark, wordmark)
    if MOBILE.exists():
        print(f"Synced logos to {MOBILE}")


if __name__ == "__main__":
    main()
