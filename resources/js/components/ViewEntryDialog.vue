<script setup lang="ts">
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
import { Label } from '@/components/ui/label';
import { CreditCard } from 'lucide-vue-next';

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
    'edit': [];
    'record-payment': [];
}>();

const formatCurrency = (amount: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP',
    }).format(parseFloat(amount));
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

const getRemainingAmount = (entry: Entry): number => {
    const entryAmount = parseFloat(entry.amount);
    const totalPaid = parseFloat(String(entry.total_paid || 0));
    return Math.max(0, entryAmount - totalPaid);
};

const isFullyPaid = (entry: Entry): boolean => {
    return getRemainingAmount(entry) === 0;
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

const formatRemainingAmount = (entry: Entry): string => {
    const remaining = getRemainingAmount(entry);
    if (remaining === 0) {
        return formatCurrency('0');
    }
    return formatCurrency(String(remaining));
};

const close = () => {
    emit('update:open', false);
};

const handleEdit = () => {
    emit('edit');
};

const handleRecordPayment = () => {
    emit('record-payment');
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>Entry Details</DialogTitle>
                <DialogDescription>
                    View entry information
                </DialogDescription>
            </DialogHeader>
            
            <div v-if="entry" class="grid gap-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground">Type</Label>
                        <div>
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                    getTypeBadgeColor(entry.type),
                                ]"
                            >
                                {{ entry.type === 'income' ? 'Income' : 'Expense' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground">Amount</Label>
                        <div
                            :class="[
                                'text-lg font-semibold',
                                getTypeColor(entry.type),
                            ]"
                        >
                            {{ formatCurrency(entry.amount) }}
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground">Category</Label>
                        <div class="text-sm font-medium">
                            {{ entry.category?.name ?? 'Uncategorized' }}
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground">Date</Label>
                        <div class="text-sm font-medium">
                            {{ formatDate(entry.date) }}
                        </div>
                    </div>

                    <div
                        v-if="entry.description"
                        class="grid gap-2 sm:col-span-2"
                    >
                        <Label class="text-xs text-muted-foreground">Description</Label>
                        <div class="text-sm">
                            {{ entry.description }}
                        </div>
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label class="text-xs text-muted-foreground">Payment Status</Label>
                        <div class="rounded-lg border border-sidebar-border/70 bg-muted/50 p-4 dark:border-sidebar-border">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium">Total Amount</div>
                                    <div
                                        :class="[
                                            'text-lg font-semibold',
                                            getTypeColor(entry.type),
                                        ]"
                                    >
                                        {{ formatCurrency(entry.amount) }}
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-sm font-medium">Total Paid</div>
                                    <div class="text-lg font-semibold">
                                        {{ formatCurrency(String(entry.total_paid || 0)) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium">Remaining</div>
                                    <div
                                        :class="[
                                            'text-lg font-semibold',
                                            getRemainingAmount(entry) === 0
                                                ? 'text-muted-foreground'
                                                : getTypeColor(entry.type),
                                        ]"
                                    >
                                        {{ formatRemainingAmount(entry) }}
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="isFullyPaid(entry)"
                                class="mt-2 text-center text-xs text-green-600 dark:text-green-400"
                            >
                                ✓ Fully Paid
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="outline"
                        @click="close"
                    >
                        Close
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="outline"
                    @click="handleRecordPayment"
                    :disabled="!entry || isFullyPaid(entry!)"
                >
                    <CreditCard class="h-4 w-4 mr-2" />
                    Record Payment
                </Button>
                <Button
                    type="button"
                    @click="handleEdit"
                    :disabled="!entry"
                >
                    Edit Entry
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

