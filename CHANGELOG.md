# Changelog

All notable changes to `fahipaydev/gateway-laravel` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-02

### Added
- FahiPay payment creation via `createTxn` API and hosted payment page redirect.
- Signed callback validation (SHA1/base64 per FahiPay specification) with
  constant-time comparison and replay protection via terminal payment states.
- Optional REST API (create/show/verify) with admin endpoints (list/update/delete)
  disabled by default behind an authenticated middleware stack.
- Webhook endpoint authenticated by callback signature, rate limited by default.
- Livewire `fahipay-pay-button` and `fahipay-payment-modal` components.
- Payment expiry job that verifies status with the gateway before failing
  pending payments, recovering ones that completed out-of-band.
- Events for payment initiated/completed/failed/cancelled, model observer,
  mail and notification classes, helper functions, and console commands.
- PHPStan (larastan) level 6 static analysis and Pint code style, enforced in CI
  across PHP 8.2–8.4 and Laravel 11–12.

### Security
- Payment status transitions are atomic (pessimistic locking) and COMPLETED is
  terminal — replayed or late failure callbacks cannot reverse a confirmed payment.
- Callback/redirect URLs are restricted to a configurable host allow-list.
- Unsigned success callbacks are rejected; success callbacks require an approval code.
- Secrets are never included in API responses or error messages.
