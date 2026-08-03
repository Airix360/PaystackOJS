<?php

/**
 * @file plugins/paymethod/paystack/mail/PaymentRefunded.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaymentRefunded
 *
 * @brief Email sent to the payer when a manager issues a full or partial
 * refund. Refunds happen against a CompletedPayment (the QueuedPayment it
 * originated from is gone by then), so unlike PaymentConfirmation/
 * PaymentFailed this mailable does not use the PaystackVariables trait
 * (which requires a QueuedPayment) — its template variables are set up
 * directly from the completed payment + refund figures.
 */

namespace APP\plugins\paymethod\paystack\mail;

use APP\journal\Journal;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Recipient;
use PKP\security\Role;

class PaymentRefunded extends Mailable
{
    use Configurable;
    use Recipient;

    protected static ?string $name = 'mailable.paystack.paymentRefunded.name';
    protected static ?string $description = 'mailable.paystack.paymentRefunded.description';
    protected static ?string $emailTemplateKey = 'PAYSTACK_PAYMENT_REFUNDED';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_AUTHOR, Role::ROLE_ID_READER, Role::ROLE_ID_SUBSCRIPTION_MANAGER];

    public static function getName(): string
    {
        $v = __(static::$name);
        if (!$v || $v === static::$name || preg_match('/^##.+##$/', $v)) {
            return 'Paystack Payment Refunded';
        }
        return $v;
    }

    public static function getDescription(): string
    {
        $v = __(static::$description);
        if (!$v || $v === static::$description || preg_match('/^##.+##$/', $v)) {
            return 'Email sent to the payer when a Paystack payment is refunded (fully or partially).';
        }
        return $v;
    }

    public function __construct(
        Journal $context,
        string $paymentName,
        string $currencySymbol,
        string $currencyCode,
        float $refundedAmount,
        float $totalRefundedAmount,
        float $originalAmount,
        string $reference,
        ?string $refundReference,
        bool $isFullRefund
    ) {
        parent::__construct([$context]);

        $this->addData([
            'paymentName' => $paymentName,
            'currencySymbol' => $currencySymbol,
            'paymentCurrency' => $currencyCode,
            'refundedAmount' => number_format($refundedAmount, 2, '.', ''),
            'totalRefundedAmount' => number_format($totalRefundedAmount, 2, '.', ''),
            'paymentAmount' => number_format($originalAmount, 2, '.', ''),
            'paymentReference' => $reference,
            'refundReference' => $refundReference ?? '',
            'refundDate' => date('Y-m-d H:i:s'),
            'refundStatus' => $isFullRefund
                ? __('plugins.paymethod.paystack.refund.statusFull')
                : __('plugins.paymethod.paystack.refund.statusPartial'),
        ]);
    }

    public static function getDataDescriptions(): array
    {
        return array_merge(
            parent::getDataDescriptions(),
            [
                'paymentName' => __('plugins.paymethod.paystack.email.paymentReference'),
                'currencySymbol' => __('plugins.paymethod.paystack.email.currencySymbol'),
                'paymentCurrency' => __('plugins.paymethod.paystack.email.paymentCurrency'),
                'refundedAmount' => __('plugins.paymethod.paystack.email.paymentAmount'),
                'totalRefundedAmount' => __('plugins.paymethod.paystack.email.paymentAmount'),
                'paymentAmount' => __('plugins.paymethod.paystack.email.paymentAmount'),
                'paymentReference' => __('plugins.paymethod.paystack.email.paymentReference'),
                'refundReference' => __('plugins.paymethod.paystack.email.transactionId'),
                'refundDate' => __('plugins.paymethod.paystack.email.paymentDate'),
                'refundStatus' => __('plugins.paymethod.paystack.email.paymentReference'),
            ]
        );
    }
}
