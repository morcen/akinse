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

interface Props {
    open: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    confirm: [];
}>();

const close = () => {
    emit('update:open', false);
};

const handleConfirm = () => {
    emit('confirm');
};
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
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
                        @click="close"
                    >
                        Cancel
                    </Button>
                </DialogClose>
                <Button
                    type="button"
                    variant="destructive"
                    @click="handleConfirm"
                >
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

