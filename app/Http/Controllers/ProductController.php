<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $sortable = ['name', 'updated_at'];
        if (in_array($request->query('sort'), $sortable, true)) {
            $sort = $request->query('sort');
            $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        } else {
            $sort = 'updated_at';
            $direction = 'desc';
        }

        return ProductResource::collection(
            Product::query()
                ->with('category')
                ->when($request->string('search')->trim()->toString(), fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy($sort, $direction)
                ->paginate(15)
                ->withQueryString()
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        $product = Product::create($request->validated());

        return ProductResource::make($product->load('category'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        return ProductResource::make($product->load('category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return ProductResource::make($product->load('category'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
