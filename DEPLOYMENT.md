# Production Deployment Checklist

Internal checklist for deploying GatewayHub to production.

## 1. Environment Setup

### Required Variables

| Variable | Description |
|----------|-------------|
| `APP_ENV` | Must be `production` |
| `APP_KEY` | Run `php artisan key:generate` if missing |
| `APP_DEBUG` | Must be `false` in production |
| `DB_CONNECTION` | `mysql`, `pgsql`, or `sqlite` |
| `DB_*` | Host, database, username, password per connection type |

### Database

- **SQLite**: Set `DB_DATABASE` (path to database file)
- **MySQL/MariaDB**: Set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

### PayPal (if used)

If using PayPal webhooks, all three must be set:

- `PAYPAL_CLIENT_ID`
- `PAYPAL_CLIENT_SECRET`
- `PAYPAL_WEBHOOK_ID`

### Optional / Gateway-Specific

- `RATE_LIMIT_API_MAX_ATTEMPTS` (default: 60/min per merchant)
- `RATE_LIMIT_WEBHOOKS_MAX_ATTEMPTS` (default: 200/min per IP)
- `COINS_WEBHOOK_ALLOW_DEV_BYPASS` — **Never** set to `true` in production
- `GCASH_WEBHOOK_ALLOW_DEV_BYPASS` — **Never** set to `true` in production
- `MAYA_WEBHOOK_ALLOW_DEV_BYPASS` — **Never** set to `true` in production
- `PAYPAL_WEBHOOK_ALLOW_DEV_BYPASS` — **Never** set to `true` in production

### Validation

The application validates critical environment variables on boot when `APP_ENV=production`. Missing values will throw a clear exception and prevent startup.

---

## 2. Queue Worker Setup

Webhook processing runs via queues. A queue worker must be running.

### Commands

```bash
# Run a single worker (use process manager in production)
php artisan queue:work --queue=default

# With supervisor, use:
php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
```

### Failed Jobs

- Failed jobs are stored in the `failed_jobs` table
- Retry with: `php artisan queue:retry all`
- Monitor: `php artisan queue:failed`
- Failed webhook jobs are logged; check logs for `Webhook job failed after retries`

### Configuration

- `QUEUE_CONNECTION` — Use `database`, `redis`, or `sqs` in production (not `sync`)
- Ensure `jobs` and `failed_jobs` tables exist: `php artisan migrate`

---

## 3. Webhook URL Registration

Register these URLs with each payment provider:

| Provider | URL Path |
|----------|----------|
| Coins.ph | `{APP_URL}/api/webhooks/coins` |
| GCash | `{APP_URL}/api/webhooks/gcash` |
| Maya | `{APP_URL}/api/webhooks/maya` |
| PayPal | `{APP_URL}/api/webhooks/paypal` |

- Use HTTPS in production
- Ensure `APP_URL` is set correctly

---

## 4. Rate Limit Expectations

| Endpoint | Limit | Key |
|----------|-------|-----|
| `POST /api/payments` | 60 requests/min | Per merchant (API key) |
| `POST /api/webhooks/*` | 200 requests/min | Per IP |

When exceeded, clients receive HTTP 429 with a JSON message. Rate limit violations are logged.

---

## 5. Pre-Deploy Checklist

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` set
- [ ] Database configured and migrated
- [ ] PayPal credentials set (if using PayPal)
- [ ] Queue worker running
- [ ] Webhook URLs registered with providers
- [ ] No `*_WEBHOOK_ALLOW_DEV_BYPASS=true` in production
