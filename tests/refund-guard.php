<?php

/**
 * @file tests/refund-guard.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief Self-check for RefundGuard: refund amount is validated against the
 * remaining refundable balance (original minus everything already
 * refunded), not just the original total — closing the repeated-click
 * over-refund gap.
 *
 * Usage: php tests/refund-guard.php   (exit code 0 = pass)
 */

require_once dirname(__DIR__) . '/classes/RefundGuard.php';

use APP\plugins\paymethod\paystack\classes\RefundGuard;

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertThrows(callable $fn, string $message): void
{
    try {
        $fn();
    } catch (\InvalidArgumentException $e) {
        return;
    }
    fwrite(STDERR, "FAIL: {$message} (expected InvalidArgumentException, none thrown)\n");
    exit(1);
}

// First refund, no amount specified: refunds the full remaining balance.
assertSameValue(
    100.0,
    RefundGuard::resolveRefundAmount(100.0, 0.0, null),
    'First refund with no amount specified refunds the full original amount'
);

// Explicit partial refund is accepted.
assertSameValue(
    40.0,
    RefundGuard::resolveRefundAmount(100.0, 0.0, 40.0),
    'Explicit partial refund within bounds is accepted'
);

// A second partial refund is validated against what's LEFT, not the original total.
assertSameValue(
    60.0,
    RefundGuard::resolveRefundAmount(100.0, 40.0, 60.0),
    'Second refund for exactly the remaining balance is accepted'
);

// The over-refund case the audit flagged: repeated "valid against original total" clicks.
assertThrows(
    function () { RefundGuard::resolveRefundAmount(100.0, 40.0, 80.0); },
    'A refund request that would exceed the remaining balance must be rejected, even though 80 <= the original 100'
);

// Already fully refunded: any further refund attempt is rejected outright.
assertThrows(
    function () { RefundGuard::resolveRefundAmount(100.0, 100.0, null); },
    'A payment already fully refunded must reject further refund attempts'
);
assertThrows(
    function () { RefundGuard::resolveRefundAmount(100.0, 100.0, 1.0); },
    'A payment already fully refunded must reject further refund attempts even for a small amount'
);

// Zero/negative requested amounts are rejected.
assertThrows(
    function () { RefundGuard::resolveRefundAmount(100.0, 0.0, 0.0); },
    'A zero refund amount must be rejected'
);
assertThrows(
    function () { RefundGuard::resolveRefundAmount(100.0, 0.0, -10.0); },
    'A negative refund amount must be rejected'
);

// "Refund what's left" after a partial refund uses the remaining balance, not the original.
assertSameValue(
    60.0,
    RefundGuard::resolveRefundAmount(100.0, 40.0, null),
    'Omitting the amount after a partial refund refunds exactly what remains'
);

echo "RefundGuard tests passed\n";
