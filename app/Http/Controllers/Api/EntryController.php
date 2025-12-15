<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEntryRequest;
use App\Http\Requests\UpdateEntryRequest;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Enums\EntryTypesEnum;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EntryController extends Controller
{
    /**
     * Display listing of entries grouped by date.
     */
    public function index(Request $request): JsonResponse
    {
        // This endpoint should only be used for API calls, not Inertia requests
        if ($request->header('X-Inertia')) {
            abort(400, 'This endpoint is for API calls only. Use /entries/{group} for Inertia navigation.');
        }

        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$request->has('date_from') || !$request->has('date_to')) {
            // Default date range: ±3 days from current date
            $today = Carbon::today();
            $dateFrom = $today->copy()->subDays(3)->toDateString();
            $dateTo = $today->copy()->addDays(3)->toDateString();
        } else {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
        }

        $query = Entry::where('user_id', $request->user()->id)
            ->with(['category']);

        $query->where('date', '>=', $dateFrom)
            ->where('date', '<=', $dateTo);

        // Apply optional filters
        if ($request->has('type') && $request->type !== '' && $request->type !== null) {
            $query->where('type', $request->type);
        }

        // Handle multiple category IDs (can be array or single value)
        if ($request->has('category_id')) {
            $categoryIds = $request->input('category_id');
            if (is_array($categoryIds) && !empty($categoryIds)) {
                // Filter out empty values and ensure all are numeric
                $categoryIds = array_filter(array_map('intval', $categoryIds));
                if (!empty($categoryIds)) {
                    $query->whereIn('category_id', $categoryIds);
                }
            } elseif ($categoryIds !== '' && $categoryIds !== null) {
                // Single category ID (backward compatibility)
                $query->where('category_id', $categoryIds);
            }
        }

        // Get all entries (no pagination for grouped view)
        $entries = $query->oldest('date')
            ->oldest('created_at')
            ->get();

        // Transform entries
        $entries->transform(function ($entry) {
            if ($entry->date instanceof Carbon) {
                $entry->setAttribute('date', $entry->date->toDateString());
            }
            $entry->setAttribute('total_paid', $entry->totalPaid);
            return $entry;
        });

        // Group entries
        $groupedEntries = [];

        // Group entries by date
        $grouped = $entries->groupBy(function (Entry $entry) {
            $date = $entry->date instanceof Carbon ? $entry->date : Carbon::parse($entry->date);
            return $date->format('Y-m-d');
        });

        // Generate all dates in the range
        $startDate = Carbon::parse($dateFrom);
        $endDate = Carbon::parse($dateTo);
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->format('Y-m-d');
            $groupEntries = $grouped->get($dateString, collect());

            // Calculate totals
            $totalPayable = $groupEntries->where('type', EntryTypesEnum::EXPENSE)->sum('amount');
            $totalPayment = $groupEntries->sum(fn ($e) => $e->totalPaid);
            $totalRemaining = $totalPayable - $totalPayment;
            $totalIncome = $groupEntries->where('type', EntryTypesEnum::INCOME)->sum('amount');

            $groupedEntries[] = [
                'groupKey' => $dateString,
                'groupLabel' => $currentDate->format('M d, Y'),
                'entries' => $groupEntries->values()->all(),
                'totalPayable' => $totalPayable,
                'totalPayment' => $totalPayment,
                'totalRemaining' => $totalRemaining,
                'totalIncome' => $totalIncome,
            ];

            $currentDate->addDay();
        }

        // Return category_id as array for consistency
        $categoryIdFilter = $request->input('category_id');
        if (!is_array($categoryIdFilter) && $categoryIdFilter !== '' && $categoryIdFilter !== null) {
            $categoryIdFilter = [$categoryIdFilter];
        } elseif (!is_array($categoryIdFilter)) {
            $categoryIdFilter = [];
        }

        return response()->json([
            'groupedEntries' => $groupedEntries,
            'filters' => [
                'type' => $request->get('type', ''),
                'category_id' => $categoryIdFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Store a newly created entry in storage.
     */
    public function store(StoreEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // Handle category creation if category_name is provided
        $categoryId = $validated['category_id'] ?? null;

        if (!$categoryId && isset($validated['category_name'])) {
            $categoryName = trim($validated['category_name']);

            if (empty($categoryName)) {
                abort(422, 'Category name cannot be empty.');
            }

            // Check if category already exists for this user (case-insensitive)
            $category = Category::where('user_id', $user->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($categoryName)])
                ->first();

            if (!$category) {
                // Create new category
                $category = Category::create([
                    'user_id' => $user->id,
                    'name' => $categoryName,
                ]);
            }

            $categoryId = $category->id;
        }

        $entry = Entry::create([
            'user_id' => $user->id,
            'category_id' => $categoryId,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
        ]);

        $entry->load('category');

        // Format date to Y-m-d format for consistent frontend handling
        if ($entry->date instanceof Carbon) {
            $entry->setAttribute('date', Carbon::parse($entry->date)->format('Y-m-d'));
        }

        return response()->json($entry, 201);
    }

    /**
     * Update the specified entry in storage.
     */
    public function update(UpdateEntryRequest $request, Entry $entry): JsonResponse
    {
        // Ensure the entry belongs to the authenticated user
        if ($entry->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validated();
        $user = $request->user();

        // Handle category creation if category_name is provided
        $categoryId = $validated['category_id'] ?? null;

        if (!$categoryId && isset($validated['category_name'])) {
            $categoryName = trim($validated['category_name']);

            if (empty($categoryName)) {
                abort(422, 'Category name cannot be empty.');
            }

            // Check if category already exists for this user (case-insensitive)
            $category = Category::where('user_id', $user->id)
                ->whereRaw('LOWER(name) = ?', [strtolower($categoryName)])
                ->first();

            if (!$category) {
                // Create new category
                $category = Category::create([
                    'user_id' => $user->id,
                    'name' => $categoryName,
                ]);
            }

            $categoryId = $category->id;
        }

        $entry->update([
            'category_id' => $categoryId,
            'type' => $validated['type'],
            'amount' => $validated['amount'],
            'date' => $validated['date'],
            'description' => $validated['description'] ?? null,
        ]);

        $entry->load('category');

        // Format date to Y-m-d format for consistent frontend handling
        if ($entry->date instanceof Carbon) {
            $entry->setAttribute('date', Carbon::parse($entry->date)->format('Y-m-d'));
        }

        return response()->json($entry);
    }

    /**
     * Display the specified entry.
     */
    public function show(Request $request, Entry $entry): JsonResponse
    {
        // Ensure the entry belongs to the authenticated user
        if ($entry->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $entry->load('category');

        // Format date to Y-m-d format for consistent frontend handling
        if ($entry->date instanceof Carbon) {
            $entry->setAttribute('date', Carbon::parse($entry->date)->format('Y-m-d'));
        }

        return response()->json($entry);
    }

    /**
     * Remove the specified entry from storage.
     */
    public function destroy(Request $request, Entry $entry): JsonResponse
    {
        // Ensure the entry belongs to the authenticated user
        if ($entry->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action.');
        }

        $entry->delete();

        return response()->json(['message' => 'Entry deleted successfully'], 200);
    }
}
