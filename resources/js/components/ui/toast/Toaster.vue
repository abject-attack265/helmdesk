<script setup lang="ts">
import { useErrorHandling, useToast } from '@/composables/useToast';
import Toast from './Toast.vue';
import ToastClose from './ToastClose.vue';
import ToastDescription from './ToastDescription.vue';
import ToastProvider from './ToastProvider.vue';
import ToastTitle from './ToastTitle.vue';
import ToastViewport from './ToastViewport.vue';
import ToastAction from './ToastAction.vue';

const { toasts, removeToast } = useToast();

// 根级 Toaster 统一注册全局 flash 和请求错误监听。
useErrorHandling();

const getToastClass = (type?: string) => {
  if (type === 'error') {
    return 'border-destructive bg-destructive text-destructive-foreground';
  }

  return 'border-border bg-card text-card-foreground backdrop-blur-sm';
};

const getCloseClass = (type?: string) => {
  if (type === 'error') {
    return 'text-destructive-foreground/70 hover:bg-white/15 hover:text-destructive-foreground focus:ring-destructive-foreground/70';
  }

  return '';
};
</script>

<template>
  <ToastProvider>
    <Toast
      v-for="toast in toasts"
      :key="toast.id"
      :duration="toast.duration"
      :class="getToastClass(toast.type)"
      @update:open="(open) => !open && removeToast(toast.id)"
    >
      <div class="min-w-0 flex-1 space-y-1.5 pr-6">
        <ToastTitle
          v-if="toast.title"
          class="break-words text-sm font-medium leading-none tracking-tight [overflow-wrap:anywhere]"
        >
          {{ toast.title }}
        </ToastTitle>
        <ToastDescription
          v-if="toast.description"
          class="whitespace-pre-wrap break-words text-sm leading-snug opacity-90 [overflow-wrap:anywhere]"
        >
          {{ toast.description }}
        </ToastDescription>
        <ToastAction
          v-if="toast.action"
          :alt-text="toast.action.label"
          class="mt-2"
          @click="toast.action.onClick"
        >
          {{ toast.action.label }}
        </ToastAction>
      </div>

      <ToastClose :class="getCloseClass(toast.type)" />
    </Toast>
    <ToastViewport />
  </ToastProvider>
</template>
