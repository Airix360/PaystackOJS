# Changelog

All notable changes to this project will be documented in this file.

## 1.3.0 - 2026-07-25

### Security
- Webhook audit logs (`paystack_webhook_logs`) are now purged past a 30-day
  TTL on every webhook hit, matching the existing dedupe-table retention.
- Refunds are now validated against the *remaining* refundable balance
  (original amount minus everything already refunded), not just the original
  total, closing a repeated-click over-refund gap. See
  `classes/RefundGuard.php` and `tests/refund-guard.php`.
- Webhook fulfilment now fails **closed**: if the fulfilment-guard table is
  missing or errors, the webhook is rejected (HTTP 5xx, Paystack retries)
  instead of silently fulfilling unguarded.
- The webhook IP allowlist's `X-Forwarded-For` parsing now uses a
  configurable trusted-proxy hop count (`trustedProxyHops`, default 1)
  instead of always trusting the last hop.

### Added
- **Scheduled reconciliation**: an optional PKP scheduled task
  (`ReconcilePendingTransactions`, every 15 minutes) re-verifies pending
  payment attempts directly against Paystack and fulfils any that actually
  succeeded, healing the case where both the webhook and the payer's browser
  callback fail to reach the server. Pending attempts are recorded in a new
  `paystack_transactions` table at checkout-start time. On by default;
  requires the server to run `php lib/pkp/tools/scheduler.php run` via cron.
  See `classes/ReconciliationDecider.php` and
  `tests/reconciliation-decider.php`.
- Local refund records (`storeRefundRecord`) and a payer-facing "payment
  refunded" notification email (`PAYSTACK_PAYMENT_REFUNDED`) — a refund
  previously only ever touched the Paystack API and left no local trace.

### Fixed
- `PaymentRefunded` was never registered with OJS's mailable registry
  (`addMailable()`), unlike the other three plugin email templates. Fixed
  for consistency with the rest of the plugin's mailables (sending itself
  was unaffected — it falls back to a hardcoded template regardless).

### Documentation
- README now documents the previously-undocumented `trustedProxyHops`
  setting and the `PAYSTACK_PAYMENT_REFUNDED` email template.

## 1.2.0 - 2026-06-11

### Added
- Payment description now includes the article title for **submission fees**
  (submissionFee plugin, payment type 5) as well as publication fees:
  "Submission Fee — <Article Title>" on the payment details page and in
  the initiated charge.

## 1.1.1 – 2026-06-10

### Compatibility / Security
- Added a temporary, fail-closed workaround for
  [pkp/pkp-lib#12885](https://github.com/pkp/pkp-lib/issues/12885). OJS 3.5
  creates APC queues under the requesting editor while notifying the author.
  Only the primary assigned author, or the sole assigned author when no primary
  author can be resolved, may repair that queue ownership before checkout.
- Existing ownership checks remain enforced for all payment types. The
  workaround is a no-op when OJS supplies the correct owner and should be
  removed once the minimum supported OJS version contains the upstream fix.

## 1.1.0 – 2026-06-10

### Security
- Optional **webhook IP allowlist**: when enabled, webhooks are only accepted
  from Paystack's documented source IPs (52.31.139.75, 52.49.173.169,
  52.214.14.220), in addition to the HMAC signature check. Off by default
  (proxies/CDNs can hide the client IP).

### Reliability
- Email-send idempotency moved from unbounded `emailed_success_*` plugin
  settings into the TTL'd `paystack_webhook_dedupe` table (legacy keys still
  honoured).

### Documentation
- New "Email templates & sponsorship" README section explaining the OJS
  paymethod mailable restriction and the sponsor-only Payment Method Support
  companion addon.

## 1.0.1 – 2026-06-10

### Reliability
- Webhook idempotency moved from plugin settings to a dedicated, TTL-purged
  `paystack_webhook_dedupe` table (30-day retention; settings fallback kept).
- Double-fulfilment race between the callback and webhook closed with a
  `paystack_fulfillment_guards` unique-insert claim inside a transaction.
- Webhook audit log table (`paystack_webhook_logs`) is now created at
  install/upgrade time.
- O(1) reference → completed-payment lookup via a reverse index written at
  fulfilment time.

### Features
- Added XOF (West African CFA Franc) to the supported currencies for
  Côte d'Ivoire merchants.

### Housekeeping
- All settings-page text is now localized (test-mode banner, secret-key hints).
- Removed dead code and stale files (legacy upgrade descriptor, display-name
  migration, unused template-health checks, unused test-mode banner hooks).
- Licensing made consistent: GPL v3 across LICENSE, composer.json, and file
  headers.

## 1.0.0 – 2026-06-03

- Initial release: Paystack checkout + callback + webhook flow with
  HMAC-SHA512 signature verification, amount/currency/reference re-checks,
  idempotent fulfilment, manager transactions list with refunds, user payment
  history and receipts, and OJS-native editable email templates.
