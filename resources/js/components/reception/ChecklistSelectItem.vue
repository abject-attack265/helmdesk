<!--
  接待方案选择弹窗中的可选项，展示标题、副标题和选中状态。
-->
<script setup lang="ts">
import { Check } from '@lucide/vue';
import type { Component } from 'vue';

defineProps<{
  /** 标题文案 */
  title: string;
  /** 副标题文案（分类 / 提供方等） */
  subtitle: string | null;
  /** 是否被选中 */
  selected: boolean;
  /** 行首图标组件（不传则不渲染图标位） */
  icon?: Component;
  /** 选中时高亮整行 */
  highlightSelected?: boolean;
  /** 禁止修改选择状态 */
  disabled?: boolean;
}>();

const emit = defineEmits<{
  select: [];
}>();
</script>

<template>
  <div
    :class="[
      'rounded-lg border transition-colors',
      selected
        ? highlightSelected
          ? 'border-foreground bg-accent'
          : 'border-foreground'
        : highlightSelected
          ? 'hover:bg-muted'
          : '',
    ]"
  >
    <button
      type="button"
      class="flex w-full items-center gap-3 px-3 py-2.5 text-left disabled:cursor-not-allowed disabled:opacity-60"
      :aria-pressed="selected"
      :disabled="disabled"
      @click="emit('select')"
    >
      <component
        :is="icon"
        v-if="icon"
        class="h-4 w-4 shrink-0 text-muted-foreground"
      />
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium">{{ title }}</p>
        <p class="text-xs text-muted-foreground">{{ subtitle }}</p>
      </div>
      <Check v-if="selected" class="h-4 w-4 shrink-0" />
    </button>
    <slot />
  </div>
</template>
