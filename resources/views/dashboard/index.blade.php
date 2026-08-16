<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/dashboard.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-7xl mx-auto">
        @include('partials.nav')

        <h1 class="text-2xl font-semibold mb-6">Dashboard</h1>

        <div id="stat-tiles" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8"></div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div>
                <h2 class="font-medium mb-3">Low Stock</h2>
                <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm">
                    <thead class="bg-gray-100 text-left">
                        <tr>
                            <th class="px-4 py-2 border-b border-gray-200">Name</th>
                            <th class="px-4 py-2 border-b border-gray-200">SKU</th>
                            <th class="px-4 py-2 border-b border-gray-200">Category</th>
                            <th class="px-4 py-2 border-b border-gray-200">Stock</th>
                        </tr>
                    </thead>
                    <tbody id="low-stock-table-body"></tbody>
                </table>
                <div id="low-stock-pagination" class="flex items-center justify-between mt-2 text-sm text-gray-600"></div>
            </div>

            <div>
                <h2 class="font-medium mb-3">Products per Category</h2>
                <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm">
                    <thead class="bg-gray-100 text-left">
                        <tr>
                            <th class="px-4 py-2 border-b border-gray-200">Category</th>
                            <th class="px-4 py-2 border-b border-gray-200">Products</th>
                        </tr>
                    </thead>
                    <tbody id="category-breakdown-table-body"></tbody>
                </table>
                <div id="category-breakdown-pagination" class="flex items-center justify-between mt-2 text-sm text-gray-600"></div>
            </div>

            <div class="lg:col-span-2">
                <h2 class="font-medium mb-3">Recently Updated Products</h2>
                <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm">
                    <thead class="bg-gray-100 text-left">
                        <tr>
                            <th class="px-4 py-2 border-b border-gray-200">Name</th>
                            <th class="px-4 py-2 border-b border-gray-200">Category</th>
                            <th class="px-4 py-2 border-b border-gray-200">Updated</th>
                        </tr>
                    </thead>
                    <tbody id="recent-products-table-body"></tbody>
                </table>
                <div id="recent-products-pagination" class="flex items-center justify-between mt-2 text-sm text-gray-600"></div>
            </div>
        </div>
    </div>
</body>
</html>
