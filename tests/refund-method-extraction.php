<?php

/**
 * @file tests/refund-method-extraction.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief Structural regression guard for the shared refund extraction: a
 * new public refundByCompletedPaymentId() lets other plugins (e.g.
 * submissionFee-OJS) trigger a real Paystack refund in-process, without
 * going through HTTP/CSRF. It must reuse the exact same refund logic/guards
 * as the HTTP-facing manage() 'refund' action — the cumulative-refund cap,
 * the local refund record, and the payer notification email — via one
 * shared private method, not a second copy of the refund logic.
 *
 * Cannot exercise a live Paystack refund without an OJS + HTTP bootstrap, so
 * this checks the wiring the same way tests/refund-entrypoint-parity.php
 * does in the sibling FlutterwaveOJS plugin: both call the same shared
 * helper, the public method has the exact required signature and returns
 * the exact required shape, and the HTTP-only Guzzle refund POST call
 * appears exactly once in the file (i.e. it was actually extracted, not
 * duplicated).
 *
 * Usage: php tests/refund-method-extraction.php   (exit code 0 = pass)
 */

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assertSameValue($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$source = file_get_contents($root . '/PaystackPlugin.php');
assertTrue($source !== false, 'Could not read PaystackPlugin.php');

// ── The shared extraction point exists ──────────────────────────────────────
assertTrue(
    strpos($source, 'private function performRefund(') !== false,
    'Shared performRefund() helper is missing'
);

// ── The public cross-plugin entrypoint exists with the exact required signature ──
assertTrue(
    strpos($source, 'public function refundByCompletedPaymentId(int $contextId, int $completedPaymentId, ?float $amount = null): array') !== false,
    'refundByCompletedPaymentId() is missing or its signature does not exactly match the required contract'
);

// ── Both the HTTP handler and the public entrypoint call the SAME shared helper ──
$manageVerbStart = strpos($source, "\$request->getUserVar('verb') === 'refund'");
assertTrue($manageVerbStart !== false, "Could not locate manage() verb='refund' block");
$manageEnd = strpos($source, "return parent::manage(\$args, \$request);\n    }", $manageVerbStart);
$manageBlock = $manageEnd !== false
    ? substr($source, $manageVerbStart, $manageEnd - $manageVerbStart)
    : substr($source, $manageVerbStart, 6000);
assertTrue(
    strpos($manageBlock, '$this->performRefund(') !== false,
    "manage() verb='refund' does not call the shared performRefund() helper — refund logic may have been duplicated instead of extracted"
);

$publicMethodStart = strpos($source, 'public function refundByCompletedPaymentId(');
assertTrue($publicMethodStart !== false, 'Could not locate refundByCompletedPaymentId()');
$publicMethodBody = substr($source, $publicMethodStart, 1200);
assertTrue(
    strpos($publicMethodBody, '$this->performRefund(') !== false,
    'refundByCompletedPaymentId() does not call the shared performRefund() helper'
);

// ── The HTTP-only Guzzle refund POST appears exactly once (i.e. no duplicate copy) ──
$refundPostCount = substr_count($source, "self::PAYSTACK_API_URL . '/refund'");
assertSameValue(
    1,
    $refundPostCount,
    "Expected exactly one Paystack /refund API call site (inside the shared performRefund() helper); found {$refundPostCount} — refund logic may be duplicated rather than extracted"
);

// ── The over-refund guard (RefundGuard) is used exactly once, inside the shared helper ──
$refundGuardCallCount = substr_count($source, 'RefundGuard::resolveRefundAmount(');
assertSameValue(
    1,
    $refundGuardCallCount,
    "Expected exactly one call site for RefundGuard::resolveRefundAmount() (inside the shared performRefund() helper); found {$refundGuardCallCount}"
);

// ── refundByCompletedPaymentId() carries a docblock documenting it is not authorization-checked ──
$docblockStart = strrpos(substr($source, 0, $publicMethodStart), '/**');
assertTrue($docblockStart !== false, 'Could not locate the docblock preceding refundByCompletedPaymentId()');
$docblock = substr($source, $docblockStart, $publicMethodStart - $docblockStart);
assertTrue(
    stripos($docblock, 'SECURITY') !== false && stripos($docblock, 'not') !== false,
    'refundByCompletedPaymentId() docblock does not clearly document that it performs no authorization check of its own'
);
assertTrue(
    stripos($docblock, 'HTTP') !== false,
    'refundByCompletedPaymentId() docblock does not document that it is not exposed over HTTP'
);

echo "Refund method extraction tests passed\n";
