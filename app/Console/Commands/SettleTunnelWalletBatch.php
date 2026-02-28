<?php

namespace App\Console\Commands;

use App\Models\SurepayBatchSetting;
use App\Services\Billing\WalletSettlementService;
use Illuminate\Console\Command;

class SettleTunnelWalletBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:settle-surepay-batch {--merchant_id=} {--limit=100} {--scheduled}';

    /**
     * @var list<string>
     */
    protected $aliases = ['wallet:settle-tunnel-batch'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Settle pending SurePay wallet net balances to real wallets in batch.';

    /**
     * Execute the console command.
     */
    public function handle(WalletSettlementService $service): int
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            $this->info('Wallet settlement feature is disabled.');

            return self::SUCCESS;
        }

        $merchantId = $this->option('merchant_id');
        $limit = (int) $this->option('limit');
        $scheduled = (bool) $this->option('scheduled');
        if ($limit <= 0) {
            $limit = 100;
        }

        $setting = SurepayBatchSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'batch_interval_minutes' => 15,
                'batch_interval_seconds' => 900,
                'tax_percentage' => 0,
                'tax_absolute_value' => 0,
                'updated_by' => null,
            ]
        );

        if ($scheduled && ! $this->isDueForScheduledSettlement($setting)) {
            $this->info('Batch settlement skipped: configured SurePay sending interval has not elapsed yet.');

            return self::SUCCESS;
        }

        $settled = $service->settlePendingNetBatch(
            $merchantId !== null ? (int) $merchantId : null,
            $limit
        );

        if ($scheduled) {
            $setting->last_batch_settled_at = now();
            $setting->save();
        }

        $this->info("Batch settlement completed. Settled {$settled} SurePay entries.");

        return self::SUCCESS;
    }

    private function isDueForScheduledSettlement(SurepayBatchSetting $setting): bool
    {
        $intervalSeconds = max(1, $setting->intervalSeconds());
        $lastRunAt = $setting->last_batch_settled_at;

        if ($lastRunAt === null) {
            return true;
        }

        return $lastRunAt->addSeconds($intervalSeconds)->lessThanOrEqualTo(now());
    }
}
