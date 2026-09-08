# Permedjat icon family

Vector source of truth for every app icon in the repo. The `.svg` files here are
what the platform assets were generated from — regenerate rather than editing a
PNG by hand.

## Shared spec

| | |
|---|---|
| Mark | `#D4AF37` gold |
| Background | `#121212` neutral black |
| Contrast | 8.91 (WCAG, mark against ground) |
| Source canvas | 1024 × 1024 |
| Corner radius | never baked in — iOS and Android mask it, web applies it |

**Only Manager is on this palette so far.** It moved here on 2026-09-08 from
`#C9A227` on `#2A2522` (contrast 6.26). Superadmin, Kiosk and Employee — and
`web.svg`, which is the Manager gateway scaled 1.16× — are still on the old
pair. See Known gaps.

The warm charcoal ground was doing quiet work for `#C9A227`: a warm ground reads
a slightly olive gold as rich. Take the warmth out of the ground and the same
gold drifts toward mustard, so the mark was relit at the same time as the
ground. `#D4AF37` was picked over a brighter `#E0B93C` (which reads yellow
rather than metal above 64px) and over the copper direction `#C08A2E` / `#B8860B`
(handsome at 512, but collapses to brown at 16–22px — the exact failure the
black ground was meant to fix). `#121212` rather than `#0D0D0D` or `#000000`
because on an OLED black home screen those two lose the rounded-tile silhouette
entirely; `#121212` keeps it and matches the Material dark surface the app
itself uses.

Every mark is drawn from Egyptian **administrative, architectural and writing**
culture. Nothing here is a deity, a ritual object or royal insignia, and nothing
should be added that is.

## Status

### Locked — approved, do not change without a new review

| App | Mark | Notes |
|---|---|---|
| **Manager** | Gateway on a platform | The platform is load-bearing: it is what gives the flagship its weight. Earlier passes without it read as binoculars, then as trousers. Recoloured to the black/`#D4AF37` pair 2026-09-08; geometry untouched. |
| **Superadmin** | Obelisk | Shaft widened 15% (plinth 10%) over the first draft, which thinned to a sliver at 22px. |
| **Web** | The Manager gateway | Mark scaled 1.16× — identical geometry, less padding, so it uses more of a 16px browser tab. |

### Placeholder — exported so nothing ships blank, NOT approved

| App | Mark | Why it is still open |
|---|---|---|
| **Kiosk** | Door with an arrow cut out | Functionally clear and legible at every size, but generic: an arrow inside a rectangle is the universal log-in / exit glyph. A stranger reads it as a system icon rather than as Permedjat. Pending a revision that feels more distinctly ours. |
| **Employee** | Reed pen over a written line | Reads close to the standard "edit" pencil, and at 22px it collapses to exactly that — a diagonal above a line. The chisel nib distinguishes a cut reed from a sharpened pencil only above ~32px. The scribe's palette (a bar with two ink wells) is the standing alternative; nothing else in UI resembles it. |

## Where the generated assets live

The repo has no single icon directory — each app carries its own, and that
convention was kept:

```
frontend/mobile/<app>/branding/          icon_master.png · icon_bg.png · icon_foreground.png · <app>.svg
frontend/mobile/<app>/ios/…/AppIcon.appiconset/    Icon-App-<pt>x<pt>@<scale>x.png   (manager, employee)
frontend/mobile/<app>/android/…/res/mipmap-<dpi>/  ic_launcher.png                   48 → 192
frontend/mobile/<app>/android/…/res/drawable-<dpi>/ic_launcher_{background,foreground}.png   108 → 432
frontend/web/manager/public/icons/       icon.svg · favicon-16/32/48 · apple-icon · icon-192 · icon-512
frontend/web/manager/public/logo.png
frontend/web/site/assets/                favicon-32 · apple-icon · logo
frontend/desktop/manager/build/          icon.png · icon.ico · icon.icns
```

## Regenerating

The Flutter apps read `branding/` through `flutter_launcher_icons`, so:

```bash
cd frontend/mobile/<app> && dart run flutter_launcher_icons
```

reproduces the iOS and Android sets from the masters. They were also written
directly by the export, so a checkout is complete without running Flutter.

## Known gaps

- `frontend/web/manager/public/manifest.json` still declares
  `"theme_color": "#0E7C86"` — the retired teal. Changing it is a brand decision
  beyond the icon work, so it was left alone.
- Store assets under `frontend/mobile/*/store_assets/` were not regenerated;
  they are release artefacts, not build inputs.
- **The Manager recolour stopped at the mobile app.** `web.svg` is a separate
  source — the same gateway wrapped in `scale(1.16)` — and everything under
  `frontend/web/manager/public/icons/`, `frontend/web/manager/public/logo.png`
  and `frontend/desktop/manager/build/` is rendered from *it*, not from
  `manager.svg`. They are all still warm charcoal, so the browser tab and the
  desktop `.dmg` / `.exe` no longer match the phone icon. Recolouring `web.svg`
  and re-rendering that set is the outstanding half.
