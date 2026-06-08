<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SalesEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateSalesEvents extends Command
{
    private const EVENT_COUNT = 5;
    private const PRODUCTS_PER_EVENT = 50;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-sales-events';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate five active sales events and attach products to each event';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $productIds = Product::query()
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($productIds === []) {
            $this->error('No active products found. Seed or create products before generating sales events.');

            return self::FAILURE;
        }

        $productsPerEvent = min(self::PRODUCTS_PER_EVENT, count($productIds));
        $createdEvents = 0;

        DB::transaction(function () use ($productIds, $productsPerEvent, &$createdEvents) {
            for ($i = 1; $i <= self::EVENT_COUNT; $i++) {
                $event = SalesEvent::factory()->create();

                $selectedProductIds = collect($productIds)
                    ->shuffle()
                    ->take($productsPerEvent)
                    ->values();

                $now = now();
                $rows = $selectedProductIds
                    ->map(fn (int $productId, int $offset) => [
                        'product_id' => $productId,
                        'sales_event_id' => $event->id,
                        'event_stock_limit' => random_int(100, 1000),
                        'event_price' => random_int(50, 3000),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::table('product_sales_event')->insert($rows);
                $createdEvents++;

                $this->info("Created sales event {$event->id} with {$productsPerEvent} products.");
            }
        });

        $this->info("Generated {$createdEvents} sales events.");

        return self::SUCCESS;
    }
}
