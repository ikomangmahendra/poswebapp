<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($category) ? 'Edit Category' : 'New Category' }} - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/categories-form.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-lg mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">{{ isset($category) ? 'Edit Category' : 'New Category' }}</h1>
            <a href="{{ route('categories.list') }}" class="text-sm text-gray-600 hover:underline">Back to list</a>
        </div>

        <form
            id="category-form"
            data-category-id="{{ $category->id ?? '' }}"
            class="bg-white border border-gray-200 rounded-md p-4 space-y-4"
        >
            <div>
                <label for="name" class="block text-sm mb-1">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    value="{{ $category->name ?? '' }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="description" class="block text-sm mb-1">Description</label>
                <textarea
                    id="description"
                    name="description"
                    rows="3"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >{{ $category->description ?? '' }}</textarea>
            </div>

            <div id="form-errors" class="hidden text-sm text-red-600 space-y-1"></div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black">
                    Save
                </button>
                <a
                    href="{{ route('categories.list') }}"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>
