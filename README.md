# NCST Main Feed

Public main feed + admin CRUD for **NCST Main Feed** — plain PHP / CSS / JS / MySQL with `CS_`-prefixed tables only.

## Features

- Chronological public feed (newest first) with category filters
- Card layouts by topic:
  - **Incident** (`CRIME`, `FIRE`, `TRAFFIC`) — image, updates timeline, agencies, dispatched/cleared, optional social share
  - **News** (`NEWS`, `UPDATES`) — image, body, optional read-more
  - **Weather** (`WEATHER`) — image, body, optional read-more, `VALID: … TO …`
- Live “CURRENT” clock and noon/midnight milestones between cards
- Article pages for long-form content:
  - News/Updates: `/article/news.php?id=`
  - Weather: `/article/weather.php?id=`
- Admin login (`CS_users`) with post CRUD, image uploads, updates, publish flag

## Local requirements

- PHP 8.x with **PDO MySQL** (`extension=pdo_mysql`)
- **fileinfo** recommended for image uploads (`extension=fileinfo`); upload code also falls back if it is missing
- MariaDB / MySQL
- Project root mirrors deploy root (`httpdocs/`)

## Setup

1. Copy `.env.example` to `.env` and set `DB_*`, `SITE_URL`, and `SESSION_SECRET`.
2. Create the database and apply schema (see below).
3. From the project root:

```bash
php -S localhost:8080 -t .
```

- Public feed: http://localhost:8080/
- Admin: http://localhost:8080/admin/

### Local MariaDB

Remote `cowadmin` credentials often **do not** work on a local MariaDB install. This project uses a local-only user for development:

| Setting | Local value |
| --- | --- |
| `DB_NAME` | `cowscan_` |
| `DB_USER` | `ncst_local` |
| `DB_PASSWORD` | `ncst_local_dev` |

Create once (as MariaDB root):

```sql
CREATE DATABASE IF NOT EXISTS cowscan_ CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ncst_local'@'localhost' IDENTIFIED BY 'ncst_local_dev';
GRANT ALL PRIVILEGES ON cowscan_.* TO 'ncst_local'@'localhost';
FLUSH PRIVILEGES;
```

Apply schema and seed:

```bash
mysql -u ncst_local -pncst_local_dev cowscan_ < sql/schema.sql
mysql -u ncst_local -pncst_local_dev cowscan_ < sql/seed.sql
```

On Windows (example MariaDB path):

```bash
"C:\Program Files\MariaDB 12.3\bin\mysql.exe" -u ncst_local -pncst_local_dev cowscan_ < sql/schema.sql
"C:\Program Files\MariaDB 12.3\bin\mysql.exe" -u ncst_local -pncst_local_dev cowscan_ < sql/seed.sql
```

Tables created: `CS_users`, `CS_posts`, `CS_post_updates`. Do not alter existing unprefixed tables.

Default seed admin (change after first login):

- Username: `admin`
- Password: `changeme`

### Migrations

Fresh installs can use `sql/schema.sql` alone. Existing databases may need the incremental scripts under `sql/migrate_*.sql` (social URLs, post updates, event datetimes, read-more URL, recorded/expires, article body). Apply any that have not already been run.

## Layout

```
index.php                 Public feed
article/news.php          News / Updates article template
article/weather.php       Weather article template
api/feed.php              Infinite-scroll JSON
admin/                    Login + post CRUD
assets/                   CSS, JS, images, uploads
includes/                 bootstrap, PDO, auth, posts, feed helpers, partials
sql/                      schema, seed, migrations (deny web access in production)
```

## Post field notes

- Feed teaser: `CS_posts.body`
- Long-form article: `CS_posts.article_body` (powers internal “read more” when set)
- Weather validity: `recorded_at` / `expires_at`
- Incident meta: `agency`, `dispatched_at`, `cleared_at`, `CS_post_updates`
- Optional outbound link: `read_more_url` (used only when there is no `article_body`)

## Deploy notes

- FTP into `httpdocs/` **root** only for this app’s files.
- **Never** overwrite or rename sibling folders: `2/`, `advertising/`, `bid/`, `dev/`.
- Place a server-side `.env` (or inject env vars). Do not commit secrets.
- Run `sql/schema.sql` (or pending migrations) on remote via phpMyAdmin — **`CS_*` tables only**.
- Prefer denying web access to `sql/` (e.g. `.htaccess`).
- Restore production DB credentials in `.env` before deploy (do not leave local-only `ncst_local` on the server).

Do **not** auto-FTP deploy unless asked. Keep `.env` out of git.

## Out of scope (v1)

- Real Maps / Other Feeds pages (placeholders only)
- Advertising app tables/auth
- Live scanner ingestion
- Automatic FTP deploy
