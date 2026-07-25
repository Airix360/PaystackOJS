<?php

/**
 * @file plugins/paymethod/paystack/mail/PaymentDisputeAlert.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaymentDisputeAlert
 *
 * @brief Email sent to journal managers when Paystack reports a
 * dispute/chargeback event (`charge.dispute.create`, `charge.dispute.remind`,
 * `charge.dispute.resolve`) against one of the journal's payments. Mirrors
 * PaymentConfirmationAdmin's shape (sent to a manager, not tied to a
 * QueuedPayment) rather than PaystackVariables, since a dispute is raised
 * against a CompletedPayment that may be long gone from the queue.
 */

namespace APP\plugins\paymethod\paystack\mail;

use APP\journal\Journal;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\mail\traits\Sender;
use PKP\security\Role;

class PaymentDisputeAlert extends Mailable
{
    use Configurable;
    use Sender;

    protected static ?string $name = 'mailable.paystack.paymentDispute.name';
    protected static ?string $description = 'mailable.paystack.paymentDispute.description';
    protected static ?string $emailTemplateKey = 'PAYSTACK_PAYMENT_DISPUTE';
    protected static bool $supportsTemplates = true;
    protected static array $groupIds = [self::GROUP_OTHER];
    protected static array $fromRoleIds = [self::FROM_SYSTEM];
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUBSCRIPTION_MANAGER];

    public static function getName(): string
    {
        $v = __(static::$name);
        if (!$v || $v === static::$name || preg_match('/^##.+##$/', $v)) {
            return 'Paystack Dispute Alert';
        }
        return $v;
    }

    public static function getDescription(): string
    {
        $v = __(static::$description);
        if (!$v || $v === static::$description || preg_match('/^##.+##$/', $v)) {
            return 'Email sent to journal managers when Paystack reports a dispute or chargeback against a payment.';
        }
        return $v;
    }

    public function __construct(
        Journal $context,
        string $event,
        ?string $reference,
        ?string $transactionId,
        ?string $disputeId,
        string $status,
        ?float $amount,
        string $currencySymbol,
        ?string $currency,
        ?string $dueAt
    ) {
        parent::__construct([$context]);

        $this->addData([
            'disputeEvent' => $event,
            'paymentReference' => $reference ?? '',
            'transactionId' => $transactionId ?? '',
            'disputeId' => $disputeId ?? '',
            'disputeStatus' => $status,
            'disputeAmount' => $amount !== null ? number_format($amount, 2, '.', '') : 'n/a',
            'currencySymbol' => $currencySymbol,
            'paymentCurrency' => $currency ?? '',
            'disputeDueAt' => $dueAt ?: 'n/a',
        ]);
    }

    public static function getDataDescriptions(): array
    {
        return array_merge(
            parent::getDataDescriptions(),
            [
                'disputeEvent' => __('plugins.paymethod.paystack.email.disputeEvent'),
                'paymentReference' => __('plugins.paymethod.paystack.email.paymentReference'),
                'transactionId' => __('plugins.paymethod.paystack.email.transactionId'),
                'disputeId' => __('plugins.paymethod.paystack.email.disputeId'),
                'disputeStatus' => __('plugins.paymethod.paystack.email.disputeStatus'),
                'disputeAmount' => __('plugins.paymethod.paystack.email.paymentAmount'),
                'currencySymbol' => __('plugins.paymethod.paystack.email.currencySymbol'),
                'paymentCurrency' => __('plugins.paymethod.paystack.email.paymentCurrency'),
                'disputeDueAt' => __('plugins.paymethod.paystack.email.disputeDueAt'),
            ]
        );
    }
}
