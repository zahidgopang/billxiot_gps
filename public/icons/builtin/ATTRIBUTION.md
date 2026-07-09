# Built-in map marker icons

These SVG markers are **original artwork** created for BillX GPS — clean,
top-down (bird's-eye) transportation silhouettes designed specifically for
live fleet tracking maps. They are not derived from any third-party icon
set.

- Canvas: 64x64, transparent background (no badge circle).
- Orientation: every icon is drawn nose/front-up (north), so the map layer
  can rotate the marker directly from the raw GPS heading (0deg = north).
- Colors come from `config/builtin_map_icon_sources.php`
  (`icon_colors` / `category_colors`).
- Source of truth for the icon list is `config/vehicle_icon_catalog.php`.

Regenerate all icons after changing the catalog or colors:

```bash
npm run generate:map-icons
```

That runs `scripts/generate-topdown-map-icons.mjs`, which procedurally
draws every icon (no external image sources) and writes the results into
`public/icons/builtin/{Category}/{icon_id}.svg`.

Do not edit files in this folder manually — changes will be overwritten the
next time the generator runs.

> Legacy note: `scripts/sync-builtin-map-icons.mjs` (Tabler side-view
> icons with a colored badge circle) is kept only for historical reference
> and is no longer the recommended generator.
