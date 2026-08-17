<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Transaction #{{ $transaction->id }} - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/transactions-show.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-3xl mx-auto" data-transaction-id="{{ $transaction->id }}">
        @include('partials.nav')

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Transaction #{{ $transaction->id }}</h1>
            <a href="{{ route('transactions.list') }}" class="text-sm text-gray-600 hover:underline">Back to list</a>
        </div>

        <p id="transaction-created-at" class="text-sm text-gray-600 mb-1"></p>
        <p id="transaction-cashier" class="text-sm text-gray-600 mb-4"></p>

        <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="px-4 py-2 border-b border-gray-200">Product</th>
                    <th class="px-4 py-2 border-b border-gray-200">Quantity</th>
                    <th class="px-4 py-2 border-b border-gray-200">Unit Price</th>
                    <th class="px-4 py-2 border-b border-gray-200">Subtotal</th>
                </tr>
            </thead>
            <tbody id="item-table-body"></tbody>
        </table>

        <div class="flex justify-end mt-4 text-lg font-semibold">
            Total: <span id="transaction-total" class="ml-2"></span>
        </div>
    </div>
</body>
</html>
