# TMIS Backend API

Backend services for TMIS. This document outlines the minimum setup steps every developer should follow before contributing.

## Prerequisites

- PHP 8.2+
- Composer 2.x
- PostgreSQL (recommended v14+)
- Git

## Getting Started

1. **Clone the repository**
   ```bash
   git clone https://github.com/PO-RALG/tims-api.git
   cd tims-api/ttpb-backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Prepare the environment**
   ```bash
   cp .env.example .env
   ```
   - Update database credentials (e.g. `DB_DATABASE=ttpb_db`).
   - Set mail, queue, cache, and any other environment-specific values.
   - When running NationalitiesSeeder behind a corporate proxy or with missing root certificates, set `REST_COUNTRIES_SKIP_SSL=true`.

4. **Generate the app key**
   ```bash
   php artisan key:generate --ansi
   ```

5. **Run migrations**
   ```bash
   php artisan migrate
   ```

6. **Seed baseline data**
   ```bash
   php artisan db:seed
   ```
   Seeds demo users, roles, permissions, default admin hierarchy, etc.

7. **Generate Swagger docs**
   ```bash
   php artisan l5-swagger:generate
   ```

8. **Optimize and refresh autoloaders (optional but recommended)**
   ```bash
   composer dump-autoload
   php artisan optimize
   ```

9. **Run the dev server**
   ```bash
   php artisan serve
   ```
   API base URL: `http://127.0.0.1:8000/api`

## Running with Docker

The stack (`docker-compose.yml`) has three services:

| Service | Purpose |
|---------|---------|
| `app`   | Laravel API served by Apache (PHP 8.3). Runs migrations on start. |
| `queue` | `queue:work` worker for the GePG outbound / inbox queues. |
| `db`    | PostgreSQL 16 (data persisted in the `db-data` volume). |

1. **Prepare the environment**
   ```bash
   cp .env.example .env
   php artisan key:generate --show   # or any base64 32-byte key; paste into APP_KEY
   ```
   `DB_HOST`/`DB_PORT` are overridden to point at the `db` container; the
   Postgres user, password and database are created from `DB_USERNAME`,
   `DB_PASSWORD` and `DB_DATABASE`.

2. **Provide GePG keys** (required for bill signing). Place the key files in
   `docker/keys/` (git-ignored) or set `GEPG_KEYS_DIR` to another host folder.
   They are mounted read-only at `/opt/keys`, matching the `GEPG_*_KEY_PATH`
   values in `.env`.

3. **Start**
   ```bash
   docker compose up -d --build
   docker compose exec app php artisan db:seed --force   # first run only
   ```
   API: `http://localhost:8304/api` (change with `APP_PORT`), Swagger UI:
   `http://localhost:8304/api/documentation`, health check: `/up`.

Useful commands:

```bash
docker compose logs -f app queue
docker compose exec app php artisan <command>
docker compose down        # stop (keeps data)
docker compose down -v     # stop and delete database/storage volumes
```

Optional `.env` variables for Docker: `APP_PORT` (default `8304`),
`GEPG_KEYS_DIR` (default `./docker/keys`), `RUN_MIGRATIONS` (default `true`),
`RUN_SEEDERS` (default `false`). With `APP_ENV=production` the entrypoint
caches config, routes and views on start.

## API References

- **REST API:** `<your_host>/api/...`
- **Swagger UI:** `<your_host>/api/documentation`

## Useful Commands

- Clear caches: `php artisan optimize:clear`
- Rerun Swagger: `php artisan l5-swagger:generate`
- Run feature tests: `php artisan test`

Keep this README up-to-date when new environment variables or setup steps are introduced.
