<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEntryRequest;
use App\Http\Requests\UpdateEntryRequest;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Enums\EntryTypesEnum;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntryController extends Controller
{
    /**
     * Display a listing of the entries.
     */
    public function index(Request $request): JsonResponse
    {
        $entries = Entry::where('user_id', $request->user()->id)
            ->with(['category'])
            ->oldest('date')
            ->oldest('created_at')
            ->paginate(15);

        // Format dates to Y-m-d format for consistent frontend handling
        $entries->getCollection()->transform(function ($entry) {
            if ($entry->date instanceof Carbon) {
                $entry->setAttribute('date', $entry->date->toDateString());
            }
            $entry->setAttribute('total_paid', $entry->totalPaid);
            return $entry;
        });

        return response()->json($entries);
    }

    /**
     * Display the entries page with all entries.
     */
    public function entries(Request $request): Response
    {
        $query = Entry::where('user_id', $request->user()->id)
            ->with(['category']);

        // Apply filters
        if ($request->has('type') && $request->type !== '' && $request->type !== null) {
            $query->where('type', $request->type);
        }

        if ($request->has('category_id') && $request->category_id !== '' && $request->category_id !== null) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('date_from') && $request->date_from !== '' && $request->date_from !== null) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to !== '' && $request->date_to !== null) {
            $query->where('date', '<=', $request->date_to);
        }

        // Sort by date ASC (oldest first) by default
        $entries = $query->oldest('date')
            ->oldest('created_at')
            ->paginate(15)
            ->withQueryString(); // Preserve query parameters in pagination links

        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $entries->getCollection()->transform(function ($entry) {
            $entry->setAttribute('total_paid', $entry->totalPaid);
            $entry->setAttribute('is_paid', $entry->totalPaid >= $entry->amount);
            return $entry;
        });

        return Inertia::render('Entries', [
            'entries' => $entries,
            'categories' => $categories,
            'filters' => [
                'type' => $request->get('type', ''),
                'category_id' => $request->get('category_id', ''),
                'date_from' => $request->get('date_from', ''),
                'date_to' => $request->get('date_to', ''),
            ],
        ]);
    }

    /**
     * Display grouped entries page.
     */
    public function grouped(Request $request, ?string $group = null): Response|RedirectResponse
    {
        // Default to 'date' if not provided or invalid
        if (!$group || !in_array($group, ['date', 'category'])) {
            $group = 'date';
        }

        if (!$request->has('date_from') || !$request->has('date_to')) {
            // Default date range: ±3 days from current date
            $today = Carbon::today();
            $dateFrom = $today->copy()->subDays(3)->toDateString();
            $dateTo = $today->copy()->addDays(3)->toDateString();

            return redirect()->route('entries.grouped', ['group' => $group, 'date_from' => $dateFrom, 'date_to' => $dateTo]);
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

        if ($request->has('category_id') && $request->category_id !== '' && $request->category_id !== null) {
            $query->where('category_id', $request->category_id);
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
        
        if ($group === 'date') {
            $grouped = $entries->groupBy('date');
            foreach ($grouped as $date => $groupEntries) {
                // total payable is the sum of the amounts of the expense entries
                $totalPayable = $groupEntries->where('type', EntryTypesEnum::EXPENSE)->sum('amount');
                
                $totalPayment = $groupEntries->sum(fn($e) => $e->totalPaid);
                $totalRemaining = $totalPayable - $totalPayment;
                
                $totalIncome = $groupEntries->where('type', EntryTypesEnum::INCOME)->sum('amount');
                $groupedEntries[] = [
                    'groupKey' => $date,
                    'groupLabel' => Carbon::parse($date)->format('M d, Y'),
                    'entries' => $groupEntries->values()->all(),
                    'totalPayable' => $totalPayable,
                    'totalPayment' => $totalPayment,
                    'totalRemaining' => $totalRemaining,
                    'totalIncome' => $totalIncome,
                ];
            }
            // Sort by date ascending
            usort($groupedEntries, function ($a, $b) {
                return strcmp($a['groupKey'], $b['groupKey']);
            });
        } else {
            $grouped = $entries->groupBy(function ($entry) {
                return $entry->category ? $entry->category->name : 'Uncategorized';
            });
            foreach ($grouped as $categoryName => $groupEntries) {
                $totalPayable = $groupEntries->where('type', EntryTypesEnum::EXPENSE)->sum('amount');
                $totalPayment = $groupEntries->sum(fn($e) => $e->totalPaid);
                $totalRemaining = $totalPayable - $totalPayment;
                $totalIncome = $groupEntries->where('type', EntryTypesEnum::INCOME)->sum('amount');

                $groupedEntries[] = [
                    'groupKey' => $categoryName,
                    'groupLabel' => $categoryName,
                    'entries' => $groupEntries->sortBy('date')->values()->all(),
                    'totalPayable' => $totalPayable,
                    'totalPayment' => $totalPayment,
                    'totalRemaining' => $totalRemaining,
                    'totalIncome' => $totalIncome,
                ];
            }
            // Sort by category name
            usort($groupedEntries, function ($a, $b) {
                return strcmp($a['groupLabel'], $b['groupLabel']);
            });
        }

        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('GroupedEntries', [
            'groupedEntries' => $groupedEntries,
            'group' => $group,
            'categories' => $categories,
            'filters' => [
                'type' => $request->get('type', ''),
                'category_id' => $request->get('category_id', ''),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Store a newly created entry in storage.
     */
    public function store(StoreEntryRequest $request): RedirectResponse|JsonResponse
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

        // If it's a JSON API request (not Inertia), return JSON
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($entry, 201);
        }

        // For Inertia requests, redirect back to the previous page (or entries page as fallback)
        $previousUrl = $request->header('Referer');
        if ($previousUrl && str_contains($previousUrl, '/entries/grouped')) {
            return redirect($previousUrl)->with('success', 'Entry created successfully');
        }
        return redirect()->route('entries')->with('success', 'Entry created successfully');
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
     * Update the specified entry in storage.
     */
    public function update(UpdateEntryRequest $request, Entry $entry): RedirectResponse|JsonResponse
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

        // If it's a JSON API request (not Inertia), return JSON
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json($entry);
        }

        // For Inertia requests, redirect back to the previous page (or entries page as fallback)
        $previousUrl = $request->header('Referer');
        if ($previousUrl && str_contains($previousUrl, '/entries/grouped')) {
            return redirect($previousUrl)->with('success', 'Entry updated successfully');
        }
        return redirect()->route('entries')->with('success', 'Entry updated successfully');
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
