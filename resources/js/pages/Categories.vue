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
import AppLayout from '@/layouts/AppLayout.vue';
import { categories as categoriesRoute } from '@/routes';
import { destroy, store as storeCategory, update as updateCategory } from '@/routes/categories';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, ref, withDefaults } from 'vue';
import { Edit, Trash2, Plus, Eye } from 'lucide-vue-next';

interface Category {
    id: number;
    name: string;
    description: string | null;
    entries_count?: number;
}

interface Props {
    categories: {
        data: Category[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const props = withDefaults(defineProps<Props>(), {
    categories: () => ({
        data: [],
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    }),
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Categories',
        href: categoriesRoute().url,
    },
];

// Form state
const formName = ref('');
const formDescription = ref('');

// Edit state
const editingCategory = ref<Category | null>(null);
const isEditing = computed(() => editingCategory.value !== null);

// Modal state
const addDialogOpen = ref(false);
const editDialogOpen = ref(false);

// Delete confirmation dialog state
const deleteDialogOpen = ref(false);
const categoryToDelete = ref<Category | null>(null);

// Entries and payments modal state
const entriesPaymentsDialogOpen = ref(false);
const entriesPaymentsLoading = ref(false);
const entriesPaymentsData = ref<{
    category: { id: number; name: string };
    items: Array<{
        type: 'entry' | 'payment';
        id: number;
        date: string;
        amount: string | number;
        type_label?: string;
        entry_type?: string;
        description?: string | null;
        notes?: string | null;
        entry_id: number;
        entry_description?: string | null;
    }>;
} | null>(null);

// Open add category modal
const openAddModal = () => {
    clearEdit();
    addDialogOpen.value = true;
};

// Populate form with category data for editing
const editCategory = (category: Category) => {
    editingCategory.value = category;
    formName.value = category.name;
    formDescription.value = category.description ?? '';
    editDialogOpen.value = true;
};

// Clear edit state
const clearEdit = () => {
    editingCategory.value = null;
    formName.value = '';
    formDescription.value = '';
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
const confirmDelete = (category: Category) => {
    categoryToDelete.value = category;
    deleteDialogOpen.value = true;
};

// Delete category
const handleDelete = () => {
    if (!categoryToDelete.value) return;
    
    router.delete(destroy.url({ category: categoryToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            deleteDialogOpen.value = false;
            categoryToDelete.value = null;
        },
    });
};

// Open entries and payments modal
const openEntriesPaymentsModal = async (category: Category) => {
    entriesPaymentsDialogOpen.value = true;
    entriesPaymentsLoading.value = true;
    entriesPaymentsData.value = null;

    try {
        const response = await fetch(`/categories/${category.id}/entries-and-payments`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        
        if (!response.ok) {
            throw new Error('Failed to fetch entries and payments');
        }
        
        const data = await response.json();
        entriesPaymentsData.value = data;
    } catch (error) {
        console.error('Error fetching entries and payments:', error);
    } finally {
        entriesPaymentsLoading.value = false;
    }
};

// Close entries and payments modal
const closeEntriesPaymentsModal = () => {
    entriesPaymentsDialogOpen.value = false;
    entriesPaymentsData.value = null;
};

// Format currency
const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP',
    }).format(parseFloat(String(amount)));
};

// Format date
const formatDate = (date: string | null | undefined) => {
    if (!date) {
        return '';
    }
    
    try {
        const dateObj = new Date(date);
        return dateObj.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    } catch (error) {
        console.error('Error formatting date:', date, error);
        return '';
    }
};

// Get type color
const getTypeColor = (type: string) => {
    return type === 'income'
        ? 'text-green-600 dark:text-green-400'
        : 'text-red-600 dark:text-red-400';
};

// Get type badge color
const getTypeBadgeColor = (type: string) => {
    return type === 'income'
        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
};
</script>

<template>
    <Head title="Categories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Categories</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage your income and expense categories
                    </p>
                </div>
                <Button @click="openAddModal">
                    <Plus class="h-4 w-4 mr-2" />
                    Add Category
                </Button>
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
                                    Name
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-sm font-medium text-muted-foreground"
                                >
                                    Description
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-sm font-medium text-muted-foreground"
                                >
                                    Entries
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
                                v-if="!categories?.data || categories.data.length === 0"
                                class="border-b border-sidebar-border/70"
                            >
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-sm text-muted-foreground"
                                >
                                    No categories found. Start by adding your first
                                    category.
                                </td>
                            </tr>
                            <tr
                                v-for="category in categories?.data || []"
                                :key="category.id"
                                class="border-b border-sidebar-border/70 transition-colors hover:bg-muted/50"
                            >
                                <td class="px-4 py-3 text-sm font-medium">
                                    {{ category.name }}
                                </td>
                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                    {{ category.description || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-sm text-muted-foreground">
                                            {{ category.entries_count || 0 }}
                                        </span>
                                        <button
                                            v-if="(category.entries_count || 0) > 0"
                                            type="button"
                                            @click="openEntriesPaymentsModal(category)"
                                            class="rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="View entries and payments"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            @click="editCategory(category)"
                                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            title="Edit category"
                                        >
                                            <Edit class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="confirmDelete(category)"
                                            class="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                                            title="Delete category"
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

            <!-- Add Category Dialog -->
            <Dialog v-model:open="addDialogOpen">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Add Category</DialogTitle>
                        <DialogDescription>
                            Create a new category for your entries
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        :key="'create'"
                        v-bind="storeCategory.form()"
                        :reset-on-success="true"
                        :preserve-scroll="true"
                        @success="closeAddModal"
                        class="grid gap-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="add-name">Name</Label>
                            <Input
                                id="add-name"
                                type="text"
                                name="name"
                                v-model="formName"
                                required
                                placeholder="Enter category name"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="add-description">Description (optional)</Label>
                            <textarea
                                id="add-description"
                                name="description"
                                v-model="formDescription"
                                rows="3"
                                placeholder="Add a description for this category..."
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <InputError :message="errors.description" />
                        </div>

                        <DialogFooter>
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
                                {{ processing ? 'Adding...' : 'Add Category' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>

            <!-- Edit Category Dialog -->
            <Dialog v-model:open="editDialogOpen">
                <DialogContent class="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit Category</DialogTitle>
                        <DialogDescription>
                            Update the category details
                        </DialogDescription>
                    </DialogHeader>
                    <Form
                        v-if="editingCategory"
                        :key="`edit-${editingCategory.id}`"
                        v-bind="updateCategory.form({ category: editingCategory.id })"
                        :reset-on-success="false"
                        :preserve-scroll="true"
                        @success="closeEditModal"
                        class="grid gap-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="edit-name">Name</Label>
                            <Input
                                id="edit-name"
                                type="text"
                                name="name"
                                v-model="formName"
                                required
                                placeholder="Enter category name"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-description">Description (optional)</Label>
                            <textarea
                                id="edit-description"
                                name="description"
                                v-model="formDescription"
                                rows="3"
                                placeholder="Add a description for this category..."
                                class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <InputError :message="errors.description" />
                        </div>

                        <DialogFooter>
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
                                {{ processing ? 'Updating...' : 'Update Category' }}
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>

            <!-- Entries and Payments Dialog -->
            <Dialog v-model:open="entriesPaymentsDialogOpen">
                <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            Entries and Payments
                            <span v-if="entriesPaymentsData?.category" class="text-muted-foreground">
                                - {{ entriesPaymentsData.category.name }}
                            </span>
                        </DialogTitle>
                        <DialogDescription>
                            All entries and payments for this category, sorted by date
                        </DialogDescription>
                    </DialogHeader>
                    
                    <div v-if="entriesPaymentsLoading" class="py-8 text-center text-sm text-muted-foreground">
                        Loading...
                    </div>
                    
                    <div v-else-if="entriesPaymentsData && entriesPaymentsData.items.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                        No entries or payments found for this category.
                    </div>
                    
                    <div v-else-if="entriesPaymentsData" class="space-y-2">
                        <div
                            v-for="item in entriesPaymentsData.items"
                            :key="`${item.type}-${item.id}`"
                            class="rounded-lg border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                        >
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span
                                            v-if="item.type === 'entry'"
                                            :class="[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize',
                                                getTypeBadgeColor(item.type_label || ''),
                                            ]"
                                        >
                                            Entry - {{ item.type_label }}
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200"
                                        >
                                            Payment
                                        </span>
                                        <span class="text-xs text-muted-foreground">
                                            {{ formatDate(item.date) }}
                                        </span>
                                    </div>
                                    
                                    <div
                                        :class="[
                                            'text-lg font-semibold mb-1',
                                            item.type === 'entry'
                                                ? getTypeColor(item.type_label || '')
                                                : 'text-blue-600 dark:text-blue-400',
                                        ]"
                                    >
                                        {{ formatCurrency(item.amount) }}
                                    </div>
                                    
                                    <div v-if="item.type === 'entry' && item.description" class="text-sm text-muted-foreground">
                                        {{ item.description }}
                                    </div>
                                    
                                    <div v-if="item.type === 'payment'" class="space-y-1">
                                        <div class="text-sm text-muted-foreground">
                                            Payment for: 
                                            <span class="capitalize">{{ item.entry_type }}</span>
                                            <span v-if="item.entry_description"> - {{ item.entry_description }}</span>
                                        </div>
                                        <div v-if="item.notes" class="text-sm text-muted-foreground italic">
                                            {{ item.notes }}
                                        </div>
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
                                @click="closeEntriesPaymentsModal"
                            >
                                Close
                            </Button>
                        </DialogClose>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="deleteDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Category</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this category? This
                            action cannot be undone. If this category has entries,
                            they will become uncategorized.
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
                                        categoryToDelete = null;
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

            <div
                v-if="categories?.last_page && categories.last_page > 1"
                class="flex items-center justify-between text-sm text-muted-foreground"
            >
                <div>
                    Showing {{ categories?.data?.length || 0 }} of {{ categories?.total || 0 }}
                    categories
                </div>
                <div class="flex gap-2">
                    <button
                        v-if="categories?.current_page && categories.current_page > 1"
                        @click="router.get(categoriesRoute().url, {
                            page: (categories?.current_page || 1) - 1,
                        }, { preserveState: true, preserveScroll: true })"
                        class="rounded-md border border-sidebar-border/70 px-3 py-1.5 hover:bg-muted"
                    >
                        Previous
                    </button>
                    <button
                        v-if="categories?.current_page && categories?.last_page && categories.current_page < categories.last_page"
                        @click="router.get(categoriesRoute().url, {
                            page: (categories?.current_page || 1) + 1,
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

