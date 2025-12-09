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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeEntry } from '@/routes/entries';
import { Form } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface Category {
    id: number;
    name: string;
}

interface Props {
    open: boolean;
    categories: Category[];
    initialDate?: string;
}

const props = withDefaults(defineProps<Props>(), {
    initialDate: undefined,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    close: [];
    'save-and-add-new': [];
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
const dateInput = ref(props.initialDate ? formatDateLocal(parseDateLocal(props.initialDate)) : formatDateLocal(new Date()));
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
const datepickerRef = ref<HTMLElement | null>(null);
const dateInputRef = ref<HTMLElement | null>(null);

// Watch for dialog open to set initial date
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        if (props.initialDate) {
            dateInput.value = formatDateLocal(parseDateLocal(props.initialDate));
            const date = parseDateLocal(props.initialDate);
            currentMonth.value = date.getMonth();
            currentYear.value = date.getFullYear();
        } else {
            dateInput.value = formatDateLocal(new Date());
            const today = new Date();
            currentMonth.value = today.getMonth();
            currentYear.value = today.getFullYear();
        }
    }
});

// Watch for initialDate prop changes when dialog is open
watch(() => props.initialDate, (newDate) => {
    if (newDate && props.open) {
        dateInput.value = formatDateLocal(parseDateLocal(newDate));
        const date = parseDateLocal(newDate);
        currentMonth.value = date.getMonth();
        currentYear.value = date.getFullYear();
    }
});

// Save and add new state
const saveAndAddNew = ref(false);
const addEntryFormRef = ref<any>(null);

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

const handleSaveAndAddNew = () => {
    saveAndAddNew.value = true;
    sessionStorage.setItem('saveAndAddNew', 'true');
    const submitButton = addEntryFormRef.value?.$el?.querySelector('button[type=submit]') as HTMLButtonElement;
    if (submitButton) {
        submitButton.click();
    }
};

const close = () => {
    emit('update:open', false);
    emit('close');
    clearCategory();
    formType.value = 'expense';
    formAmount.value = '';
    formDescription.value = '';
    // Reset to initialDate if provided, otherwise today
    dateInput.value = props.initialDate 
        ? formatDateLocal(parseDateLocal(props.initialDate))
        : formatDateLocal(new Date());
    showDatepicker.value = false;
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
                <DialogTitle>Add Entry</DialogTitle>
                <DialogDescription>
                    Create a new income or expense entry
                </DialogDescription>
            </DialogHeader>
            <Form
                ref="addEntryFormRef"
                :key="'create'"
                v-bind="storeEntry.form()"
                :reset-on-success="true"
                :preserve-scroll="true"
                @success="() => { 
                    clearCategory(); 
                    if (!saveAndAddNew.value) {
                        close();
                    }
                    saveAndAddNew.value = false;
                }"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2 sm:col-span-1">
                    <Label for="add-type">Type</Label>
                    <div class="inline-flex gap-1 rounded-lg border border-input bg-background p-1">
                        <button
                            type="button"
                            @click="formType = 'expense'"
                            :class="[
                                'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                formType === 'expense'
                                    ? 'bg-red-500 text-white shadow-sm'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            Expense
                        </button>
                        <button
                            type="button"
                            @click="formType = 'income'"
                            :class="[
                                'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                formType === 'income'
                                    ? 'bg-green-500 text-white shadow-sm'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            Income
                        </button>
                    </div>
                    <input
                        type="hidden"
                        name="type"
                        :value="formType"
                    />
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

                <div class="relative grid gap-2 sm:col-span-1">
                    <Label for="add-category">Category</Label>
                    <div class="relative">
                        <Input
                            ref="categoryInputRef"
                            id="add-category"
                            v-model="categoryInput"
                            @input="handleCategoryInput"
                            @focus="showSuggestions = true"
                            @blur="showSuggestions = false"
                            required
                            placeholder="Type to search or create new category"
                            autocomplete="off"
                        />
                        <input
                            v-if="selectedCategoryId"
                            type="hidden"
                            name="category_id"
                            :value="selectedCategoryId"
                        />
                        <input
                            v-else
                            type="hidden"
                            name="category_name"
                            :value="categoryInput.trim()"
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

                <div class="relative grid gap-2 sm:col-span-1">
                    <Label for="add-date">Date</Label>
                    <div class="relative">
                        <Input
                            ref="dateInputRef"
                            id="add-date"
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
                        <input
                            type="hidden"
                            name="date"
                            :value="dateInput"
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
                            @click="close"
                        >
                            Cancel
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="processing"
                        @click="handleSaveAndAddNew"
                    >
                        {{ processing ? 'Saving...' : 'Save and Add New' }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="processing"
                    >
                        {{ processing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>

