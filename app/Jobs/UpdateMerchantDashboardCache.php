<?php

// app/Jobs/UpdateMerchantDashboardCache.php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateMerchantDashboardCache implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(
        public int $saleEventId
    ) {}

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        $summary = Order::query()
            ->where('sales_event_id', $this->saleEventId)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('COALESCE(SUM(price), 0) as total_revenue')
            ->first();

        Cache::put(
            "dashboard:sale_event:{$this->saleEventId}",
            [
                'total_orders' => (int) $summary->total_orders,
                'total_revenue' => (float) $summary->total_revenue,
                'refreshed_at' => now()->toISOString(),
            ],
            now()->addMinutes(5)
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to update merchant dashboard cache.', [
            'sales_event_id' => $this->saleEventId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
