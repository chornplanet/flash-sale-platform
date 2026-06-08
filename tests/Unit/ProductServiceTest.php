<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reduce_stock_succeeds_when_enough_stock_is_available(): void
    {
        $product = Product::factory()->create([
            'stock_count' => 5,
        ]);

        $reduced = app(ProductService::class)->reduceStock($product->id, 2);

        $this->assertTrue($reduced);
        $this->assertSame(3, $product->refresh()->stock_count);
    }

    public function test_reduce_stock_fails_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create([
            'stock_count' => 1,
        ]);

        $reduced = app(ProductService::class)->reduceStock($product->id, 2);

        $this->assertFalse($reduced);
        $this->assertSame(1, $product->refresh()->stock_count);
    }

    public function test_reduce_stock_allows_only_one_concurrent_decrement_to_win(): void
    {
        $product = Product::factory()->create([
            'stock_count' => 1,
        ]);

        $service = app(ProductService::class);

        $firstReduction = $service->reduceStock($product->id, 1);
        $racingReduction = $service->reduceStock($product->id, 1);

        $this->assertTrue($firstReduction);
        $this->assertFalse($racingReduction);
        $this->assertSame(0, $product->refresh()->stock_count);
    }

    public function test_search_products_returns_only_active_matching_products(): void
    {
        $matchingProduct = Product::factory()->create([
            'name' => 'Limited Headphones',
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Limited Camera',
            'is_active' => false,
        ]);
        Product::factory()->create([
            'name' => 'Everyday Backpack',
            'is_active' => true,
        ]);

        $results = app(ProductService::class)->searchProducts('Limited', 10);

        $this->assertSame([$matchingProduct->id], collect($results->items())->pluck('id')->all());
    }
}
