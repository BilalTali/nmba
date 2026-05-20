# NMBA Agent Portal

An internal Laravel/Inertia.js portal for logging and submitting Nasha Mukt event data to the government portal at [nashamuktjk.org](https://nashamuktjk.org).

**Stack:** Laravel 11 · React/Inertia.js · PHP 8.3 · MySQL · LiteSpeed · Hostinger Shared Hosting

---

## Deployment Secrets

The following secrets **must** be configured in `.env` before the application will function. Never commit `.env` to version control — it is listed in `.gitignore`.

### CRON_TOKEN

Authenticates the web-cron endpoint (`/nmba-cron.php?token=...`) called by Hostinger hPanel every 5 minutes.

**To generate a new token:**
```bash
openssl rand -hex 32
```

**To set it up:**
1. Add `CRON_TOKEN=<generated_value>` to your `.env` file on the server
2. Log in to Hostinger hPanel → Cron Jobs
3. Update the cron job URL to: `https://nmbabudgam.in/nmba-cron.php?token=<generated_value>`
4. Test it: the URL should return HTTP 200 with a timestamp

> **Rotation policy:** Rotate the cron token whenever a deployment team member leaves, or at minimum annually. Generate a new value with `openssl rand -hex 32`, update `.env` and hPanel simultaneously.

### PORTAL_EMAIL / PORTAL_PASSWORD

Credentials for authenticating against `nashamuktjk.org/enterprise`.

**Rotation policy (quarterly):**
1. Change the password on the government portal
2. Update `PORTAL_PASSWORD` in `.env`
3. Update `PORTAL_CREDENTIALS_LAST_ROTATED` to today's date (`YYYY-MM-DD`)
4. Run `php artisan portal:check-credentials` to verify

### ADMIN_EMAIL

Email address that receives automated sync backlog alerts when events are stuck in `pending` status for over 30 minutes (runs every 15 minutes via scheduler).

### SYNC_MODE

Controls queue dispatch behaviour. Set in `.env`:
- `SYNC_MODE=async` — normal operation, events are dispatched to the queue (default)
- `SYNC_MODE=sync` — emergency rollback mode, portal API is called synchronously per request

See `ROLLBACK.md` for full rollback procedure.

---

## Local Development Setup

```bash
git clone https://github.com/BilalTali/nmba
cd nmba
composer install
npm install

cp .env.example .env
# Edit .env: set DB credentials, PORTAL_*, CRON_TOKEN, ADMIN_EMAIL

php artisan key:generate
php artisan migrate
php artisan db:seed

npm run dev
php artisan serve
```

Start the queue worker in a separate terminal:
```bash
php artisan queue:work database --tries=10
```

---

## Production Deployment

See `DEPLOYMENT.md` for full production deployment instructions including:
- hPanel cron setup (two entries for burst throughput)
- Queue worker configuration via `nmba-worker.conf`
- Throughput math and capacity planning

---

## Artisan Commands Reference

| Command | Description | Schedule |
|---|---|---|
| `php artisan sync:health-check` | Alerts if events stuck in pending >30min | Every 15 min |
| `php artisan portal:check-credentials` | Tests portal auth and logs result | Weekly |
| `php artisan audit:rehash-events` | Detects B-03 hash corruption (run once) | Manual |
| `php artisan queue:retry all` | Re-queues all failed jobs | Manual |
| `php artisan queue:monitor database:50` | Reports queue depth | Manual |

---

## Running Tests

```bash
php artisan test
```

Individual test groups:
```bash
php artisan test --filter=CronToken          # SEC-01
php artisan test --filter=SemanticHash       # ARCH-01
php artisan test --filter=ConcurrentSubmit   # ARCH-02
php artisan test --filter=SyncHealthCheck    # OPS-01
php artisan test --filter=AuditRehash        # DATA-01
```

---

## Security

- `.env` is gitignored — never commit credentials to the repo
- `CRON_TOKEN` is loaded from `.env` at runtime only — never hardcoded
- Report security issues privately to the project maintainer
