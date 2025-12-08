<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { entries as entriesRoute } from '@/routes';
import { destroy } from '@/routes/entries';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Filter, X, Plus, CreditCard, ChevronDown, Layers } from 'lucide-vue-next';
import AddEntryDialog from '@/components/AddEntryDialog.vue';
import ViewEntryDialog from '@/components/ViewEntryDialog.vue';
import EditEntryDialog from '@/components/EditEntryDialog.vue';
import DeleteEntryDialog from '@/components/DeleteEntryDialog.vue';
import RecordPaymentDialog from '@/components/RecordPaymentDialog.vue';

interface Entry {
    id: number;
    type: 'income' | 'expense';
    amount: string;
    date: string;
    description: string | null;
    category: {
        id: number;
        name: string;
    } | null;
    total_paid?: string | number;
}

interface Category {
    id: number;
    name: string;
}

interface Props {
    entries: {
        data: Entry[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    categories: Category[];
    filters?: {
        type: string;
        category_id: string;
        date_from: string;
        date_to: string;
    };
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({
        type: '',
        category_id: '',
        date_from: '',
        date_to: '',
    }),
    entries: () => ({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    }),
    categories: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Entries',
        href: entriesRoute().url,
    },
];

// Filter state
const filterType = ref(props.filters.type || '');
const filterCategoryId = ref(props.filters.category_id || '');
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');

// Apply filters
const applyFilters = () => {
    router.get(entriesRoute().url, {
        type: filterType.value || undefined,
        category_id: filterCategoryId.value || undefined,
        date_from: filterDateFrom.value || undefined,
        date_to: filterDateTo.value || undefined,
        page: 1, // Reset to first page when filters change
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Clear all filters
const clearFilters = () => {
    filterType.value = '';
    filterCategoryId.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    applyFilters();
};

// Check if any filters are active
const hasActiveFilters = computed(() => {
    return filterType.value !== '' ||
           filterCategoryId.value !== '' ||
           filterDateFrom.value !== '' ||
           filterDateTo.value !== '';
});

// Filter collapsible state - open by default if filters are active
const filtersOpen = ref(
    (props.filters?.type || '') !== '' ||
    (props.filters?.category_id || '') !== '' ||
    (props.filters?.date_from || '') !== '' ||
    (props.filters?.date_to || '') !== ''
);

// Grouping state
const groupBy = ref<'date' | 'category' | null>(null);

// Sync filter refs when props change (e.g., browser back/forward)
watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        filterType.value = newFilters.type || '';
        filterCategoryId.value = newFilters.category_id || '';
        filterDateFrom.value = newFilters.date_from || '';
        filterDateTo.value = newFilters.date_to || '';
    }
}, { immediate: true });


// Autocomplete state for category
const categoryInput = ref('');
const showSuggestions = ref(false);
const selectedCategoryId = ref<number | null>(null);
const categoryInputRef = ref<HTMLElement | null>(null);
const categoryDropdownRef = ref<HTMLElement | null>(null);

// Datepicker helper functions
// Helper to format date as YYYY-MM-DD in local time (not UTC)
const formatDateLocal = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Helper to parse YYYY-MM-DD string as local date (not UTC)
// Also handles ISO 8601 format (YYYY-MM-DDTHH:mm:ss) by extracting just the date part
const parseDateLocal = (dateString: string | null | undefined): Date => {
    if (!dateString) {
        return new Date();
    }
    
    // Extract just the date part (YYYY-MM-DD) if it's in ISO format
    const datePart = dateString.split('T')[0].split(' ')[0];
    const [year, month, day] = datePart.split('-').map(Number);
    
    // Validate the parsed values
    if (isNaN(year) || isNaN(month) || isNaN(day)) {
        console.warn('Invalid date string:', dateString);
        return new Date();
    }
    
    return new Date(year, month - 1, day);
};

// Datepicker state
const showDatepicker = ref(false);
const dateInput = ref(formatDateLocal(new Date()));
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
const datepickerRef = ref<HTMLElement | null>(null);
const dateInputRef = ref<HTMLElement | null>(null);

// Format date for display
const formattedDate = computed(() => {
    if (!dateInput.value) return '';
    const date = parseDateLocal(dateInput.value);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
});

const filteredCategories = computed(() => {
    if (!categoryInput.value.trim()) {
        return props.categories;
    }
    const searchTerm = categoryInput.value.toLowerCase();
    return props.categories.filter((category) =>
        category.name.toLowerCase().includes(searchTerm)
    );
});

const selectCategory = (category: Category) => {
    categoryInput.value = category.name;
    selectedCategoryId.value = category.id;
    showSuggestions.value = false;
};

const clearCategory = () => {
    categoryInput.value = '';
    selectedCategoryId.value = null;
    showSuggestions.value = false;
};

const handleCategoryInput = () => {
    showSuggestions.value = true;
    
    // Check if the input exactly matches an existing category
    const exactMatch = props.categories.find(
        (cat) => cat.name.toLowerCase() === categoryInput.value.toLowerCase().trim()
    );
    
    if (exactMatch) {
        selectedCategoryId.value = exactMatch.id;
    } else {
        selectedCategoryId.value = null;
    }
};

// Datepicker functions
const getDaysInMonth = (month: number, year: number) => {
    return new Date(year, month + 1, 0).getDate();
};

const getFirstDayOfMonth = (month: number, year: number) => {
    return new Date(year, month, 1).getDay();
};

const getCalendarDays = computed(() => {
    const daysInMonth = getDaysInMonth(currentMonth.value, currentYear.value);
    const firstDay = getFirstDayOfMonth(currentMonth.value, currentYear.value);
    const days: (number | null)[] = [];
    
    // Add empty cells for days before the first day of the month
    for (let i = 0; i < firstDay; i++) {
        days.push(null);
    }
    
    // Add days of the month
    for (let i = 1; i <= daysInMonth; i++) {
        days.push(i);
    }
    
    return days;
});

const getMonthName = (month: number) => {
    const date = new Date(2000, month, 1);
    return date.toLocaleDateString('en-US', { month: 'long' });
};

const selectDate = (day: number | null) => {
    if (day === null) return;
    
    const selectedDate = new Date(currentYear.value, currentMonth.value, day);
    dateInput.value = formatDateLocal(selectedDate);
    showDatepicker.value = false;
};

// Update current month/year when dateInput changes
watch(dateInput, (newDate) => {
    if (newDate) {
        const date = parseDateLocal(newDate);
        currentMonth.value = date.getMonth();
        currentYear.value = date.getFullYear();
    }
});

const previousMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
};

