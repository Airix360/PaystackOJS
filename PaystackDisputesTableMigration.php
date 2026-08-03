<?php

/**
 * @file plugins/paymethod/paystack/PaystackDisputesTableMigration.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackDisputesTableMigration
 *
 * @brief Creates the `paystack_disputes` table, a local record of
 * dispute/chargeback webhook events (`charge.dispute.create`,
 * `charge.dispute.remind`, `charge.dispute.resolve`) received from Paystack,
 * mirroring the equivalent table in the sibling FlutterwaveOJS plugin
 * (`flutterwave_disputes`).
 */

namespace APP\plugins\paymethod\paystack;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class PaystackDisputesTableMigration extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paystack_disputes')) {
            return;
        }
        Schema::create('paystack_disputes', function (Blueprint $table) {
            $table->bigIncrements('dispute_id');
            $table->bigInteger('context_id')->nullable();
            $table->string('event', 64)->nullable();
            $table->string('reference', 128)->nullable();
            $table->string('provider_tx_id', 128)->nullable();
            $table->string('provider_dispute_id', 128)->nullable();
            $table->string('status', 32)->default('pending');
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->text('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
            $table->index(['context_id', 'status']);
            $table->index(['reference']);
            $table->index(['provider_dispute_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('paystack_disputes')) {
            Schema::drop('paystack_disputes');
        }
    }
}
