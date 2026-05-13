<script setup>
import GlobalLoadingIndicator from '@/components/GlobalLoadingIndicator.vue';
import ToastNotifications from '@/components/ToastNotifications.vue';
import WorkspaceLoadingOverlay from '@/components/WorkspaceLoadingOverlay.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

onMounted(() => {
    document.body.style.overflow = '';
    document.body.classList.remove('storefront-menu-open');
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center justify-center bg-[#f4f6f8] px-4 py-10"
    >
        <GlobalLoadingIndicator />
        <ToastNotifications />

        <div class="w-full max-w-md">
            <div class="mb-8 flex justify-center">
                <Link href="/" class="inline-flex">
                    <img
                        src="/brand/dom-mood-stacked.png"
                        alt="DomMood"
                        class="h-24 w-auto object-contain"
                    >
                </Link>
            </div>

            <div
                class="relative overflow-hidden rounded-xl border border-slate-200 bg-white px-6 py-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]"
            >
                <WorkspaceLoadingOverlay />
                <slot />
            </div>
        </div>
    </div>
</template>
