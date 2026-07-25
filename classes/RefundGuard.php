<?php

/**
 * @file plugins/paymethod/paystack/classes/RefundGuard.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class RefundGuard
 *
 * @brief Pure math for the local over-refund guard: refund amount is
 * validated against the *remaining* refundable balance (original amount
 * minus everything already refunded), not just the original total, so a
 * manager can't click "Refund" repeatedly to drain more than the payment
 * was worth.
 *
 * Kept dependency-free (no OJS framework classes) so it can be unit tested
 * in isolation, the same way classes/ApcOwnerCompatibility.php is.
 */

namespace APP\plugins\paymethod\paystack\classes;

class RefundGuard
{
    /**
     * Resolve the amount (major currency units) to submit to the refund API.
     *
     * @param float $originalAmount   The original payment total.
     * @param float $alreadyRefunded  Sum of all previously successful local refund records.
     * @param float|null $requestedAmount  Manager-supplied amount, or null for "refund what's left".
     *
     * @throws \InvalidArgumentException if the request would exceed the remaining refundable balance,
     *                                    if the payment is already fully refunded, or the amount is <= 0.
     */
    public static function resolveRefundAmount(float $originalAmount, float $alreadyRefunded, ?float $requestedAmount): float
    {
        $remaining = round($originalAmount - $alreadyRefunded, 2);
        if ($remaining <= 0.0) {
            throw new \InvalidArgumentException('This payment has already been fully refunded.');
        }

        $amount = $requestedAmount ?? $remaining;
        if ($amount <= 0.0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }
        if ($amount > $remaining + 0.01) {
            throw new \InvalidArgumentException('Refund amount exceeds the remaining refundable balance.');
        }

        return $amount;
    }
}
