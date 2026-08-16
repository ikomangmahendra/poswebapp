<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 10;

    private const PER_PAGE = 10;

    /**
     * Display the dashboard's summary stats.
     */
    public function index(Request $request)
    {
        return [
            'total_products' => Product::query()->count(),
            'total_categories' => Category::query()->count(),
            'inventory_value' => Product::query()->selectRaw('SUM(price * stock) as total')->value('total') ?? 0,
            'low_stock_threshold' => self::LOW_STOCK_THRESHOLD,
            'low_stock_count' => Product::query()->where('stock', '<', self::LOW_STOCK_THRESHOLD)->count(),
        ];
    }

    /**
     * Display a paginated listing of low stock products.
     */
    public function lowStock(Request $request)
    {
        return ProductResource::collection(
            Product::query()
                ->with('category')
                ->where('stock', '<', self::LOW_STOCK_THRESHOLD)
                ->orderBy('stock')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
        );
    }

    /**
     * Display a paginated products-per-category breakdown.
     */
    public function categoryBreakdown(Request $request)
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderByDesc('products_count')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'product_count' => $category->products_count,
            ]);

        return JsonResource::collection($categories);
    }

    /**
     * Display a paginated listing of recently updated products.
     */
    public function recentProducts(Request $request)
    {
        return ProductResource::collection(
            Product::query()
                ->with('category')
                ->latest('updated_at')
                ->paginate(self::PER_PAGE)
                ->withQueryString()
        );
    }
}
