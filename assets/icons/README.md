# Permedjat icon family

Vector source of truth for every app icon in the repo. The `.svg` files here are
what every platform raster was generated from — regenerate rather than editing a
PNG by hand.

**Final design approved 2026-09-08.** All four marks and the shared ground were
settled that day; the full platform export below was cut from these files on the
same date.

## Shared spec

| | |
|---|---|
| Background | `#1A1A1A` neutral black, identical in all four files |
| Source canvas | 1024 × 1024 |
| Corner radius | **never baked into a raster** — see Corners below |

Every mark is drawn from Egyptian **administrative, architectural and writing**
culture. Nothing here is a deity, a ritual object or royal insignia, and nothing
should be added that is.

## The four marks

### Manager — `manager.svg`
Three stacked plates, widening front-to-back, each carrying two rules of writing:
a pile of records. Capsule ends (`rx` = half the plate height), a hairline stroke
on each plate, no seal and no page-fold — both were cut on 2026-09-08 because
below 32px they clotted the plates into hollow outlines.

| | |
|---|---|
| Back plate | `#8A6416` |
| Middle plate | `#C9962B` |
| Front plate | `#F0C64E` |
| Rules | `#1A1A1A` at 0.5 opacity |
| Strokes | `#F0C64E` at 0.25 (back, middle) · `#FFFFFF` at 0.3 (front) |

### Kiosk — `kiosk.svg`
A hollow round-topped arch standing on a threshold bar — a small entrance. Drawn
as a single stroked path so the crown and both jambs carry one constant weight
(150 units); the sill is a separate rounded rect the legs run into.

| | |
|---|---|
| Arch | `#D4AF37` |
| Threshold | `#8A6416` |

Replaced the door-with-an-arrow placeholder, which read as the universal
log-in/exit glyph rather than as Permedjat.

### Employee — `employee.svg`
A reed pen above a written line. Mark `#C9A227`.

### Superadmin — `superadmin.svg`
An obelisk on a plinth. Shaft widened 15% (plinth 10%) over the first draft,
which thinned to a sliver at 22px. Mark `#C9A227`.

## Contrast

Measured against the `#1A1A1A` ground. The bar for a graphical element is 3:1.

| Colour | Where | Ratio |
|---|---|---|
| `#F0C64E` | Manager front plate | 10.69 |
| `#D4AF37` | Kiosk arch | 8.28 |
| `#C9A227` | Employee · Superadmin | 7.19 |
| `#C9962B` | Manager middle plate | 6.53 |
| `#8A6416` | Manager back plate · Kiosk threshold | 3.24 |

## Corners

`manager.svg` and `kiosk.svg` carry `rx="192"`; `employee.svg` and
`superadmin.svg` are square. That divergence is cosmetic in the source and does
**not** reach the rasters: every exported PNG is composited onto opaque
`#1A1A1A` before it is downsampled, so all of them ship as full-bleed squares.
iOS and Android apply their own mask, and a baked radius would leave transparent
corners that flatten to black underneath it.

## What was exported (2026-09-08)

Rendered at 4× the target and reduced with Lanczos, so the small sizes stay
clean. 139 files.

| Platform | Sizes | Path |
|---|---|---|
| **iOS** (manager, employee) | 20 · 29 · 40 · 50 · 57 · 58 · 60 · 72 · 76 · 80 · 87 · 100 · 114 · 120 · 144 · 152 · 167 · 180 · 1024 — 21 files + `Contents.json` (25 entries) | `frontend/mobile/<app>/ios/Runner/Assets.xcassets/AppIcon.appiconset/` |
| **Android launcher** | 48 · 72 · 96 · 144 · 192 (mdpi → xxxhdpi) | `frontend/mobile/<app>/android/app/src/main/res/mipmap-<dpi>/ic_launcher.png` |
| **Android adaptive** | 108 · 162 · 216 · 324 · 432, background + foreground | `…/res/drawable-<dpi>/ic_launcher_{background,foreground}.png` |
| **Flutter masters** | `icon_master.png` 1024 · `icon_bg.png` 1024 · `icon_foreground.png` 1024 · `<app>.svg` | `frontend/mobile/<app>/branding/` |
| **Web / PWA** (manager) | favicon 16 · 32 · 48 · apple-icon 180 · 192 · 512 · `icon.svg` · `logo.png` 512 | `frontend/web/manager/public/icons/`, `public/logo.png` |
| **Store** | Play 512 + master 1024 · App Store 1024 | `frontend/mobile/<app>/store_assets/<store>/icon/` |

The adaptive foreground is the mark alone at **1.25×** about the canvas centre —
the scale `flutter_launcher_icons` had already used for the sets in the repo, so
`dart run flutter_launcher_icons` reproduces these byte-for-byte in shape.

`frontend/web/manager/public/manifest.json` was updated: the icon list now names
favicon-48, icon-192 and icon-512 (the last two `any maskable`), and
`theme_color` moved from the retired teal `#0E7C86` to `#1A1A1A`.

## Regenerating

The Flutter apps read `branding/` through `flutter_launcher_icons`:

```bash
cd frontend/mobile/<app> && dart run flutter_launcher_icons
```

That reproduces the iOS and Android sets from the masters. They were also written
directly by the export, so a checkout is complete without running Flutter.

## Known gaps

- ~~`web.svg` is stale~~ — **re-cut 2026-09-08.** It is now the Manager stack
  wrapped in `scale(1.16)`, derived from `manager.svg` so the two cannot drift:
  neutralise the scale and it renders pixel-identical to `manager.svg`. The
  1.16 framing is kept from the retired file — it uses more of a 16px browser
  tab. The web PNGs under `frontend/web/manager/public/` are still cut from
  `manager.svg` (unscaled), which is deliberate: they are masked by the browser,
  the desktop icon is not.
- ~~Desktop was not re-exported~~ — **done 2026-09-08** from `web.svg`:
  `icon.png` 1024, `icon.ico` (16 · 32 · 48 · 256), `icon.icns` (16 → 1024, the
  full Apple iconset). These three keep their **rounded corners transparent**,
  unlike every mobile raster: macOS and Windows do not mask a desktop app icon,
  so a hard square would read as a dated tile in the Dock.
- **Kiosk and Superadmin have no iOS project** (no `ios/` directory,
  `ios: false` in `pubspec.yaml`), so they have no App Icon set and no
  `app_store/` store assets. Nothing to update until they target iOS.
- Store screenshots and feature graphics under `store_assets/` were not
  regenerated; only the icons were. They are release artefacts, not build inputs.
