<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Users - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/users.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-7xl mx-auto">
        @include('partials.nav')

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Users</h1>
            <a
                href="{{ route('users.create') }}"
                class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black"
            >
                New User
            </a>
        </div>

        <form id="search-form" class="mb-4">
            <input
                type="search"
                id="search"
                name="search"
                minlength="3"
                placeholder="Search by name or email (min. 3 characters)..."
                class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
            >
        </form>

        <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="px-4 py-2 border-b border-gray-200">
                        <button type="button" id="sort-name" class="flex items-center gap-1 font-medium hover:underline">
                            Name
                            <span id="sort-name-indicator" class="text-gray-400"></span>
                        </button>
                    </th>
                    <th class="px-4 py-2 border-b border-gray-200">Email</th>
                    <th class="px-4 py-2 border-b border-gray-200"></th>
                </tr>
            </thead>
            <tbody id="user-table-body"></tbody>
        </table>

        <div id="pagination" class="flex items-center justify-between mt-4 text-sm text-gray-600"></div>
    </div>
</body>
</html>
