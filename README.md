# Flash Sale Platform

## Getting started

```bash
git clone https://github.com/chornplanet/flash-sale-platform

cd flash-sale-platform
docker compose up -d --build
```

## Readiness check

```
docker compose exec app php artisan app:healthy-check
```

## Seed the database with 100K orders

```
docker compose exec app php artisan db:seed
```

# Architecture Overview

This project is a Laravel 12 API for flash-sale ordering. It is designed around a small synchronous checkout core, with slower side effects handled by Redis queues supervised by Laravel Horizon.

## Runtime Topology

- `nginx` serves the API and forwards PHP requests to the `app` PHP-FPM container.
- `app` runs Laravel, installs dependencies on first container startup, and connects to MySQL and Redis through Docker Compose.
- `mysql` stores users, products, sales events, orders, order logs, jobs, cache metadata, and Sanctum tokens.
- `redis` backs cache and queue workloads.
- `queue` runs `php artisan horizon` so queued work is supervised by Horizon instead of a plain queue worker.

The Docker PHP image installs the runtime extensions needed by the application and Horizon, including `pdo_mysql`, `redis`, `pcntl`, and `posix`.

## API Boundary

The API is token-authenticated with Laravel Sanctum. Registration and login are public, while product browsing, product search, order placement, order history, deletes, and dashboard endpoints run behind `auth:sanctum`.

Core API groups:

- Auth: `POST /api/register`, `POST /api/login`
- Sales events: `GET /api/sales-events`
- Products: `GET /api/products`, `GET /api/products/search`, `GET /api/products/{product}`, `DELETE /api/products/{product}`
- Orders: `POST /api/orders/purchase`, `GET /api/orders`, `GET /api/orders/{order}`, `DELETE /api/orders/{order}`
- Dashboard: `GET /api/orders/dashboard`, `GET /api/orders/dashboard/sale-events/{salesEventId}/summary`

## Domain Flow

Checkout is intentionally synchronous for correctness. `OrderController` validates the request, then delegates purchase behavior to `OrderService`. `OrderService` opens a database transaction, verifies the sale event is active, locks the product row with `lockForUpdate()`, checks stock, prevents duplicate user purchases for the same product and event, decrements stock, creates the order, and writes an order log.

After the transaction commits, the service dispatches `UpdateMerchantDashboardCache` to the `dashboard` queue. This keeps the user-facing purchase response tied only to the critical work required to confirm the order.

`ProductService` owns product-specific operations such as atomic stock reduction, top-selling product lookup, and safe product search pagination.

## Data Model

The core tables are:

- `users`: authenticated customers and API token owners.
- `products`: catalog records with active status, price, stock count, and soft deletes.
- `sales_events`: flash-sale windows with active state and start/end timing.
- `product_sales_event`: pivot table for event-specific product limits and pricing.
- `orders`: confirmed purchases linked to user, product, and sales event, with soft deletes.
- `order_logs`: audit records for order lifecycle events and request context.

Indexes are chosen around common flash-sale reads and writes: active products, sale-event lookups, order dashboards, user order history, and duplicate-purchase checks.

## Async Work, Cache, and Observability

Queued jobs are separated by purpose:

- `dashboard`: refresh merchant dashboard aggregates after confirmed orders. [Implemented & Tested]
- `emails`: send order confirmation mail. [Implemented]
- `analytics`: send external analytics webhooks. [Implemented]

Dashboard list responses use tagged cache entries. `OrderObserver` flushes the `dashboard` tag when orders are created, updated, deleted, restored, or force deleted, while the dashboard summary endpoint can read the aggregate cache produced by the queued dashboard job.

Laravel Telescope is included for local query and request inspection. Horizon is included for queue supervision, retries, timeouts, and queue visibility.

## Testing Strategy

Tests use the Laravel test harness with an in-memory SQLite database from `phpunit.xml`. Database-dependent tests opt into `RefreshDatabase`.

Current focused coverage includes:

- `tests/Unit/ProductServiceTest.php`: stock reduction, concurrent decrement behavior, and product search filtering.
- `tests/Feature/OrderPurchaseTest.php`: authenticated purchase success, sold-out handling, duplicate-purchase prevention, and stock/order persistence.

```bash
php artisan test tests/Unit/ProductServiceTest.php
php artisan test tests/Feature/OrderPurchaseTest.php
```

