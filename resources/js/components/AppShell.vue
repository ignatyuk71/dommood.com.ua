<script setup>
import GlobalLoadingIndicator from '@/components/GlobalLoadingIndicator.vue';
import ToastNotifications from '@/components/ToastNotifications.vue';
</script>

<template>
    <GlobalLoadingIndicator />
    <ToastNotifications />
    <slot />
</template>
