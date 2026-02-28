<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Services\Billing\PlatformFeeService;
use App\Services\Billing\WalletSettlementService;
use Illuminate\Console\Command;

class BackfillTunnelWalletLedger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:backfill-surepay-ledger {--merchant_id=} {--limit=500} {--dry-run}';

    /**
     * @var list<string>
     */
    protected $aliases = ['wallet:backfill-tunnel-ledger'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill SurePay wallet ledger entries for paid payments missing settlement records.';

    /**
     * Execute the console command.
     */
    public function handle(PlatformFeeService $platformFeeService, WalletSettlementService $walletSettlementService): int
    {
        if (! config('surepay.features.wallet_settlement', false)) {
            $this->info('Wallet settlement feature is disabled.');

            return self::SUCCESS;
        }

        $merchantId = $this->option('merchant_id');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($limit <= 0) {
            $limit = 500;
        }

        $missingEntryTypes = [
            WalletTransaction::ENTRY_TUNNEL_NET_AVAILABLE,
            WalletTransaction::ENTRY_REAL_WALLET_NET_CREDIT_DIRECT,
        ];

        $query = Payment::query()
            ->where('status', 'paid')
            ->when($merchantId !== null, fn ($q) => $q->where('user_id', (int) $merchantId))
            ->whereDoesntHave('walletTransactions', fn ($q) => $q->whereIn('entry_type', $missingEntryTypes))
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->limit($limit);

        $payments = $query->get();

        if ($payments->isEmpty()) {
            $this->info('No paid payments require backfill.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: {$payments->count()} paid payments are eligible for SurePay wallet backfill.");

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            try {
                $platformFeeService->record($payment);
                $walletSettlementService->recordPaidPayment($payment);
                $processed++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("Failed payment {$payment->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Backfill completed. Processed {$processed} payment(s), failed {$failed}.");

        return self::SUCCESS;
    }
}