const isToday = (day: number | null) => {
    if (day === null) return false;
    const today = new Date();
    return (
        day === today.getDate() &&
        currentMonth.value === today.getMonth() &&
        currentYear.value === today.getFullYear()
    );
};

const isSelected = (day: number | null) => {
    if (day === null || !dateInput.value) return false;
    const selected = parseDateLocal(dateInput.value);
    return (
        day === selected.getDate() &&
        currentMonth.value === selected.getMonth() &&
        currentYear.value === selected.getFullYear()
    );
};

const goToToday = () => {
    const today = new Date();
    currentMonth.value = today.getMonth();
    currentYear.value = today.getFullYear();
    dateInput.value = formatDateLocal(today);
    showDatepicker.value = false;
};

// Handle click outside to close datepicker
const handleClickOutside = (event: MouseEvent) => {
    if (!showDatepicker.value) return;
    
    const target = event.target as HTMLElement;
    const datepickerElement = datepickerRef.value;
    const inputElement = dateInputRef.value;
    
    if (
        datepickerElement &&
        inputElement &&
        !datepickerElement.contains(target) &&
        !inputElement.contains(target)
    ) {
        showDatepicker.value = false;
    }
};

// Handle click outside to close category dropdown
const handleCategoryDropdownClickOutside = (event: MouseEvent) => {
    if (!showSuggestions.value) return;
    
    const target = event.target as HTMLElement;
    const dropdownElement = categoryDropdownRef.value;
    const inputElement = categoryInputRef.value;
    
    if (
        dropdownElement &&
        inputElement &&
        !dropdownElement.contains(target) &&
        !inputElement.contains(target)
    ) {
        showSuggestions.value = false;
    }
};

// Prevent blur when clicking inside datepicker
const handleDatepickerMouseDown = (event: MouseEvent) => {
    event.preventDefault();
};

let routerUnsubscribe: (() => void) | null = null;

onMounted(() => {
    // Watch for Inertia navigation completion to reopen modal after "Save and Add New"
    routerUnsubscribe = router.on('finish', () => {
        // Check if we should reopen modal after redirect (for "Save and Add New")
        if (sessionStorage.getItem('saveAndAddNew') === 'true') {
            sessionStorage.removeItem('saveAndAddNew');
            // Small delay to ensure DOM is updated
            setTimeout(() => {
                addDialogOpen.value = true;
            }, 100);
        }
    });
    
    // Check if we should reopen modal after page load (for "Save and Add New")
    if (sessionStorage.getItem('saveAndAddNew') === 'true') {
        sessionStorage.removeItem('saveAndAddNew');
        // Small delay to ensure page is fully loaded
        setTimeout(() => {
            addDialogOpen.value = true;
        }, 100);
    }
});

