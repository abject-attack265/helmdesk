<!--
  文件说明：网站渠道列表里的「最近使用网站」单元格，紧凑展示 host + 相对时间。
-->
<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { computed } from 'vue';

const props = defineProps<{
  host: string | null;
  at: string | null;
}>();

const { t } = useI18n();
const { formatRelativeShortWithTooltip } = useDateTime();

const time = computed(() => {
  if (!props.at) {
    return null;
  }
  return formatRelativeShortWithTooltip(props.at);
});
</script>

<template>
  <template v-if="host && time">
    <code class="font-mono text-foreground">{{ host }}</code>
    <span class="ml-1 text-xs text-muted-foreground" :title="time.full">
      · {{ time.short }}
    </span>
  </template>
  <span v-else class="text-xs text-muted-foreground">{{ t('尚未使用') }}</span>
</template>
