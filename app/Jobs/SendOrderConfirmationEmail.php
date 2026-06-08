<?php

// app/Jobs/SendOrderConfirmationEmail.php

namespace App\Jobs;

use App\Mail\OrderConfirmedMail;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $orderId
    ) {}

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(): void
    {
        $order = Order::query()
            ->with(['user', 'product'])
            ->findOrFail($this->orderId);

        Mail::to($order->user->email)
            ->send(new OrderConfirmedMail($order));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Failed to send order confirmation email.', [
            'order_id' => $this->orderId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
