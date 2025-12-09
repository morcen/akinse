<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { entries as entriesRoute } from '@/routes';
import { destroy, grouped } from '@/routes/entries';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { CreditCard, Filter, ChevronDown, CheckCircle } from 'lucide-vue-next';
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

interface GroupedEntry {
    groupKey: string;
    groupLabel: string;
    entries: Entry[];
    totalPayable: number;
    totalPayment: number;
    totalRemaining: number;
    totalIncome: number;
}

interface Props {
    groupedEntries: GroupedEntry[];
    group: 'date' | 'category';
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
    groupedEntries: () => [],
    categories: () => [],
    group: 'date',
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Entries',
        href: entriesRoute().url,
    },
    {
        title: 'Grouped Entries',
        href: '#',
    },
];

// Helper to parse YYYY-MM-DD string as local date (not UTC)
const parseDateLocal = (dateString: string | null | undefined): Date => {
    if (!dateString) {
        return new Date();
    }
    
    const datePart = dateString.split('T')[0].split(' ')[0];
    const [year, month, day] = datePart.split('-').map(Number);
    
    if (isNaN(year) || isNaN(month) || isNaN(day)) {
        console.warn('Invalid date string:', dateString);
        return new Date();
    }
    
    return new Date(year, month - 1, day);
};

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

// Get card background color class based on payment status and due date
const getCardColorClass = (entry: Entry): string => {
    if (entry.type === 'income') {
        return 'border-l-4 border-l-green-500';
    } else if (entry.type === 'expense') {
        return 'border-l-4 border-l-red-500';
    }

    // Everything else uses default background
    return 'border-l-4 border-l-gray-300 dark:border-l-gray-600';
};

const getAmountColorByAmount = (amount: number) => {
    return amount > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
};

const getAmountColor = (entry: Entry) => {
    return entry.type === 'income'
        ? 'text-green-600 dark:text-green-400'
        : isFullyPaid(entry) 
            ? 'text-red-600 dark:text-red-400 line-through' 
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
const openAddModal = (date?: string) => {
    addDialogOpen.value = true;
    if (date) {
        // Store the date to pass to AddEntryDialog
        entryDateForAdd.value = date;
    } else {
        entryDateForAdd.value = undefined;
    }
};

// Store date for AddEntryDialog when opening from empty date group
const entryDateForAdd = ref<string | undefined>(undefined);

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

// Date range filter state
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

// Filter collapsible state - closed by default
const filtersOpen = ref(false);

// Watch for prop changes to update date range
watch(() => props.filters, (newFilters) => {
    dateFrom.value = newFilters?.date_from || '';
    dateTo.value = newFilters?.date_to || '';
}, { immediate: true, deep: true });

// Submit date range filter
const submitDateRange = () => {
    router.get(grouped.url({ group: props.group }), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    });
};

// Generate all dates in a range
const generateDateRange = (startDate: string, endDate: string): string[] => {
    const dates: string[] = [];
    const start = parseDateLocal(startDate);
    const end = parseDateLocal(endDate);
    
    // Ensure start is before or equal to end
    if (start > end) {
        return dates;
    }
    
    const current = new Date(start);
    while (current <= end) {
        const year = current.getFullYear();
        const month = String(current.getMonth() + 1).padStart(2, '0');
        const day = String(current.getDate()).padStart(2, '0');
        dates.push(`${year}-${month}-${day} 00:00:00`);
        current.setDate(current.getDate() + 1);
    }
    
    return dates;
};

// Create complete list of groups including empty dates
const completeGroupedEntries = computed(() => {
    // Only process if group is 'date' and we have date filters
    if (props.group !== 'date' || !dateFrom.value || !dateTo.value) {
        return props.groupedEntries;
    }
    
    // Generate all dates in the range
    const allDates = generateDateRange(dateFrom.value, dateTo.value);
    
    // Create a map of existing groups by date
    const existingGroupsMap = new Map<string, GroupedEntry>();
    props.groupedEntries.forEach(group => {
        existingGroupsMap.set(group.groupKey, group);
    });
    
    // Create complete list with empty groups for dates without entries
    const completeGroups: GroupedEntry[] = allDates.map(date => {
        const existingGroup = existingGroupsMap.get(date);
        if (existingGroup) {
            return existingGroup;
        }
        
        // Create empty group for date without entries
        return {
            groupKey: date,
            groupLabel: formatDate(date),
            entries: [],
            totalPayable: 0,
            totalPayment: 0,
            totalRemaining: 0,
            totalIncome: 0,
        };
    });
    
    return completeGroups;
});

