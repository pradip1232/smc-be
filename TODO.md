# TODO

- [ ] Update `api/v1/data/CreateOrderOnline.php`:
  - [x] Remove stray `print_r($items);` that breaks JSON output
  - [x] Fix `$order_ref` generation bug (`||` precedence issue) with correct concatenation
  - [x] Fix validation for `$total_amount` (don’t reject valid totals incorrectly)
  - [x] Prevent null `$prod` access (throw a clear error if product not found)
  - [ ] Decide transaction/atomicity behavior around PhonePe initiation; implement chosen approach
  - [ ] Improve error messages / HTTP codes for client



- [ ] Quick test call against `UAT_PHONEPE_TEST.md` payload (verify JSON and redirect URL)
- [ ] (Optional) align `CreateOrderOnline_SDK.php` after confirming ONLINE endpoint behavior

