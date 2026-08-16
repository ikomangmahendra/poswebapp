<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in - {{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 text-gray-900 p-6 lg:p-10">
    <div class="max-w-sm mx-auto mt-20">
        <h1 class="text-2xl font-semibold mb-6 text-center">{{ config('app.name', 'Laravel') }}</h1>

        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="bg-white border border-gray-200 rounded-md p-4 space-y-4"
        >
            @csrf

            <div>
                <label for="email" class="block text-sm mb-1">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    autofocus
                    value="{{ old('email') }}"
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            <div>
                <label for="password" class="block text-sm mb-1">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring focus:border-blue-300"
                >
            </div>

            @if ($errors->any())
                <div class="text-sm text-red-600 space-y-1">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <button
                type="submit"
                class="w-full px-4 py-2 bg-gray-900 text-white rounded-md text-sm hover:bg-black"
            >
                Log in
            </button>
        </form>
    </div>
</body>
</html>