# Task 1: Project Setup & Architecture

## 1. Get Test User Email Command

```
docker compose exec app php artisan app:get-test-user
```

## 2. Login and get an access token

You can use email from item#1 for testing.

```
POST /api/login
{
    "email": "...",
    "password" :  "..."
}
```

## 3. Use the token from Login to access protected APIs

```
GET /api/orders
Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

# Task 2: Database Design & Indexing Strategy

## 2a - Schema Implementation

- Implemented migrations for all 5 core entities in database\migrations
- Implemented foreign key of each entity in app\Models
- Implemented **soft deleted** on orders and products in database\migrations
- ImplementedTimestamp are on all tables
- Soft Delete API: It require a token from `POST /api/login`.

**Example of delete a product**

```
DELETE /api/products/5"
Authorization: Bearer {token}
```

**Example of delete a order**

```
DELETE /api/orders/5"
Authorization: Bearer {token}
```

## 2b - Index Design

- Some column is indexed regarding it is frequently used in WHERE, JOIN, ORDER BY, or GROUP BY.

Example of index:

```
$table->index('status')
$table->index(['sales_event_id', 'product_id', 'status']);
$table->index(['user_id', 'status', 'created_at']);
```

- Use single-column indexes when one column is often queried alone such as `$table->index('status');`.

- Use composite indexes when columns are usually queried together such as `$table->index(['sales_event_id', 'product_id', 'status']);`.

- Trade-off: extra storage, slower writes, more index maintenance during high traffic, and possible lock contention.

## 2c - Database Indexing Decisions

### Q1 Answer: Use a composite index, not only a single-column index.

```
$table->index(
    ['user_id', 'status', 'created_at'],
    'idx_orders_user_status_created'
);
```

Example SQL: This query filters by user_id+status, and sorts by created_at DESC.

```
Why: SELECT * FROM orders
WHERE user_id = ? AND status = ?
ORDER BY created_at DESC;
```

### Q2 Answer:

One example is adding a single-column index on orders.status

status has low cardinality, and during a flash sale thousands of inserts may write the same value, such as confirmed. Every insert must update that index, so many concurrent transactions compete on nearby index pages. This increases write overhead, lock waits, and possible deadlocks when combined with stock updates or other order-related writes.

I would avoid indexing status alone unless we have a proven query that needs it. I would prefer a composite index such as:

```
$table->index(['sales_event_id', 'product_id', 'status']);
```

because it supports real reporting/filtering queries and is more selective.

### Q3 Answer:

**status** is a low-cardinality column because it only has a few possible values, e.g.
pending, confirmed, paid, cancelled, and failed.

I would avoid indexing **orders.status** alone. It has low cardinality, so MySQL may ignore it because it does not reduce the search space enough. It also adds write overhead during high-volume order inserts. A composite index like (sales_event_id, product_id, status) is better because it matches real query patterns and is more selective.

### Q4 Answer

A covering index is useful when the index contains all columns needed by the query, so MySQL can answer from the index without reading the full table row.

**Example query from this project**

```
SELECT id, status, created_at
FROM orders
WHERE user_id = ? AND status = ?
ORDER BY created_at DESC
LIMIT 20;
```

**Covering index**

```
$table->index(
    ['user_id', 'status', 'created_at', 'id'],
    'idx_orders_user_status_created_id'
);
```

**Why useful**

```
The query filters by user_id and status, sorts by created_at, and only selects id, status, and created_at. Since all required columns are inside the index, MySQL can use the index directly without extra table lookups.
```

# Task 3: Performance Optimization

## 1. List of Performance Problem

- Load all events into memory. Bad if there many sale events.

```
$events = SaleEvent::all();
```

- Runs 1 query per event. This creates an N+1 query problem.

```
$orders = Order::where('sale_event_id', $event->id)->get();
$user = User::find($order->user_id);
$product = Product::find($order->product_id);
```

- Runs 2 extra queries per order. With 100k orders, this can become hundreds of thousands of queries.

```
$user = User::find($order->user_id);
$product = Product::find($order->product_id);
```

- Revenue `$revenue += $product->price;` should normally use the price stored on the order, not the current product price.
- No pagination. Could return massive JSON and crash memory `$result[] = [...]`.
- Also `$total and revenue are calculated but never returned`.

## 2. Example of Eloquent Eager Loading Approach

Rewrite the method using Eloquent eager loading to eliminate N+1 queries.

**Define the relationship:**

```
class SaleEvent extends Model
{
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
```

Then `$events = SaleEvent::with('orders')->get();`.

**Laravel execute only 2 queries:**

```
SELECT * FROM sale_events;
SELECT * FROM orders WHERE sale_event_id IN (1,2,3,...,50);
```

## 3. Pagination for Dashboard

```
public function dashboard(Request $request)
{
    $orders = Order::with([
        'saleEvent:id,name',
        'user:id,email',
        'product:id,name
    ])
    ->paginate($request->integer('per_page', 50));

    $orders->through(function ($order) {
        return [
            'event'   => $order->saleEvent?->name,
            'user'    => $order->user?->email,
            'product' => $order->product?->name,
            'price'   => $order->price,
            'status'  => $order->status,
        ];
    });

    return response()->json($orders);
}
```

This executes only a few queries:

```
SELECT * FROM orders LIMIT 50 OFFSET 0;
SELECT id,name FROM sale_events WHERE id IN (...);
SELECT id,email FROM users WHERE id IN (...);
SELECT id,name FROM products WHERE id IN (...);
```

## 4. Dashboard with Redis Cache

**Implemented Redis for cache driver.**

Using Redis cache tags allows all dashboard-related cache entries (multiple pages, filters, sorting options) to be invalidated with a single operation when a new order is created, while still maintaining a 60-second TTL as a fallback expiration mechanism.

```
public function dashboard(Request $request)
{
    $perPage = $request->integer('per_page', 50);
    $page = $request->integer('page', 1);

    $cacheKey = "page:{$page}:per_page:{$perPage}";

    $orders = Cache::tags(['dashboard'])->remember(
        $cacheKey,
        60,
        function () use ($perPage) {

            $orders = Order::with([
                'saleEvent:id,name',
                'user:id,email',
                'product:id,name',
            ])->paginate($perPage);

            $orders->through(function ($order) {
                return [
                    'event'   => $order->saleEvent?->name,
                    'user'    => $order->user?->email,
                    'product' => $order->product?->name,
                    'price'   => $order->price,
                    'status'  => $order->status,
                ];
            });

            return $orders;
        }
    );

    return response()->json($orders);
}

class OrderObserver
{
    public function created(Order $order): void
    {
        Cache::tags(['dashboard'])->flush();
    }
}
```

## 5. Database Query Log

We have query_count after added the "api/orders/dashboard" to fix the issue following.

```
local.DEBUG: Database query summary {"path":"api/orders/dashboard","method":"GET","query_count":5}
```

## 6. Telescope Query Count

![Telescope Query Count](.docs/Telescope%20query_count.png)

## 7. DB::raw() Aggregate Queries Discussion

Use `DB::raw()` aggregate queries when need the database to calculate results directly, instead of loading many Eloquent models into PHP memory.

```
$summary = Order::query()
    ->selectRaw('COUNT(*) as total_orders')
    ->selectRaw('SUM(price) as total_revenue')
    ->selectRaw('AVG(price) as average_order_value')
    ->where('sale_event_id', $saleEventId)
    ->first();
```

Do not use raw aggregates when need full domain objects and business behavior.

I would use DB::raw() or selectRaw() for aggregate queries such as COUNT, SUM, AVG, MIN, MAX, and grouped reports. For example, a dashboard showing total orders and revenue per sale event should be calculated in MySQL, not by loading all Eloquent models into PHP. This reduces memory usage, avoids model hydration overhead, and usually returns only a few summary rows. I would still use Eloquent when I need full models, relationships, events, or business logic.

# Task 4: Queue Design & Laravel Horizon

## 4a - Order Placement Flow

**Main rule:**

```
Anything required for correctness must be synchronous. Anything slow, external, or non-critical should be queued.
```

Laravel **queues** are designed for deferring slow tasks, and **Horizon** supervises Redis queue workers with configurable supervisors, queues, balancing, retries, and timeouts.

| Step                                         | Sync / Queue    | Reason                                                               |
| -------------------------------------------- | --------------- | -------------------------------------------------------------------- |
| 1. Validate request                          | **Synchronous** | Must fail fast before touching stock/order data.                     |
| 2. Decrement product stock                   | **Synchronous** | Critical for overselling prevention. Must be race-condition safe.    |
| 3. Create order record                       | **Synchronous** | The HTTP response should only return success after the order exists. |
| 4. Send confirmation email                   | **Queued**      | Slow I/O. Not required before returning success.                     |
| 5. Write `order_logs` entry                  | **Synchronous** | Audit trail must be committed with the order.                        |
| 6. Push real-time notification               | **Queued**      | Useful UX, but not required for order correctness.                   |
| 7. Update merchant dashboard aggregate cache | **Queued**      | Can be eventually consistent. Avoid slowing checkout.                |
| 8. Trigger third-party analytics webhook     | **Queued**      | External API is unreliable/slow. Should retry separately.            |

## 4b - Implementation

### Queue Design and Laravel Horizon

The order placement flow keeps correctness-critical work synchronous:

- request validation
- sale event validation
- race-condition-safe stock decrement
- order creation
- order log creation

These must complete before the HTTP response because they decide whether the order is valid and whether stock is available.

The following actions are queued:

- `SendOrderConfirmationEmail` on the `emails` queue
- `UpdateMerchantDashboardCache` on the `dashboard` queue
- `SendOrderAnalyticsWebhook` on the `analytics` queue

These jobs are asynchronous because they are slow, external, or eventually consistent. They should not block checkout success.

Jobs are dispatched with `afterCommit()` so workers only process them after the order transaction has been committed.

### Named Queues

The project uses named queues instead of the default queue:

- `emails`
- `dashboard`
- `analytics`

This prevents slow external webhooks from blocking emails or dashboard cache updates.

### Retry Strategy

Each job defines:

- `$tries`
- `backoff()`
- `failed()`

This allows transient failures such as mail server issues or webhook timeouts to be retried safely. Failed jobs are logged for later investigation.

### Horizon Balance Strategy

We use `balance => auto` so Horizon can dynamically shift workers to busy queues during burst traffic. I use `autoScalingStrategy => time` because the best signal is not just job count, but the estimated time to clear the queue. This is useful in flash sales where some jobs are fast, like dashboard cache updates, while others are slow, like external analytics webhooks. I still isolate external jobs in a separate supervisor so slow third-party services cannot block critical queues like emails and dashboard updates.

Horizon uses:

```php
'balance' => 'auto',
'autoScalingStrategy' => 'time',
```

## 4c - Queue & Horizon Decisions

### Q1. Should product stock decrement go through a queue?

No. Product stock decrement should be handled synchronously inside the order placement transaction.

Stock decrement is correctness-critical. The user should only receive a successful response if stock was actually reserved and the order was created.

Risk of queuing stock decrement:

```
User receives "order successful"
→ stock job is delayed
→ another user buys the same product
→ stock becomes negative or oversold
```

To prevent overselling without a queue, use a database transaction with row-level locking:

```
DB::transaction(function () use ($productId, $userId, $saleEventId) {
    $product = Product::query()
        ->whereKey($productId)
        ->lockForUpdate()
        ->firstOrFail();

    if ($product->stock_count <= 0) {
        throw new RuntimeException('Product is sold out.');
    }

    $product->decrement('stock_count');

    $order = Order::create([
        'user_id' => $userId,
        'product_id' => $product->id,
        'sale_event_id' => $saleEventId,
        'status' => 'confirmed',
        'price' => $product->price,
    ]);

    OrderLog::create([
        'order_id' => $order->id,
        'user_id' => $userId,
        'action' => 'order.confirmed',
    ]);

    return $order;
});
```

This ensure that, only one transaction updates the product stock at a time
stock cannot go below zero
order and stock update succeed or fail together.

### Q2. Why not queue everything?

Queuing everything can make the user experience worse when the **user needs an immediate and reliable** answer.

Bad example:

```
User clicks Buy Now
→ order creation is queued
→ API immediately returns "processing"
→ user does not know if they actually got the product
→ queue is delayed during flash-sale traffic
→ user refreshes/retries
→ duplicate attempts or confusion
```

For a flash-sale system, the order result must be immediate:

```
confirmed
sold out
already ordered
sale event closed
```

If order creation is queued, the user may wait seconds or minutes to know the result. That is worse than handling the critical order logic synchronously and only queuing side effects such as email, dashboard cache, notifications, and analytics webhooks.

### Q3. Emails queue has 50,000 jobs. What would you do?

First, check whether checkout is affected. Emails are **asynchronous**, so order placement should still work.

Diagnosis steps:

```
1. Check Horizon dashboard
   - queue wait time
   - throughput
   - failed jobs
   - worker count
   - job runtime

2. Check application logs
   - mail provider errors
   - timeout errors
   - rate-limit errors
   - authentication errors

3. Check failed jobs
   php artisan queue:failed

4. Check Horizon status
   php artisan horizon:status

5. Check server resources
   - CPU
   - memory
   - Redis health
   - network
```

Possible fixes:

```
1. Temporarily increase maxProcesses for the emails supervisor
2. Scale Horizon workers horizontally if infrastructure allows
3. Lower email job timeout if jobs are hanging
4. Add backoff if the mail provider is rate-limiting
5. Move emails to a separate supervisor so they do not block other queues
6. Pause non-critical jobs if needed
7. Use batch/digest emails if business allows
8. Retry failed jobs after fixing the root cause
```

Example Horizon adjustment:

```
'supervisor-emails' => [
    'connection' => 'redis',
    'queue' => ['emails'],
    'balance' => 'auto',
    'autoScalingStrategy' => 'time',
    'minProcesses' => 2,
    'maxProcesses' => 20,
    'tries' => 3,
    'timeout' => 30,
],
```

Long-term prevention:

```
monitor queue wait time
set alerts for backlog size
separate critical and non-critical queues
confirm mail provider rate limits
add idempotency to email jobs
use retry/backoff correctly
```

### Q4. When would you use ShouldBeUnique?

Use ShouldBeUnique when duplicate queued jobs would waste resources or produce incorrect repeated side effects.

Concrete example: dashboard aggregate cache refresh.

During a flash sale, thousands of orders may be created for the same sale event. If every order dispatches this job:

```
UpdateMerchantDashboardCache::dispatch($saleEventId);
```

the queue may contain thousands of duplicate cache-refresh jobs for the same sale event.

Better:

```
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;

class UpdateMerchantDashboardCache implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 20;
    public int $uniqueFor = 30;

