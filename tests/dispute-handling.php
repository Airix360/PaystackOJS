<?php

/**
 * @file tests/dispute-handling.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief Structural regression guard for Paystack dispute/chargeback
 * capture, mirroring the sibling FlutterwaveOJS plugin's dispute handling:
 * a webhook handler for the dispute events, a local `paystack_disputes`
 * table migration, and a manager-facing dispute alert Mailable.
 *
 * Cannot exercise the real webhook path without an OJS bootstrap (DB,
 * facades, Mail), so this checks — the same way
 * tests/refund-entrypoint-parity.php does for FlutterwaveOJS — that the
 * wiring exists and is connected correctly: the webhook switch recognizes
 * Paystack's documented dispute event names, routes them to a handler that
 * stores a local record and alerts managers, and that the migration/mailable
 * files exist and are wired into the plugin's install migration and
 * mailable registry respectively.
 *
 * Usage: php tests/dispute-handling.php   (exit code 0 = pass)
 */

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$pluginSource = file_get_contents($root . '/PaystackPlugin.php');
assertTrue($pluginSource !== false, 'Could not read PaystackPlugin.php');

// ── Webhook recognizes Paystack's documented dispute event names ───────────
assertTrue(
    strpos($pluginSource, "'charge.dispute.create', 'charge.dispute.remind', 'charge.dispute.resolve'") !== false,
    'Webhook handler does not check for the documented Paystack dispute event names'
);

// ── Dispute events are routed to a dedicated handler ────────────────────────
assertTrue(
    strpos($pluginSource, 'function handleDisputeEvent(') !== false,
    'handleDisputeEvent() handler is missing'
);
assertTrue(
    strpos($pluginSource, '$this->handleDisputeEvent(') !== false,
    'The webhook switch does not call handleDisputeEvent() for dispute events'
);

// ── Local record + manager alert both wired from the handler ───────────────
$handlerStart = strpos($pluginSource, 'private function handleDisputeEvent(');
assertTrue($handlerStart !== false, 'Could not locate handleDisputeEvent() body');
$nextMethodPos = preg_match('/\n    (private|public) function [a-zA-Z]+\(/', $pluginSource, $m, PREG_OFFSET_CAPTURE, $handlerStart + 40);
$handlerBody = $nextMethodPos
    ? substr($pluginSource, $handlerStart, $m[0][1] - $handlerStart)
    : substr($pluginSource, $handlerStart, 4000);

assertTrue(
    strpos($handlerBody, '$this->storeDisputeRecord(') !== false,
    'handleDisputeEvent() does not persist a local dispute record'
);
assertTrue(
    strpos($handlerBody, '$this->notifyDisputeManagers(') !== false,
    'handleDisputeEvent() does not alert managers'
);

// ── storeDisputeRecord() targets the paystack_disputes table ───────────────
assertTrue(
    strpos($pluginSource, "function storeDisputeRecord(") !== false,
    'storeDisputeRecord() is missing'
);
assertTrue(
    strpos($pluginSource, "Schema::hasTable('paystack_disputes')") !== false,
    'storeDisputeRecord() does not guard on the paystack_disputes table existing'
);
assertTrue(
    strpos($pluginSource, "DB::table('paystack_disputes')->insert(") !== false,
    'storeDisputeRecord() does not insert into paystack_disputes'
);

// ── notifyDisputeManagers() uses the Mailable pattern, not raw Mail::raw ────
assertTrue(
    strpos($pluginSource, 'function notifyDisputeManagers(') !== false,
    'notifyDisputeManagers() is missing'
);
assertTrue(
    strpos($pluginSource, 'new PaymentDisputeAlert(') !== false,
    'notifyDisputeManagers() does not construct the PaymentDisputeAlert mailable'
);
assertTrue(
    strpos($pluginSource, '$this->dispatchConfiguredMailable($mailable, $context, null, [$email, $name],') !== false,
    'notifyDisputeManagers() does not dispatch via the shared dispatchConfiguredMailable() pattern'
);

// ── PaymentDisputeAlert mailable is registered with OJS's mailable list ────
assertTrue(
    strpos($pluginSource, 'PaymentDisputeAlert::class] as $mailableClass') !== false,
    'PaymentDisputeAlert is not registered in addMailable()'
);

// ── Migration file exists, creates the table, and is wired into install ────
assertTrue(
    is_file($root . '/PaystackDisputesTableMigration.php'),
    'PaystackDisputesTableMigration.php is missing'
);
$migrationSource = file_get_contents($root . '/PaystackDisputesTableMigration.php');
assertTrue(
    strpos($migrationSource, "Schema::create('paystack_disputes'") !== false,
    'PaystackDisputesTableMigration does not create the paystack_disputes table'
);

assertTrue(
    is_file($root . '/PaystackInstallMigration.php'),
    'PaystackInstallMigration.php (composite install migration) is missing'
);
$installMigrationSource = file_get_contents($root . '/PaystackInstallMigration.php');
assertTrue(
    strpos($installMigrationSource, 'new PaystackDisputesTableMigration()') !== false,
    'PaystackInstallMigration does not run PaystackDisputesTableMigration'
);
assertTrue(
    strpos($installMigrationSource, 'new PaystackWebhookTableMigration()') !== false,
    'PaystackInstallMigration does not still run the original webhook/dedupe/guard/reconciliation migration'
);
assertTrue(
    strpos($pluginSource, 'return new PaystackInstallMigration();') !== false,
    'getInstallMigration() does not return the composite PaystackInstallMigration'
);

// ── Mailable class itself exists with the expected template key ────────────
assertTrue(
    is_file($root . '/mail/PaymentDisputeAlert.php'),
    'mail/PaymentDisputeAlert.php is missing'
);
$mailableSource = file_get_contents($root . '/mail/PaymentDisputeAlert.php');
assertTrue(
    strpos($mailableSource, "'PAYSTACK_PAYMENT_DISPUTE'") !== false,
    'PaymentDisputeAlert does not declare the PAYSTACK_PAYMENT_DISPUTE template key'
);

echo "Dispute-handling wiring tests passed\n";
