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

Route::middleware(['auth'])->group(function () {
    Route::get('entries', [EntryController::class, 'entries'])
        ->middleware(['verified'])
        ->name('entries');
    
    // Grouped entries route must be defined BEFORE apiResource to avoid route model binding conflicts
    Route::get('entries/grouped/{group?}', [EntryController::class, 'grouped'])
        ->middleware(['verified'])
        ->where('group', 'date|category')
        ->name('entries.grouped');

    Route::get('entries/{group?}', [EntryController::class, 'index'])
        ->middleware(['verified'])
        ->where('group', 'date|category')
        ->name('entries.index');
    
    Route::apiResource('entries', EntryController::class)->except(['index']);
    Route::post('entry-payments', [\App\Http\Controllers\EntryPaymentController::class, 'store'])
        ->name('entry-payments.store');
    
    Route::get('categories', [CategoryController::class, 'categories'])
        ->middleware(['verified'])
        ->name('categories');
    
    // Search route must be defined BEFORE apiResource to avoid route model binding conflicts
    Route::get('categories/search', [CategoryController::class, 'search'])
        ->name('categories.search');
    
    Route::apiResource('categories', CategoryController::class)->except(['index']);
    Route::get('categories/{category}/entries-and-payments', [CategoryController::class, 'entriesAndPayments'])
        ->name('categories.entries-and-payments');
});

require __DIR__.'/settings.php';
