<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/AppLayout.vue';
import { entries as entriesRoute } from '@/routes';
import { destroy, index as entriesIndex } from '@/routes/entries';
import { type BreadcrumbItem } from '@/types';
import { getCsrfToken } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { CreditCard, Filter, CheckCircle } from 'lucide-vue-next';
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
    group: 'date' | 'category';
    categories: Category[];
    filters?: {
        type: string;
        category_id: string | number[];
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
    categories: () => [],
    group: 'date',
});

// Reactive state for API-fetched data
const groupedEntries = ref<GroupedEntry[]>([]);
const isLoading = ref(false);
const isLoadingMore = ref(false);
const isLoadMoreInProgress = ref(false);
const isLoadingPrevious = ref(false);
const isLoadPreviousInProgress = ref(false);
const isFirstLoad = ref(true);

// Normalize category_id to array format
const normalizeCategoryId = (value: string | number[] | undefined): number[] => {
    if (!value) return [];
    if (Array.isArray(value)) return value.map(id => Number(id));
    if (value === '') return [];
    return [Number(value)];
};

const apiFilters = ref({
    type: props.filters?.type || '',
    category_id: normalizeCategoryId(props.filters?.category_id),
    date_from: props.filters?.date_from || '',
    date_to: props.filters?.date_to || '',
});

// Selected categories for the filter dialog (array of category IDs)
const selectedCategories = ref<number[]>(apiFilters.value.category_id);

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
const handleDelete = async () => {
    if (!entryToDelete.value) return;
    
    try {
        // Get CSRF token
        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            console.error('CSRF token not found');
            return;
        }
        
        const response = await fetch(destroy.url({ entry: entryToDelete.value.id }), {
            method: 'DELETE',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken,
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error(`Failed to delete: ${response.status}`);
        }

        deleteDialogOpen.value = false;
        entryToDelete.value = null;
        
        // Refetch data after deletion
        await fetchGroupedEntries();
    } catch (error) {
        console.error('Error deleting entry:', error);
    }
};

// Open payment modal
const openPaymentModal = (entry: Entry) => {
    entryForPayment.value = entry;
    paymentDialogOpen.value = true;
};

// Date range filter state
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

// Filter dialog state - closed by default
const filtersDialogOpen = ref(false);

// Watch for dialog open to initialize dialog state with current filters
watch(filtersDialogOpen, (isOpen) => {
    if (isOpen) {
        // When dialog opens, initialize dialog state with current apiFilters
        dateFrom.value = apiFilters.value.date_from;
        dateTo.value = apiFilters.value.date_to;
        selectedCategories.value = [...apiFilters.value.category_id];
    }
});

// Watch for prop changes to update dialog state (but don't trigger API calls)
// API calls should only happen when user clicks "Apply Filter"
watch(() => props.filters, (newFilters) => {
    const newDateFrom = newFilters?.date_from || '';
    const newDateTo = newFilters?.date_to || '';
    const newCategoryId = normalizeCategoryId(newFilters?.category_id);
    
    // Only update dialog state from props (e.g., when navigating back/forward)
    // Don't trigger API calls here - that should only happen on "Apply Filter"
    dateFrom.value = newDateFrom;
    dateTo.value = newDateTo;
    selectedCategories.value = newCategoryId;
    
    // Update apiFilters to match props (for initial load and external changes)
    // But don't call fetchGroupedEntries here - it will be called on mount
    apiFilters.value = {
        type: newFilters?.type || '',
        category_id: newCategoryId,
        date_from: newDateFrom,
        date_to: newDateTo,
    };
}, { immediate: true, deep: true });

// Watch for group changes and refetch
watch(() => props.group, (newGroup) => {
    // Remove scroll listener if switching away from date group
    if (newGroup !== 'date') {
        window.removeEventListener('scroll', handleScroll);
    } else {
        // Add scroll listener when switching to date group
        window.addEventListener('scroll', handleScroll);
    }
    fetchGroupedEntries();
});

