<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

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
