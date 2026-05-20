# NMBA Agent Portal — Rollback Playbook

## When to Use This Document

Use this playbook if:
- A deployment introduces a regression in event sync behaviour
- The async queue architecture causes data loss or doubled submissions
- The portal API integration breaks after a code change
- You need to immediately halt all queue processing

---

## Step 0 — Pre-Deployment Snapshot (Run BEFORE every deployment)

```bash
# MySQL dump — run on the server before pulling any new code
mysqldump -u DB_USERNAME -p DB_DATABASE > /home/u335000182/backups/nmba_pre_deploy_$(date +%Y%m%d_%H%M%S).sql

# Verify the dump is non-zero
ls -lh /home/u335000182/backups/nmba_pre_deploy_*.sql
```

---

## Step 1 — Emergency: Switch to Synchronous Mode

If the async queue is causing problems, flip the `SYNC_MODE` flag to bypass the queue entirely. Events will be submitted synchronously per HTTP request (slower UX, but guaranteed delivery).

**Edit `.env` on the server:**
```
SYNC_MODE=sync
```

Then clear the config cache:
```bash
php artisan config:cache
```

> In `SYNC_MODE=sync`, `EventController::store()` calls the portal API directly within the HTTP request cycle. No queue workers are involved.

**To resume async operation:**
```
SYNC_MODE=async
php artisan config:cache
```

---

## Step 2 — Halt Queue Processing

To stop all workers from picking up new jobs immediately:

```bash
# Signal all running workers to stop after their current job
php artisan queue:pause database

# Or kill the worker process directly if using Supervisor/nmba-worker.conf
supervisorctl stop nmba-worker:*
```

To disable the hPanel cron (web-based trigger):
- Log into Hostinger hPanel → Cron Jobs → disable the cron entries

---

## Step 3 — Re-Queue Stuck Events

If events are stuck in `syncing` status after a worker crash:

```bash
# View current queue depth
php artisan queue:monitor database:50

# Retry all failed jobs (jobs in the failed_jobs table)
php artisan queue:retry all

# Reset events that are frozen in 'syncing' status
# (The scheduler's zombie detection handles this automatically every 10min,
# but you can force it manually:)
php artisan tinker --execute="
    App\Models\Event::where('sync_status', 'syncing')
        ->where('updated_at', '<', now()->subMinutes(10))
        ->update(['sync_status' => 'pending', 'last_attempt_at' => null]);
    echo 'Reset complete';
"
```

---

## Step 4 — Revert Code

```bash
cd /home/u335000182/domains/nmbabudgam.in/nmbaagent

# Find the last good commit
git log --oneline -10

# Revert to the last good commit
git revert HEAD           # creates a new revert commit (safe)
# OR
git checkout <commit-sha> # point HEAD at a specific commit (check out only)

# Rerun migrations if needed (rollback)
php artisan migrate:rollback --step=1

# Rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## Step 5 — Restore from DB Snapshot

Only use this if data integrity is compromised and a revert is not sufficient.

```bash
# Drop and restore
mysql -u DB_USERNAME -p DB_DATABASE < /home/u335000182/backups/nmba_pre_deploy_YYYYMMDD_HHMMSS.sql

# Clear application caches after restore
php artisan cache:clear
php artisan config:cache
```

---

## Step 6 — Verify Recovery

```bash
# Check portal health
php artisan portal:check-credentials

# Run health check manually
php artisan sync:health-check --dry-run

# Check queue depth
php artisan queue:monitor database:50

# Confirm no events are stuck in 'syncing'
php artisan tinker --execute="
    echo App\Models\Event::where('sync_status', 'syncing')->count() . ' syncing events';
    echo App\Models\Event::where('sync_status', 'pending')->count() . ' pending events';
"
```

---

## Emergency Contacts

| Role | Responsibility |
|---|---|
| Application Owner | Approve rollback decisions, coordinate with government portal team |
| Developer On-call | Execute rollback steps, deploy fixes |
| Hostinger Support | Server/hPanel issues — live chat at hpanel.hostinger.com |

---

## Queue Monitoring Commands Reference

```bash
# Show queue depth (alert if > 50)
php artisan queue:monitor database:50

# List all failed jobs
php artisan queue:failed

# Retry a specific failed job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Flush (delete) all failed jobs
php artisan queue:flush

# Pause queue processing
php artisan queue:pause database

# Resume queue processing
php artisan queue:resume database
```
