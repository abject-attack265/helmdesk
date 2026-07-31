<script setup lang="ts">
import { ToastProvider, type ToastProviderProps } from 'reka-ui';

withDefaults(defineProps<ToastProviderProps>(), {
  duration: 3000,
  swipeDirection: 'right',
});
</script>

<template>
  <ToastProvider v-bind="$props">
    <slot />
  </ToastProvider>
</template>
