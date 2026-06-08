<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'sales_event_id' => ['required', 'integer', 'exists:sales_events,id'],
        ]);

        try {
            $order = $this->orderService->purchase(
                userId: $request->user()->id,
                productId: $validated['product_id'],
                salesEventId: $validated['sales_event_id'],
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            return response()->json([
                'message' => 'Order confirmed.',
                'data' => $order,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    public function index(Request $request)
    {
        return $request->user()
            ->orders()
            ->latest()
            ->paginate(20);
    }

    public function show(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        return response()->json([
            'data' => $order->load(['product', 'salesEvent']),
        ]);
    }

    public function destroy(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        $order->delete();

        return response()->json([
            'message' => 'Order deleted.',
            'data' => $order->load(['product', 'salesEvent']),
        ]);
    }

    public function dashboard(Request $request)
    {
        $perPage = $request->integer('per_page', 50);
        $page = $request->integer('page', 1);
        $status = $request->string('status')->toString();
        $sortBy = $request->string('sort_by', 'created_at')->toString();
        $sortDirection = $request->string('sort_direction', 'desc')->toString();

        if (! in_array($sortBy, ['id', 'created_at', 'ordered_at'], true)) {
            $sortBy = 'created_at';
        }

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $cacheKey = "orders:page:{$page}:per_page:{$perPage}:status:{$status}:sort:{$sortBy}:{$sortDirection}";

        $orders = Cache::tags(['dashboard'])->remember(
            $cacheKey,
            60,
            function () use ($perPage, $status, $sortBy, $sortDirection) {

                $orders = Order::with([
                    'salesEvent:id,name',
                    'user:id,email',
                    'product:id,name',
                ])
                    ->when($status !== '', fn ($query) => $query->where('status', $status))
                    ->orderBy($sortBy, $sortDirection)
                    ->paginate($perPage);

                $orders->through(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_no' => $order->order_no,
                        'event' => $order->salesEvent?->name,
                        'user' => $order->user?->email,
                        'product' => $order->product?->name,
                        'price' => $order->price,
                        'status' => $order->status,
                        'ordered_at' => $order->ordered_at,
                        'created_at' => $order->created_at,
                    ];
                });

                return $orders;
            }
        );

        return response()->json($orders);
    }

    public function saleEventDashboardSummary(int $salesEventId)
    {
        $cacheKey = "dashboard:sale_event:{$salesEventId}";
        $summary = Cache::get($cacheKey);

        return response()->json([
            'data' => [
                'sales_event_id' => $salesEventId,
                'cache_key' => $cacheKey,
                'cached' => $summary !== null,
                'summary' => $summary,
            ],
        ]);
    }
}
