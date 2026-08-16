<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($product) ? 'Edit Product' : 'New Product' }} - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/products-form.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-lg mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">{{ isset($product) ? 'Edit Product' : 'New Product' }}</h1>
            <a href="{{ route('products.list') }}" class="text-sm text-gray-600 hover:underline">Back to list</a>
        </div>

        <form
            id="product-form"
            data-product-id="{{ $product->id ?? '' }}"
            class="bg-white border border-gray-200 rounded-md p-4 space-y-4"
        >
            <div>
                <label for="category_id" class="block text-sm mb-1">Category</label>
                <select
                    id="category_id"
                    name="category_id"
                    required
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
                    <option value="" disabled {{ isset($product) ? '' : 'selected' }}>Select a category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(($product->category_id ?? null) === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="name" class="block text-sm mb-1">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    value="{{ $product->name ?? '' }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="sku" class="block text-sm mb-1">SKU</label>
                <input
                    type="text"
                    id="sku"
                    name="sku"
                    value="{{ $product->sku ?? '' }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="price" class="block text-sm mb-1">Price</label>
                <input
                    type="number"
                    id="price"
                    name="price"
                    step="0.01"
                    min="0"
                    required
                    value="{{ $product->price ?? '' }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="stock" class="block text-sm mb-1">Stock</label>
                <input
                    type="number"
                    id="stock"
                    name="stock"
                    step="1"
                    min="0"
                    value="{{ $product->stock ?? 0 }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div id="form-errors" class="hidden text-sm text-red-600 space-y-1"></div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black">
                    Save
                </button>
                <a
                    href="{{ route('products.list') }}"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>
