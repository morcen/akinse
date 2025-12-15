<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\EntryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('entries', [EntryController::class, 'index'])
        ->name('entries');
    
    Route::post('entry-payments', [\App\Http\Controllers\EntryPaymentController::class, 'store'])
        ->name('entry-payments.store');
    
    Route::get('categories', [CategoryController::class, 'categories'])
        ->name('categories');
    
    // Search route must be defined BEFORE apiResource to avoid route model binding conflicts
    Route::get('categories/search', [CategoryController::class, 'search'])
        ->name('categories.search');
    
    Route::apiResource('categories', CategoryController::class)->except(['index']);
    Route::get('categories/{category}/entries-and-payments', [CategoryController::class, 'entriesAndPayments'])
        ->name('categories.entries-and-payments');
    Route::get('categories/grouped/entries', [CategoryController::class, 'groupedEntries'])
        ->name('categories.grouped-entries');
});

require __DIR__.'/settings.php';
