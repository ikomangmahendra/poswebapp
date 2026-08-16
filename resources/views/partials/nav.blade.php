<nav class="flex items-center justify-between gap-4 mb-6 text-sm">
    <div class="flex items-center gap-4">
        <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900 hover:underline">Dashboard</a>
        <a href="{{ route('categories.list') }}" class="text-gray-600 hover:text-gray-900 hover:underline">Categories</a>
        <a href="{{ route('products.list') }}" class="text-gray-600 hover:text-gray-900 hover:underline">Products</a>
        <a href="{{ route('transactions.list') }}" class="text-gray-600 hover:text-gray-900 hover:underline">Transactions</a>
        <a href="{{ route('users.list') }}" class="text-gray-600 hover:text-gray-900 hover:underline">Users</a>
    </div>

    <div class="flex items-center gap-3 text-gray-600">
        <span>{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="hover:text-gray-900 hover:underline">Log out</button>
        </form>
    </div>
</nav>
