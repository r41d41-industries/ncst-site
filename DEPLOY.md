# Production deploy (cowetascanner.com)

## Target

- FTP into **`httpdocs/`** only (account root is not the web root).
- **Never** overwrite sibling folders: `2/`, `advertising/`, `bid/`, `dev/`.
- Do **not** upload local `.env` (server keeps its own production env).

## Credentials

Set in local `.env` (deploy only):

- `FTP_ADDRESS`
- `FTP_PORT` (usually `21`)
- `FTP_USER`
- `FTP_PASSWORD`

## Quick deploy (changed Facebook auto-post files)

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\scripts\deploy-ftp.ps1
```

## Migrations

Prefer:

```bash
php sql/apply_facebook_auto_post.php
```

on the server (or a token-gated one-shot runner under `httpdocs/`, deleted after use).

`facebook_ensure_auto_post_schema()` also adds `CS_facebook_comments.applied_at` idempotently when cron/auto-post admin runs.

## Smoke checklist

- https://www.cowetascanner.com/
- https://www.cowetascanner.com/admin/login.php
- https://www.cowetascanner.com/admin/settings/facebook/auto-post.php
- https://www.cowetascanner.com/admin/settings/facebook/cron.php
- Siblings still up: `/advertising/`, `/2/`, `/bid/`
