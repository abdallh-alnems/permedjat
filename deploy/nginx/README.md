# Server-side nginx / Cloudflare rules that are **not** deployed by `deploy.sh`

`deploy.sh` rsyncs application code only. The nginx vhosts live on the server
(`/etc/nginx/sites-available/permedjat*`, `/etc/nginx/snippets/permedjat-common.conf`)
and are recorded here so a rebuilt server can be brought back to the same state.

## `uploads/` lockdown — 2026-08-15

**What was wrong.** `snippets/permedjat-common.conf` sets `root /var/www/permedjat` and
ends with `location / { try_files $uri $uri/ =404; }`. The deny list covered
`config|core|models|vendor|migrations|seeds|scripts|lang` but **not `uploads`**,
so every stored file was a public static file to anyone holding the URL:

```
GET /backend_medjet/uploads/payslips/1/payslip_2_2026-06_*.pdf → 200, 65997 bytes
```

That is payslips, identity documents, signatures, face captures — and, once web
attendance and the kiosk go live, punch photos and kiosk evidence. Directory
listing was already off (`403`), so it required knowing a filename, but any URL
that leaked stayed public forever. Cloudflare made it worse: the response was
cacheable (`cache-control: max-age=14400`), so a fetched payslip sat on the edge
for four hours independently of the origin.

Nothing legitimate ever fetched these paths — every consumer goes through an
authenticated PHP endpoint (`documents/view.php`, `payroll/get_slip_pdf.php`,
`support/attachment.php`, `employees/my_document_view.php`, `kiosk/capture.php`,
`attendance/punch_photo.php`). `kiosk/capture.php` even carries the comment
"uploads/ is not web-served", which was the assumption this closes.

**Origin fix** — in `/etc/nginx/snippets/permedjat-common.conf`, immediately after
the existing `config|core|models|...` deny line:

```nginx
# Employee evidence — payslips, identity documents, face and punch captures.
# Everything under uploads/ is served only through an authenticated PHP endpoint
# (documents/view.php, payroll/get_slip_pdf.php, attendance/punch_photo.php, ...).
# ^~ also beats the \.php$ block, so an uploaded script is never executed.
location ^~ /backend_medjet/uploads/ { deny all; }
location ^~ /uploads/ { deny all; }
```

Then `nginx -t && systemctl reload nginx`.

**Edge fix** — the origin rule cannot evict what Cloudflare already cached, and
the zone token has no cache-purge scope, so a WAF custom rule blocks the path
before cache instead (zone `permedjat.com`, phase `http_request_firewall_custom`):

```
action:     block
expression: (http.request.uri.path contains "/uploads/")
```

Recreate with:

```bash
curl -X PUT "https://api.cloudflare.com/client/v4/zones/$ZONE/rulesets/phases/http_request_firewall_custom/entrypoint" \
  -H "Authorization: Bearer $CF_TOKEN" -H "Content-Type: application/json" \
  --data '{"rules":[{"action":"block","description":"uploads/ is served only through authenticated PHP endpoints","expression":"(http.request.uri.path contains \"/uploads/\")","enabled":true}]}'
```

**Verify** (both layers, from outside):

```bash
curl -sI https://api.permedjat.com/backend_medjet/uploads/payslips/1/<any>.pdf | head -1   # 403
curl -s -o /dev/null -w '%{http_code}\n' https://api.permedjat.com/backend_medjet/app/auth/login.php  # 401, still alive
```

A backup of the pre-change snippet is at `/root/permedjat-common.conf.bak-20260815`.

## Laravel mounted at the host root — 2026-09-04

`backend/api` now serves `api.permedjat.com/`. The legacy backend was not touched:
it is still reached at `/backend_medjet/...` and still resolves against the
server-level `root /var/www/permedjat`.

**Why the root and not a prefix.** `/join`, `/.well-known/{file}` and
`/iclock/{action}` are declared outside the `/v1` group, so they have to sit at
the root. `/backend` was never a real prefix on this server — it returns 404,
despite what the top-level `CLAUDE.md` says — and `/api` would be redundant
under `api.permedjat.com`.

**Applied to `/etc/nginx/snippets/permedjat-common.conf`**, replacing the three
trailing lines (`location /`, favicon, robots) and nothing else:

```nginx
location = /index.php {
    root /var/www/permedjat/api/public;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    fastcgi_param HTTPS $https if_not_empty;
    fastcgi_read_timeout 120s;
}

location / {
    root /var/www/permedjat/api/public;
    try_files $uri $uri/ /index.php?$query_string;
}
location = /favicon.ico { root /var/www/permedjat/api/public; access_log off; log_not_found off; }
location = /robots.txt  { root /var/www/permedjat/api/public; access_log off; log_not_found off; }
```

`location = /index.php` is the load-bearing line. Without it the `try_files`
fallback internally redirects to `/index.php`, that redirect is re-matched
against the `~ \.php$` regex block, and `SCRIPT_FILENAME` becomes
`/var/www/permedjat/index.php` — which does not exist. Every Laravel route would
404 while every legacy URL kept working: a failure that reads as "the deploy did
nothing" rather than as a routing bug. An exact-match location outranks a regex
one, which is what keeps the front controller off the legacy docroot.

Legacy is safe for the same reason in reverse: every `/backend_medjet/...` URL
ends in `.php`, so the regex block claims it before the `location /` prefix is
ever considered.

No deny rule changed. Verified afterwards that none of the 297 routes is
shadowed by the `(config|core|models|vendor|migrations|seeds|scripts|lang)/`,
`*.json` or hidden-file rules.

Backup of the pre-change snippet: `/root/permedjat-common.conf.bak-20260904-prelaravel`.

**Verify:**

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://api.permedjat.com/up                              # 200
curl -s -o /dev/null -w '%{http_code}\n' https://api.permedjat.com/backend_medjet/app/auth/login.php  # 401, still alive
curl -s https://api.permedjat.com/                                                                  # {"status":"success",...}
```

### ⚠ `permedjat-common.conf` in this directory does NOT match the server

The copy checked in here was written for a server layout that was never
deployed: it expects the legacy backend at `/var/www/permedjat/backend` with its
endpoints under `api/`, and adds rewrites from `/backend_medjet/` to
`/backend/api/`. The live server still has `/var/www/permedjat/backend_medjet`
with endpoints under `app/`. **Applying the checked-in file as-is would 404 the
entire legacy API.** The Laravel change above was therefore made against the
live file, not by deploying this one. Reconcile the two before any server
rebuild relies on this directory.