onUnmounted(() => {
    if (routerUnsubscribe) {
        routerUnsubscribe();
    }
});

const formatCurrency = (amount: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP',
    }).format(parseFloat(amount));
};

const formatDate = (date: string | null | undefined) => {
    if (!date) {
        return '';
    }
    
    try {
        const year = parseDateLocal(date).getFullYear();
        
        // check if year is current year
        if (year === new Date().getFullYear()) {
            return parseDateLocal(date).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
            });
        }
        
        return parseDateLocal(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch (error) {
        console.error('Error formatting date:', date, error);
        return '';
    }
};

// Calculate remaining amount for an entry
const getRemainingAmount = (entry: Entry): number => {
    const entryAmount = parseFloat(entry.amount);
    const totalPaid = parseFloat(String(entry.total_paid || 0));
    return Math.max(0, entryAmount - totalPaid);
};

// Check if entry is fully paid
const isFullyPaid = (entry: Entry): boolean => {
    return getRemainingAmount(entry) === 0;
};

// Check if entry is partially paid
const isPartiallyPaid = (entry: Entry): boolean => {
    const totalPaid = parseFloat(String(entry.total_paid || 0));
    return totalPaid > 0 && !isFullyPaid(entry);
};

// Get row background color class based on payment status and due date
const getRowColorClass = (entry: Entry): string => {
    if (entry.type === 'income') {
        return '';
    }
    // Fully paid is green
    if (isFullyPaid(entry)) {
        return 'bg-green-50 dark:bg-green-950/20 text-green-600 dark:text-green-400';
    }
    
    // Partially paid is yellow
    if (isPartiallyPaid(entry)) {
        return 'bg-yellow-50 dark:bg-yellow-950/20';
    }
    
    // Check due date
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const dueDate = new Date(entry.date);
    dueDate.setHours(0, 0, 0, 0);
    
    const daysUntilDue = Math.ceil((dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    
    // Overdue is red
    if (daysUntilDue < 0) {
        return 'bg-red-50 dark:bg-red-950/20';
    }
    
    // Due in 3 days or less is orange
    if (daysUntilDue <= 3) {
        return 'bg-orange-50 dark:bg-orange-950/20';
    }
    
    // Everything else uses default background
    return '';
};

// Format remaining amount with sign (only show sign if amount is not zero)
const formatRemainingAmount = (entry: Entry): string => {
    const remaining = getRemainingAmount(entry);
    if (remaining === 0) {
        return formatCurrency('0');
    }
    return formatCurrency(String(remaining));
};

const getAmountColor = (entry: Entry) => {
    return entry.type === 'income'
        ? 'text-green-600 dark:text-green-400'
        : isFullyPaid(entry) 
            ? 'text-green-600 dark:text-green-400' 
            : 'text-red-600 dark:text-red-400';
};

const getTypeBadgeColor = (type: 'income' | 'expense') => {
    return type === 'income'
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
};

// Modal state
const addDialogOpen = ref(false);
const editDialogOpen = ref(false);
const viewDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const paymentDialogOpen = ref(false);

// Entry state
const viewingEntry = ref<Entry | null>(null);
const editingEntry = ref<Entry | null>(null);
const entryToDelete = ref<Entry | null>(null);
const entryForPayment = ref<Entry | null>(null);

// Open add entry modal
const openAddModal = () => {
    addDialogOpen.value = true;
};

// Open view modal
const viewEntry = (entry: Entry) => {
    viewingEntry.value = entry;
    viewDialogOpen.value = true;
};

// Open edit modal from view modal
const openEditFromView = () => {
    if (!viewingEntry.value) return;
    
    viewDialogOpen.value = false;
    editingEntry.value = viewingEntry.value;
    editDialogOpen.value = true;
};

// Open payment modal from view modal
const openPaymentFromView = () => {
    if (!viewingEntry.value) return;
    
    viewDialogOpen.value = false;
    entryForPayment.value = viewingEntry.value;
    paymentDialogOpen.value = true;
};

// Open edit modal directly
const editEntry = (entry: Entry) => {
    editingEntry.value = entry;
    editDialogOpen.value = true;
};

// Open delete confirmation dialog
const confirmDelete = (entry: Entry) => {
    entryToDelete.value = entry;
    deleteDialogOpen.value = true;
};

// Delete entry
const handleDelete = () => {
    if (!entryToDelete.value) return;
    
    router.delete(destroy.url({ entry: entryToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            entryToDelete.value = null;
        },
    });
};

// Open payment modal
const openPaymentModal = (entry: Entry) => {
    entryForPayment.value = entry;
    paymentDialogOpen.value = true;
};

// Grouped entries computed property
interface GroupedEntry {
    groupKey: string;
    groupLabel: string;
    entries: Entry[];
    totalPayable: number;
    totalPayment: number;
    totalRemaining: number;
}

// Helper function to calculate totals for a group of entries
const calculateGroupTotals = (entries: Entry[]) => {
    let totalPayable = 0;
    let totalPayment = 0;
    let totalRemaining = 0;

    entries.forEach((entry) => {
        const amount = parseFloat(entry.amount);
        const paid = parseFloat(String(entry.total_paid || 0));
        const remaining = getRemainingAmount(entry);

        totalPayable += amount;
        totalPayment += paid;
        totalRemaining += remaining;
    });

    return {
        totalPayable,
        totalPayment,
        totalRemaining,
    };
};

const groupedEntries = computed<GroupedEntry[] | null>(() => {
    if (!groupBy.value || !props.entries?.data || props.entries.data.length === 0) {
        return null;
    }

    const entries = props.entries.data;
    const groups = new Map<string, Entry[]>();

    // Helper function to sort entries by date ascending
    const sortEntriesByDate = (entries: Entry[]): Entry[] => {
        return [...entries].sort((a, b) => {
            const dateA = parseDateLocal(a.date);
            const dateB = parseDateLocal(b.date);
            return dateA.getTime() - dateB.getTime();
        });
    };

    if (groupBy.value === 'date') {
        // Group by date
        entries.forEach((entry) => {
            const dateKey = entry.date || 'No Date';
            if (!groups.has(dateKey)) {
                groups.set(dateKey, []);
            }
            groups.get(dateKey)!.push(entry);
        });

        // Convert to array and sort by date ascending (oldest first)
        return Array.from(groups.entries())
            .map(([dateKey, entries]) => {
                const sortedEntries = sortEntriesByDate(entries);
                const totals = calculateGroupTotals(sortedEntries);
                return {
                    groupKey: dateKey,
                    groupLabel: formatDate(dateKey),
                    entries: sortedEntries,
                    ...totals,
                };
            })
            .sort((a, b) => {
                // Sort by date ascending (oldest first)
                const dateA = parseDateLocal(a.groupKey);
                const dateB = parseDateLocal(b.groupKey);
                return dateA.getTime() - dateB.getTime();
            });
    } else if (groupBy.value === 'category') {
        // Group by category
        entries.forEach((entry) => {
            const categoryKey = entry.category?.name || 'Uncategorized';
            if (!groups.has(categoryKey)) {
                groups.set(categoryKey, []);
            }
            groups.get(categoryKey)!.push(entry);
        });

        // Convert to array and sort by category name, then sort entries within each group by date
        return Array.from(groups.entries())
            .map(([categoryKey, entries]) => {
                const sortedEntries = sortEntriesByDate(entries);
                const totals = calculateGroupTotals(sortedEntries);
                return {
                    groupKey: categoryKey,
                    groupLabel: categoryKey,
                    entries: sortedEntries,
                    ...totals,
                };
            })
            .sort((a, b) => a.groupLabel.localeCompare(b.groupLabel));
    }

    return null;
});
</script>

<template>
    <Head title="Entries" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Entries</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage your income and expenses
                    </p>
                </div>
            </div>

            <div class="relative flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                    <Button @click="openAddModal">
                        <Plus class="h-4 w-4 mr-2" />
                        Add Entry
                    </Button>
                    
                    <div class="flex items-center gap-2">
                        <!-- Group Button -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" type="button">
                                    <Layers class="h-4 w-4 mr-2" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    @click="groupBy = 'date'"
                                    :class="{ 'bg-accent': groupBy === 'date' }"
                                >
                                    Group by date
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    @click="groupBy = 'category'"
                                    :class="{ 'bg-accent': groupBy === 'category' }"
                                >
                                    Group by category
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-if="groupBy !== null"
                                    @click="groupBy = null"
                                >
                                    Remove grouping
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        
                        <!-- Filters Collapsible -->
                        <Collapsible v-model:open="filtersOpen" class="flex items-center gap-2">
                            <CollapsibleTrigger as-child>
                                <Button variant="outline" type="button">
                                    <Filter class="h-4 w-4 mr-2" />
                                    <ChevronDown
                                        class="h-4 w-4 ml-2 transition-transform duration-200"
                                        :class="{ 'rotate-180': filtersOpen }"
                                    />
                                </Button>
                            </CollapsibleTrigger>
                            <Button
                                v-if="hasActiveFilters"
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="clearFilters"
                                class="h-9 text-xs"
                            >
                                <X class="h-3 w-3 mr-1" />
                                Clear
                            </Button>
                        </Collapsible>
                    </div>
                </div>
                
                <Collapsible v-model:open="filtersOpen" class="w-full">
                    <CollapsibleContent class="mt-0">
                        <div
                            class="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border"
                        >
                            <div class="grid gap-4 md:grid-cols-4">
                                <div class="grid gap-2">
                                    <Label for="filter-type">Type</Label>
                                    <select
                                        id="filter-type"
                                        v-model="filterType"
                                        @change="applyFilters"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="">All Types</option>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="filter-category">Category</Label>
                                    <select
                                        id="filter-category"
                                        v-model="filterCategoryId"
                                        @change="applyFilters"
                                        class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="">All Categories</option>
                                        <option
                                            v-for="category in categories"
                                            :key="category.id"
                                            :value="category.id"
                                        >
                                            {{ category.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="filter-date-from">Date From</Label>
                                    <Input
                                        id="filter-date-from"
                                        type="date"
                                        v-model="filterDateFrom"
                                        @change="applyFilters"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="filter-date-to">Date To</Label>
                                    <Input
                                        id="filter-date-to"
                                        type="date"
                                        v-model="filterDateTo"
                                        @change="applyFilters"
                                    />
                                </div>
                            </div>
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            </div>


            <div
                class="relative flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="border-b border-sidebar-border/70 bg-muted/50">
                            <tr>
                                <th
                                    v-if="groupBy !== 'date'"
                                    class="px-4 py-3 text-left text-sm font-medium text-muted-foreground"
                                >
                                    Date
                                </th>
                                <th
                                    v-if="groupBy !== 'category'"
                                    class="px-4 py-3 text-left text-sm font-medium text-muted-foreground"
                                >
                                    Category
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-sm font-medium text-muted-foreground"
                                >
                                    Amount
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-sm font-medium text-muted-foreground"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- No entries message -->
                            <tr
                                v-if="!entries?.data || entries.data.length === 0"
                                class="border-b border-sidebar-border/70"
                            >
                                <td
                                    :colspan="groupBy === 'date' || groupBy === 'category' ? 3 : 4"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No entries found. Start by adding your first
                                    entry.
                                </td>
                            </tr>
                            
                            <!-- Grouped entries -->
                            <template v-if="groupedEntries">
                                <template v-for="(group, groupIndex) in groupedEntries" :key="`group-${group.groupKey}-${groupIndex}`">
                                    <!-- Group header -->
                                    <tr class="bg-muted/30 border-b-2 border-sidebar-border/70">
                                        <td
                                            :colspan="groupBy === 'date' || groupBy === 'category' ? 3 : 4"
                                            class="px-4 py-3 text-sm font-semibold text-foreground"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    {{ group.groupLabel }}
                                                    <span class="ml-2 text-xs font-normal text-muted-foreground">
                                                        ({{ group.entries.length }} {{ group.entries.length === 1 ? 'entry' : 'entries' }})
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 text-xs flex-col">
                                                    <div class="flex items-center justify-end gap-1 text-muted-foreground">
                                                        Payable: <span class="text-red-600 dark:text-red-400">{{ formatCurrency(String(group.totalPayable)) }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-1 text-muted-foreground">
                                                        Paid: <span class="text-green-600 dark:text-green-400">{{ formatCurrency(String(group.totalPayment)) }}</span></div>
                                                    <div class="flex items-center gap-1 text-muted-foreground">
                                                        Remaining: <span :class="group.totalRemaining > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">{{ formatCurrency(String(group.totalRemaining)) }}</span></div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <!-- Group entries -->
                                    <tr
                                        v-for="entry in group.entries"
                                        :key="entry.id"
                                        @click="viewEntry(entry)"
                                        :class="[
                                            'border-b border-sidebar-border/70 transition-colors cursor-pointer',
                                            getRowColorClass(entry),
                                            !isFullyPaid(entry) && 'hover:opacity-80',
                                        ]"
                                    >
                                        <td v-if="groupBy !== 'date'" class="px-4 py-3 text-sm">
                                            {{ formatDate(entry.date) }}
                                        </td>
                                        <td v-if="groupBy !== 'category'" class="px-4 py-3 text-sm">
                                            {{ entry.category?.name ?? 'Uncategorized' }}
                                        </td>
                                        <td
                                            :class="[
                                                'px-4 py-3 text-right text-sm font-medium',
                                                getAmountColor(entry),
                                            ]"
                                        >
                                            {{ formatCurrency(String(entry.amount)) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-center gap-2">
                                                <button
                                                    type="button"
                                                    @click.stop="openPaymentModal(entry)"
                                                    class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                    title="Record payment"
                                                >
                                                    <CreditCard class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </template>
                            
                            <!-- Ungrouped entries (default view) -->
                            <template v-else>
                                <tr
                                    v-for="entry in entries?.data || []"
                                    :key="entry.id"
                                    @click="viewEntry(entry)"
                                    :class="[
                                        'border-b border-sidebar-border/70 transition-colors cursor-pointer',
                                        getRowColorClass(entry),
                                        !isFullyPaid(entry) && 'hover:opacity-80',
                                    ]"
                                >
                                    <td v-if="groupBy !== 'date'" class="px-4 py-3 text-sm">
                                        <div>{{ formatDate(entry.date) }}</div>
                                    </td>
                                    <td v-if="groupBy !== 'category'" class="px-4 py-3 text-sm">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 font-medium capitalize',
                                                getTypeBadgeColor(entry.type),
                                            ]"
                                        >{{ entry.category?.name ?? 'Uncategorized' }}</span>
                                    </td>
                                    <td
                                        :class="[
                                            'px-4 py-3 text-right text-sm font-medium',
                                            getAmountColor(entry),
                                        ]"
                                    >
                                        {{ formatCurrency(entry.amount) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                @click.stop="openPaymentModal(entry)"
                                                class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                                title="Record payment"
                                            >
                                                <CreditCard class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Entry Dialog -->
            <AddEntryDialog
                v-model:open="addDialogOpen"
                :categories="categories"
                @close="addDialogOpen = false"
            />

            <!-- View Entry Dialog -->
            <ViewEntryDialog
                v-model:open="viewDialogOpen"
                :entry="viewingEntry"
                @edit="openEditFromView"
                @record-payment="openPaymentFromView"
            />

            <!-- Edit Entry Dialog -->
            <EditEntryDialog
                v-model:open="editDialogOpen"
                :entry="editingEntry"
                :categories="categories"
                @close="editDialogOpen = false"
                @delete="confirmDelete(editingEntry!)"
            />

            <!-- Delete Confirmation Dialog -->
            <DeleteEntryDialog
                v-model:open="deleteDialogOpen"
                @confirm="handleDelete"
            />

            <!-- Record Payment Dialog -->
            <RecordPaymentDialog
                v-model:open="paymentDialogOpen"
                :entry="entryForPayment"
                @close="paymentDialogOpen = false"
            />

            <div
                v-if="entries?.last_page && entries.last_page > 1"
                class="flex items-center justify-between text-sm text-muted-foreground"
            >
                <div>
                    Showing {{ entries?.data?.length || 0 }} of {{ entries?.total || 0 }}
                    entries
                </div>
                <div class="flex gap-2">
                    <button
                        v-if="entries?.current_page && entries.current_page > 1"
                        @click="router.get(entriesRoute().url, {
                            type: filterType || undefined,
                            category_id: filterCategoryId || undefined,
                            date_from: filterDateFrom || undefined,
                            date_to: filterDateTo || undefined,
                            page: (entries?.current_page || 1) - 1,
                        }, { preserveState: true, preserveScroll: true })"
                        class="rounded-md border border-sidebar-border/70 px-3 py-1.5 hover:bg-muted"
                    >
                        Previous
                    </button>
                    <button
                        v-if="entries?.current_page && entries?.last_page && entries.current_page < entries.last_page"
                        @click="router.get(entriesRoute().url, {
                            type: filterType || undefined,
                            category_id: filterCategoryId || undefined,
                            date_from: filterDateFrom || undefined,
                            date_to: filterDateTo || undefined,
                            page: (entries?.current_page || 1) + 1,
                        }, { preserveState: true, preserveScroll: true })"
                        class="rounded-md border border-sidebar-border/70 px-3 py-1.5 hover:bg-muted"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

