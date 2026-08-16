<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categories - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/categories.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-semibold mb-6">Categories</h1>

        <form id="category-form" class="bg-white border border-gray-200 rounded-md p-4 mb-8 space-y-4">
            <h2 id="form-title" class="font-medium">Add Category</h2>

            <input type="hidden" id="category-id" value="">

            <div>
                <label for="name" class="block text-sm mb-1">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="description" class="block text-sm mb-1">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="2"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                ></textarea>
            </div>

            <div id="form-errors" class="hidden text-sm text-red-600 space-y-1"></div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black">
                    Save
                </button>
                <button
                    type="button"
                    id="cancel-edit"
                    class="hidden px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100"
                >
                    Cancel
                </button>
            </div>
        </form>

        <table class="w-full bg-white border border-gray-200 rounded-md overflow-hidden text-sm">
            <thead class="bg-gray-100 text-left">
                <tr>
                    <th class="px-4 py-2 border-b border-gray-200">Name</th>
                    <th class="px-4 py-2 border-b border-gray-200">Description</th>
                    <th class="px-4 py-2 border-b border-gray-200"></th>
                </tr>
            </thead>
            <tbody id="category-table-body"></tbody>
        </table>
    </div>
</body>
</html>
