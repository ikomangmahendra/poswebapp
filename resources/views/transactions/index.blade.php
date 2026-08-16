<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transactions - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/transactions.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-7xl mx-auto">
        @include('partials.nav')

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Transactions</h1>
            <a
                href="{{ route('transactions.create') }}"
                class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black"
            >
                New Transaction
            </a>
        </div>

        <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="px-4 py-2 border-b border-gray-200">ID</th>
                    <th class="px-4 py-2 border-b border-gray-200">Items</th>
                    <th class="px-4 py-2 border-b border-gray-200">
                        <button type="button" id="sort-total" class="flex items-center gap-1 font-medium hover:underline">
                            Total
                            <span id="sort-total-indicator" class="text-gray-400"></span>
                        </button>
                    </th>
                    <th class="px-4 py-2 border-b border-gray-200">
                        <button type="button" id="sort-created_at" class="flex items-center gap-1 font-medium hover:underline">
                            Created
                            <span id="sort-created_at-indicator" class="text-gray-400"></span>
                        </button>
                    </th>
                    <th class="px-4 py-2 border-b border-gray-200"></th>
                </tr>
            </thead>
            <tbody id="transaction-table-body"></tbody>
        </table>

        <div id="pagination" class="flex items-center justify-between mt-4 text-sm text-gray-600"></div>
    </div>
</body>
</html>
