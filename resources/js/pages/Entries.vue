<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { entries as entriesRoute } from '@/routes';
import { destroy, store as storeEntry, update as updateEntry } from '@/routes/entries';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch, withDefaults } from 'vue';
import { Edit, Trash2, Filter, X, Plus, CreditCard, ChevronDown } from 'lucide-vue-next';

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

// Prevent blur when clicking inside datepicker
const handleDatepickerMouseDown = (event: MouseEvent) => {
    event.preventDefault();
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('mousedown', handlePaymentDatepickerClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
    document.removeEventListener('mousedown', handlePaymentDatepickerClickOutside);
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

// Format remaining amount with sign (only show sign if amount is not zero)
const formatRemainingAmount = (entry: Entry): string => {
    const remaining = getRemainingAmount(entry);
    if (remaining === 0) {
        return formatCurrency('0');
    }
    return formatCurrency(String(remaining));
};

const getTypeColor = (type: 'income' | 'expense') => {
    return type === 'income'
        ? 'text-green-600 dark:text-green-400'
        : 'text-red-600 dark:text-red-400';
};

const getTypeBadgeColor = (type: 'income' | 'expense') => {
    return type === 'income'
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
};

// Form state for editing
const formType = ref('');
const formAmount = ref('');
const formDescription = ref('');

// Edit state
const editingEntry = ref<Entry | null>(null);
const isEditing = computed(() => editingEntry.value !== null);

// Modal state
const addDialogOpen = ref(false);
const editDialogOpen = ref(false);

// Delete confirmation dialog state
const deleteDialogOpen = ref(false);
const entryToDelete = ref<Entry | null>(null);

// Payment modal state
const paymentDialogOpen = ref(false);
const entryForPayment = ref<Entry | null>(null);
const paymentAmount = ref('');
const paymentDate = ref(formatDateLocal(new Date()));
const paymentNotes = ref('');
const paymentDatepickerOpen = ref(false);
const paymentCurrentMonth = ref(new Date().getMonth());
const paymentCurrentYear = ref(new Date().getFullYear());
const paymentDatepickerRef = ref<HTMLElement | null>(null);
const paymentDateInputRef = ref<HTMLElement | null>(null);

// Open add entry modal
const openAddModal = () => {
    clearEdit();
    addDialogOpen.value = true;
};

// Populate form with entry data for editing
const editEntry = (entry: Entry) => {
    editingEntry.value = entry;
    categoryInput.value = entry.category?.name ?? '';
    selectedCategoryId.value = entry.category?.id ?? null;
    
    // Ensure date is in YYYY-MM-DD format and update datepicker month/year
    const entryDate = entry.date ? parseDateLocal(entry.date) : new Date();
    dateInput.value = formatDateLocal(entryDate);
    currentMonth.value = entryDate.getMonth();
    currentYear.value = entryDate.getFullYear();
    
    formType.value = entry.type;
    formAmount.value = entry.amount;
    formDescription.value = entry.description ?? '';
    showDatepicker.value = false;
    
    // Open edit modal
    editDialogOpen.value = true;
};

// Clear edit state
const clearEdit = () => {
    editingEntry.value = null;
    categoryInput.value = '';
    selectedCategoryId.value = null;
    dateInput.value = formatDateLocal(new Date());
    formType.value = '';
    formAmount.value = '';
    formDescription.value = '';
    showDatepicker.value = false;
};

// Close add modal
const closeAddModal = () => {
    addDialogOpen.value = false;
    clearEdit();
};

// Close edit modal
const closeEditModal = () => {
    editDialogOpen.value = false;
    clearEdit();
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
    
    // Calculate remaining amount (entry amount - total paid)
    const entryAmount = parseFloat(entry.amount);
    const totalPaid = parseFloat(String(entry.total_paid || 0));
    const remainingAmount = Math.max(0, entryAmount - totalPaid);
    
    paymentAmount.value = remainingAmount > 0 ? remainingAmount.toFixed(2) : '';
    paymentDate.value = formatDateLocal(new Date());
    paymentNotes.value = '';
    paymentCurrentMonth.value = new Date().getMonth();
    paymentCurrentYear.value = new Date().getFullYear();
    paymentDatepickerOpen.value = false;
    paymentDialogOpen.value = true;
};

// Close payment modal
const closePaymentModal = () => {
    paymentDialogOpen.value = false;
    entryForPayment.value = null;
    paymentAmount.value = '';
    paymentDate.value = formatDateLocal(new Date());
    paymentNotes.value = '';
    paymentDatepickerOpen.value = false;
};

// Payment datepicker functions
const getPaymentCalendarDays = computed(() => {
    const daysInMonth = getDaysInMonth(paymentCurrentMonth.value, paymentCurrentYear.value);
    const firstDay = getFirstDayOfMonth(paymentCurrentMonth.value, paymentCurrentYear.value);
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

const selectPaymentDate = (day: number | null) => {
    if (day === null) return;
    
    const selectedDate = new Date(paymentCurrentYear.value, paymentCurrentMonth.value, day);
    paymentDate.value = formatDateLocal(selectedDate);
    paymentDatepickerOpen.value = false;
};

const paymentFormattedDate = computed(() => {
    if (!paymentDate.value) return '';
    const date = parseDateLocal(paymentDate.value);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
});

const isPaymentDateSelected = (day: number | null) => {
    if (day === null || !paymentDate.value) return false;
    const selected = parseDateLocal(paymentDate.value);
    return (
        day === selected.getDate() &&
        paymentCurrentMonth.value === selected.getMonth() &&
        paymentCurrentYear.value === selected.getFullYear()
    );
};

const isPaymentDateToday = (day: number | null) => {
    if (day === null) return false;
    const today = new Date();
    return (
        day === today.getDate() &&
        paymentCurrentMonth.value === today.getMonth() &&
        paymentCurrentYear.value === today.getFullYear()
    );
};

const previousPaymentMonth = () => {
    if (paymentCurrentMonth.value === 0) {
        paymentCurrentMonth.value = 11;
        paymentCurrentYear.value--;
    } else {
        paymentCurrentMonth.value--;
    }
};

const nextPaymentMonth = () => {
    if (paymentCurrentMonth.value === 11) {
        paymentCurrentMonth.value = 0;
        paymentCurrentYear.value++;
    } else {
        paymentCurrentMonth.value++;
    }
};

const goToPaymentToday = () => {
    const today = new Date();
    paymentCurrentMonth.value = today.getMonth();
    paymentCurrentYear.value = today.getFullYear();
    paymentDate.value = formatDateLocal(today);
    paymentDatepickerOpen.value = false;
};

// Handle click outside to close payment datepicker
const handlePaymentDatepickerClickOutside = (event: MouseEvent) => {
    if (!paymentDatepickerOpen.value) return;
    
    const target = event.target as HTMLElement;
    const datepickerElement = paymentDatepickerRef.value;
    const inputElement = paymentDateInputRef.value;
    
    if (
        datepickerElement &&
        inputElement &&
        !datepickerElement.contains(target) &&
        !inputElement.contains(target)
    ) {
        paymentDatepickerOpen.value = false;
    }
};

// Prevent blur when clicking inside payment datepicker
const handlePaymentDatepickerMouseDown = (event: MouseEvent) => {
    event.preventDefault();
};

// Watch payment date to update month/year
watch(paymentDate, (newDate) => {
    if (newDate) {
        const date = parseDateLocal(newDate);
        paymentCurrentMonth.value = date.getMonth();
        paymentCurrentYear.value = date.getFullYear();
    }
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
                    
                    <!-- Filters Collapsible -->
                    <Collapsible v-model:open="filtersOpen" class="flex items-center gap-2">
                        <CollapsibleTrigger as-child>
                            <Button variant="outline" type="button">
                                <Filter class="h-4 w-4 mr-2" />
                                Filters
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
                                    class="px-4 py-3 text-left text-sm font-medium text-muted-foreground"
                                >
                                    Date
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-sm font-medium text-muted-foreground"
                                >
                                    Type
                                </th>
                                <th
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
                            <tr
                                v-if="!entries?.data || entries.data.length === 0"
                                class="border-b border-sidebar-border/70"
                            >
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No entries found. Start by adding your first
                                    entry.
                                </td>
                            </tr>
                            <tr
                                v-for="entry in entries?.data || []"
                                :key="entry.id"
                                :class="[
                                    'border-b border-sidebar-border/70 transition-colors',
                                    isFullyPaid(entry)
                                        ? 'bg-muted/30 opacity-75'
                                        : 'hover:bg-muted/50',
                                ]"
                            >
                                <td class="px-4 py-3 text-sm">
                                    {{ formatDate(entry.date) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize',
                                            getTypeBadgeColor(entry.type),
                                        ]"
                                    >
                                        {{ entry.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    {{ entry.category?.name ?? 'Uncategorized' }}
                                </td>
                                <td
                                    :class="[
                                        'px-4 py-3 text-right text-sm font-medium',
                                        getTypeColor(entry.type),
                                    ]"
                                >
                                    {{ formatRemainingAmount(entry) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="openPaymentModal(entry)"
                                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="Record payment"
                                        >
                                            <CreditCard class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="editEntry(entry)"
                                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="Edit entry"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(entry)"
                                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete entry"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Entry Dialog -->
            <Dialog v-model:open="addDialogOpen">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Add Entry</DialogTitle>
                        <DialogDescription>
                            Create a new income or expense entry
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        :key="'create'"
                        v-bind="storeEntry.form()"
                        :reset-on-success="true"
                        :preserve-scroll="true"
                        @success="() => { clearCategory(); closeAddModal(); }"
                        class="grid gap-4 sm:grid-cols-2"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2 sm:col-span-1">
                            <Label for="add-type">Type</Label>
                            <select
                                id="add-type"
                                name="type"
                                v-model="formType"
                                required
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Select type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>

                        <div class="relative grid gap-2 sm:col-span-1">
                            <Label for="add-amount">Amount</Label>
                            <Input
                                id="add-amount"
                                type="number"
                                step="0.01"
                                min="0"
                                name="amount"
                                v-model="formAmount"
                                required
                                placeholder="0.00"
                            />
                            <InputError :message="errors.amount" />
                        </div>

                        <div class="relative grid gap-2 sm:col-span-2">
                            <Label for="add-category">Category</Label>
                            <div class="relative">
                                <Input
                                    id="add-category"
                                    v-model="categoryInput"
                                    @input="handleCategoryInput"
                                    @focus="showSuggestions = true"
                                    @blur="setTimeout(() => (showSuggestions = false), 200)"
                                    required
                                    placeholder="Type to search or create new category"
                                    autocomplete="off"
                                />
                                <!-- Hidden input for category_id if an existing category is selected -->
                                <input
                                    v-if="selectedCategoryId"
                                    type="hidden"
                                    name="category_id"
                                    :value="selectedCategoryId"
                                />
                                <!-- Hidden input for category_name if no existing category is selected -->
                                <input
                                    v-else
                                    type="hidden"
                                    name="category_name"
                                    :value="categoryInput.trim()"
                                />
                                <!-- Suggestions dropdown -->
                                <div
                                    v-if="showSuggestions && filteredCategories.length > 0"
                                    class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border border-sidebar-border/70 bg-card shadow-lg dark:border-sidebar-border"
                                >
                                    <ul class="py-1">
                                        <li
                                            v-for="category in filteredCategories"
                                            :key="category.id"
                                            @mousedown.prevent="selectCategory(category)"
                                            class="cursor-pointer px-4 py-2 text-sm hover:bg-muted"
                                        >
                                            {{ category.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <InputError :message="errors.category_name || errors.category_id" />
                        </div>

                        <div class="relative grid gap-2 sm:col-span-2">
                            <Label for="add-date">Date</Label>
                            <div class="relative">
                                <Input
                                    ref="dateInputRef"
                                    id="add-date"
                                    type="text"
                                    :model-value="formattedDate"
                                    @focus="showDatepicker = true"
                                    @click="showDatepicker = true"
                                    required
                                    placeholder="Select date"
                                    autocomplete="off"
                                    readonly
                                />
                                <!-- Hidden input for form submission with proper name -->
                                <input
                                    type="hidden"
                                    name="date"
                                    :value="dateInput"
                                />
                                <!-- Datepicker dropdown -->
                                <div
                                    ref="datepickerRef"
                                    v-if="showDatepicker"
                                    @mousedown="handleDatepickerMouseDown"
                                    class="absolute z-50 mt-1 w-[280px] rounded-md border border-sidebar-border/70 bg-card shadow-lg dark:border-sidebar-border"
                                >
                                    <div class="p-4">
                                        <!-- Month/Year header -->
                                        <div class="mb-4 flex items-center justify-between">
                                            <button
                                                type="button"
                                                @click="previousMonth"
                                                class="rounded-md p-1 hover:bg-muted"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 19l-7-7 7-7"
                                                    />
                                                </svg>
                                            </button>
                                            <div class="text-sm font-medium">
                                                {{ getMonthName(currentMonth) }} {{ currentYear }}
                                            </div>
                                            <button
                                                type="button"
                                                @click="nextMonth"
                                                class="rounded-md p-1 hover:bg-muted"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 5l7 7-7 7"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                        
                                        <!-- Day names header -->
                                        <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                                            <div>S</div>
                                            <div>M</div>
                                            <div>T</div>
                                            <div>W</div>
                                            <div>T</div>
                                            <div>F</div>
                                            <div>S</div>
                                        </div>
                                        
                                        <!-- Calendar grid -->
                                        <div class="grid grid-cols-7 gap-1">
                                            <button
                                                v-for="(day, index) in getCalendarDays"
                                                :key="index"
                                                type="button"
                                                @mousedown.prevent="selectDate(day)"
                                                :disabled="day === null"
                                                :class="[
                                                    'h-9 w-9 rounded-md text-sm transition-colors flex items-center justify-center',
                                                    day === null
                                                        ? 'cursor-default invisible'
                                                        : 'hover:bg-muted cursor-pointer',
                                                    isToday(day) && !isSelected(day)
                                                        ? 'bg-primary/10 text-primary font-semibold'
                                                        : '',
                                                    isSelected(day)
                                                        ? 'bg-primary text-primary-foreground font-semibold'
                                                        : 'text-foreground',
                                                ]"
                                            >
                                                {{ day }}
                                            </button>
                                        </div>
                                        
                                        <!-- Today button -->
                                        <div class="mt-3 flex justify-center">
                                            <button
                                                type="button"
                                                @click="goToToday"
                                                class="text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                Today
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="errors.date" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="add-description">Description (optional)</Label>
                            <textarea
                                id="add-description"
                                name="description"
                                v-model="formDescription"
                                rows="3"
                                placeholder="Add a description for this entry..."
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <InputError :message="errors.description" />
                        </div>

                        <DialogFooter class="sm:col-span-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="closeAddModal"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                :disabled="processing"
                            >
                                {{ processing ? 'Adding...' : 'Add Entry' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>

            <!-- Edit Entry Dialog -->
            <Dialog v-model:open="editDialogOpen">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit Entry</DialogTitle>
                        <DialogDescription>
                            Update the entry details
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        v-if="editingEntry"
                        :key="`edit-${editingEntry.id}`"
                        v-bind="updateEntry.form({ entry: editingEntry.id })"
                        :reset-on-success="false"
                        :preserve-scroll="true"
                        @success="() => { clearCategory(); closeEditModal(); }"
                        class="grid gap-4 sm:grid-cols-2"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2 sm:col-span-1">
                            <Label for="edit-type">Type</Label>
                            <select
                                id="edit-type"
                                name="type"
                                v-model="formType"
                                required
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="">Select type</option>
                                <option value="income">Income</option>
                                <option value="expense">Expense</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>

                        <div class="relative grid gap-2 sm:col-span-1">
                            <Label for="edit-amount">Amount</Label>
                            <Input
                                id="edit-amount"
                                type="number"
                                step="0.01"
                                min="0"
                                name="amount"
                                v-model="formAmount"
                                required
                                placeholder="0.00"
                            />
                            <InputError :message="errors.amount" />
                        </div>

                        <div class="relative grid gap-2 sm:col-span-2">
                            <Label for="edit-category">Category</Label>
                            <div class="relative">
                                <Input
                                    id="edit-category"
                                    v-model="categoryInput"
                                    @input="handleCategoryInput"
                                    @focus="showSuggestions = true"
                                    @blur="setTimeout(() => (showSuggestions = false), 200)"
                                    required
                                    placeholder="Type to search or create new category"
                                    autocomplete="off"
                                />
                                <!-- Hidden input for category_id if an existing category is selected -->
                                <input
                                    v-if="selectedCategoryId"
                                    type="hidden"
                                    name="category_id"
                                    :value="selectedCategoryId"
                                />
                                <!-- Hidden input for category_name if no existing category is selected -->
                                <input
                                    v-else
                                    type="hidden"
                                    name="category_name"
                                    :value="categoryInput.trim()"
                                />
                                <!-- Suggestions dropdown -->
                                <div
                                    v-if="showSuggestions && filteredCategories.length > 0"
                                    class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border border-sidebar-border/70 bg-card shadow-lg dark:border-sidebar-border"
                                >
                                    <ul class="py-1">
                                        <li
                                            v-for="category in filteredCategories"
                                            :key="category.id"
                                            @mousedown.prevent="selectCategory(category)"
                                            class="cursor-pointer px-4 py-2 text-sm hover:bg-muted"
                                        >
                                            {{ category.name }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <InputError :message="errors.category_name || errors.category_id" />
                        </div>

                        <div class="relative grid gap-2 sm:col-span-2">
                            <Label for="edit-date">Date</Label>
                            <div class="relative">
                                <Input
                                    ref="dateInputRef"
                                    id="edit-date"
                                    type="text"
                                    :model-value="formattedDate"
                                    @focus="showDatepicker = true"
                                    @click="showDatepicker = true"
                                    required
                                    placeholder="Select date"
                                    autocomplete="off"
                                    readonly
                                />
                                <!-- Hidden input for form submission with proper name -->
                                <input
                                    type="hidden"
                                    name="date"
                                    :value="dateInput"
                                />
                                <!-- Datepicker dropdown -->
                                <div
                                    ref="datepickerRef"
                                    v-if="showDatepicker"
                                    @mousedown="handleDatepickerMouseDown"
                                    class="absolute z-50 mt-1 w-[280px] rounded-md border border-sidebar-border/70 bg-card shadow-lg dark:border-sidebar-border"
                                >
                                    <div class="p-4">
                                        <!-- Month/Year header -->
                                        <div class="mb-4 flex items-center justify-between">
                                            <button
                                                type="button"
                                                @click="previousMonth"
                                                class="rounded-md p-1 hover:bg-muted"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 19l-7-7 7-7"
                                                    />
                                                </svg>
                                            </button>
                                            <div class="text-sm font-medium">
                                                {{ getMonthName(currentMonth) }} {{ currentYear }}
                                            </div>
                                            <button
                                                type="button"
                                                @click="nextMonth"
                                                class="rounded-md p-1 hover:bg-muted"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 5l7 7-7 7"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                        
                                        <!-- Day names header -->
                                        <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                                            <div>S</div>
                                            <div>M</div>
                                            <div>T</div>
                                            <div>W</div>
                                            <div>T</div>
                                            <div>F</div>
                                            <div>S</div>
                                        </div>
                                        
                                        <!-- Calendar grid -->
                                        <div class="grid grid-cols-7 gap-1">
                                            <button
                                                v-for="(day, index) in getCalendarDays"
                                                :key="index"
                                                type="button"
                                                @mousedown.prevent="selectDate(day)"
                                                :disabled="day === null"
                                                :class="[
                                                    'h-9 w-9 rounded-md text-sm transition-colors flex items-center justify-center',
                                                    day === null
                                                        ? 'cursor-default invisible'
                                                        : 'hover:bg-muted cursor-pointer',
                                                    isToday(day) && !isSelected(day)
                                                        ? 'bg-primary/10 text-primary font-semibold'
                                                        : '',
                                                    isSelected(day)
                                                        ? 'bg-primary text-primary-foreground font-semibold'
                                                        : 'text-foreground',
                                                ]"
                                            >
                                                {{ day }}
                                            </button>
                                        </div>
                                        
                                        <!-- Today button -->
                                        <div class="mt-3 flex justify-center">
                                            <button
                                                type="button"
                                                @click="goToToday"
                                                class="text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                Today
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="errors.date" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="edit-description">Description (optional)</Label>
                            <textarea
                                id="edit-description"
                                name="description"
                                v-model="formDescription"
                                rows="3"
                                placeholder="Add a description for this entry..."
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <InputError :message="errors.description" />
                        </div>

                        <DialogFooter class="sm:col-span-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="closeEditModal"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                :disabled="processing"
                            >
                                {{ processing ? 'Updating...' : 'Update Entry' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Entry</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this entry? This
                            action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="outline"
                                @click="
                                    () => {
                                        deleteDialogOpen = false;
                                        entryToDelete = null;
                                    }
                                "
                            >
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            @click="handleDelete"
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Record Payment Dialog -->
            <Dialog v-model:open="paymentDialogOpen">
                <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Record Payment</DialogTitle>
                        <DialogDescription>
                            Record a payment for this entry
                        </DialogDescription>
                    </DialogHeader>
                    
                    <!-- Entry Information -->
                    <div
                        v-if="entryForPayment"
                        class="rounded-lg border border-sidebar-border/70 bg-muted/50 p-4 dark:border-sidebar-border"
                    >
                        <div class="text-sm font-medium text-foreground">
                            {{ entryForPayment.description || entryForPayment.category?.name || 'Entry' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            <span class="capitalize">{{ entryForPayment.type }}</span>
                            <span class="mx-2">•</span>
                            <span>{{ formatCurrency(entryForPayment.amount) }}</span>
                            <span v-if="entryForPayment.total_paid && parseFloat(String(entryForPayment.total_paid)) > 0" class="ml-2">
                                ({{ formatCurrency(String(entryForPayment.total_paid)) }} paid)
                            </span>
                        </div>
                    </div>
                    
                    <Form
                        v-if="entryForPayment"
                        :key="`payment-${entryForPayment.id}`"
                        :action="`/entry-payments`"
                        method="post"
                        :preserve-scroll="true"
                        @success="closePaymentModal"
                        class="grid gap-4 sm:grid-cols-2"
                        v-slot="{ errors, processing }"
                    >
                        <input
                            type="hidden"
                            name="entry_id"
                            :value="entryForPayment.id"
                        />

                        <div class="relative grid gap-2 sm:col-span-1">
                            <Label for="payment-amount">Amount</Label>
                            <Input
                                id="payment-amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="amount"
                                v-model="paymentAmount"
                                required
                                placeholder="0.00"
                            />
                            <InputError :message="errors.amount" />
                        </div>

                        <div class="relative grid gap-2 sm:col-span-1">
                            <Label for="payment-date">Date</Label>
                            <div class="relative">
                                <Input
                                    ref="paymentDateInputRef"
                                    id="payment-date"
                                    type="text"
                                    :model-value="paymentFormattedDate"
                                    @focus="paymentDatepickerOpen = true"
                                    @click="paymentDatepickerOpen = true"
                                    required
                                    placeholder="Select date"
                                    autocomplete="off"
                                    readonly
                                />
                                <!-- Hidden input for form submission -->
                                <input
                                    type="hidden"
                                    name="date"
                                    :value="paymentDate"
                                />
                                <!-- Datepicker dropdown -->
                                <div
                                    ref="paymentDatepickerRef"
                                    v-if="paymentDatepickerOpen"
                                    @mousedown="handlePaymentDatepickerMouseDown"
                                    class="absolute z-50 mt-1 w-[280px] rounded-md border border-sidebar-border/70 bg-card shadow-lg dark:border-sidebar-border"
                                >
                                    <div class="p-4">
                                        <!-- Month/Year header -->
                                        <div class="mb-4 flex items-center justify-between">
                                            <button
                                                type="button"
                                                @click="previousPaymentMonth"
                                                class="rounded-md p-1 hover:bg-muted"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 19l-7-7 7-7"
                                                    />
                                                </svg>
                                            </button>
                                            <div class="text-sm font-medium">
                                                {{ getMonthName(paymentCurrentMonth) }} {{ paymentCurrentYear }}
                                            </div>
                                            <button
                                                type="button"
                                                @click="nextPaymentMonth"
                                                class="rounded-md p-1 hover:bg-muted"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 5l7 7-7 7"
                                                    />
                                                </svg>
                                            </button>
                                        </div>
                                        
                                        <!-- Day names header -->
                                        <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                                            <div>S</div>
                                            <div>M</div>
                                            <div>T</div>
                                            <div>W</div>
                                            <div>T</div>
                                            <div>F</div>
                                            <div>S</div>
                                        </div>
                                        
                                        <!-- Calendar grid -->
                                        <div class="grid grid-cols-7 gap-1">
                                            <button
                                                v-for="(day, index) in getPaymentCalendarDays"
                                                :key="index"
                                                type="button"
                                                @mousedown.prevent="selectPaymentDate(day)"
                                                :disabled="day === null"
                                                :class="[
                                                    'h-9 w-9 rounded-md text-sm transition-colors flex items-center justify-center',
                                                    day === null
                                                        ? 'cursor-default invisible'
                                                        : 'hover:bg-muted cursor-pointer',
                                                    isPaymentDateToday(day) && !isPaymentDateSelected(day)
                                                        ? 'bg-primary/10 text-primary font-semibold'
                                                        : '',
                                                    isPaymentDateSelected(day)
                                                        ? 'bg-primary text-primary-foreground font-semibold'
                                                        : 'text-foreground',
                                                ]"
                                            >
                                                {{ day }}
                                            </button>
                                        </div>
                                        
                                        <!-- Today button -->
                                        <div class="mt-3 flex justify-center">
                                            <button
                                                type="button"
                                                @click="goToPaymentToday"
                                                class="text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                Today
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <InputError :message="errors.date" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="payment-notes">Notes (optional)</Label>
                            <textarea
                                id="payment-notes"
                                name="notes"
                                v-model="paymentNotes"
                                rows="3"
                                placeholder="Add notes about this payment..."
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <InputError :message="errors.notes" />
                        </div>

                        <DialogFooter class="sm:col-span-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="closePaymentModal"
                                >
                                    Cancel
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                :disabled="processing"
                            >
                                {{ processing ? 'Recording...' : 'Record Payment' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>

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

