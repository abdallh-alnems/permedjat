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

## Taking the old backend out of the path — 2026-09-04

Everything that still reached into `/var/www/permedjat/backend_medjet` now goes
to Laravel, so the directory can be deleted. Four kinds of thing pointed at it:

**The association files, `/join` and `/join_team`** — on both the API and the
promo vhost — now hand off through `permedjat-laravel-front.conf`. That snippet
sets `SCRIPT_FILENAME` to the front controller and leaves `REQUEST_URI` alone,
so the routing decision stays in the application. The `.well-known` locations
stay exact-match: the `*.json` and hidden-file deny rules are regexes and would
otherwise 403 `assetlinks.json` before the prefix location could reach the app.
The application picks the employee pair or the management pair from the host
asked, which is why one snippet serves both vhosts.

`/public/join.css` is gone with no replacement — the landing views inline their
CSS.

**The attendance terminals** on `:8090` were the last thing still executing a
file from the old tree. Verified before the swap that Laravel's `/iclock/`
returns a byte-identical body to `device/iclock.php`, which matters more than
usual here: a terminal that does not get a clean 200 re-sends the same batch
forever.

**`/iclock/` is now denied on the API vhost.** Mounting the application at the
root made the terminal protocol reachable through Cloudflare as well, and it is
declared outside the `app.secret` group, so it answered unauthenticated. An
unknown serial auto-inserts an `unclaimed` row in `attendance_devices` — fine
on a port only terminals can reach, not fine on the open internet. The `^~`
prefix beats both the regex blocks and `location /`, so the devices vhost is
once again the only way in.

**The cron jobs and the monitoring probes** were repointed too, and are not in
this directory: `/usr/local/bin/permedjat-cron-run.sh` now calls
`http://127.0.0.1/v1/cron/<name>`, and the two blackbox targets in
`/etc/prometheus/prometheus.yml` now probe `/v1/employees`, which returns the
same 401 the modules already expect — so no module change was needed.

Backups from this change: `/root/permedjat-{common.conf,web,devices}.bak-20260904*`,
`/root/prometheus.yml.bak-20260904`, `/root/permedjat-cron-run.sh.bak-20260904`,
`/root/cron.d-permedjat.bak-20260904`.

**Verify:**

```bash
ssh permedjat 'curl -s "http://127.0.0.1:8090/iclock/cdata?SN=TEST&options=all"'  # the option block
curl -s -o /dev/null -w '%{http_code}\n' 'https://api.permedjat.com/iclock/cdata?SN=TEST'  # 403
curl -s -o /dev/null -w '%{http_code}\n' https://permedjat.com/join                        # 200
```

### These files match the server

Every `.conf` here was copied from the running server on 2026-09-04 and verified
identical, so this directory is once again what its first paragraph claims: the
record a rebuilt server can be restored from.

It was not, before that. The `permedjat-common.conf` checked in here had been
written for a layout that was never deployed — it expected the old backend at
`/var/www/permedjat/backend` with its endpoints under `api/`, and rewrote
`/backend_medjet/` to `/backend/api/`. The server still had
`/var/www/permedjat/backend_medjet` with endpoints under `app/`. Applying the
checked-in file would have 404'd the entire legacy API, so the Laravel changes
were made against the live files and copied back here afterwards.

`backend/legacy/` in the repo still differs from what is deployed the same way —
see the note in the top-level docs before running anything from it.
