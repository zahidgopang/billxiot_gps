#!/usr/bin/env python3
"""Extract FalconEyeGPS branding assets from the combined mockup sheet."""
from __future__ import annotations

import shutil
from pathlib import Path

from PIL import Image, ImageChops, ImageOps

ROOT = Path(__file__).resolve().parents[1]
SRC = Path(
    r"C:\Users\zhg78\.cursor\projects\d-laragon-www-gps-traccar\assets"
    r"\c__Users_zhg78_AppData_Roaming_Cursor_User_workspaceStorage_5705bef7430b9545b89d560934c7910a_images_"
    r"ChatGPT_Image_May_28__2026__11_47_27_PM-36d07156-deb3-48f3-886e-dabae6f4437e.png"
)

WEB_BRAND = ROOT / "public" / "branding" / "web"
MOBILE_BRAND = ROOT / "public" / "branding" / "mobile"
FAVICON_DIR = ROOT / "public" / "branding" / "favicon"
APP_ICON_DIR = ROOT / "public" / "branding" / "app-icon"

# Legacy paths (layouts still reference public/images via config)
LEGACY_IMAGES = ROOT / "public" / "images"
MOBILE_BRAND_FLUTTER = ROOT.parent / "gps_tracker_pro_mobile" / "assets" / "branding"


def is_background(r: int, g: int, b: int, a: int, threshold: int = 242) -> bool:
    return a < 15 or (r >= threshold and g >= threshold and b >= threshold)


def trim_transparent(im: Image.Image, pad: int = 6) -> Image.Image:
    if im.mode != "RGBA":
        im = im.convert("RGBA")
    bg = Image.new("RGBA", im.size, (255, 255, 255, 255))
    diff = ImageChops.difference(im, bg)
    bbox = diff.getbbox()
    if not bbox:
        return im
    x0, y0, x1, y1 = bbox
    return im.crop(
        (
            max(0, x0 - pad),
            max(0, y0 - pad),
            min(im.width, x1 + pad),
            min(im.height, y1 + pad),
        )
    )


def white_to_transparent(im: Image.Image, threshold: int = 245) -> Image.Image:
    im = im.convert("RGBA")
    px = im.load()
    w, h = im.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if is_background(r, g, b, a, threshold):
                px[x, y] = (255, 255, 255, 0)
    return trim_transparent(im, pad=4)


def content_bbox(im: Image.Image, x0: int, y0: int, x1: int, y1: int) -> tuple[int, int, int, int]:
    im = im.convert("RGBA")
    px = im.load()
    min_x, min_y, max_x, max_y = im.width, im.height, 0, 0
    for y in range(y0, min(y1, im.height)):
        for x in range(x0, min(x1, im.width)):
            r, g, b, a = px[x, y]
            if is_background(r, g, b, a):
                continue
            min_x = min(min_x, x)
            min_y = min(min_y, y)
            max_x = max(max_x, x)
            max_y = max(max_y, y)
    if max_x <= min_x:
        return x0, y0, x1, y1
    return min_x, min_y, max_x + 1, max_y + 1


def make_dark_logo(im: Image.Image) -> Image.Image:
    """Navy text -> white; keep orange accents; transparent background."""
    out = im.convert("RGBA")
    px = out.load()
    w, h = out.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if a < 20:
                continue
            if is_background(r, g, b, a):
                px[x, y] = (255, 255, 255, 0)
                continue
            # Orange brand accents
            if r > 160 and g < 140 and b < 100:
                continue
            # Yellow beak highlights
            if r > 200 and g > 160 and b < 120:
                continue
            # Light blues in icon — keep
            if b > r and b > 120 and r > 80:
                continue
            # Dark navy / black text -> white
            luminance = 0.299 * r + 0.587 * g + 0.114 * b
            if luminance < 120:
                px[x, y] = (255, 255, 255, a)
    return trim_transparent(out, pad=6)


