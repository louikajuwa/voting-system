# BallotBox

A single-choice, open-email voting system. Laravel 12 + MySQL + Redis, with live results
pushed over a Node/Socket.IO bridge. Runs entirely in Docker.

## Stack

| Service     | What it is                          | URL / Port             |
|-------------|--------------------------------------|-------------------------|
| `app`       | Laravel (PHP 8.4-fpm)                 | —                        |
| `nginx`     | Web server                             | http://localhost:8080   |
| `mysql`     | Database                               | localhost:3308           |
| `redis`     | Cache / sessions / queue / pub-sub    | —                        |
| `socket`    | Node Socket.IO bridge (live results)   | proxied via nginx        |
| `phpmyadmin`| DB admin UI (opt-in, `tools` profile)  | http://127.0.0.1:8081   |
| `queue`     | Queue worker                           | —                        |
| `scheduler` | Laravel scheduler (runs every minute)  | —                        |

## Prerequisites

- Docker Desktop (with WSL2 integration, if on Windows)
- This project's directory must **not** contain a space — Docker's bind-mount parser breaks on
  spaces (`invalid spec: empty section between colons`). Keep it at a path like
  `voting-system`, not `voting system`.

## First-time setup

From the project root:

```bash
cp .env.example .env

# Generate the shared secret used between Laravel and the Socket.IO bridge
echo "SOCKET_SECRET=$(openssl rand -hex 32)" >> .env

# Build and start every container
docker compose up -d --build

# Install PHP dependencies and the app key
docker compose exec app composer install
docker compose exec app php artisan key:generate --force

# .env values are snapshotted into the container at boot, so recreate once
# the key + socket secret are in place
docker compose up -d --force-recreate app queue scheduler socket
docker compose restart nginx

# Storage permissions, public disk symlink, migrate + seed demo data
docker compose exec app sh -c "chmod -R 777 storage bootstrap/cache"
docker compose exec app php artisan storage:link
docker compose exec app php artisan migrate --seed --force
```

Then visit **http://localhost:8080**.

> If you have `make` installed, all of the above (except the two `.env` lines) is just
> `make setup`.

## Running it again (after the first time)

```bash
docker compose up -d
```

That's it — no need to re-run composer, migrate, or seed. Stop everything with:

```bash
docker compose down
```

Your data persists in Docker volumes between `up`/`down` cycles. To wipe the database and
reseed from scratch:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

## Visiting the site

| Page                          | URL                                                              |
|--------------------------------|-------------------------------------------------------------------|
| Public — live elections        | http://localhost:8080/                                            |
| Public — cast a ballot          | http://localhost:8080/vote/student-union-president-2026            |
| Public — live results           | http://localhost:8080/vote/student-union-president-2026/results    |
| Admin login                     | http://localhost:8080/login                                        |
| Admin dashboard                  | http://localhost:8080/dashboard (after logging in)                 |
| phpMyAdmin (opt-in, see Security)| http://127.0.0.1:8081                                             |

Voting itself needs no account — anyone with the ballot link picks a candidate and enters an
email. One vote per email per election is enforced at the database level.

## Admin login

The seeder creates two accounts (password is the same for both, change it before any real use):

| Role    | Email                     | Password  |
|---------|---------------------------|-----------|
| Admin   | `admin@ballotbox.test`    | `password`|
| Manager | `manager@ballotbox.test`  | `password`|

Log in at `/login` with either. Both roles can create elections, add candidates, change an
election's status (Draft → Live → Closed), and export a CSV of results from
`/admin/elections/{slug}`. There is no self-registration for admins — new staff accounts are
created directly in the database or via `php artisan tinker` for now.

## Useful commands

```bash
docker compose exec app php artisan migrate        # run new migrations
docker compose exec app php artisan db:seed        # re-run seeders
docker compose exec app bash                       # shell into the app container
docker compose logs -f --tail=100                  # tail all logs
docker compose ps                                  # container status
```

Running the test suite (isolated — sqlite in-memory, array drivers, does not touch your dev DB):

```bash
docker compose exec -T \
  -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e CACHE_STORE=array -e SESSION_DRIVER=array -e QUEUE_CONNECTION=sync -e MAIL_MAILER=array \
  app php artisan test
```

## Security and configuration

- **Registration and email verification.** `/register` creates a new organization and its first Admin. Registration is throttled (5/min) and the account must verify its email (the `verified` middleware guards the dashboard and admin areas). With `MAIL_MAILER=log` the verification link is written to `storage/logs/laravel.log`; configure real SMTP for production.
- **phpMyAdmin is opt-in.** Start it with `docker compose --profile tools up -d phpmyadmin`; it (and MySQL on :3308) only listens on `127.0.0.1`.
- **Redis password.** Set `REDIS_PASSWORD` in `.env` (e.g. `openssl rand -hex 24`) and recreate the stack (`docker compose up -d --force-recreate redis app queue scheduler socket`). `null` or empty means no password (dev only).
- **Trusted proxies.** `TRUSTED_PROXIES` (comma-separated IPs/CIDRs) controls whose `X-Forwarded-For`/`X-Forwarded-Proto` are honoured. The default is the private Docker ranges, and the bundled nginx overwrites those headers. Never set `*`; per-IP throttling and recorded vote IPs depend on this.
- **Rotate the dev socket secret.** Replace `SOCKET_SECRET` in `.env` with `openssl rand -hex 32`, then recreate `app`, `queue` and `socket`.
- **Production checklist.** `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `opcache.validate_timestamps=0`.
- Voter-level data (vote log with email/IP, `votes.csv`, `votes.xlsx`) is Admin-only; the aggregate `report.csv` is available to Managers too. Exports neutralise spreadsheet formulas.

## License

MIT — see [LICENSE](LICENSE).
