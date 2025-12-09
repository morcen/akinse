<script setup lang="ts">
import { categories, dashboard } from '@/routes';
import entriesRoutes from '@/routes/entries';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Home, Wallet, Tag, Settings } from 'lucide-vue-next';

interface TabItem {
    title: string;
    href: string;
    icon: any;
}

const tabs: TabItem[] = [
    {
        title: 'Home',
        href: dashboard().url,
        icon: Home,
    },
    {
        title: 'Entries',
        href: entriesRoutes.grouped({ group: 'date' }).url,
        icon: Wallet,
    },
    {
        title: 'Categories',
        href: entriesRoutes.grouped({ group: 'category' }).url,
        icon: Tag,
    },
    {
        title: 'Settings',
        href: '/settings',
        icon: Settings,
    },
];

const currentUrl = computed(() => {
    return usePage().url;
});

const isActive = (href: string) => {
    // Special handling for settings - match any /settings route
    if (href.includes('/settings')) {
        return currentUrl.value.startsWith('/settings');
    }
    return currentUrl.value === href || currentUrl.value.startsWith(href + '/');
};
</script>

<template>
    <nav
        class="fixed bottom-0 left-0 right-0 z-50 border-t border-sidebar-border/70 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 dark:border-sidebar-border safe-area-inset-bottom"
        style="padding-bottom: env(safe-area-inset-bottom);"
    >
        <div class="mx-auto flex h-16 max-w-screen-xl items-center justify-around px-2">
            <Link
                v-for="tab in tabs"
                :key="tab.href"
                :href="tab.href"
                :class="[
                    'flex flex-1 flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 transition-colors',
                    isActive(tab.href)
                        ? 'text-primary'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
            >
                <component
                    :is="tab.icon"
                    :class="[
                        'h-5 w-5 transition-transform',
                        isActive(tab.href) ? 'scale-110' : '',
                    ]"
                />
                <span
                    :class="[
                        'text-xs font-medium',
                        isActive(tab.href) ? 'font-semibold' : '',
                    ]"
                >
                    {{ tab.title }}
                </span>
            </Link>
        </div>
    </nav>
</template>

