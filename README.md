# Spacechip

Travel eSIMs, virtual phone numbers and verification numbers, sold through a web dashboard (installable PWA) and a React Native app that share one Laravel backend.

* **eSIMs** via Airalo: buy, install by QR, top up
* **Virtual numbers** via Twilio: monthly numbers with an SMS/voice inbox
* **Social numbers** via SMSPool: one-time OTP numbers and monthly rentals
* **Payments** via Paystack (card, NGN/USD), Cryptomus (crypto, web) or a USD wallet

**Read next:** [ARCHITECTURE.md](ARCHITECTURE.md) (how it fits together) · [docs/mobile_api.md](docs/mobile_api.md) (mobile API contract) · [docs/ADMIN_CONSOLE.md](docs/ADMIN_CONSOLE.md) (admin console)

---

## Requirements

* PHP 8.3+ with `pdo_mysql` (or `pdo_sqlite` for local/testing)
* Composer 2
* Node 20+ (only if you build front-end assets)
* MySQL 8 in production

## Getting started

```bash
composer setup          # install, copy .env, generate key, migrate, build assets
composer dev            # server + queue + logs + Vite, all at once
```

Then set the provider credentials you need in `.env`. Every key is listed in `.env.example`. Use `AIRALO_SANDBOX=true` and the Paystack test keys locally.

To grant yourself admin access, add your email to `ADMIN_EMAILS` (comma-separated) or set `users.is_admin = 1`.

## Everyday commands

| Task | Command |
|---|---|
| Run tests | `composer test` |
| Format code | `./vendor/bin/pint` |
| List routes | `php artisan route:list --except-vendor` |
| Run scheduled jobs once | `php artisan schedule:run` |
| Warm the eSIM catalogue | `php artisan airalo:sync-catalog` |
| Build CSS/JS | `npm run build` |

## Deployment

Pushing to **`main` deploys to production** (`.github/workflows/deploy.yml`): pull, `composer install --no-dev`, migrate, then cache config, routes and views. Work on a branch and merge deliberately.

The server cron must run `php artisan schedule:run` every minute so virtual number and rental renewals happen.

## Project layout (short version)

```
app/Http/Controllers/{Api,Admin,Auth,Esim,Payments,VirtualNumbers,Webhooks}
routes/admin.php         admin console (/admin/*)
app/Services/            one adapter per provider (Airalo, GloEsim, Twilio, SMSPool, Cryptomus, FX)
routes/web.php           pages; loads routes/web/*.php (one file per domain)
public/css/spacechip-ui.css   design-system foundation layer
```

Full map, flows, data model and conventions: [ARCHITECTURE.md](ARCHITECTURE.md).
