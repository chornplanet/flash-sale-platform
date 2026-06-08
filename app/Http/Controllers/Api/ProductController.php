<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        return Product::query()
            ->where('is_active', true)
            ->latest()
            ->paginate($request->integer('per_page', 20));
    }

    public function show(Product $product)
    {
        return response()->json([
            'data' => $product,
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted.',
        ]);
    }

    public function search(Request $request, ProductService $productService)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $productService->searchProducts(
            keyword: $validated['q'] ?? null,
            perPage: $validated['per_page'] ?? 20,
        );
    }
}
