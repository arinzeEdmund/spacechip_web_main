# Spacechip Mobile API (Card + Wallet, no Crypto)

This document describes the HTTP APIs to implement the full Spacechip mobile experience (authentication → eSIM → wallet → virtual numbers).

## Conventions

- **Base URL**: `https://spacechipltd.com`
- **Auth**: Bearer token (Laravel Sanctum)
  - Header: `Authorization: Bearer <token>`
  - Header: `Accept: application/json`
- **Money**
  - `*_minor` means cents (USD) or kobo (NGN) depending on the `currency`.
  - `USD 1.23` is `123` minor.
- **Standard error**
  - `4xx/5xx` responses usually include `{ "message": "..." }`

## Authentication

### Register
`POST /api/auth/register`

Body:
```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "password1234",
  "device_name": "ios"
}
```

Response:
```json
{
  "ok": true,
  "token_type": "Bearer",
  "token": "plain_text_token_here",
  "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "email_verified": false }
}
```

### Login
`POST /api/auth/login`

Body:
```json
{ "email": "jane@example.com", "password": "password1234", "device_name": "android" }
```

Response:
```json
{
  "ok": true,
  "token_type": "Bearer",
  "token": "plain_text_token_here",
  "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com", "email_verified": true, "wallet_balance_minor": 500 }
}
```

### Current user
`GET /api/auth/me` (Auth required)

### Logout
`POST /api/auth/logout` (Auth required)

### Resend verification email
`POST /api/auth/resend-verification` (Auth required)

### Forgot password (email reset link)
`POST /api/auth/forgot-password`

Body:
```json
{ "email": "jane@example.com" }
```

## Wallet (USD)

### Balance
`GET /api/wallet/balance` (Auth + verified)

Response:
```json
{ "ok": true, "balance_minor": 1234, "balance_formatted": "$12.34" }
```

### Transactions (ledger)
`GET /api/wallet/transactions` (Auth + verified)

Response:
```json
{
  "ok": true,
  "items": [
    {
      "id": 10,
      "direction": "credit",
      "action": "deposit",
      "amount_minor": 2000,
      "balance_after_minor": 2500,
      "currency": "USD",
      "payment_id": 55,
      "meta": { "reference": "..." },
      "created_at": "2026-04-11T12:34:56Z"
    }
  ]
}
```

### Deposit (Paystack initialize)
`POST /api/wallet/deposit/paystack/initialize` (Auth + verified)

Body:
```json
{ "amount_usd": 20 }
```

Response includes Paystack `access_code` + `reference`. Use Paystack mobile SDK to charge.

### Deposit (Paystack verify)
`POST /api/wallet/deposit/paystack/verify` (Auth + verified)

Body:
```json
{ "reference": "uuid-from-initialize" }
```

## eSIM (Glo eSIM) — Card + Wallet

### Landing / assets list
These are public data feeds used to build the store UI.

- `GET /api/landing`
- `GET /api/allassets?tab=countries|regions&page=1&q=...`

### eSIM checkout with card (Paystack)
- Initialize: `POST /api/paystack/initialize` (Auth)
- Verify: `POST /api/paystack/verify` (Auth)
- Status/polling: `POST /api/paystack/status` (Auth)

Payloads depend on the selected asset + bundle (the web app uses these already). Mobile should send the same fields the web checkout sends.

### eSIM checkout with wallet (USD)
`POST /api/wallet/pay/esim` (Auth + verified)

Body (buy):
```json
{
  "type": "country",
  "id": "US",
  "bundle": "BUNDLE_ID",
  "package_type": "DATA-ONLY"
}
```

Body (top-up / renew):
```json
{
  "type": "country",
  "id": "US",
  "bundle": "BUNDLE_ID",
  "package_type": "DATA-ONLY",
  "topup_esim_id": "ESIM_ID"
}
```

Response:
- `ok=true` and `fulfillment` contains QR + activation data if fulfilled immediately.
- If provider is pending, response indicates it and the client should refresh “My eSIMs”.

### My eSIMs
- List: `GET /api/my-esims` (Auth)
- Sync/refresh: `POST /api/my-esims/sync` (Auth)

## Virtual Numbers — Card + Wallet + Inbox

### Countries catalog
`GET /api/virtual-numbers/countries?page=1&per_page=60&q=...&context=dashboard` (Auth + verified)

### Country meta (product + starting price)
`GET /api/virtual-numbers/country/{countryIso}` (Auth + verified)

### Available numbers in a country/product
`GET /api/virtual-numbers/available?product_id=123&page=0&per_page=20&q=...` (Auth + verified)

### My subscriptions
`GET /api/virtual-numbers/my` (Auth + verified)

### Cancel subscription
`POST /api/virtual-numbers/{subscriptionId}/cancel` (Auth + verified)

### Buy with card (Paystack)
- Initialize: `POST /api/virtual-numbers/paystack/initialize` (Auth + verified)
- Verify + fulfill: `POST /api/virtual-numbers/paystack/verify` (Auth + verified)

### Buy with wallet
`POST /api/wallet/pay/virtual-number` (Auth + verified)

Body:
```json
{
  "product_id": 123,
  "phone_number": "+14155552671",
  "number_type": "Local"
}
```

### Inbox (receive + reply)

#### List messages (SMS/MMS + voicemail records)
`GET /api/virtual-numbers/{subscriptionId}/messages?per_page=120` (Auth + verified)

#### Send SMS/MMS
`POST /api/virtual-numbers/{subscriptionId}/messages/send` (Auth + verified)

Body:
```json
{ "to": "+14155552671", "body": "Hello", "media_urls": [] }
```

#### View MMS media
`GET /api/virtual-numbers/messages/{messageId}/media/{index}` (Auth + verified)

#### Play voicemail audio
`GET /api/virtual-numbers/messages/{messageId}/recording` (Auth + verified)

### Forwarding (email/phone)
Attach an email or phone to receive notifications for inbound messages/voicemail.

- Get settings: `GET /api/virtual-numbers/{subscriptionId}/settings` (Auth + verified)
- Update settings: `POST /api/virtual-numbers/{subscriptionId}/settings` (Auth + verified)

Body:
```json
{
  "forward_to_email": "notify@example.com",
  "forward_to_phone": "+14155552671"
}
```

## Notes for Mobile Payments (Card)

- Paystack should be completed using the official Paystack Android/iOS SDK.
- The API returns `access_code` and `reference`. Use those to complete the charge, then call the matching `verify` endpoint.

