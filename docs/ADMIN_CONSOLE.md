# Admin console

`/admin` — available to users with `is_admin = true` (or listed in `ADMIN_EMAILS`). Every write action is rate limited and recorded in the **Audit trail**.

## Pages

| Section | What you can see | What you can do |
|---|---|---|
| Dashboard | Alerts (stuck orders, provider outages/low balance, past-due numbers, debug-in-production), revenue/customers/wallet KPIs, 30-day revenue & sign-up chart, revenue mix, payment outcomes, provider strip, latest payments, new customers, admin activity | — |
| Users | Search, filters (verified, unverified, funded, suspended, admins), sort | Open a customer 360° profile |
| User profile | Identity, balance, lifetime spend, payments, wallet ledger, virtual/social numbers & rentals, sign-in history, app sessions, admin history | Suspend / reinstate, verify email, sign out all devices, grant/revoke admin, credit/debit wallet |
| Payments / eSIM orders | Every checkout with status chips, provider/product/date filters, ICCID & provisioning errors | Retry eSIM fulfilment (only when nothing was provisioned), refund to wallet |
| Wallet ledger | All credits/debits, float, 30-day flows, largest balances | — |
| Virtual numbers | Subscriptions, MRR, catalogue, latest SMS/calls | Cancel & release a number |
| Social numbers | Orders with price vs provider cost, margin, SMS success rate, top apps, rentals | Cancel order (provider cancel + wallet refund), cancel rental |
| Providers | Paystack, Airalo, GloEsim, SMSPool, 5SIM, Twilio, Cryptomus, mail — status and live balances (cached 5 min, stale-while-revalidate) | Check now |
| Settings | Maintenance mode & message, sign-ups, per-product kill switches, pricing multipliers, alert thresholds | Save (takes effect immediately) |
| Login activity | Success/failure, channel (web, Google/Facebook, mobile app), device, IP; repeated-failure IPs | — |
| System & logs | Environment, queue (pending/failed), table sizes, `laravel.log` tail with level filter | Retry failed jobs, clear caches |
| Audit trail | Who did what, when, from which IP | — |

## How enforcement works

- `EnsureAccountActive` (web + API): suspended users are signed out / get `403 account_suspended`. `/api/auth/login` refuses suspended accounts.
- `EnforcePlatformSettings` (web + API): maintenance mode returns `503 maintenance` to customers (admins, login and payment webhooks stay open); feature switches return `503 feature_disabled` on the purchase endpoints listed in the middleware.
- Pricing settings override the matching `config('services.…')` keys at boot (`SettingsService::applyConfigOverrides`), so services need no changes.

## Code map

```
app/Http/Controllers/Admin/   Dashboard, User, Payment, Wallet, Numbers, Platform controllers
app/Services/Admin/           AdminMetrics, AuditLogger, LoginRecorder, ProviderStatusService,
                              SettingsService, EsimFulfillmentRetrier
app/Http/Middleware/          EnsureAccountActive, EnforcePlatformSettings
resources/views/admin/        Pages
resources/views/components/admin/  Layout (sidebar) + UI kit (card, stat, badge, table, dialog…)
routes/admin.php              All admin routes
tests/Feature/AdminConsoleTest.php
```
