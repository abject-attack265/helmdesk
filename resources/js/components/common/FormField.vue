<!--
  表单字段通用布局组件，组合 Label、插槽内容和 InputError，统一表单字段的间距与错误提示样式。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Label } from '@/components/ui/label';

withDefaults(
  defineProps<{
    label: string;
    labelFor?: string;
    error?: string;
    help?: string;
    required?: boolean;
  }>(),
  {
    labelFor: undefined,
    error: undefined,
    help: undefined,
    required: false,
  },
);
</script>

<template>
  <div class="grid gap-2">
    <Label :for="labelFor" :required="required">{{ label }}</Label>
    <slot />
    <p v-if="help" class="text-sm text-muted-foreground">
      {{ help }}
    </p>
    <InputError class="mt-2" :message="error" />
  </div>
</template>
