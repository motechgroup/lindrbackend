# Lindr Multi-Gateway Payment API Contract

This document specifies the payment endpoints for integration with the **React Native / Expo** mobile client application.

---

## 1. Overview

The Lindr mobile app consumes a single, unified payment API interface (`/api/v1/payments/*`). All payment provider decisions (country eligibility, active payment methods, gateway priority, coin calculation, pricing validation, and wallet crediting) are handled authoritative backend side.

No financial logic, coin amounts, or gateway API keys exist in the React Native codebase.

---

## 2. Authentication

All customer payment endpoints require Sanctum bearer token authentication:

```http
Authorization: Bearer <user_sanctum_token>
Accept: application/json
```

---

## 3. Endpoints

### 3.1 Fetch Available Payment Methods

Dynamically fetches payment methods currently enabled and available for the user's detected or specified country/currency.

```http
GET /api/v1/payments/methods?country=KE&currency=KES
```

#### Query Parameters (Optional)
- `country` (string, ISO 2-letter, e.g. `KE`, `NG`, `GH`, `US`): Override detected country.
- `currency` (string, ISO 3-letter, e.g. `KES`, `NGN`, `GHS`, `USD`): Override currency.

#### Response (`200 OK`)
```json
{
    "success": true,
    "data": {
        "country": "KE",
        "currency": "KES",
        "methods": [
            {
                "code": "mpesa",
                "name": "M-Pesa Express (STK Push)",
                "provider": "mpesa",
                "enabled": true,
                "minimum_amount": 10.00,
                "maximum_amount": 250000.00
            },
            {
                "code": "korapay",
                "name": "KoraPay Checkout",
                "provider": "korapay",
                "enabled": true,
                "minimum_amount": 10.00,
                "maximum_amount": 500000.00
            },
            {
                "code": "flutterwave",
                "name": "Flutterwave (Cards & Mobile Money)",
                "provider": "flutterwave",
                "enabled": true,
                "minimum_amount": 10.00,
                "maximum_amount": 1000000.00
            },
            {
                "code": "google_pay",
                "name": "Google Pay",
                "provider": "google_pay",
                "enabled": true,
                "minimum_amount": 10.00,
                "maximum_amount": 1000000.00
            }
        ]
    }
}
```

---

### 3.2 Initiate Payment Transaction

Initiates a coin package purchase transaction using the selected payment method.

```http
POST /api/v1/payments/initiate
Content-Type: application/json
```

#### Request Body
```json
{
    "coin_package_id": 3,
    "payment_method": "mpesa",
    "country": "KE",
    "phone_number": "254711111111",
    "return_url": "https://lindr.app/payment/complete"
}
```

#### Request Fields
- `coin_package_id` (integer, required): ID of the selected `CoinPackage`.
- `payment_method` (string, required): Code of the selected payment method (`mpesa`, `korapay`, `flutterwave`, `google_pay`).
- `country` (string, optional): 2-letter country code (`KE`, `NG`, etc.).
- `phone_number` (string, required for M-Pesa / Mobile Money): E.164 or local phone number.
- `return_url` (string, optional): Web/App deep-link return URL following hosted checkout.
- `google_pay_token` (string, optional for Google Pay): Encrypted Google Pay payment token string.

#### Response (`201 Created` / `200 OK`)
```json
{
    "success": true,
    "message": "Payment initiated successfully.",
    "data": {
        "public_reference": "PAY-20260918-A1B2C3",
        "provider": "mpesa",
        "payment_method": "mpesa",
        "amount": 500.00,
        "currency": "KES",
        "expected_coins": 575,
        "status": "pending",
        "checkout_url": null,
        "action_data": {
            "checkout_request_id": "ws_CO_18092026194512_1234",
            "phone_number": "254711111111"
        }
    }
}
```

---

### 3.3 Check Payment Status

Polls payment status following initiation or mobile checkout sheet completion.

```http
GET /api/v1/payments/PAY-20260918-A1B2C3
```

#### Response (`200 OK`)
```json
{
    "success": true,
    "data": {
        "public_reference": "PAY-20260918-A1B2C3",
        "provider": "mpesa",
        "payment_method": "mpesa",
        "amount": 500.00,
        "currency": "KES",
        "expected_coins": 575,
        "status": "successful",
        "provider_reference": "QFH8123456",
        "error_message": null,
        "created_at": "2026-09-18T17:45:00Z",
        "updated_at": "2026-09-18T17:45:15Z"
    }
}
```

#### Possible Status Values
- `pending`: Payment requested; awaiting user authorization or STK PIN input.
- `processing`: Provider processing transaction.
- `successful`: Payment confirmed and coins credited to user wallet.
- `failed`: Payment declined or failed.
- `cancelled`: Payment cancelled by user or gateway.
- `expired`: Payment timed out.

---

### 3.4 Payment Transaction History

Fetches paginated transaction history for the authenticated user.

```http
GET /api/v1/payments?page=1&per_page=15&status=successful
```

---

## 4. Webhooks Architecture

Provider webhooks are authenticated server-side and processed asynchronously:

- `POST /api/v1/webhooks/kora` (Validates HMAC SHA512 signature header `X-Korapay-Signature`)
- `POST /api/v1/webhooks/flutterwave` (Validates header `verif-hash`)
- `POST /api/v1/webhooks/mpesa` (Processes Safaricom Daraja STK Push callbacks)
- `POST /api/v1/webhooks/googlepay` (Processes processor webhooks)

Coins are credited to the user's wallet **exactly once** using database transactions with row-level locking. Duplicate webhooks or retries are handled idempotently and safely ignored.
