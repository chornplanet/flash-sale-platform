<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductService
{
    /**
     * Atomically reduce product stock.
     *
     * This prevents overselling during concurrent checkout requests.
     */
    public function reduceStock(int $productId, int $qty): bool
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $affectedRows = Product::query()
            ->whereKey($productId)
            ->where('stock_count', '>=', $qty)
            ->update([
                'stock_count' => DB::raw("stock_count - {$qty}"),
                'updated_at' => now(),
            ]);

        return $affectedRows === 1;
    }

    /**
     * Get top-selling products.
     *
     * Assumption:
     * - orders table has product_id
     * - orders table has quantity
     *
     * If your project uses order_items, move this aggregation to order_items instead.
     */
    public function getTopSellingProducts(int $limit = 10): Collection
    {
        $topProductIds = Order::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity) as total_sold')
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->pluck('product_id');

        return Product::query()
            ->whereIn('id', $topProductIds)
            ->get()
            ->sortBy(fn (Product $product) => $topProductIds->search($product->id))
            ->values();
    }

    /**
     * Search products safely.
     *
     * The controller should pass a validated keyword into this method.
     */
    public function searchProducts(?string $keyword, int $perPage = 20): LengthAwarePaginator
    {
        $keyword = trim((string) $keyword);

        return Product::query()
            ->select([
                'id',
                'sku',
                'name',
                'price',
                'stock_count',
                'is_active',
                'created_at',
            ])
            ->where('is_active', true)
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where('name', 'like', '%'.$this->escapeLike($keyword).'%');
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Escape LIKE wildcard characters.
     */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
