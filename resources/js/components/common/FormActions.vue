<!--
  表单操作栏通用组件，提供提交按钮、操作插槽和可选的取消/返回链接。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import { Link } from '@inertiajs/vue3';

withDefaults(
  defineProps<{
    submitLabel: string;
    cancelHref?: string;
    cancelLabel?: string;
    processing?: boolean;
    submitDisabled?: boolean;
    cancelDisabled?: boolean;
    submitDataTest?: string;
  }>(),
  {
    cancelHref: undefined,
    cancelLabel: undefined,
    processing: false,
    submitDisabled: false,
    cancelDisabled: false,
    submitDataTest: undefined,
  },
);

const { t } = useI18n();
</script>

<template>
  <div class="flex flex-wrap items-center gap-4">
    <Button
      type="submit"
      :disabled="processing || submitDisabled"
      :data-test="submitDataTest"
    >
      <slot name="submit">{{ submitLabel }}</slot>
    </Button>

    <slot />

    <Button
      v-if="cancelHref"
      variant="outline"
      as-child
      :disabled="processing || cancelDisabled"
    >
      <Link :href="cancelHref">
        {{ cancelLabel ?? t('返回') }}
      </Link>
    </Button>
  </div>
</template>
