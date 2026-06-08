<?php

namespace Tests\Feature;

use App\Jobs\UpdateMerchantDashboardCache;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_place_an_order(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'stock_count' => 2,
            'price' => 99.50,
        ]);
        $salesEvent = SalesEvent::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders/purchase', [
            'product_id' => $product->id,
            'sales_event_id' => $salesEvent->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Order confirmed.')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.sales_event_id', $salesEvent->id)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertSame(1, $product->refresh()->stock_count);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
            'sales_event_id' => $salesEvent->id,
            'status' => 'confirmed',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('order_logs', [
            'user_id' => $user->id,
            'action' => 'order.confirmed',
        ]);

        Queue::assertPushed(UpdateMerchantDashboardCache::class);
    }

    public function test_order_placement_fails_when_product_is_out_of_stock(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'stock_count' => 0,
        ]);
        $salesEvent = SalesEvent::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/orders/purchase', [
            'product_id' => $product->id,
            'sales_event_id' => $salesEvent->id,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'Product is sold out.');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(0, $product->refresh()->stock_count);
        Queue::assertNothingPushed();
    }

    public function test_order_placement_requires_authentication(): void
    {
        $product = Product::factory()->create();
        $salesEvent = SalesEvent::factory()->create();

        $response = $this->postJson('/api/orders/purchase', [
            'product_id' => $product->id,
            'sales_event_id' => $salesEvent->id,
        ]);

        $response->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_user_cannot_duplicate_order_for_same_product_in_same_sale_event(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'stock_count' => 2,
        ]);
        $salesEvent = SalesEvent::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/orders/purchase', [
            'product_id' => $product->id,
            'sales_event_id' => $salesEvent->id,
        ])->assertCreated();

        $response = $this->postJson('/api/orders/purchase', [
            'product_id' => $product->id,
            'sales_event_id' => $salesEvent->id,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('message', 'User already ordered this product in this sales event.');

        $this->assertSame(1, $product->refresh()->stock_count);
        $this->assertSame(1, Order::query()->count());
    }
}
