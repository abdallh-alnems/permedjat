---
name: permedjat-infra
description: The live Permedjat infrastructure inventory — Hetzner VPS layout, nginx hostname routes, cron schedule, Android release signing, and Firebase. Use when deploying, changing server or DNS configuration, debugging a live hostname or cron job, or cutting an Android release.
---

# Permedjat infrastructure

The deployment *rules* (never edit on the server, never hand-run SQL, never edit an applied
migration, always run `check-drift.sh`) live in the root `CLAUDE.md` and always apply. This file is
the inventory of what is actually running.

## Server

Single **Hetzner VPS** (Ubuntu 26.04, PHP 8.5 / MySQL 8.4 / Nginx) behind **Cloudflare** (proxied,
Full-strict, origin IP hidden; UFW allows 80/443 from Cloudflare ranges only). Deploy is `rsync`
from the Mac — no CI.

- `api.permedjat.com/backend` → the backend at `/var/www/permedjat/backend`.
  `/backend_medjet` is the pre-rename prefix and is still matched, because app builds already in
  the stores call it.
- `app.permedjat.com` → Next.js via systemd `permedjat-web.service` (`next start` on :3000)
- `permedjat.com` + `www` → static promo site (`frontend/web/site`), plus `/join` and
  `/.well-known/*` deep links served from the backend copies
- `grafana.permedjat.com` (Grafana + Prometheus) and `db.permedjat.com` (Adminer, basic-auth)

## Cron

`/etc/cron.d/permedjat` (Africa/Cairo) — leave rollover 00:00+00:30 (CLI), catch-up absences 23:50,
daily alerts 07:00 (both via `/usr/local/bin/permedjat-cron-*.sh`, which pass **both** `key=` and
`cron_secret=`), mysqldump backup 02:00 with 14-day retention.

## Android release

Signed with the upload keystore at `android/app/upload-keystore.jks` (gitignored), wired via
`key.properties` in `build.gradle.kts`. `flutter build appbundle --release` for the store.

## Firebase

Project `permedjat`. Maintenance/force-update is driven by Remote Config; the admin toggle also
pushes an FCM topic for instant effect.
