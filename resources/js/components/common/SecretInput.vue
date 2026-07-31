<!--
  敏感值输入框：默认以密码圆点隐藏内容，点击眼睛图标在明文/密文间切换。
  透传 name / placeholder / required 等原生属性给内部 Input，支持 v-model 与 default-value 两种用法。
-->
<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import { Eye, EyeOff } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
  defaultValue?: string | number;
  modelValue?: string | number;
  class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
  (e: 'update:modelValue', payload: string | number): void;
}>();

const { t } = useI18n();
const revealed = ref(false);
</script>

<template>
  <div :class="cn('relative', props.class)">
    <Input
      v-bind="$attrs"
      :type="revealed ? 'text' : 'password'"
      :default-value="props.defaultValue"
      :model-value="props.modelValue"
      class="w-full pr-10"
      @update:model-value="(value) => emits('update:modelValue', value)"
    />
    <button
      type="button"
      class="absolute inset-y-0 right-0 flex items-center px-3 text-muted-foreground hover:text-foreground"
      :aria-label="revealed ? t('隐藏') : t('显示')"
      @click="revealed = !revealed"
    >
      <EyeOff v-if="revealed" class="h-4 w-4" />
      <Eye v-else class="h-4 w-4" />
    </button>
  </div>
</template>
