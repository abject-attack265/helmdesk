<script setup lang="ts">
import {
  ToastRoot,
  type ToastRootEmits,
  type ToastRootProps,
  useForwardPropsEmits,
} from 'reka-ui';
import { computed, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<
  ToastRootProps & { class?: HTMLAttributes['class'] }
>();
const emits = defineEmits<ToastRootEmits>();

const delegatedProps = computed(() => {
  const delegated = { ...props };
  delete delegated.class;

  return delegated;
});

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <ToastRoot
    v-bind="forwarded"
    :class="
      cn(
        'group pointer-events-auto relative flex w-full items-start gap-3 overflow-hidden rounded-lg border p-4 shadow-xl transition-all',
        'data-[swipe=cancel]:translate-x-0',
        'data-[swipe=end]:translate-x-(--reka-toast-swipe-end-x)',
        'data-[swipe=move]:translate-x-(--reka-toast-swipe-move-x)',
        'data-[swipe=move]:transition-none',
        'data-[state=open]:animate-in data-[state=open]:slide-in-from-top-2 data-[state=open]:fade-in-0',
        'data-[state=closed]:animate-out data-[state=closed]:slide-out-to-right-full data-[state=closed]:fade-out-0',
        'data-[swipe=end]:animate-out data-[swipe=end]:slide-out-to-right-full',
        props.class,
      )
    "
  >
    <slot />
  </ToastRoot>
</template>