// Check if we should show the grouped entries section
const shouldShowGroupedEntries = computed(() => {
    if (props.group === 'date') {
        // For date grouping, show if we have date filters
        return !!(dateFrom.value && dateTo.value);
    }
    // For other groupings, show if we have entries
    return props.groupedEntries && props.groupedEntries.length > 0;
});
</script>

<template>
    <Head title="Grouped Entries" />

    <AppLayout :breadcrumbs="breadcrumbs" class="bg-secondary/50">
        <div
            class="flex h-full flex-1 flex-col gap-4 rounded-xl"
        >
            <div class="sticky top-0 z-20 flex items-center justify-between bg-background px-4 pb-4 pt-4">
                <div>
                    <h1 class="text-xl font-semibold">Entries</h1>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="group !== 'date'">
                            Entries grouped by category
                        </template>
                        <template v-else>
                            {{ formatDate(dateFrom) }} - {{ formatDate(dateTo) }}
                        </template>
                    </p>
                </div>
                <div class="flex items-center gap-2">
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
                    </Collapsible>
                    <Button @click="openAddModal">
                        Add Entry
                    </Button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <Collapsible v-model:open="filtersOpen" class="w-full px-4">
                <CollapsibleContent class="mt-0">
                    <div class="sticky top-[88px] z-20 rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
                        <form @submit.prevent="submitDateRange" class="flex flex-col gap-4 sm:flex-row sm:items-end">
                            <div class="flex flex-1 gap-2">
                                <div class="flex-1">
                                    <Label for="date-from">Date From</Label>
                                    <Input
                                        id="date-from"
                                        type="date"
                                        v-model="dateFrom"
                                        required
                                    />
                                </div>
                                <div class="flex-1">
                                    <Label for="date-to">Date To</Label>
                                    <Input
                                        id="date-to"
                                        type="date"
                                        v-model="dateTo"
                                        required
                                    />
                                </div>
                            </div>
                            <Button type="submit">
                                Apply Filter
                            </Button>
                        </form>
                    </div>
                </CollapsibleContent>
            </Collapsible>

            <!-- No entries message (only show if no date range is set for date grouping) -->
            <div
                v-if="!shouldShowGroupedEntries"
                class="mx-4 rounded-xl border border-sidebar-border/70 bg-card p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
            >
                <template v-if="group === 'date' && (!dateFrom || !dateTo)">
                    Please select a date range to view entries.
                </template>
                <template v-else>
                    No entries found for the selected filters. Start by adding your first entry.
                </template>
            </div>

            <!-- Grouped entries cards -->
            <div v-else class="flex flex-col gap-4 px-4 pb-4">
                <div
                    v-for="(entryGroup, groupIndex) in completeGroupedEntries"
                    :key="`group-${entryGroup.groupKey}-${groupIndex}`"
                    class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                >
                    <!-- Group header -->
                    <div 
                        :class="[
                            'sticky z-10 mb-4 flex-col items-center justify-between border-b border-sidebar-border/70 bg-card px-4 pb-3 pt-4',
                            filtersOpen ? 'top-[200px]' : 'top-[70px]'
                        ]"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-col items-center gap-2">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-lg font-semibold">{{ group === 'date' ? formatDate(entryGroup.groupKey) : entryGroup.groupLabel }}</h2>
                                    <span v-if="entryGroup.entries.length > 0" class="text-sm text-muted-foreground">
                                        ({{ entryGroup.entries.length }} {{ entryGroup.entries.length === 1 ? 'entry' : 'entries' }})
                                    </span>
                                </div>
                            </div>
                            <div v-if="entryGroup.entries.length > 0" class="flex items-end gap-1 text-xs">
                                Net: <span class="text-muted-foreground font-medium" :class="getAmountColorByAmount(entryGroup.totalIncome - entryGroup.totalPayable)">
                                        {{ formatCurrency(String(entryGroup.totalIncome - entryGroup.totalPayable)) }}
                                    </span>
                            </div>
                        </div>

                        <div v-if="entryGroup.entries.length > 0" class="flex items-center justify-evenly gap-2">
                           <div class="flex-col items-center gap-2 w-full">
                                <div class="text-xs text-center text-muted-foreground">Payable</div>
                                <div class="text-xs text-center text-red-600 dark:text-red-400 font-medium">{{ formatCurrency(String(entryGroup.totalPayable)) }}</div>
                           </div>
                           <div class="flex-col items-center gap-2 w-full" style="border-right: 1px solid #e0e0e0; border-left: 1px solid #e0e0e0;">
                                <div class="text-xs text-center text-muted-foreground">Paid</div>
                                <div class="text-xs text-center text-green-600 dark:text-green-400 font-medium">{{ formatCurrency(String(entryGroup.totalPayment)) }}</div>
                           </div>
                           <div class="flex-col items-center gap-2 w-full">
                                <div class="text-xs text-center text-muted-foreground">Remaining</div>
                                <div class="text-xs text-center text-red-600 dark:text-red-400 font-medium">{{ formatCurrency(String(entryGroup.totalRemaining)) }}</div>
                           </div>
                        </div>
                    </div>

                    <!-- Entries in group or empty state -->
                    <div v-if="entryGroup.entries.length > 0" class="grid gap-3 px-4 pb-4">
                        <div
                            v-for="entry in entryGroup.entries"
                            :key="entry.id"
                            @click="viewEntry(entry)"
                            :class="[
                                'rounded-lg border border-sidebar-border/70 p-4 transition-colors cursor-pointer hover:shadow-md',
                                getCardColorClass(entry),
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span
                                            v-if="group !== 'category'"
                                            class="text-sm font-bold"
                                        >
                                            <span v-if="isFullyPaid(entry)" class="flex items-center gap-1">    
                                                <CheckCircle class="h-4 w-4 text-green-600 dark:text-green-400" /> {{ entry.category?.name ?? 'Uncategorized' }}
                                            </span>
                                            <span v-else>  
                                            {{ entry.category?.name ?? 'Uncategorized' }}
                                            </span> 
                                        </span>
                                        <span
                                            v-if="group !== 'date'"
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ formatDate(entry.date) }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="entry.description"
                                        class="text-sm text-muted-foreground mb-2"
                                    >
                                        {{ entry.description }}
                                    </div>
                                    <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                        <span v-if="entry.total_paid && parseFloat(String(entry.total_paid)) > 0">
                                            Paid: <span class="text-green-600 dark:text-green-400">{{ formatCurrency(String(entry.total_paid)) }}</span>
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-col items-center gap-3 text-right">
                                    <div
                                        :class="[
                                            'text-lg font-semibold',
                                            getAmountColor(entry),
                                        ]"
                                    >
                                        {{ formatCurrency(String(entry.amount)) }}
                                    </div>
                                    
                                    <div class="flex items-center gap-4 text-xs text-muted-foreground">
                                        <span v-if="entry.type === 'expense' && !!entry?.total_paid && parseFloat(String(entry.total_paid)) > 0">
                                            Remaining: <span class="text-red-600 dark:text-red-400">{{ formatCurrency(String(getRemainingAmount(entry))) }}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Empty state for dates without entries -->
                    <div v-else class="px-4 pb-4">
                        <div class="rounded-lg border border-sidebar-border/70 border-dashed p-8 text-center">
                            <p class="text-sm text-muted-foreground mb-4">
                                No entries for this date.
                            </p>
                            <Button @click="openAddModal(entryGroup.groupKey)" variant="outline">
                                Add Entry
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Entry Dialog -->
            <AddEntryDialog
                v-model:open="addDialogOpen"
                :categories="categories"
                :initial-date="entryDateForAdd"
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
        </div>
    </AppLayout>
</template>

