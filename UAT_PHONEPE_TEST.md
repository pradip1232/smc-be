# UAT: PhonePe Payment API Test (SMC)

## 1) Pre-checks (XAMPP)
1. Ensure Apache is running.
2. Enable PHP extensions in `php.ini`:
   - `curl`
   - `openssl`
   - `pdo_mysql`
3. Restart Apache after enabling extensions.

## 2) Verify PhonePe endpoints exist
- Initiate payment (creates order + calls PhonePe):
  - `POST http://localhost/smc/api/v1/data/CreateOrderOnline.php`
- Callback handler (PhonePe server hits this):
  - `POST http://localhost/smc/api/v1/data/phonepe-callback.php`

## 3) Mandatory configuration
Edit this file and replace placeholders:
- `api/v1/data/phonepe-config.php`

Must set:
- `PHONEPE_MERCHANT_ID`
- `PHONEPE_MERCHANT_KEY`
- `PHONEPE_REDIRECT_URL`
- `PHONEPE_CALLBACK_URL`

> IMPORTANT: redirectUrl and callbackUrl should be publicly reachable from PhonePe.
> On local testing, you must use an ngrok/localtunnel URL.

## 4) How to run a UAT test (local curl)
### 4.1 Payment initiation request
Run this curl from your machine:

```bash
curl -X POST "http://localhost/smc/api/v1/data/CreateOrderOnline.php" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_method":"ONLINE",
    "user_id": 1,
    "total_amount": 799.00,
    "subtotal": 799.00,
    "tax": 0,
    "shipping_cost": 0,
    "shipping": {
      "firstName": "Test",
      "lastName": "User",
      "name": "Test User",
      "phone": "9999999999",
      "customer_name": "Test User",
      "customer_email": "test@example.com",
      "email": "test@example.com",
      "shipping_address": "Some Address",
      "address": "Some Address",
      "city": "Mumbai",
      "state": "Maharashtra",
      "country": "India",
      "zip": "400001"
    },
    "items": [
      {
        "product_id": "SMC-PROD-002",
        "quantity": 1,
        "unit_price": 799.00
      }
    ]
  }'
```

### 4.2 Expected response
HTTP 200 with JSON:
- `status: true`
- `order_id: <your order ref>`
- `payment_url: <PhonePe redirectUrl>`

Open the returned `payment_url` in browser.

## 5) Callback verification (UAT)
After successful payment, your PhonePe will call callback URL.

Expected callback response:
- JSON: `{ "success": true }`

Then check in MySQL:
- `orders.payment_status` becomes `paid`
- `orders.order_status` becomes `confirmed`

## 6) Known schema mismatch warning (you must align)
This repo contains schema definitions that may not match the PhonePe order code.
- `CreateOrderOnline.php` inserts/updates columns like:
  - `customer_phone`, `customer_name`, `payment_status`, `order_status`, `shipping_cost`, `pincode`, etc.
- Your schema in `db/schema.sql` may differ.

If callback doesn’t update correctly, sync DB schema to the columns used by:
- `api/v1/data/CreateOrderOnline.php`
- `api/v1/data/phonepe-callback.php`

## 7) Troubleshooting
- If error is `curl not found`:
  - enable `extension=curl` in `php.ini` and restart Apache.
- If `PDO` errors:
  - ensure `pdo_mysql` enabled.
- If signature error:
  - confirm Merchant Key + payload match.

---
End of UAT test guide.