    public function __construct(
        public int $saleEventId
    ) {}

    public function uniqueId(): string
    {
        return 'dashboard-cache-sale-event-' . $this->saleEventId;
    }

    public function handle(): void
    {
        // Recalculate and cache dashboard summary
    }
}
```

This means:

```
only one dashboard cache update job per sale event can be queued/running within the unique window
```

# Task 5: Code Review

The junior code works only in a very small test case, but it is not safe for production, especially for a flash-sale system.

## 5.1 Review: reduceStock()

**Original code**

```
$product = Product::find($productId);

if ($product->stock >= $qty) {
    $product->stock = $product->stock - $qty;
    $product->save();
    return true;
}
```

**Problems**

Bug: product maybe null. If the product does not exist, this line `$product->stock` will crash because $product is null.

**Serious bug: race condition / overselling**

This is the biggest issue. Example: Current stock =1 but two customers buy at the same time.

Request A: `$product->stock = 1`
Request B: `$product->stock = 1`

Both pass this condition `if ($product->stock >= $qty)` and save successfully.

Result, the system sells 2 items even though only 1 item exists.

**Missing validation**

The method accepts `$qty = 0` and `$qty = -5`.

A negative quantity could accidentally increase stock: `$product->stock - (-5)` that become `$product->stock + 5`.

**Best-practice issue**

For stock decrement in a flash-sale system, we should avoid: `find → check → save`. Use atomic database update instead.

## 5.2 Review: getTopSellingProducts()

**Original code**

```
$orders = Order::all();
```

**Problems**

Performance issue: loads all orders into memory.

This is dangerous. If the system has 1,000,000 orders, this line loads all rows into PHP memory. This can cause "Allowed memory size exhausted" or very slow response time.

**Wrong aggregation location**

Counting should be done by the database, not by PHP.

Bad:

```
foreach ($orders as $order) {
    ...
}
```

Better:

```
GROUP BY product_id
ORDER BY total_sold DESC
LIMIT 10
```

**Possible wrong business logic**

The code counts number of orders `+1`.

But if one order has quantity 5, it still counts as only 1.

For top-selling products, we usually need `SUM(quantity)`, not `COUNT(order_id)`.

**N+1 query problem**

This part runs one query per product:

```
foreach ($topIds as $id) {
    $products[] = Product::find($id);
}
```

If there are 10 products, that means 10 extra queries.

Better: `Product::whereIn('id', $topIds)->get();`

## 5.3 Review: searchProducts()

**Original code**

```
$results = DB::select(
    "SELECT * FROM products WHERE name LIKE '%" . $keyword . "%'"
);
```

**Problems**

Critical security issue: SQL injection.
This is unsafe `"%' . $keyword . '%'"`.

A malicious user could input SQL that changes the query.

Example input: `' OR 1=1 --`
This could return all products or become worse depending on the query.

