<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { getCsrfToken } from '@/lib/utils';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateEntry } from '@/routes/entries';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
}

interface Category {
    id: number;
    name: string;
}

interface Props {
    open: boolean;
    entry: Entry | null;
    categories: Category[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    close: [];
    delete: [];
}>();

// Helper functions (must be defined before use)
const formatDateLocal = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

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

// Form state
const formType = ref('expense');
const formAmount = ref('');
const formDescription = ref('');

// Category autocomplete state
const categoryInput = ref('');
const showSuggestions = ref(false);
const selectedCategoryId = ref<number | null>(null);
const categoryInputRef = ref<HTMLElement | null>(null);
const categoryDropdownRef = ref<HTMLElement | null>(null);

// Datepicker state
const showDatepicker = ref(false);
const dateInput = ref(formatDateLocal(new Date()));
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
const datepickerRef = ref<HTMLElement | null>(null);
const dateInputRef = ref<HTMLElement | null>(null);

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
    
    for (let i = 0; i < firstDay; i++) {
        days.push(null);
    }
    
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

watch(dateInput, (newDate) => {
    if (newDate) {
        const date = parseDateLocal(newDate);
        currentMonth.value = date.getMonth();
        currentYear.value = date.getFullYear();
    }
});

// Watch entry prop to populate form
watch(() => props.entry, (newEntry) => {
    if (newEntry) {
        categoryInput.value = newEntry.category?.name ?? '';
        selectedCategoryId.value = newEntry.category?.id ?? null;
        
        const entryDate = newEntry.date ? parseDateLocal(newEntry.date) : new Date();
        dateInput.value = formatDateLocal(entryDate);
        currentMonth.value = entryDate.getMonth();
        currentYear.value = entryDate.getFullYear();
        
        formType.value = newEntry.type;
        formAmount.value = newEntry.amount;
        formDescription.value = newEntry.description ?? '';
        showDatepicker.value = false;
    }
}, { immediate: true });

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

const handleDatepickerMouseDown = (event: MouseEvent) => {
    event.preventDefault();
};

const close = () => {
    emit('update:open', false);
    emit('close');
    clearCategory();
    showDatepicker.value = false;
};

const handleDelete = () => {
    emit('delete');
    close();
};

// Form submission state
const processing = ref(false);
const errors = ref<Record<string, string>>({});

const handleSubmit = async () => {
    if (!props.entry) return;
    
    // Reset errors
    errors.value = {};
    
    // Validate required fields
    if (!formAmount.value || parseFloat(formAmount.value) <= 0) {
        errors.value.amount = 'Amount is required and must be greater than 0.';
        return;
    }
    
    if (!categoryInput.value.trim() && !selectedCategoryId.value) {
        errors.value.category_name = 'Category is required.';
        return;
    }
    
    if (!dateInput.value) {
        errors.value.date = 'Date is required.';
        return;
    }
    
    processing.value = true;
    
    try {
        const formData: Record<string, any> = {
            type: formType.value,
            amount: parseFloat(formAmount.value),
            date: dateInput.value,
            description: formDescription.value || null,
        };
        
        if (selectedCategoryId.value) {
            formData.category_id = selectedCategoryId.value;
        } else {
            formData.category_name = categoryInput.value.trim();
        }
        
        // Get clean URL without query parameters to avoid method spoofing
        // Construct URL directly to avoid any route helper quirks
        const entryId = props.entry.id;
        const cleanUrl = `/entries/${entryId}`;
        
        // Get CSRF token
        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            errors.value._general = 'CSRF token not found. Please refresh the page.';
            processing.value = false;
            return;
        }
        
        const response = await fetch(cleanUrl, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(formData),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            // Handle validation errors
            if (response.status === 422 && data.errors) {
                errors.value = data.errors;
                return;
            }
            throw new Error(data.message || `Failed to update entry: ${response.status}`);
        }
        
        // Success - show confirmation and close
        clearCategory();
        close();
    } catch (error) {
        console.error('Error updating entry:', error);
        errors.value._general = error instanceof Error ? error.message : 'An error occurred while updating the entry.';
    } finally {
        processing.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('mousedown', handleCategoryDropdownClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
    document.removeEventListener('mousedown', handleCategoryDropdownClickOutside);
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Edit Entry</DialogTitle>
                <DialogDescription>
                    Update the entry details
                </DialogDescription>
            </DialogHeader>
            <form
                v-if="entry"
                @submit.prevent="handleSubmit"
                class="grid gap-4 sm:grid-cols-2"
            >
                <div class="grid gap-2 sm:col-span-1">
                    <Label for="edit-type">Type</Label>
                    <select
                        id="edit-type"
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
                            ref="categoryInputRef"
                            id="edit-category"
                            v-model="categoryInput"
                            @input="handleCategoryInput"
                            @focus="showSuggestions = true"
                            @blur="showSuggestions = false"
                            required
                            placeholder="Type to search or create new category"
                            autocomplete="off"
                        />
                        <div
                            ref="categoryDropdownRef"
                            v-if="showSuggestions && filteredCategories.length > 0"
                            @mousedown.prevent
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
                            @blur="showDatepicker = false"
                            required
                            placeholder="Select date"
                            autocomplete="off"
                            readonly
                        />
                        <div
                            ref="datepickerRef"
                            v-if="showDatepicker"
                            @mousedown="handleDatepickerMouseDown"
                            class="absolute z-50 mt-1 w-[280px] rounded-md border border-sidebar-border/70 bg-card shadow-lg dark:border-sidebar-border"
                        >
                            <div class="p-4">
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
                                
                                <div class="mb-2 grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground">
                                    <div>S</div>
                                    <div>M</div>
                                    <div>T</div>
                                    <div>W</div>
                                    <div>T</div>
                                    <div>F</div>
                                    <div>S</div>
                                </div>
                                
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
                        v-model="formDescription"
                        rows="3"
                        placeholder="Add a description for this entry..."
                        class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    ></textarea>
                    <InputError :message="errors.description" />
                </div>

                <div v-if="errors._general" class="sm:col-span-2">
                    <InputError :message="errors._general" />
                </div>

                <DialogFooter class="sm:col-span-2">
                    <div class="flex w-full items-center justify-between">
                        <Button
                            type="button"
                            variant="destructive"
                            @click="handleDelete"
                            :disabled="processing"
                        >
                            Delete
                        </Button>
                        <div class="flex gap-2">
                            <DialogClose as-child>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="close"
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
                        </div>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

