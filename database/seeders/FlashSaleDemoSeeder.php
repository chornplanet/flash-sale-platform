<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\Product;
use App\Models\SalesEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Laravel\Telescope\Telescope;
use RuntimeException;

class FlashSaleDemoSeeder extends Seeder
{
    private const USER_COUNT = 10000;

    private const PRODUCT_COUNT = 500;

    private const SALES_EVENT_COUNT = 20;

    private const PRODUCTS_PER_EVENT = 50;

    private const ORDER_COUNT = 100000;

    private const BATCH_SIZE = 1000;

    public function run(): void
    {
        DB::disableQueryLog();

        Telescope::withoutRecording(function (): void {
            $this->seedUsers();

            $this->seedProducts();

            $this->seedSalesEvents();

            $productIds = Product::query()->pluck('id')->all();
            $eventIds = SalesEvent::query()->pluck('id')->all();

            $this->seedEventProducts($eventIds, $productIds);

            $userIds = User::query()->pluck('id')->all();
            $eventProducts = DB::table('product_sales_event')
                ->select('product_id', 'sales_event_id', 'event_price')
                ->orderBy('sales_event_id')
                ->orderBy('product_id')
                ->get()
                ->all();

            $existingOrders = Order::query()->count();

            if ($existingOrders >= self::ORDER_COUNT) {
                $this->command->info("Orders already seeded ({$existingOrders}/".self::ORDER_COUNT.'). Skipping orders.');
                $this->seedMissingOrderLogs();

                $this->command->info('Seeder completed.');

                return;
            }

            $this->command->info('Seeding orders to 100k...');

            $inserted = $existingOrders;
            $candidateIndex = 0;
            $possibleCombinations = count($userIds) * count($eventProducts);
            $statuses = ['confirmed', 'confirmed', 'confirmed', 'cancelled', 'failed'];
            $seededAt = now();

            if ($existingOrders + $possibleCombinations < self::ORDER_COUNT) {
                throw new RuntimeException('Not enough unique user/product/event combinations to seed '.self::ORDER_COUNT.' orders.');
            }

            while ($inserted < self::ORDER_COUNT && $candidateIndex < $possibleCombinations) {
                $orders = [];

                while (count($orders) < self::BATCH_SIZE && $candidateIndex < $possibleCombinations) {
                    $pair = $eventProducts[$candidateIndex % count($eventProducts)];
                    $userId = $userIds[intdiv($candidateIndex, count($eventProducts))];
                    $orderedAt = $seededAt->copy()->subMinutes($candidateIndex % (30 * 24 * 60));

                    $orders[] = [
                        'user_id' => $userId,
                        'product_id' => $pair->product_id,
                        'sales_event_id' => $pair->sales_event_id,
                        'order_no' => sprintf('ORD-%06d-%06d-%06d', $userId, $pair->product_id, $pair->sales_event_id),
                        'status' => $statuses[$candidateIndex % count($statuses)],
                        'price' => $pair->event_price ?? 100 + ($candidateIndex % 4900),
                        'quantity' => 1,
                        'ordered_at' => $orderedAt,
                        'created_at' => $seededAt,
                        'updated_at' => $seededAt,
                    ];

                    $candidateIndex++;
                }

                // Existing rows are ignored, then the deterministic candidate walk continues until 100k are inserted.
                $inserted += DB::table('orders')->insertOrIgnore($orders);

                $this->command->info('Seeded orders: '.min($inserted, self::ORDER_COUNT).'/'.self::ORDER_COUNT);
            }

            if ($inserted < self::ORDER_COUNT) {
                throw new RuntimeException("Only seeded {$inserted} orders before exhausting unique combinations.");
            }

            $this->seedMissingOrderLogs();

            $this->command->info('Seeder completed.');
        });
    }

    private function seedUsers(): void
    {
        $existing = User::query()->count();

        if ($existing >= self::USER_COUNT) {
            $this->command->info("Users already seeded ({$existing}/".self::USER_COUNT.'). Skipping users.');

            return;
        }

        $missing = self::USER_COUNT - $existing;

        $this->command->info("Creating {$missing} users...");
        User::factory()->count($missing)->create();
    }

    private function seedProducts(): void
    {
        $existing = Product::query()->count();

        if ($existing >= self::PRODUCT_COUNT) {
            $this->command->info("Products already seeded ({$existing}/".self::PRODUCT_COUNT.'). Skipping products.');

            return;
        }

        $missing = self::PRODUCT_COUNT - $existing;

        $this->command->info("Creating {$missing} products...");
        Product::factory()->count($missing)->create();
    }

    private function seedSalesEvents(): void
    {
        $existing = SalesEvent::query()->count();

        if ($existing >= self::SALES_EVENT_COUNT) {
            $this->command->info("Sales events already seeded ({$existing}/".self::SALES_EVENT_COUNT.'). Skipping sales events.');

            return;
        }

        $missing = self::SALES_EVENT_COUNT - $existing;

        $this->command->info("Creating {$missing} sales events...");
        SalesEvent::factory()->count($missing)->create();
    }

    /**
     * Ensure each sales event has enough attached products without rewriting existing pivot rows.
     *
     * @param  array<int, int>  $eventIds
     * @param  array<int, int>  $productIds
     */
    private function seedEventProducts(array $eventIds, array $productIds): void
    {
        if (count($productIds) < self::PRODUCTS_PER_EVENT) {
            throw new RuntimeException('Not enough products to attach '.self::PRODUCTS_PER_EVENT.' products to each sales event.');
        }

        $this->command->info('Attaching missing products to sales events...');

        foreach ($eventIds as $eventId) {
            $existingProductIds = DB::table('product_sales_event')
                ->where('sales_event_id', $eventId)
                ->orderBy('product_id')
                ->pluck('product_id')
                ->all();

            if (count($existingProductIds) >= self::PRODUCTS_PER_EVENT) {
                $this->command->info("Sales event {$eventId} already has ".count($existingProductIds).' products. Skipping attachments.');

                continue;
            }

            $missing = self::PRODUCTS_PER_EVENT - count($existingProductIds);
            $availableProductIds = array_values(array_diff($productIds, $existingProductIds));

            if (count($availableProductIds) < $missing) {
                throw new RuntimeException("Not enough available products to finish sales event {$eventId} attachments.");
            }

            $now = now();
            $rows = [];

            foreach (array_slice($availableProductIds, 0, $missing) as $offset => $productId) {
                $rows[] = [
                    'product_id' => $productId,
                    'sales_event_id' => $eventId,
                    'event_stock_limit' => 100 + (($eventId + $productId + $offset) % 901),
                    'event_price' => 50 + (($eventId * 17 + $productId * 13 + $offset) % 2951),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('product_sales_event')->insertOrIgnore($rows);
            $this->command->info("Attached {$missing} products to sales event {$eventId}.");
        }
    }

    private function seedMissingOrderLogs(): void
    {
        $this->command->info('Creating missing order logs...');

        $created = 0;
        Order::query()
            ->select('id', 'user_id')
            ->whereDoesntHave('logs', fn ($query) => $query->where('action', 'order.seeded'))
            ->chunkById(1000, function ($orders) use (&$created) {
                $logs = [];

                foreach ($orders as $order) {
                    $logs[] = [
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'action' => 'order.seeded',
                        'payload' => json_encode(['source' => 'FlashSaleDemoSeeder']),
                        'ip_address' => '127.0.0.1',
                        'user_agent' => 'Seeder',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                OrderLog::insert($logs);
                $created += count($logs);
            });

        $this->command->info("Created {$created} missing order logs.");
    }
}