**Bad Laravel practice: raw SQL string concatenation**

Laravel provides safe query bindings:

```
DB::select('SELECT * FROM products WHERE name LIKE ?', ["%{$keyword}%"]);
```

**Service should not depend directly on Request**

This method is inside ProductService, but it accepts: `Request $request`.

Better design:

Controller handles the HTTP request.
Service handles business logic.

So instead of: `searchProducts(Request $request)`
Use: `searchProducts(?string $keyword)`.

**Missing validation / input control**

The code does not limit keyword length.

Someone could send a very long search string and create unnecessary database load.

**Missing pagination**

Search can return many rows.

Better: `paginate(20)`

## 5.4 Corrected Version

- [ProductService.php](app\Services\ProductService.php)
- [Better ProductController for search](app\Http\Controllers\Api\ProductController.php)

## Important Note About orders vs order_items

For a real e-commerce or flash-sale system, this is usually better:

```
orders
- id
- user_id
- status
- total_amount

order_items
- id
- order_id
- product_id
- quantity
- price
```

Then top-selling products should be calculated from order_items, not directly from orders.

Example of more correct design.:

```
$topProductIds = DB::table('order_items')
    ->select('product_id')
    ->selectRaw('SUM(quantity) as total_sold')
    ->groupBy('product_id')
    ->orderByDesc('total_sold')
    ->limit(10)
    ->pluck('product_id');
```

