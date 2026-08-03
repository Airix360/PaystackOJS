<?php

/**
 * @file plugins/paymethod/paystack/PaystackInstallMigration.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackInstallMigration
 *
 * @brief Composite install migration: runs every table-creating migration
 * this plugin owns. Returned by PaystackPlugin::getInstallMigration() so
 * PKP's native install machinery (web installer and
 * installPluginVersion.php) creates/upgrades all of them, including for
 * existing installs upgrading past the version that added disputes.
 */

namespace APP\plugins\paymethod\paystack;

use Illuminate\Database\Migrations\Migration;

class PaystackInstallMigration extends Migration
{
    /** @return Migration[] */
    private function migrations(): array
    {
        return [
            new PaystackWebhookTableMigration(),
            new PaystackDisputesTableMigration(),
        ];
    }

    public function up(): void
    {
        foreach ($this->migrations() as $migration) {
            $migration->up();
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->migrations()) as $migration) {
            $migration->down();
        }
    }
}
