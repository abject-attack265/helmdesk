<script setup lang="ts">
import { ToastViewport, type ToastViewportProps } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<
  ToastViewportProps & { class?: HTMLAttributes['class'] }
>();

const delegatedProps = computed(() => {
  const delegated = { ...props };
  delete delegated.class;

  return delegated;
});
</script>

<template>
  <ToastViewport
    v-bind="delegatedProps"
    :class="
      cn(
        'fixed right-0 bottom-0 z-100 flex max-h-screen w-full flex-col gap-2 p-4 md:max-w-[420px]',
        props.class,
      )
    "
  />
</template>
