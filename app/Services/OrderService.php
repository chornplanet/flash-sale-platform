<?php

namespace App\Services;

use App\Jobs\UpdateMerchantDashboardCache;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Product;
use App\Models\SalesEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrderService
{
    public function purchase(int $userId, int $productId, int $salesEventId, ?string $ip = null, ?string $userAgent = null): Order
    {
        $order = DB::transaction(function () use ($userId, $productId, $salesEventId, $ip, $userAgent) {
            $event = SalesEvent::query()
                ->where('id', $salesEventId)
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first();

            if (! $event) {
                throw new RuntimeException('Sales event is not active or has ended.');
            }

            $product = Product::query()
                ->where('id', $productId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            if ($product->stock_count < 1) {
                throw new RuntimeException('Product is sold out.');
            }

            $alreadyOrdered = Order::query()
                ->where('user_id', $userId)
                ->where('product_id', $productId)
                ->where('sales_event_id', $salesEventId)
                ->exists();

            if ($alreadyOrdered) {
                throw new RuntimeException('User already ordered this product in this sales event.');
            }

            $product->decrement('stock_count');

            $order = Order::create([
                'user_id' => $userId,
                'product_id' => $productId,
                'sales_event_id' => $event->id,
                'order_no' => 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
                'status' => 'confirmed',
                'price' => $product->price,
                'quantity' => 1,
                'ordered_at' => now(),
            ]);

            OrderLog::create([
                'order_id' => $order->id,
                'user_id' => $userId,
                'action' => 'order.confirmed',
                'payload' => [
                    'product_id' => $productId,
                    'sales_event_id' => $salesEventId,
                    'price' => $product->price,
                ],
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);

            return $order;
        }, 3);

        UpdateMerchantDashboardCache::dispatch($order->sales_event_id)
            ->afterCommit()
            ->onQueue('dashboard');

        return $order;
    }
}
