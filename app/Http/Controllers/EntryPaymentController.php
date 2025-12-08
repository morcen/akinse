<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEntryPaymentRequest;
use App\Models\Entry;
use App\Models\EntryPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntryPaymentController extends Controller
{
    /**
     * Store a newly created entry payment in storage.
     */
    public function store(StoreEntryPaymentRequest $request): RedirectResponse|JsonResponse
    {
        // Ensure the entry belongs to the authenticated user
        $entry = Entry::findOrFail($request->validated()['entry_id']);
        
        if ($entry->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $payment = EntryPayment::create($request->validated());

        // If it's a JSON API request (not Inertia), return JSON
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($payment, 201);
        }

        // For Inertia requests, redirect back to the previous page (or entries page as fallback)
        $previousUrl = $request->header('Referer');
        if ($previousUrl && str_contains($previousUrl, '/entries/grouped')) {
            return redirect($previousUrl)->with('success', 'Payment recorded successfully');
        }
        return redirect()->route('entries')->with('success', 'Payment recorded successfully');
    }
}
