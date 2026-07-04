# PhonePe SDK endpoints (STANDARD CHECKOUT)

## 1) Configure credentials
Edit:
- `api/v1/data/phonepe-sdk-config.php`

Update:
- `clientId`
- `clientVersion`
- `clientSecret`
- `env` (`SANDBOX` or `PRODUCTION`)
- `redirectUrl`

## 2) Endpoints
### A) Initiate payment (SDK)
`POST /smc/api/v1/data/CreateOrderOnline_SDK.php`

Returns:
- `status` (boolean)
- `order_id`
- `payment_state`
- `payment_url` (only when `payment_state` is `PENDING`)

### B) Status check (SDK)
`POST /smc/api/v1/data/PhonePeStatusCheck_SDK.php`

Body:
- `merchant_order_id`

Returns:
- PhonePe status fields (`state`, `amount`, etc.)

### C) Refund (SDK)
`POST /smc/api/v1/data/PhonePeRefund_SDK.php`

Body:
- `original_merchant_order_id`
- `amount` (rupees as decimal OR paisa as integer)

Optional:
- `merchant_refund_id`

## 3) Notes
- This repo already has `CreateOrderOnline.php` using custom cURL + signature logic.
- The files above are SDK-based equivalents and do not replace the existing callback.