// Fetch grouped entries from API
const fetchGroupedEntries = async () => {
    isLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (apiFilters.value.date_from) {
            params.append('date_from', apiFilters.value.date_from);
        }
        if (apiFilters.value.date_to) {
            params.append('date_to', apiFilters.value.date_to);
        }
        if (apiFilters.value.type) {
            params.append('type', apiFilters.value.type);
        }
        // Append each category ID separately to create an array
        if (apiFilters.value.category_id && apiFilters.value.category_id.length > 0) {
            apiFilters.value.category_id.forEach((id) => {
                params.append('category_id[]', String(id));
            });
        }

        let url: string;
        if (props.group === 'date') {
            // Use API entries endpoint for date grouping
            // For arrays, we need to build the query string manually
            const queryParts: string[] = [];
            params.forEach((value, key) => {
                queryParts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
            });
            const queryString = queryParts.length > 0 ? '?' + queryParts.join('&') : '';
            url = entriesIndex.url().split('?')[0] + queryString;
        } else {
            // Use category grouped endpoint for category grouping
            url = `/categories/grouped/entries${params.toString() ? '?' + params.toString() : ''}`;
        }

        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch: ${response.status}`);
        }

        const data = await response.json();
        groupedEntries.value = data.groupedEntries || [];
        
        // Update filters from API response
        if (data.filters) {
            apiFilters.value = {
                type: data.filters.type || '',
                category_id: normalizeCategoryId(data.filters.category_id),
                date_from: data.filters.date_from || '',
                date_to: data.filters.date_to || '',
            };
            selectedCategories.value = apiFilters.value.category_id;
            dateFrom.value = data.filters.date_from || '';
            dateTo.value = data.filters.date_to || '';
        }
        
        // Scroll to current date on first load (only for date grouping)
        if (isFirstLoad.value && props.group === 'date' && groupedEntries.value.length > 0) {
            await nextTick();
            // Use requestAnimationFrame to ensure DOM is fully painted before scrolling
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    scrollToCurrentDate();
                });
            });
            isFirstLoad.value = false;
        }
    } catch (error) {
        console.error('Error fetching grouped entries:', error);
        groupedEntries.value = [];
    } finally {
        isLoading.value = false;
    }
};

// Submit filters (date range and categories)
const submitFilters = () => {
    // Update apiFilters with dialog values
    apiFilters.value.date_from = dateFrom.value;
    apiFilters.value.date_to = dateTo.value;
    apiFilters.value.category_id = selectedCategories.value;
    // Now trigger the API call
    fetchGroupedEntries();
    filtersDialogOpen.value = false;
};

// Reset dialog state to current apiFilters when dialog is closed/cancelled
const resetDialogState = () => {
    dateFrom.value = apiFilters.value.date_from;
    dateTo.value = apiFilters.value.date_to;
    selectedCategories.value = [...apiFilters.value.category_id];
};

// Get the latest date from grouped entries
const getLatestDate = (): string | null => {
    if (groupedEntries.value.length === 0 || props.group !== 'date') {
        return null;
    }
    
    // Find the latest date from groupKey (format: "YYYY-MM-DD")
    const dates = groupedEntries.value
        .map(group => group.groupKey)
        .filter(key => key && typeof key === 'string')
        .map(key => {
            // Extract date part (YYYY-MM-DD)
            return key;
        })
        .filter(date => date.match(/^\d{4}-\d{2}-\d{2}$/));
    
    if (dates.length === 0) {
        return null;
    }
    
    // Sort dates and get the latest
    dates.sort((a, b) => b.localeCompare(a));
    return dates[0];
};

// Get the earliest date from grouped entries
const getEarliestDate = (): string | null => {
    if (groupedEntries.value.length === 0 || props.group !== 'date') {
        return null;
    }
    
    // Find the earliest date from groupKey (format: "YYYY-MM-DD")
    const dates = groupedEntries.value
        .map(group => group.groupKey)
        .filter(key => key && typeof key === 'string')
        .map(key => {
            // Extract date part (YYYY-MM-DD)
            return key;
        })
        .filter(date => date.match(/^\d{4}-\d{2}-\d{2}$/));
    
    if (dates.length === 0) {
        return null;
    }
    
    // Sort dates and get the earliest
    dates.sort((a, b) => a.localeCompare(b));
    return dates[0];
};

// Add days to a date string (YYYY-MM-DD)
const addDaysToDate = (dateString: string, days: number): string => {
    const date = parseDateLocal(dateString);
    date.setDate(date.getDate() + days);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

// Load the next 3 days after the latest date
const loadNextDays = async () => {
    // Only work for date grouping
    if (props.group !== 'date') {
        return;
    }
    
    // Prevent duplicate requests
    if (isLoadMoreInProgress.value || isLoadingMore.value) {
        return;
    }
    
    const latestDate = getLatestDate();
    if (!latestDate) {
        return;
    }
    
    isLoadMoreInProgress.value = true;
    isLoadingMore.value = true;
    
    try {
        // Calculate next 3 dates: latestDate+1, latestDate+2, latestDate+3
        const nextDate1 = addDaysToDate(latestDate, 1);
        const nextDate3 = addDaysToDate(latestDate, 3);
        
        // Build API request with date range
        const params = new URLSearchParams();
        params.append('date_from', nextDate1);
        params.append('date_to', nextDate3);
        
        if (apiFilters.value.type) {
            params.append('type', apiFilters.value.type);
        }
        // Append each category ID separately to create an array
        if (apiFilters.value.category_id && apiFilters.value.category_id.length > 0) {
            apiFilters.value.category_id.forEach((id) => {
                params.append('category_id[]', String(id));
            });
        }
        
        let url: string;
        if (props.group === 'date') {
            // For arrays, we need to build the query string manually
            const queryParts: string[] = [];
            params.forEach((value, key) => {
                queryParts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
            });
            const queryString = queryParts.length > 0 ? '?' + queryParts.join('&') : '';
            url = entriesIndex.url().split('?')[0] + queryString;
        } else {
            url = `/categories/grouped/entries?${params.toString()}`;
        }
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
        });
        
        if (!response.ok) {
            throw new Error(`Failed to fetch: ${response.status}`);
        }
        
        const data = await response.json();
        const newEntries = data.groupedEntries || [];
        
        if (newEntries.length > 0) {
            // Create a map of existing groupKeys to avoid duplicates
            const existingKeys = new Set(groupedEntries.value.map(g => g.groupKey));
            
            // Filter out duplicates and append new entries
            const uniqueNewEntries = newEntries.filter(
                (entry: GroupedEntry) => !existingKeys.has(entry.groupKey)
            );
            
            // Append new entries to existing ones
            groupedEntries.value = [...groupedEntries.value, ...uniqueNewEntries];
            
            // Update dateTo to include the newly loaded dates so completeGroupedEntries includes them
            if (dateTo.value) {
                const currentDateTo = parseDateLocal(dateTo.value);
                const newDateTo = parseDateLocal(nextDate3);
                if (newDateTo > currentDateTo) {
                    const year = newDateTo.getFullYear();
                    const month = String(newDateTo.getMonth() + 1).padStart(2, '0');
                    const day = String(newDateTo.getDate()).padStart(2, '0');
                    dateTo.value = `${year}-${month}-${day}`;
                }
            }
        }
    } catch (error) {
        console.error('Error loading next days:', error);
    } finally {
        isLoadingMore.value = false;
        isLoadMoreInProgress.value = false;
    }
};

// Load the previous 3 days before the earliest date
const loadPreviousDays = async () => {
    // Only work for date grouping
    if (props.group !== 'date') {
        return;
    }
    
    // Prevent duplicate requests
    if (isLoadPreviousInProgress.value || isLoadingPrevious.value) {
        return;
    }
    
    const earliestDate = getEarliestDate();
    if (!earliestDate) {
        return;
    }
    
    isLoadPreviousInProgress.value = true;
    isLoadingPrevious.value = true;
    
    // Save current scroll position and document height before loading
    const scrollTopBefore = window.pageYOffset || document.documentElement.scrollTop;
    const documentHeightBefore = document.documentElement.scrollHeight;
    
    // Also save reference element position for more accurate positioning
    const referenceElement = document.getElementById(earliestDate);
    const referenceElementOffsetBefore = referenceElement ? referenceElement.offsetTop : null;
    
    try {
        // Calculate previous 3 dates: earliestDate-3, earliestDate-2, earliestDate-1
        const prevDate3 = addDaysToDate(earliestDate, -3);
        const prevDate1 = addDaysToDate(earliestDate, -1);
        
        // Build API request with date range
        const params = new URLSearchParams();
        params.append('date_from', prevDate3);
        params.append('date_to', prevDate1);
        
        if (apiFilters.value.type) {
            params.append('type', apiFilters.value.type);
        }
        // Append each category ID separately to create an array
        if (apiFilters.value.category_id && apiFilters.value.category_id.length > 0) {
            apiFilters.value.category_id.forEach((id) => {
                params.append('category_id[]', String(id));
            });
        }
        
        let url: string;
        if (props.group === 'date') {
            // For arrays, we need to build the query string manually
            const queryParts: string[] = [];
            params.forEach((value, key) => {
                queryParts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
            });
            const queryString = queryParts.length > 0 ? '?' + queryParts.join('&') : '';
            url = entriesIndex.url().split('?')[0] + queryString;
        } else {
            url = `/categories/grouped/entries?${params.toString()}`;
        }
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'include',
        });
        
        if (!response.ok) {
            throw new Error(`Failed to fetch: ${response.status}`);
        }
        
        const data = await response.json();
        const newEntries = data.groupedEntries || [];
        
        if (newEntries.length > 0) {
            // Create a map of existing groupKeys to avoid duplicates
            const existingKeys = new Set(groupedEntries.value.map(g => g.groupKey));
            
            // Filter out duplicates and prepend new entries
            const uniqueNewEntries = newEntries.filter(
                (entry: GroupedEntry) => !existingKeys.has(entry.groupKey)
            );
            
            // Prepend new entries to existing ones (maintain date order)
            groupedEntries.value = [...uniqueNewEntries, ...groupedEntries.value];
            
            // Update dateFrom to include the newly loaded dates so completeGroupedEntries includes them
            if (dateFrom.value) {
                const currentDateFrom = parseDateLocal(dateFrom.value);
                const newDateFrom = parseDateLocal(prevDate3);
                if (newDateFrom < currentDateFrom) {
                    const year = newDateFrom.getFullYear();
                    const month = String(newDateFrom.getMonth() + 1).padStart(2, '0');
                    const day = String(newDateFrom.getDate()).padStart(2, '0');
                    dateFrom.value = `${year}-${month}-${day}`;
                }
            }
            
            // Wait for DOM to update, then adjust scroll position
            await nextTick();
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    // Calculate height difference using document height
                    const documentHeightAfter = document.documentElement.scrollHeight;
                    const heightDifference = documentHeightAfter - documentHeightBefore;
                    
                    // Use reference element if available for more accurate positioning
                    if (referenceElementOffsetBefore !== null) {
                        const referenceElementAfter = document.getElementById(earliestDate);
                        if (referenceElementAfter) {
                            const referenceElementOffsetAfter = referenceElementAfter.offsetTop;
                            const elementHeightDifference = referenceElementOffsetAfter - referenceElementOffsetBefore;
                            
                            // Adjust scroll position to maintain visual position relative to reference element
                            window.scrollTo({
                                top: scrollTopBefore + elementHeightDifference,
                                behavior: 'auto' // Use 'auto' to prevent smooth scroll animation during adjustment
                            });
                            return;
                        }
                    }
                    
                    // Fallback: adjust scroll position by document height difference
                    window.scrollTo({
                        top: scrollTopBefore + heightDifference,
                        behavior: 'auto' // Use 'auto' to prevent smooth scroll animation during adjustment
                    });
                });
            });
        }
    } catch (error) {
        console.error('Error loading previous days:', error);
    } finally {
        isLoadingPrevious.value = false;
        isLoadPreviousInProgress.value = false;
    }
};

// Handle scroll event for infinite loading
const handleScroll = () => {
    // Only work for date grouping
    if (props.group !== 'date') {
        return;
    }
    
    // Get scroll position
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;
    
    // Check if within 300px of top
    if (scrollTop <= 300) {
        // Prevent loading if already in progress
        if (!isLoadPreviousInProgress.value && !isLoadingPrevious.value) {
            loadPreviousDays();
        }
    }
    
    // Check if within 300px of bottom
    const distanceFromBottom = documentHeight - (scrollTop + windowHeight);
    if (distanceFromBottom <= 300) {
        // Prevent loading if already in progress
        if (!isLoadMoreInProgress.value && !isLoadingMore.value) {
            loadNextDays();
        }
    }
};

// Fetch data on mount
onMounted(() => {
    fetchGroupedEntries();
    
    // Add scroll event listener for infinite loading
    if (props.group === 'date') {
        window.addEventListener('scroll', handleScroll);
    }
});

// Clean up scroll listener
onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

// Scroll to current date's group element
const scrollToCurrentDate = () => {
    if (props.group !== 'date') {
        return;
    }
    
    // Format current date as YYYY-MM-DD
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    const currentDateString = `${year}-${month}-${day}`;
    
    // Find the element with the current date as id
    const currentDateElement = document.getElementById(currentDateString);
    
    if (currentDateElement) {
        // Scroll to the element, accounting for sticky header
        const headerOffset = 100; // Approximate height of sticky header
        const elementTop = currentDateElement.offsetTop;
        const offsetPosition = elementTop - headerOffset;
        
        window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
        });
    }
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
        dates.push(`${year}-${month}-${day}`);
        current.setDate(current.getDate() + 1);
    }
    
    return dates;
};

// Create complete list of groups including empty dates
const completeGroupedEntries = computed(() => {
    // Only process if group is 'date' and we have date filters
    if (props.group !== 'date' || !dateFrom.value || !dateTo.value) {
        return groupedEntries.value;
    }
    
    // Generate all dates in the range
    const allDates = generateDateRange(dateFrom.value, dateTo.value);
    
    // Create a map of existing groups by date
    const existingGroupsMap = new Map<string, GroupedEntry>();
    groupedEntries.value.forEach(group => {
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
    return groupedEntries.value && groupedEntries.value.length > 0;
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
                    <!-- Filters Button -->
                    <Button 
                        variant="outline" 
                        type="button"
                        @click="filtersDialogOpen = true"
                    >
                        <Filter class="h-4 w-4" />
                    </Button>
                    <Button @click="openAddModal">
                        Add Entry
                    </Button>
                </div>
            </div>

            <!-- Filter Dialog -->
            <Dialog :open="filtersDialogOpen" @update:open="(value) => filtersDialogOpen = value">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Filter Entries</DialogTitle>
                    </DialogHeader>
                    <form @submit.prevent="submitFilters" class="flex flex-col gap-4">
                        <div class="flex flex-col sm:flex-row gap-2">
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
                        <div class="flex flex-col gap-2">
                            <Label>Categories</Label>
                            <div class="max-h-48 overflow-y-auto rounded-md border border-input p-3 space-y-2">
                                <div
                                    v-for="category in categories"
                                    :key="category.id"
                                    class="flex items-center space-x-2"
                                >
                                    <Checkbox
                                        :id="`category-${category.id}`"
                                        :checked="selectedCategories.includes(category.id)"
                                        @update:checked="(checked: boolean) => {
                                            if (checked) {
                                                if (!selectedCategories.includes(category.id)) {
                                                    selectedCategories.push(category.id);
                                                }
                                            } else {
                                                selectedCategories = selectedCategories.filter(id => id !== category.id);
                                            }
                                        }"
                                    />
                                    <Label
                                        :for="`category-${category.id}`"
                                        class="text-sm font-normal cursor-pointer flex-1"
                                    >
                                        {{ category.name }}
                                    </Label>
                                </div>
                                <div v-if="categories.length === 0" class="text-sm text-muted-foreground text-center py-2">
                                    No categories available
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Select one or more categories to filter entries. Leave all unchecked to show all categories.
                            </p>
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button 
                                type="button" 
                                variant="outline"
                                @click="() => { resetDialogState(); filtersDialogOpen = false; }"
                                class="w-full sm:w-auto"
                            >
                                Cancel
                            </Button>
                            <Button type="submit" class="w-full sm:w-auto">
                                Apply Filter
                            </Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <!-- Loading state -->
            <div
                v-if="isLoading"
                class="mx-4 rounded-xl border border-sidebar-border/70 bg-card p-8 text-center text-sm text-muted-foreground dark:border-sidebar-border"
            >
                Loading entries...
            </div>

            <!-- No entries message (only show if no date range is set for date grouping) -->
            <div
                v-else-if="!shouldShowGroupedEntries"
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
                <!-- Loading previous spinner -->
                <div
                    v-if="isLoadingPrevious && group === 'date'"
                    class="rounded-xl border border-sidebar-border/70 bg-card p-8 text-center dark:border-sidebar-border"
                >
                    <div class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Loading previous entries...</span>
                    </div>
                </div>
                <div
                    v-for="(entryGroup, groupIndex) in completeGroupedEntries"
                    :key="`group-${entryGroup.groupKey}-${groupIndex}`"
                    class="rounded-xl border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                    :id="entryGroup.groupKey"
                >
                    <!-- Group header -->
                    <div 
                        class="sticky z-10 mb-4 flex-col items-center justify-between border-b border-sidebar-border/70 bg-card px-4 pb-3 pt-4 top-[70px]"
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
                                Net: <span class="font-medium" :class="getAmountColorByAmount(entryGroup.totalIncome - entryGroup.totalPayable)">
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

            <!-- Loading more spinner -->
            <div
                v-if="isLoadingMore && group === 'date'"
                class="mx-4 rounded-xl border border-sidebar-border/70 bg-card p-8 text-center dark:border-sidebar-border"
            >
                <div class="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Loading more entries...</span>
                </div>
            </div>

            <!-- Add Entry Dialog -->
            <AddEntryDialog
                v-model:open="addDialogOpen"
                :categories="categories"
                :initial-date="entryDateForAdd"
                @close="() => { addDialogOpen = false; fetchGroupedEntries(); }"
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
                @close="() => { editDialogOpen = false; fetchGroupedEntries(); }"
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
                @close="() => { paymentDialogOpen = false; fetchGroupedEntries(); }"
            />
        </div>
    </AppLayout>
</template>

