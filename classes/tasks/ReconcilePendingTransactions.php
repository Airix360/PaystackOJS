<?php

/**
 * @file plugins/paymethod/paystack/classes/tasks/ReconcilePendingTransactions.php
 *
 * Copyright (c) 2025 Hendrix Nwaokolo, Airix Media
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class ReconcilePendingTransactions
 *
 * @brief Scheduled task that re-verifies pending Paystack transaction
 * attempts against the gateway for every journal where the plugin is
 * enabled, healing the case where a webhook never arrived and the payer
 * never completed the browser redirect back to /callback either.
 * Registered via HasTaskScheduler and executed by PKP's scheduler
 * (php lib/pkp/tools/scheduler.php run).
 */

namespace APP\plugins\paymethod\paystack\classes\tasks;

use Illuminate\Support\Facades\DB;
use PKP\plugins\PluginRegistry;
use PKP\scheduledTask\ScheduledTask;
use PKP\scheduledTask\ScheduledTaskHelper;

class ReconcilePendingTransactions extends ScheduledTask
{
    public function getName(): string
    {
        $name = __('plugins.paymethod.paystack.task.reconcile');
        return str_starts_with((string) $name, '##') ? 'Paystack transaction reconciliation' : (string) $name;
    }

    /**
     * @copydoc ScheduledTask::executeActions()
     */
    protected function executeActions(): bool
    {
        $plugins = PluginRegistry::loadCategory('paymethod');
        $plugin = $plugins['paystackplugin'] ?? null;
        if (!$plugin) {
            $this->addExecutionLogEntry(
                'Paystack plugin is not available; skipping reconciliation.',
                ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_WARNING
            );
            return true;
        }

        $ok = true;
        $rows = DB::table('plugin_settings')
            ->where('plugin_name', '=', 'paystackplugin')
            ->where('setting_name', '=', 'enabled')
            ->get(['context_id', 'setting_value']);

        foreach ($rows as $row) {
            if (!filter_var((string) $row->setting_value, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $contextId = (int) $row->context_id;
            $reconciliationEnabled = $plugin->getSetting($contextId, 'reconciliationEnabled');
            if ($reconciliationEnabled !== null && !filter_var((string) $reconciliationEnabled, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            try {
                $result = $plugin->runReconciliation($contextId);
                $this->addExecutionLogEntry(
                    sprintf(
                        'Paystack reconciliation (context %d): checked %d, fulfilled %d, failed %d.',
                        $contextId,
                        (int) ($result['checked'] ?? 0),
                        (int) ($result['fulfilled'] ?? 0),
                        (int) ($result['failed'] ?? 0)
                    ),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_COMPLETED
                );
            } catch (\Throwable $e) {
                $ok = false;
                $this->addExecutionLogEntry(
                    sprintf('Paystack reconciliation failed (context %d): %s', $contextId, $e->getMessage()),
                    ScheduledTaskHelper::SCHEDULED_TASK_MESSAGE_TYPE_ERROR
                );
            }
        }

        return $ok;
    }
}
