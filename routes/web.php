<?php

use App\Http\Controllers\LoginController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', function () {
        return view('dashboard.index');
    })->name('dashboard');

    Route::get('/categories', function () {
        return view('categories.index');
    })->name('categories.list');

    Route::get('/categories/create', function () {
        return view('categories.form');
    })->name('categories.create');

    Route::get('/categories/{category}/edit', function (Category $category) {
        return view('categories.form', ['category' => $category]);
    })->name('categories.edit');

    Route::get('/products', function () {
        return view('products.index');
    })->name('products.list');

    Route::get('/products/create', function () {
        return view('products.form', ['categories' => Category::orderBy('name')->get()]);
    })->name('products.create');

    Route::get('/products/{product}/edit', function (Product $product) {
        return view('products.form', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
        ]);
    })->name('products.edit');

    Route::get('/transactions', function () {
        return view('transactions.index');
    })->name('transactions.list');

    Route::get('/transactions/create', function () {
        return view('transactions.create');
    })->name('transactions.create');

    Route::get('/transactions/{transaction}', function (Transaction $transaction) {
        return view('transactions.show', ['transaction' => $transaction]);
    })->name('transactions.detail');

    Route::get('/users', function () {
        return view('users.index');
    })->name('users.list');

    Route::get('/users/create', function () {
        return view('users.form');
    })->name('users.create');

    Route::get('/users/{user}/edit', function (User $user) {
        return view('users.form', ['user' => $user]);
    })->name('users.edit');
});