def square_icon(im: Image.Image, size: int = 1024) -> Image.Image:
    im = trim_transparent(im, pad=8)
    contained = ImageOps.contain(im, (size, size), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    ox = (size - contained.width) // 2
    oy = (size - contained.height) // 2
    canvas.paste(contained, (ox, oy), contained)
    return canvas


def save_png(im: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    im.save(path, format="PNG", optimize=True)
    try:
        rel = path.relative_to(ROOT)
    except ValueError:
        rel = path
    print(f"  {rel}  {im.size}")


def save_favicon_set(icon: Image.Image) -> None:
    FAVICON_DIR.mkdir(parents=True, exist_ok=True)
    sizes = [16, 32, 48, 64, 128, 192, 256]
    icons: list[Image.Image] = []
    for s in sizes:
        resized = icon.resize((s, s), Image.Resampling.LANCZOS)
        p = FAVICON_DIR / f"favicon-{s}x{s}.png"
        save_png(resized, p)
        icons.append(resized)
    ico_path = FAVICON_DIR / "favicon.ico"
    base = icons[-1]  # 256px
    base.save(ico_path, format="ICO", sizes=[(s, s) for s in sizes])
    print(f"  {ico_path.relative_to(ROOT)}")


def sync_legacy(horizontal: Image.Image, horizontal_dark: Image.Image, icon: Image.Image, splash: Image.Image) -> None:
    LEGACY_IMAGES.mkdir(parents=True, exist_ok=True)
    MOBILE_BRAND_FLUTTER.mkdir(parents=True, exist_ok=True)
    (MOBILE_BRAND_FLUTTER / "web").mkdir(parents=True, exist_ok=True)
    mobile_flat = ROOT.parent / "gps_tracker_pro_mobile" / "assets" / "images"
    mobile_flat.mkdir(parents=True, exist_ok=True)

    mapping = {
        LEGACY_IMAGES / "falconeyegps-horizontal.png": horizontal,
        LEGACY_IMAGES / "falconeyegps.png": horizontal,
        LEGACY_IMAGES / "logo.png": horizontal,
        LEGACY_IMAGES / "falconeyegps-dark.png": horizontal_dark,
        LEGACY_IMAGES / "falconeyegps-icon.png": icon,
        LEGACY_IMAGES / "favicon.png": icon.resize((256, 256), Image.Resampling.LANCZOS),
        MOBILE_BRAND / "logo-horizontal.png": horizontal,
        MOBILE_BRAND / "logo-horizontal-dark.png": horizontal_dark,
        MOBILE_BRAND / "splash-logo.png": splash,
        MOBILE_BRAND / "splash-logo-dark.png": splash_dark,
        MOBILE_BRAND / "app-icon.png": app_icon,
        MOBILE_BRAND_FLUTTER / "logo-horizontal.png": horizontal,
        MOBILE_BRAND_FLUTTER / "logo-horizontal-dark.png": horizontal_dark,
        MOBILE_BRAND_FLUTTER / "splash-logo.png": splash,
        MOBILE_BRAND_FLUTTER / "splash-logo-dark.png": splash_dark,
        MOBILE_BRAND_FLUTTER / "app-icon.png": app_icon,
        MOBILE_BRAND_FLUTTER / "icon-pin.png": pin_icon,
        MOBILE_BRAND_FLUTTER / "web" / "logo-horizontal.png": horizontal,
        MOBILE_BRAND_FLUTTER / "web" / "logo-horizontal-dark.png": horizontal_dark,
        mobile_flat / "falconeyegps-horizontal.png": horizontal,
        mobile_flat / "falconeyegps.png": horizontal,
        mobile_flat / "falconeyegps-dark.png": horizontal_dark,
        mobile_flat / "falconeyegps-icon.png": icon,
    }
    for path, img in mapping.items():
        save_png(img, path)


def main() -> None:
    if not SRC.exists():
        raise SystemExit(f"Source image not found: {SRC}")

    sheet = Image.open(SRC).convert("RGBA")
    w, h = sheet.size

    # --- 1. Horizontal website logo (top section) ---
    hb = content_bbox(sheet, 0, 0, w, int(h * 0.52))
    horizontal = white_to_transparent(sheet.crop(hb))

    # --- 2. App icon (bottom squircle — icon only, no text) ---
    ab = content_bbox(sheet, 0, int(h * 0.52), w, h)
    app_raw = white_to_transparent(sheet.crop(ab))
    app_icon = square_icon(app_raw, 1024)

    # Icon-only pin from left side of horizontal (cleaner for favicon)
    pin_box = content_bbox(horizontal, 0, 0, int(horizontal.width * 0.38), horizontal.height)
    pin_icon = white_to_transparent(horizontal.crop(pin_box))
    pin_icon = square_icon(pin_icon, 512)

    # --- 3. Dark mode horizontal ---
    horizontal_dark = make_dark_logo(horizontal)

    # --- 4. Splash logos (centered, moderate size) ---
    splash = ImageOps.contain(horizontal, (900, 280), Image.Resampling.LANCZOS)
    splash = trim_transparent(splash, pad=12)
    splash_dark = ImageOps.contain(horizontal_dark, (900, 280), Image.Resampling.LANCZOS)
    splash_dark = trim_transparent(splash_dark, pad=12)

    print("Exporting branding assets…")

    save_png(horizontal, WEB_BRAND / "logo-horizontal.png")
    save_png(horizontal_dark, WEB_BRAND / "logo-horizontal-dark.png")
    save_png(app_icon, APP_ICON_DIR / "icon-1024.png")
    save_png(app_icon, APP_ICON_DIR / "icon-512.png")
    save_png(pin_icon, WEB_BRAND / "icon-pin.png")
    save_png(pin_icon, MOBILE_BRAND / "icon-pin.png")

    save_png(splash, MOBILE_BRAND / "splash-logo.png")
    save_png(splash_dark, MOBILE_BRAND / "splash-logo-dark.png")

    save_favicon_set(pin_icon)

    # Apple touch / PWA
    save_png(pin_icon.resize((180, 180), Image.Resampling.LANCZOS), FAVICON_DIR / "apple-touch-icon.png")

    sync_legacy(horizontal, horizontal_dark, pin_icon, splash_dark)

    shutil.copy2(SRC, ROOT / "public" / "branding" / "source-mockup.png")
    print("Done.")


if __name__ == "__main__":
    main()
