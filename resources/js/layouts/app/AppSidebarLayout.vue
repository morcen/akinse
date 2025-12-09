<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import BottomTabBar from '@/components/BottomTabBar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="header">
        <AppContent variant="header" class="overflow-x-hidden pb-20">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" class="hidden md:block"/>
            <slot />
        </AppContent>
        <BottomTabBar />
    </AppShell>
</template>
