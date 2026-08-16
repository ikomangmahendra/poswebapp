<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($user) ? 'Edit User' : 'New User' }} - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/users-form.js'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-lg mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">{{ isset($user) ? 'Edit User' : 'New User' }}</h1>
            <a href="{{ route('users.list') }}" class="text-sm text-gray-600 hover:underline">Back to list</a>
        </div>

        <form
            id="user-form"
            data-user-id="{{ $user->id ?? '' }}"
            class="bg-white border border-gray-200 rounded-md p-4 space-y-4"
        >
            <div>
                <label for="name" class="block text-sm mb-1">Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                    value="{{ $user->name ?? '' }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="email" class="block text-sm mb-1">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    value="{{ $user->email ?? '' }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="password" class="block text-sm mb-1">
                    Password
                    @if (isset($user))
                        <span class="text-gray-400 font-normal">(leave blank to keep current password)</span>
                    @endif
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    @if (! isset($user)) required @endif
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm mb-1">Confirm password</label>
                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    @if (! isset($user)) required @endif
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div id="form-errors" class="hidden text-sm text-red-600 space-y-1"></div>

            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black">
                    Save
                </button>
                <a
                    href="{{ route('users.list') }}"
                    class="px-4 py-2 border border-gray-300 rounded-md text-sm hover:bg-gray-100"
                >
                    Cancel
                </a>
            </div>
        </form>
    </div>
</body>
</html>
