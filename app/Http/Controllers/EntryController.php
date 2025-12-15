<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EntryController extends Controller
{
    /**
     * Display a listing of the entries.
     */
    public function index(Request $request, ?string $group = null): Response
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
        } else {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
        }

        $categories = Category::where('user_id', $request->user()->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('GroupedEntries', [
            'group' => $group,
            'categories' => $categories,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
