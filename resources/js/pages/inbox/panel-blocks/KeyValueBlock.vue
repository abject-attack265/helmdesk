<!--
  业务面板 key_value 积木块渲染器：逐行 label + value，按 value_type 决定 value 呈现方式
  （date 见 formatPanelDate；money / link / badge 分别样式化，badge 用中性色）。消费 ContactPanelBlockData。
-->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { useDateTime } from '@/composables/useDateTime';
import type { ContactPanelBlockData } from '@/types/generated';

const props = defineProps<{ block: ContactPanelBlockData }>();

const { formatDateTime } = useDateTime();

const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

/**
 * date 行的值由业务系统自行给出，可能是纯日期也可能是带时刻的 ISO 串。
 * 纯日期没有对应的时刻，不能做时区换算：dayjs 会按浏览器本地午夜解析它，
 * 再转到用户时区就会在浏览器偏移大于用户时区偏移时整体退一天，故原样渲染。
 */
function formatPanelDate(value: string): string {
  const trimmed = value.trim();

  return DATE_ONLY_PATTERN.test(trimmed)
    ? trimmed
    : formatDateTime(value, 'YYYY-MM-DD');
}
</script>

<template>
  <div class="space-y-2">
    <div
      v-if="props.block.title"
      class="text-xs font-medium text-muted-foreground"
    >
      {{ props.block.title }}
    </div>
    <div class="space-y-1.5">
      <div
        v-for="(row, index) in props.block.rows"
        :key="index"
        class="grid grid-cols-[5.5rem_minmax(0,1fr)] items-start gap-2"
      >
        <div class="min-w-0 truncate text-xs text-muted-foreground">
          {{ row.label }}
        </div>
        <div class="min-w-0 text-sm">
          <Badge
            v-if="row.value_type === 'badge'"
            variant="secondary"
            class="font-normal"
          >
            {{ row.value }}
          </Badge>
          <a
            v-else-if="row.value_type === 'link'"
            :href="row.value"
            target="_blank"
            rel="noopener noreferrer"
            class="break-all text-foreground underline underline-offset-2 hover:opacity-80"
          >
            {{ row.value }}
          </a>
          <span v-else-if="row.value_type === 'date'" class="text-foreground">
            {{ formatPanelDate(row.value) }}
          </span>
          <span
            v-else-if="row.value_type === 'money'"
            class="font-medium text-foreground tabular-nums"
          >
            {{ row.value }}
          </span>
          <span v-else class="break-words text-foreground">
            {{ row.value }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
