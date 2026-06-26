#!/usr/bin/env python3
"""
Apply the final BillX GPS brand assets to web + mobile.

Sources (in public/branding/web/):
  - final_logo_billx.png  -> horizontal wordmark + GPS icon (transparent bg)
  - billx_favicon.png     -> GPS pin icon only (transparent bg)

Outputs:
  Web:
    - public/branding/web/logo_en.png       (light backgrounds)
    - public/branding/web/logo.png          (dark backgrounds, white wordmark)
    - public/branding/favicon/*             (favicons + apple touch + ico)
  Mobile (gps_tracker_pro_mobile_billxiot):
    - assets/branding/web/logo_en.png / logo.png
    - assets/branding/mobile/* copies
    - assets/branding/app-icon/icon-1024.png / icon-512.png / icon-foreground-1024.png
"""

from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
WEB = ROOT / "public" / "branding" / "web"
FAVICON = ROOT / "public" / "branding" / "favicon"
MOBILE = ROOT.parent / "gps_tracker_pro_mobile_billxiot" / "assets" / "branding"

NAVY = (30, 41, 59)  # #1E293B
WHITE = (255, 255, 255)

SRC_LOGO = WEB / "final_logo_billx.png"
SRC_ICON = WEB / "billx_favicon.png"


def autocrop_alpha(im: Image.Image, pad: int = 0, threshold: int = 12) -> Image.Image:
    """Trim transparent margins (ignoring faint shadow halos below `threshold`)."""
    im = im.convert("RGBA")
    alpha = im.split()[3]
    mask = alpha.point(lambda a: 255 if a > threshold else 0)
    bbox = mask.getbbox()
    if bbox:
        im = im.crop(bbox)
    if pad:
        out = Image.new("RGBA", (im.width + pad * 2, im.height + pad * 2), (0, 0, 0, 0))
        out.paste(im, (pad, pad), im)
        im = out
    return im


def darken_wordmark_to_white(logo: Image.Image, region_ratio: float = 0.62) -> Image.Image:
    """
    Recolor the dark, low-saturation wordmark ("BiLL") to white for dark
    backgrounds. Colored glyphs (gradient "X", blue "GPS") and the GPS icon are
    high-saturation, so they are preserved untouched.
    """
    src = logo.convert("RGBA")
    out = src.copy()
    px = out.load()
    cutoff_x = int(src.width * region_ratio)
    for x in range(cutoff_x):
        for y in range(src.height):
            r, g, b, a = px[x, y]
            if a < 8:
                continue
            mx, mn = max(r, g, b), min(r, g, b)
            sat = mx - mn
            if sat < 45 and mx < 180:
                px[x, y] = (*WHITE, a)
    return out


def square_on_bg(icon: Image.Image, size: int, bg, content_ratio: float) -> Image.Image:
    """Center the icon on a square canvas, scaled to content_ratio of the side."""
    icon = autocrop_alpha(icon)
    target = int(size * content_ratio)
    scale = min(target / icon.width, target / icon.height)
    nw, nh = max(1, int(icon.width * scale)), max(1, int(icon.height * scale))
    resized = icon.resize((nw, nh), Image.Resampling.LANCZOS)
    if bg is None:
        canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    else:
        canvas = Image.new("RGBA", (size, size), (*bg, 255))
    canvas.paste(resized, ((size - nw) // 2, (size - nh) // 2), resized)
    return canvas


def favicon_square(icon: Image.Image, size: int) -> Image.Image:
    """White-background square favicon (crisp on browser tabs)."""
    return square_on_bg(icon, size, WHITE, 0.86)


def write_web(logo_light: Image.Image, logo_dark: Image.Image, icon: Image.Image) -> None:
    logo_light.save(WEB / "logo_en.png", optimize=True)
    logo_dark.save(WEB / "logo.png", optimize=True)
    print(f"web logo_en.png {logo_light.size}  logo.png {logo_dark.size}")

    FAVICON.mkdir(parents=True, exist_ok=True)
    for size, name in [
        (16, "favicon-16x16.png"),
        (32, "favicon-32x32.png"),
        (48, "favicon-48x48.png"),
        (64, "favicon-64x64.png"),
        (128, "favicon-128x128.png"),
        (180, "apple-touch-icon.png"),
        (192, "favicon-192x192.png"),
        (256, "favicon-256x256.png"),
    ]:
        favicon_square(icon, size).save(FAVICON / name, optimize=True)
    favicon_square(icon, 32).save(FAVICON / "favicon.ico", sizes=[(32, 32)])
    print(f"favicons -> {FAVICON}")


def write_mobile(logo_light: Image.Image, logo_dark: Image.Image, icon: Image.Image) -> None:
    if not MOBILE.exists():
        print(f"Mobile project not found at {MOBILE}; skipping mobile sync.")
        return

    pairs = [
        (logo_light, MOBILE / "web" / "logo_en.png"),
        (logo_dark, MOBILE / "web" / "logo.png"),
        (logo_light, MOBILE / "mobile" / "logo-horizontal.png"),
        (logo_dark, MOBILE / "mobile" / "logo-horizontal-dark.png"),
        (logo_light, MOBILE / "mobile" / "splash-logo.png"),
        (logo_dark, MOBILE / "mobile" / "splash-logo-dark.png"),
    ]
    for img, path in pairs:
        path.parent.mkdir(parents=True, exist_ok=True)
        img.save(path, optimize=True)

    icon_pin = autocrop_alpha(icon)
    (MOBILE / "mobile").mkdir(parents=True, exist_ok=True)
    icon_pin.save(MOBILE / "mobile" / "icon-pin.png", optimize=True)

    icon_dir = MOBILE / "app-icon"
    icon_dir.mkdir(parents=True, exist_ok=True)
    square_on_bg(icon, 1024, NAVY, 0.66).save(icon_dir / "icon-1024.png", optimize=True)
    square_on_bg(icon, 512, NAVY, 0.66).save(icon_dir / "icon-512.png", optimize=True)
    square_on_bg(icon, 1024, None, 0.58).save(icon_dir / "icon-foreground-1024.png", optimize=True)
    square_on_bg(icon, 512, NAVY, 0.66).save(MOBILE / "mobile" / "app-icon.png", optimize=True)
    print(f"mobile assets -> {MOBILE}")


def main() -> None:
    if not SRC_LOGO.exists():
        raise SystemExit(f"Missing {SRC_LOGO}")
    if not SRC_ICON.exists():
        raise SystemExit(f"Missing {SRC_ICON}")

    logo = autocrop_alpha(Image.open(SRC_LOGO), pad=6)
    icon = Image.open(SRC_ICON)

    logo_light = logo
    logo_dark = darken_wordmark_to_white(logo)

    write_web(logo_light, logo_dark, icon)
    write_mobile(logo_light, logo_dark, icon)
    print("Done.")


if __name__ == "__main__":
    main()
