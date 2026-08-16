<?php

use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/categories', function () {
    return view('categories.index');
})->name('categories.list');

Route::get('/categories/create', function () {
    return view('categories.form');
})->name('categories.create');

Route::get('/categories/{category}/edit', function (Category $category) {
    return view('categories.form', ['category' => $category]);
})->name('categories.edit');
