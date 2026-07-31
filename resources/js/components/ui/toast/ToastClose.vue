<script setup lang="ts">
import { ToastClose, type ToastCloseProps } from 'reka-ui';
import { X } from '@lucide/vue';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<
  ToastCloseProps & { class?: HTMLAttributes['class'] }
>();

const delegatedProps = computed(() => {
  const delegated = { ...props };
  delete delegated.class;

  return delegated;
});
</script>

<template>
  <ToastClose
    v-bind="delegatedProps"
    :class="
      cn(
        'absolute right-3 top-2 rounded-md p-1 text-foreground/40 transition-all hover:bg-black/5 hover:text-foreground focus:outline-none focus:ring-2 focus:ring-offset-0 dark:hover:bg-white/5',
        props.class,
      )
    "
  >
    <X class="size-4" />
  </ToastClose>
</template>
