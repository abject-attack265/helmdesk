<script setup lang="ts">
import { ToastDescription, type ToastDescriptionProps } from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<
  ToastDescriptionProps & { class?: HTMLAttributes['class'] }
>();

const delegatedProps = computed(() => {
  const delegated = { ...props };
  delete delegated.class;

  return delegated;
});
</script>

<template>
  <ToastDescription
    v-bind="delegatedProps"
    :class="cn('text-sm opacity-90', props.class)"
  >
    <slot />
  </ToastDescription>
</template>
