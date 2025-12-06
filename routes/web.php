<?php

use App\Http\Controllers\EntryController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::get('entries', [EntryController::class, 'entries'])
        ->middleware(['verified'])
        ->name('entries');
    
    Route::apiResource('entries', EntryController::class)->except(['index']);
    Route::post('entry-payments', [\App\Http\Controllers\EntryPaymentController::class, 'store'])
        ->name('entry-payments.store');
});

require __DIR__.'/settings.php';
