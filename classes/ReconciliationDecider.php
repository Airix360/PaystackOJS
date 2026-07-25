<?php

/**
 * @file plugins/paymethod/paystack/classes/ReconciliationDecider.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ReconciliationDecider
 *
 * @brief Pure logic for the reconciliation scheduled task: given a locally
 * recorded pending transaction attempt and Paystack's own re-verified view
 * of that reference, decide whether it should now be fulfilled, marked
 * failed, or left pending — and whether it's still within the lookback
 * window at all.
 *
 * The webhook and callback handlers are the normal path to fulfilment;
 * reconciliation exists only to heal the case where Paystack's webhook
 * never arrived (or arrived while the app was down) and the payer never
 * completed the browser redirect either. It re-checks the SAME amount and
 * currency rules the webhook/callback paths already enforce so it can
 * never fulfil a payment those paths would have rejected.
 *
 * Kept dependency-free (no OJS/DB classes) so it can be unit tested in
 * isolation, the same way classes/RefundGuard.php is.
 */

namespace APP\plugins\paymethod\paystack\classes;

class ReconciliationDecider
{
    public const ACTION_FULFILL = 'fulfill';
    public const ACTION_MARK_FAILED = 'mark_failed';
    public const ACTION_LEAVE_PENDING = 'leave_pending';
    public const ACTION_SKIP_ALREADY_COMPLETE = 'skip_already_complete';

    /**
     * @param string $localStatus       Current status of the locally recorded row ('pending', 'completed', 'failed', ...).
     * @param bool $alreadyFulfilledLocally  Whether OJS already has a completed payment for this reference
     *                                       (fulfilled by a webhook/callback that arrived after this row was recorded).
     * @param string $verifiedStatus    Paystack's `data.status` from GET /transaction/verify (e.g. 'success', 'failed', 'abandoned').
     * @param float $expectedAmount     The queued payment's amount (major currency units).
     * @param string $expectedCurrency  The queued payment's currency code.
     * @param float|null $verifiedAmount    Amount Paystack reports (major currency units), or null if absent.
     * @param string $verifiedCurrency  Currency Paystack reports, or '' if absent.
     * @param float $amountTolerance    Absolute tolerance for the amount comparison (matches the webhook/callback rounding tolerance).
     */
    public static function decide(
        string $localStatus,
        bool $alreadyFulfilledLocally,
        string $verifiedStatus,
        float $expectedAmount,
        string $expectedCurrency,
        ?float $verifiedAmount,
        string $verifiedCurrency,
        float $amountTolerance = 0.01
    ): string {
        if ($alreadyFulfilledLocally || $localStatus === 'completed') {
            return self::ACTION_SKIP_ALREADY_COMPLETE;
        }

        $verifiedStatus = strtolower(trim($verifiedStatus));

        if (in_array($verifiedStatus, ['failed', 'abandoned', 'reversed'], true)) {
            return self::ACTION_MARK_FAILED;
        }

        if ($verifiedStatus !== 'success') {
            // 'pending', 'processing', 'queued', unknown, etc. — check again next run.
            return self::ACTION_LEAVE_PENDING;
        }

        // Paystack says success: re-verify amount/currency exactly as the
        // webhook/callback paths do before trusting it enough to fulfil.
        $expectedCurrency = strtoupper($expectedCurrency);
        $verifiedCurrency = strtoupper($verifiedCurrency);
        if ($verifiedCurrency !== '' && $verifiedCurrency !== $expectedCurrency) {
            return self::ACTION_LEAVE_PENDING;
        }
        if ($verifiedAmount !== null && abs($verifiedAmount - $expectedAmount) > $amountTolerance) {
            return self::ACTION_LEAVE_PENDING;
        }

        return self::ACTION_FULFILL;
    }

    /**
     * Whether a pending row recorded at $createdAtTimestamp is still inside
     * the reconciliation lookback window, i.e. worth checking at all. Rows
     * older than the window are left alone (they're presumed abandoned by
     * the payer; the fulfilment guard still protects against a very late
     * webhook arriving for one).
     */
    public static function withinWindow(int $createdAtTimestamp, int $nowTimestamp, int $windowHours): bool
    {
        $windowHours = max(1, $windowHours);
        return ($nowTimestamp - $createdAtTimestamp) <= ($windowHours * 3600);
    }
}
