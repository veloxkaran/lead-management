# CLAUDE.md — Read before touching the database

## Hard rule: this database holds real, live data

`database/database.sqlite` is a **live application database with real user data** — leads,
users, goals entered by actual people. It is NOT a disposable dev/test fixture, even though
this project was originally scaffolded with heavy use of `migrate:fresh --seed` during initial
development. That phase is over.

**Never run any of these against `database/database.sqlite`:**

- `php artisan migrate:fresh`
- `php artisan migrate:refresh`
- `php artisan migrate:reset`
- `php artisan db:wipe`
- `php artisan db:seed` with `DatabaseSeeder` or `DemoDataSeeder` (these are dev-only and will
  collide with / duplicate real data)
- Any raw `DELETE`/`TRUNCATE` across a table without a specific, narrow, user-requested reason

**For schema changes:** only ever run `php artisan migrate` (additive). Write new migration
files for changes; never edit an already-applied migration in a way that requires a fresh run.

**Before any operation that touches the DB file directly** (copying, restoring, moving), run
`php artisan db:backup` first, or manually `cp database/database.sqlite
storage/app/backups/database_$(date +%Y%m%d_%H%M%S).sqlite`.

## Backups

- `php artisan db:backup` snapshots the DB to `storage/app/backups/`, keeping the 30 most
  recent (prunes older ones automatically). Scheduled hourly in `routes/console.php`.
- The schedule only fires if a real cron entry runs `php artisan schedule:run` every minute
  on whatever server this is deployed to — verify that's configured before relying on it.
- Restoring: stop the app, `cp storage/app/backups/database_<timestamp>.sqlite
  database/database.sqlite`.

## Environment notes

- `APP_ENV=production` is set. Destructive artisan commands will prompt for confirmation and
  auto-cancel in non-interactive shells unless `--force` is passed — treat that prompt as a
  signal to stop and reconsider, not an obstacle to route around.
- Local dev/testing should use `php artisan test` (which runs against an isolated `:memory:`
  SQLite DB per `phpunit.xml`, never the real file) rather than exercising the real app.
- If you need demo/sample data for local UI testing, do it in a copied-aside database file or
  a separate SQLite file — never against `database/database.sqlite` directly.
