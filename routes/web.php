<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
})->name('catalog');

Route::get('/how-it-works', function () {
    return view('app');
})->name('how-it-works');

Route::get('/pricing', function () {
    return view('app');
})->name('pricing');
