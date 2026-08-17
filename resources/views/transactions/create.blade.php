<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>New Transaction - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/transactions-create.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-3xl mx-auto">
        @include('partials.nav')

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">New Transaction</h1>
            <a href="{{ route('transactions.list') }}" class="text-sm text-gray-600 hover:underline">Back to list</a>
        </div>

        <div class="bg-white border border-gray-200 rounded-md p-4 mb-6">
            <form id="product-search-form" class="relative">
                <label for="product-search" class="block text-sm mb-1">Product</label>
                <input
                    type="search"
                    id="product-search"
                    autocomplete="off"
                    minlength="3"
                    placeholder="Search by name or SKU (min. 3 characters)..."
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
                <ul
                    id="product-search-results"
                    class="hidden absolute z-10 mt-1 w-full bg-white border border-gray-200 rounded-md shadow-md max-h-64 overflow-y-auto text-sm"
                ></ul>
            </form>

            <div id="selected-product" class="hidden items-end gap-2 mt-3">
                <div class="flex-1 text-sm">
                    <span class="text-gray-500">Selected:</span>
                    <span id="selected-product-label" class="font-medium"></span>
                    <button type="button" id="change-product-button" class="ml-2 text-blue-600 hover:underline text-xs">Change</button>
                </div>

                <div class="w-24">
                    <label for="quantity-input" class="block text-sm mb-1">Qty</label>
                    <input
                        type="number"
                        id="quantity-input"
                        value="1"
                        min="1"
                        class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                    >
                </div>

                <button
                    type="button"
                    id="add-item-button"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100"
                >
                    Add
                </button>
            </div>

            <p id="add-item-error" class="hidden text-sm text-red-600 mt-2"></p>
        </div>

        <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm mb-4">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="px-4 py-2 border-b border-gray-200">Product</th>
                    <th class="px-4 py-2 border-b border-gray-200">Quantity</th>
                    <th class="px-4 py-2 border-b border-gray-200">Unit Price</th>
                    <th class="px-4 py-2 border-b border-gray-200">Subtotal</th>
                    <th class="px-4 py-2 border-b border-gray-200"></th>
                </tr>
            </thead>
            <tbody id="cart-table-body"></tbody>
        </table>

        <div class="flex justify-end mb-6 text-lg font-semibold">
            Total: <span id="cart-total" class="ml-2">$0.00</span>
        </div>

        <div id="submit-errors" class="hidden text-sm text-red-600 space-y-1 mb-4"></div>

        <div class="flex gap-2">
            <button
                type="button"
                id="submit-button"
                class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black disabled:opacity-40 disabled:cursor-not-allowed"
                disabled
            >
                Complete Sale
            </button>
            <a
                href="{{ route('transactions.list') }}"
                class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100"
            >
                Cancel
            </a>
        </div>
    </div>
</body>
</html>
