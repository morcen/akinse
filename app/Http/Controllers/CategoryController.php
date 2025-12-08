<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Entry;
use App\Models\EntryPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::where('user_id', $request->user()->id)
            ->withCount('entries')
            ->orderBy('name')
            ->paginate(15);

        return response()->json($categories);
    }

    /**
     * Display the categories page with all categories.
     */
    public function categories(Request $request): Response
    {
        $categories = Category::where('user_id', $request->user()->id)
            ->withCount('entries')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Categories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Check if category already exists for this user (case-insensitive)
        $existingCategory = Category::where('user_id', $user->id)
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['name']))])
            ->first();

        if ($existingCategory) {
            abort(422, 'A category with this name already exists.');
        }

        $category = Category::create([
            'user_id' => $user->id,
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        $category->loadCount('entries');

        // If it's a JSON API request (not Inertia), return JSON
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($category, 201);
        }

        // For Inertia requests, redirect back to categories page to refresh the table
        return redirect()->route('categories');
    }

    /**
     * Display the specified category.
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        // Ensure the category belongs to the authenticated user
        if ($category->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $category->loadCount('entries');

        return response()->json($category);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse|JsonResponse
    {
        // Ensure the category belongs to the authenticated user
        if ($category->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validated();
        $user = $request->user();

        // Check if another category with the same name already exists for this user (case-insensitive)
        if (isset($validated['name'])) {
            $existingCategory = Category::where('user_id', $user->id)
                ->where('id', '!=', $category->id)
                ->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['name']))])
                ->first();

            if ($existingCategory) {
                abort(422, 'A category with this name already exists.');
            }
        }

        $category->update([
            'name' => isset($validated['name']) ? trim($validated['name']) : $category->name,
            'description' => $validated['description'] ?? $category->description,
        ]);

        $category->loadCount('entries');

        // If it's a JSON API request (not Inertia), return JSON
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($category);
        }

        // For Inertia requests, redirect back to categories page to refresh the table
        return redirect()->route('categories');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        // Ensure the category belongs to the authenticated user
        if ($category->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully'], 200);
    }

    /**
     * Search categories by query string.
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        
        // Only search if query is more than 2 characters
        if (strlen($query) < 3) {
            return response()->json([]);
        }
        
        $categories = Category::where('user_id', $request->user()->id)
            ->where('name', 'ILIKE', '%' . $query . '%')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);
        
        return response()->json($categories);
    }

    /**
     * Get all entries and payments for a category, combined and sorted by date.
     */
    public function entriesAndPayments(Request $request, Category $category): JsonResponse
    {
        // Ensure the category belongs to the authenticated user
        if ($category->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        // Get all entries for this category
        $entries = Entry::where('category_id', $category->id)
            ->where('user_id', $request->user()->id)
            ->get();

        // Get all payments for entries in this category
        $entryIds = $entries->pluck('id');
        $payments = EntryPayment::whereIn('entry_id', $entryIds)
            ->with('entry')
            ->get();

        // Combine entries and payments into a single collection
        $combined = collect();

        // Add entries as items
        foreach ($entries as $entry) {
            $entryDate = $entry->date;
            if ($entryDate instanceof Carbon) {
                $entryDate = $entryDate->toDateString();
            } elseif (is_string($entryDate)) {
                $entryDate = Carbon::parse($entryDate)->toDateString();
            }

            $combined->push([
                'type' => 'entry',
                'id' => $entry->id,
                'date' => $entryDate,
                'amount' => $entry->amount,
                'type_label' => $entry->type->value,
                'description' => $entry->description,
                'entry_id' => $entry->id,
            ]);
        }

        // Add payments as items
        foreach ($payments as $payment) {
            $paymentDate = $payment->date;
            if ($paymentDate instanceof Carbon) {
                $paymentDate = $paymentDate->toDateString();
            } elseif (is_string($paymentDate)) {
                $paymentDate = Carbon::parse($paymentDate)->toDateString();
            }

            $combined->push([
                'type' => 'payment',
                'id' => $payment->id,
                'date' => $paymentDate,
                'amount' => $payment->amount,
                'notes' => $payment->notes,
                'entry_id' => $payment->entry_id,
                'entry_description' => $payment->entry->description,
                'entry_type' => $payment->entry->type->value,
            ]);
        }

        // Sort by date ascending
        $sorted = $combined->sortBy('date')->values();

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'items' => $sorted,
        ]);
    }
}
