# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability within this package, please email
support@fahipay.mv. Do not open a public GitHub issue for security reports.
You will receive an acknowledgement within 72 hours, and we will keep you
informed while the issue is triaged and fixed.

## Security Model

### Signature scheme

FahiPay's API dictates the signature format: `base64(sha1(ShopID . SecretKey .
ShoppingCartID . SecretKey . value . SecretKey))`. This is **not** HMAC — it is
the scheme required by the FahiPay merchant specification, and the package
cannot unilaterally change it. Within that constraint the package:

- verifies every signature with `hash_equals()` (constant-time comparison);
- rejects unsigned success callbacks outright;
- requires an approval code on success callbacks;
- treats unsigned failed/cancel browser returns as display-only — they can
  never change payment state or fire package events.

### Replay protection

The signature contains no timestamp or nonce, so a captured callback can be
replayed. The package mitigates this with terminal payment states: once a
payment is COMPLETED, replayed or late failure/cancel callbacks are ignored.
Status transitions run inside a database transaction with a pessimistic lock,
so concurrent webhook deliveries and the expiry job cannot interleave.

### Endpoint protection

- The webhook endpoint is authenticated by the callback signature and rate
  limited (`throttle:60,1`) by default. It intentionally carries no `auth`
  middleware, because FahiPay's servers cannot authenticate against your app.
- The optional payments API requires an authenticated middleware stack
  (default `['api', 'auth']`) and is disabled by default.
- Administrative endpoints (list all payments, update, delete) are separately
  disabled by default and require their own authenticated middleware
  (`auth:sanctum` by default) to enable.

### Input validation

- Transaction IDs must match `^[A-Za-z0-9\-_]{1,100}$` everywhere they enter
  the package (API, Livewire, gateway calls).
- Amounts are validated against min/max bounds before signing.
- Custom callback/redirect URLs are rejected unless their host appears in the
  `allowed_redirect_hosts` allow-list (or unrestricted mode is explicitly
  enabled). Gateway redirect responses are validated against a fixed FahiPay
  host allow-list to prevent SSRF.
- Database access goes through Eloquent with parameter binding.

### Data exposure

- The secret key is never included in API responses, logs, or error messages.
- Webhook error responses return generic messages; details go to logs only.
- Livewire component amount and transaction id are `#[Locked]` so the client
  cannot tamper with them.

## Integration Best Practices

1. **Always verify callbacks** — never trust the `Success` parameter alone:

```php
if (! $gateway->validateCallback($request)) {
    return response('Invalid signature', 403);
}
```

2. **Use HTTPS in production** for all configured URLs.

3. **Record every transaction** in your database via `FahipayPayment` so
   webhook state transitions and the expiry job can reconcile them.

4. **Drive order fulfilment from events** (`PaymentCompletedEvent`), not from
   browser return URLs — returns are display-only.

5. **Keep the admin API disabled** unless you need it, and only enable it
   behind authenticated middleware.
