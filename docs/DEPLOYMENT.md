# PMS — Production Deployment Runbook

Demo deployment to AlmaLinux 10 on Oracle Cloud (KVM, 1 GB RAM, 2 vCPU).
Treat this as the **demo-grade** setup. Once the client commits, move to a
4–8 GB VPS and re-enable Horizon + Meilisearch + Reverb.

## Architecture (current box)

```
client browser
      │ HTTPS
      ▼
host nginx (existing — already serving lms.bjptechnologies.co.tz)
      │   pms.bjptechnologies.co.tz block proxies to ↓
      │   http://127.0.0.1:9080
      ▼
PMS app container  (in-container nginx → php-fpm)
   ├── pgsql container  (Postgres 16)
   ├── redis container  (Redis 7, 50 MB cap)
   └── scheduler container  (php artisan schedule:run loop)
```

**Deliberately NOT running on the 1 GB box** (re-enable on bigger host):
- Horizon worker — notifications are sync, no queue worker needed yet
- Meilisearch — use Postgres `ilike` for search
- Reverb websockets — no real-time features in demo
- Browsershot/Chromium — PDF generation will fail; UI returns a "Not available in demo" toast (already wrapped in try/catch in the codebase)

## One-time host prep

```bash
# Copy and run the bootstrap script
scp docker/server-bootstrap.sh opc@40.233.127.121:~
ssh opc@40.233.127.121 'chmod +x ~/server-bootstrap.sh && ~/server-bootstrap.sh'

# Log out and back in so the docker group membership takes effect
ssh opc@40.233.127.121
docker run --rm hello-world   # sanity check
```

The script:
- Installs Docker Engine + Compose plugin
- Adds `opc` to the `docker` group
- Creates `/opt/pms` working directory
- Generates `~/.ssh/gha_pms_deploy` keypair, appends the public half to `~/.ssh/authorized_keys`

## GitHub repository secrets

Settings → Secrets and variables → Actions → New repository secret:

| Secret | Value |
|---|---|
| `PROD_SSH_HOST` | `40.233.127.121` |
| `PROD_SSH_USER` | `opc` |
| `PROD_SSH_KEY` | contents of `~/.ssh/gha_pms_deploy` on the server (the PRIVATE key) |
| `GHCR_READ_TOKEN` | a PAT with `read:packages` scope (only needed if the GHCR image is private) |

## DNS + SSL

1. Add an A record: `pms.bjptechnologies.co.tz → 40.233.127.121`
2. Wait for propagation (`dig pms.bjptechnologies.co.tz +short` returns the IP)
3. Drop the nginx block and provision a cert:

```bash
# Place the server block (cert paths will be rewritten by certbot)
sudo cp /opt/pms/repo/docker/nginx-pms.conf /etc/nginx/conf.d/pms.conf
sudo nginx -t   # syntax check
# Don't reload nginx yet — the ssl_certificate path doesn't exist

sudo certbot --nginx -d pms.bjptechnologies.co.tz
# Certbot writes the cert AND rewrites the ssl_* paths in pms.conf

sudo systemctl reload nginx
```

Cert auto-renewal runs from the existing certbot timer — no extra setup.

## /opt/pms/.env

The deploy workflow expects this file to exist on the server with all
production secrets. Create it once:

```bash
ssh opc@40.233.127.121
cd /opt/pms
cp /path/to/your/repo/docker/.env.production.example .env
nano .env   # fill in real secrets
chmod 600 .env
```

> **Security:** keep `APP_DEBUG=false` in production (the app also forces it off
> as a safety net so a misconfigured server can never leak stack traces or
> Ignition). Set `BEEM_API_KEY`/`BEEM_SECRET` to deliver activation links by SMS
> as well as email — optional; email-only works without them.

Then on the server, copy the compose file in place:

```bash
sudo cp /opt/pms/repo/docker/docker-compose.production.yml /opt/pms/
```

(or `git clone` the repo to `/opt/pms/repo` so updates are easy.)

## First deploy

1. Trigger the workflow: GitHub → Actions → "Deploy to production" → Run workflow
2. Watch the run; the SSH step prints `✅ Deployed <sha>` on success
3. Verify on the server:
   ```bash
   docker compose -f /opt/pms/docker-compose.production.yml ps
   curl -I https://pms.bjptechnologies.co.tz
   ```
4. Create the first super admin:
   ```bash
   cd /opt/pms
   docker compose -f docker-compose.production.yml --env-file .env exec app \
       php artisan tinker
   # then in tinker:
   # App\Models\SuperAdminUser::create([
   #   'name' => 'BJP Admin',
   #   'email' => 'admin@bjptechnologies.co.tz',
   #   'password' => Hash::make('change-me'),
   # ]);
   ```
5. Visit `/admin/login`, provision the first client workspace, hand the URL to the boss.

## Daily ops

**Deploy a new version:** push to `main`, then trigger the workflow.

**View live logs:**
```bash
docker compose -f /opt/pms/docker-compose.production.yml logs -f --tail=100 app
```

**Run an artisan command:**
```bash
cd /opt/pms
docker compose -f docker-compose.production.yml --env-file .env exec app \
    php artisan <cmd>
```

**Backups** — set up cron once on the host:
```bash
chmod +x /opt/pms/repo/docker/backup-postgres.sh
crontab -e
# Add:
# 30 2 * * *  /opt/pms/repo/docker/backup-postgres.sh >> /opt/pms/backups/backup.log 2>&1
```

## Rollback

```bash
cd /opt/pms
./repo/docker/rollback.sh --list           # see recent SHAs
./repo/docker/rollback.sh abc1234567       # roll back

# If the SHA predates a migration, you must also rollback the schema:
docker compose -f docker-compose.production.yml --env-file .env exec app \
    php artisan migrate:rollback --step=1
```

## Memory budget on 1 GB

| Process | Approx. RSS |
|---|---|
| Existing host nginx + php-fpm + mariadb + lms site | ~300 MB |
| PMS app container (nginx + php-fpm + workers) | ~280 MB |
| Postgres container (tuned small) | ~150 MB |
| Redis container | ~20 MB |
| Scheduler container (idle) | ~50 MB |
| Kernel + buffers | ~100 MB |
| **Total** | **~900 MB** |

That leaves ~100 MB headroom on a 1 GB box. Watch swap:
```bash
free -h && docker stats --no-stream
```

If you see swap climbing or any container OOM-killed, **upgrade the box**.
Don't waste a demo trying to squeeze more out of 1 GB.

## When the demo wins

Move to a 4 GB+ host. The compose file's memory limits are conservative —
on a 4 GB box, also:
1. Add Horizon (re-enable `ShouldQueue` on notifications)
2. Add Meilisearch service
3. Bump `pm.max_children` in `docker/php-fpm.conf` to 15
4. Add the Reverb websocket service for real-time features
5. Set up Sentry (`SENTRY_DSN` env)
6. Configure Uptime Kuma external monitoring
