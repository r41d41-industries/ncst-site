# AGENTS.md

## Cursor Cloud specific instructions

This is a plain PHP 8 / MySQL(MariaDB) app (no Composer, npm, or build step). See `README.md` for the full setup/run reference; only the non-obvious cloud caveats are captured here.

### Services

- **Web app** (public feed + admin CRUD): single PHP app served from the repo root.
  - Run (dev): `php -S 0.0.0.0:8080 -t .` from `/workspace`.
  - Public feed: `http://localhost:8080/` — Admin: `http://localhost:8080/admin/` (seeded login `admin` / `changeme`).
- **MariaDB**: local DB `cowscan_`, user `ncst_local`, password `ncst_local_dev` (matches `README.md` "Local MariaDB"). Schema + seed are already applied in the VM snapshot.

### Startup caveats (non-obvious)

- MariaDB is **not** managed by systemd in this container and does **not** auto-start. Start it manually each session before running the app:
  `sudo mariadbd-safe --datadir=/var/lib/mysql >/tmp/mariadb.log 2>&1 &` then confirm with `sudo mysqladmin ping`.
- `.env` is gitignored and lives only in the VM snapshot. The startup update script recreates a local-dev `.env` if it is missing. If you change DB creds, update `.env` too.
- `DB_ADDRESS` uses `127.0.0.1` (TCP) in the local `.env`; the PDO DSN is host/port based, so avoid relying on the unix socket.

### Lint / test / build

- Build: none (interpreted PHP).
- Lint: `php -l <file>` per file (there is no Composer/PHPStan config). Lint everything with
  `find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l`.
- Tests: no unit-test framework. `sql/verify_*.php` are manual verification harnesses (e.g. `php sql/verify_settings.php`, `php sql/verify_auth_flow.php`). They must be run with MariaDB up. The SMTP2GO email step in `verify_auth_flow.php` fails locally by design unless `SMTP2GO_API_KEY` is set — that failure is expected.

### Notes

- Fresh installs only need `sql/schema.sql` + `sql/seed.sql`; the `sql/migrate_*.sql` / `sql/apply_*.php` scripts are for upgrading pre-existing databases and are not needed on a fresh snapshot.
- Image uploads use `assets/uploads/**` (gitignored except `.gitkeep` markers).
