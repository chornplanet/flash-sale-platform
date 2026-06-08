<?php

// app/Jobs/SendOrderAnalyticsWebhook.php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderAnalyticsWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(
        public int $orderId
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    public function handle(): void
    {
        $order = Order::query()->findOrFail($this->orderId);

        Http::timeout(10)
            ->withHeaders([
                'Idempotency-Key' => 'order-analytics-'.$order->id,
            ])
            ->post(config('services.analytics.webhook_url'), [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'user_id' => $order->user_id,
                'product_id' => $order->product_id,
                'sales_event_id' => $order->sales_event_id,
                'status' => $order->status,
                'price' => $order->price,
                'created_at' => $order->created_at?->toISOString(),
            ])
            ->throw();
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to send analytics webhook.', [
            'order_id' => $this->orderId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