## Summary of Issue Found

```
| Area            | Problem                                   | Severity |
| --------------- | ----------------------------------------- | -------: |
| Stock decrement | Race condition can cause overselling      | Critical |
| Stock decrement | Product may be null                       |     High |
| Stock decrement | Allows zero or negative quantity          |     High |
| Top products    | `Order::all()` loads all data into memory |     High |
| Top products    | Aggregation done in PHP instead of DB     |     High |
| Top products    | N+1 queries with `Product::find()` loop   |   Medium |
| Top products    | Counts orders instead of sold quantity    |   Medium |
| Search          | SQL injection vulnerability               | Critical |
| Search          | Raw SQL string concatenation              | Critical |
| Search          | Service depends on HTTP `Request`         |   Medium |
| Search          | No validation or max keyword length       |   Medium |
| Search          | No pagination                             |   Medium |
```

## Feedback

The main reason is:

```
For flash-sale stock, never use find → check → save. Use an atomic database update with WHERE stock >= qty.
```

Also: `Let the database do aggregation. Do not load all orders into PHP memory.`

And: `Never concatenate user input into SQL. Use Eloquent, Query Builder, or parameter binding.`

# Task 6: Unit & Feature Testing

## 6.1 ProductServiceTest

```
php artisan test tests/Unit/ProductServiceTest.php

   PASS  Tests\Unit\ProductServiceTest
  ✓ reduce stock succeeds when enough stock is available
  ✓ reduce stock fails when stock is insufficient
  ✓ reduce stock allows only one concurrent decrement to win
  ✓ search products returns only active matching products

  Tests:    4 passed (8 assertions)
```

## 6.2 OrderServiceTest

```
php artisan test tests/Feature/OrderPurchaseTest.php

   PASS  Tests\Feature\OrderPurchaseTest
  ✓ authenticated user can place an order
  ✓ order placement fails when product is out of stock
  ✓ order placement requires authentication
  ✓ user cannot duplicate order for same product in same sale event

  Tests:    4 passed (22 assertions)
```
