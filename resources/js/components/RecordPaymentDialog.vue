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
import { Form } from '@inertiajs/vue3';
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
    total_paid?: string | number;
}

interface Props {
    open: boolean;
    entry: Entry | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    close: [];
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

// Payment form state
const paymentAmount = ref('');
const paymentDate = ref(formatDateLocal(new Date()));
const paymentNotes = ref('');
const paymentDatepickerOpen = ref(false);
const paymentCurrentMonth = ref(new Date().getMonth());
const paymentCurrentYear = ref(new Date().getFullYear());
const paymentDatepickerRef = ref<HTMLElement | null>(null);
const paymentDateInputRef = ref<HTMLElement | null>(null);

const formatCurrency = (amount: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP',
    }).format(parseFloat(amount));
};

const getMonthName = (month: number) => {
    const date = new Date(2000, month, 1);
    return date.toLocaleDateString('en-US', { month: 'long' });
};

const getDaysInMonth = (month: number, year: number) => {
    return new Date(year, month + 1, 0).getDate();
};

const getFirstDayOfMonth = (month: number, year: number) => {
    return new Date(year, month, 1).getDay();
};

const getPaymentCalendarDays = computed(() => {
    const daysInMonth = getDaysInMonth(paymentCurrentMonth.value, paymentCurrentYear.value);
    const firstDay = getFirstDayOfMonth(paymentCurrentMonth.value, paymentCurrentYear.value);
    const days: (number | null)[] = [];
    
    for (let i = 0; i < firstDay; i++) {
        days.push(null);
    }
    
    for (let i = 1; i <= daysInMonth; i++) {
        days.push(i);
    }
    
    return days;
});

const paymentFormattedDate = computed(() => {
    if (!paymentDate.value) return '';
    const date = parseDateLocal(paymentDate.value);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
});

const selectPaymentDate = (day: number | null) => {
    if (day === null) return;
    
    const selectedDate = new Date(paymentCurrentYear.value, paymentCurrentMonth.value, day);
    paymentDate.value = formatDateLocal(selectedDate);
    paymentDatepickerOpen.value = false;
};

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

const handlePaymentDatepickerMouseDown = (event: MouseEvent) => {
    event.preventDefault();
};

watch(paymentDate, (newDate) => {
    if (newDate) {
        const date = parseDateLocal(newDate);
        paymentCurrentMonth.value = date.getMonth();
        paymentCurrentYear.value = date.getFullYear();
    }
});

// Watch entry to set initial payment amount
watch(() => props.entry, (newEntry) => {
    if (newEntry) {
        const entryAmount = parseFloat(newEntry.amount);
        const totalPaid = parseFloat(String(newEntry.total_paid || 0));
        const remainingAmount = Math.max(0, entryAmount - totalPaid);
        
        paymentAmount.value = remainingAmount > 0 ? remainingAmount.toFixed(2) : '';
        paymentDate.value = formatDateLocal(new Date());
        paymentNotes.value = '';
        paymentCurrentMonth.value = new Date().getMonth();
        paymentCurrentYear.value = new Date().getFullYear();
        paymentDatepickerOpen.value = false;
    }
}, { immediate: true });

const close = () => {
    emit('update:open', false);
    emit('close');
    paymentAmount.value = '';
    paymentDate.value = formatDateLocal(new Date());
    paymentNotes.value = '';
    paymentDatepickerOpen.value = false;
};

onMounted(() => {
    document.addEventListener('mousedown', handlePaymentDatepickerClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handlePaymentDatepickerClickOutside);
});
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Record Payment</DialogTitle>
                <DialogDescription>
                    Record a payment for this entry
                </DialogDescription>
            </DialogHeader>
            
            <div
                v-if="entry"
                class="rounded-lg border border-sidebar-border/70 bg-muted/50 p-4 dark:border-sidebar-border"
            >
                <div class="text-sm font-medium text-foreground">
                    <div class="text-lg font-semibold">{{ entry.category?.name }}</div>
                    <div class="text-sm text-muted-foreground">{{ entry.description || '' }}</div>
                </div>
                <div class="mt-1 text-xs text-muted-foreground">
                    <span class="capitalize">{{ entry.type }}</span>
                    <span class="mx-2">•</span>
                    <span>{{ formatCurrency(entry.amount) }}</span>
                    <span v-if="entry.total_paid && parseFloat(String(entry.total_paid)) > 0" class="ml-2">
                        ({{ formatCurrency(String(entry.total_paid)) }} paid)
                    </span>
                </div>
            </div>
            
            <Form
                v-if="entry"
                :key="`payment-${entry.id}`"
                :action="`/entry-payments`"
                method="post"
                :preserve-scroll="true"
                @success="close"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="entry_id"
                    :value="entry.id"
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
                            @blur="paymentDatepickerOpen = false"
                            required
                            placeholder="Select date"
                            autocomplete="off"
                            readonly
                        />
                        <input
                            type="hidden"
                            name="date"
                            :value="paymentDate"
                        />
                        <div
                            ref="paymentDatepickerRef"
                            v-if="paymentDatepickerOpen"
                            @mousedown="handlePaymentDatepickerMouseDown"
                            class="absolute z-50 mt-1 w-[280px] rounded-md border border-sidebar-border/70 bg-card shadow-lg dark:border-sidebar-border"
                        >
                            <div class="p-4">
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
                            @click="close"
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
</template>

