# Deploy runbook — cowetascanner.com

Practical steps to ship the NCST Main Feed app to production via FTP.

## Target

| Item | Value |
|------|--------|
| Public URL | `https://www.cowetascanner.com` |
| FTP document root | `httpdocs/` (Plesk-style; FTP root also has `error_docs/`, `logs/`) |
| App layout | Local project root mirrors `httpdocs/` (PHP at root, plus `admin/`, `api/`, `article/`, `assets/`, `includes/`, `sql/`, `cron/`, `scripts/`) |

## Credentials (local only)

Read from repo-root `.env` (gitignored). **Never commit or paste secrets into docs.**

| Variable | Purpose |
|----------|---------|
| `FTP_ADDRESS` | FTP host |
| `FTP_PORT` | Usually `21` |
| `FTP_USER` | FTP username |
| `FTP_PASSWORD` | FTP password |

Production DB settings live in the **server** `.env` under `httpdocs/` (`DB_*`, `SITE_URL`, `SESSION_SECRET`, etc.). Local `.env` often points at MariaDB (`ncst_local`) — **do not overwrite** the remote `.env` with the local file.

## Before you deploy

1. Optionally **commit and push** so git matches what you intend to ship.
2. Or deliberately deploy the **current working tree** (including uncommitted site files) when those changes must go live.
3. Confirm you will not touch sibling apps on the server.

## What to upload

Upload into `httpdocs/`:

- Root PHP: `index.php`
- Trees: `admin/`, `api/`, `article/`, `assets/` (CSS/JS/img; see uploads note), `includes/`, `sql/` (apply scripts + `.htaccess`), `cron/`, `scripts/`

Skip replacing remote user media unless you mean to. Prefer **not** uploading local `assets/uploads/**` binaries (keep remote uploads; `.gitkeep` markers are fine if needed).

## What NOT to upload / touch

- `.git/`, `.agents/`, `.claude/`, `data/`, `skills-lock.json`
- Local `.env` / `.env.example` (keep existing production `.env`)
- Sibling dirs under `httpdocs/`: **`2/`**, **`advertising/`**, **`bid/`**, **`dev/`** — never overwrite, rename, or delete these
- Do not FTP-delete the whole `httpdocs/` tree

## FTP upload

### Quick deploy (PowerShell script)

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\deploy-ftp.ps1
```

`scripts/deploy-ftp.ps1` reads `FTP_*` from `.env`, uploads into `httpdocs/`, and never prints secrets.

### Manual pattern

Use passive FTP from PowerShell (`FtpWebRequest`), or an equivalent (`curl`, WinSCP, FileZilla) with the same rules.

Sketch (credentials from `.env`; expand path filters as needed):

```powershell
# Load FTP_* from .env, then for each file under the project root:
# remote path = "httpdocs/" + relative path with forward slashes
# Create parent dirs with FTP MKD (ignore "already exists")
# Upload with WebRequestMethods.Ftp.UploadFile (binary, passive)
```

**Exclude:** `.git`, `.agents`, `.claude`, `data`, `.env*`, `skills-lock.json`, sibling project names, and non-`.gitkeep` files under `assets/uploads/`.

Expect on the order of ~100 app files for a full sync.

## Database migrations

MySQL is **not** reachable from your PC on port 3306. `sql/` is denied to the web (`.htaccess`). Apply pending migrations with a **temporary token-gated PHP runner** in `httpdocs/`, then **delete it immediately**. On a host with shell PHP, you can also run `php sql/apply_….php` directly.

### Apply script order

Run one script per request (each `require`s bootstrap; do not batch-include all in one process):

1. `sql/apply_auth_migrate.php`
2. `sql/apply_categories_settings_og.php`
3. `sql/apply_media_library.php`
4. `sql/apply_posts_trash.php`
5. `sql/apply_shortcodes_footnotes.php`
6. `sql/apply_posts_gallery_playlist.php`
7. `sql/apply_facebook_posts.php`
8. `sql/apply_facebook_convert.php`
9. `sql/apply_facebook_comments.php`
10. `sql/apply_facebook_auto_post.php`
11. `sql/apply_facebook_sync_logs.php`
12. `sql/apply_posts_sticky.php`

Optional one-shots (only when needed): `sql/apply_facebook_created_at_backfill.php`, `sql/apply_facebook_eastern_times.php`.

Scripts are idempotent (safe to re-run; they print “already present” when done).

`facebook_ensure_auto_post_schema()` also adds `CS_facebook_comments.applied_at` idempotently when cron/auto-post admin runs.

### Runner pattern

1. Upload `httpdocs/_cs_migrate_once.php` (UTF-8 **without BOM**) that:
   - Sends `text/plain`
   - Requires `?t=` matching a long random token embedded in the file
   - `require`s **one** `sql/apply_*.php` script
2. Hit: `https://www.cowetascanner.com/_cs_migrate_once.php?t=<token>`
3. Confirm HTTP 200 and “Done.” (or already-present messages)
4. **FTP-delete** `_cs_migrate_once.php` and verify it returns **404**
5. Repeat for the next apply script, or reuse one wrapper and change the `require` target each time

**Warning:** Leaving the runner on the server is a security risk. Always remove it after use.

Alternatives: run the same SQL via phpMyAdmin (`CS_*` tables only), or SSH/`php sql/apply_….php` if shell access exists.

First-time / empty DB: apply `sql/schema.sql` (+ optional `seed.sql`) via phpMyAdmin or an equivalent one-shot runner — still **`CS_*` only**.

## Smoke test checklist

All should return **200** (or expected JSON) with no PHP fatals in the body:

| Check | URL |
|-------|-----|
| Home feed | `https://www.cowetascanner.com/` |
| CSS | `/assets/css/main.css` |
| Feed JS | `/assets/js/feed.js` |
| Article (sample) | `/article/news.php?id=1` (or a real post id) |
| Admin login | `/admin/login.php` |
| Feed API | `/api/feed.php` → `"ok":true` |
| Playlist JS (asset) | `/assets/js/article-playlist.js` |
| Gallery JS (asset) | `/assets/js/article-gallery.js` |
| Facebook auto-post | `/admin/settings/facebook/auto-post.php` |
| Facebook cron | `/admin/settings/facebook/cron.php` |
| Migrate runner gone | `/_cs_migrate_once.php` → **404** |

Notes:

- Playlist/gallery scripts are referenced on article pages only when that post has a playlist/gallery attached; asset URLs should still 200 after deploy.
- Spot-check siblings still up: `/advertising/`, `/2/`, `/bid/`.

Optional browser check: open home + one article; confirm no console errors on core CSS/JS.

## Tools used successfully

- **PowerShell** + `System.Net.FtpWebRequest` (list, mkdir, upload, delete, size), including `scripts/deploy-ftp.ps1`
- **`Invoke-WebRequest`** for HTTPS smoke tests and migration runner calls
- Chrome DevTools / browser for visual confirmation (optional)

## Quick “do / don’t”

**Do:** upload app trees to `httpdocs/`; run idempotent apply scripts via a short-lived runner; smoke-test URLs; delete the runner.

**Don’t:** upload local `.env`; touch `2/`, `advertising/`, `bid/`, `dev/`; leave `_cs_migrate_once.php` on the server; invent credentials — if `.env` FTP keys are missing, stop and obtain them.
