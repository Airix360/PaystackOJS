<?php

/**
 * @file tests/reconciliation-decider.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief Self-check for ReconciliationDecider: the scheduled reconciliation
 * task must only ever fulfil a pending transaction when Paystack's
 * re-verified status is a genuine success AND the amount/currency match the
 * queued payment — the exact same guard the webhook and callback paths
 * already apply — and must never touch a row that's already fulfilled.
 *
 * Usage: php tests/reconciliation-decider.php   (exit code 0 = pass)
 */

require_once dirname(__DIR__) . '/classes/ReconciliationDecider.php';

use APP\plugins\paymethod\paystack\classes\ReconciliationDecider as D;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

// A genuine, matching success is fulfilled.
assertSameValue(
    D::ACTION_FULFILL,
    D::decide('pending', false, 'success', 100.0, 'NGN', 100.0, 'NGN'),
    'A verified success with matching amount/currency is fulfilled'
);

// Case-insensitive currency and status matching.
assertSameValue(
    D::ACTION_FULFILL,
    D::decide('pending', false, 'Success', 100.0, 'NGN', 100.0, 'ngn'),
    'Status and currency comparisons are case-insensitive'
);

// A row already fulfilled by the webhook/callback in the meantime must never be touched again.
assertSameValue(
    D::ACTION_SKIP_ALREADY_COMPLETE,
    D::decide('pending', true, 'success', 100.0, 'NGN', 100.0, 'NGN'),
    'A reference already fulfilled locally is skipped even if the row is still marked pending'
);
assertSameValue(
    D::ACTION_SKIP_ALREADY_COMPLETE,
    D::decide('completed', false, 'success', 100.0, 'NGN', 100.0, 'NGN'),
    'A row already marked completed locally is skipped'
);

// Paystack failure/abandon states are recorded as failed, not left pending forever.
foreach (['failed', 'abandoned', 'reversed'] as $status) {
    assertSameValue(
        D::ACTION_MARK_FAILED,
        D::decide('pending', false, $status, 100.0, 'NGN', 0.0, 'NGN'),
        "A verified '{$status}' status is marked failed"
    );
}

// Still genuinely pending / unknown: check again next run rather than guessing.
assertSameValue(
    D::ACTION_LEAVE_PENDING,
    D::decide('pending', false, 'pending', 100.0, 'NGN', null, ''),
    'A still-pending verification result is left pending for the next run'
);
assertSameValue(
    D::ACTION_LEAVE_PENDING,
    D::decide('pending', false, 'unknown', 100.0, 'NGN', null, ''),
    'An unrecognised status is left pending rather than fulfilled or failed'
);

// A "success" that doesn't match the queued payment's amount must NOT be auto-fulfilled
// (this is the exact tampering/mismatch check the webhook and callback already enforce).
assertSameValue(
    D::ACTION_LEAVE_PENDING,
    D::decide('pending', false, 'success', 100.0, 'NGN', 40.0, 'NGN'),
    'A verified success with a mismatched amount is never auto-fulfilled by reconciliation'
);
assertSameValue(
    D::ACTION_LEAVE_PENDING,
    D::decide('pending', false, 'success', 100.0, 'NGN', 100.0, 'USD'),
    'A verified success with a mismatched currency is never auto-fulfilled by reconciliation'
);

// Amount within tolerance still fulfils (rounding).
assertSameValue(
    D::ACTION_FULFILL,
    D::decide('pending', false, 'success', 100.0, 'NGN', 100.009, 'NGN', 0.01),
    'An amount within the configured tolerance still fulfils'
);

// Missing amount/currency in the verify payload doesn't block fulfilment (some
// Paystack responses omit fields the webhook already validated at charge time).
assertSameValue(
    D::ACTION_FULFILL,
    D::decide('pending', false, 'success', 100.0, 'NGN', null, ''),
    'A verified success with no amount/currency in the payload still fulfils (nothing to contradict)'
);

// Lookback window.
$now = 1_000_000;
if (!D::withinWindow($now - 3600, $now, 72)) {
    fwrite(STDERR, "FAIL: a row created 1 hour ago must be within a 72-hour window\n");
    exit(1);
}
if (D::withinWindow($now - (200 * 3600), $now, 72)) {
    fwrite(STDERR, "FAIL: a row created 200 hours ago must be outside a 72-hour window\n");
    exit(1);
}

echo "ReconciliationDecider tests passed\n";
