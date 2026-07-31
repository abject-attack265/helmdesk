<!--
  文件说明：网站渠道嵌入状态指示器，单行显示小部件最近一次加载情况。未嵌入时不渲染。
-->
<script setup lang="ts">
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import type { WebChannelData } from '@/types/generated';
import { computed } from 'vue';

const props = defineProps<{
  channel: WebChannelData;
}>();

const { t } = useI18n();
const { formatRelativeShortWithTooltip } = useDateTime();

const isEmbedded = computed(
  () =>
    typeof props.channel.last_embed_host === 'string' &&
    props.channel.last_embed_host.length > 0 &&
    typeof props.channel.last_embed_at === 'string' &&
    props.channel.last_embed_at.length > 0,
);

const lastEmbedTime = computed(() => {
  if (!props.channel.last_embed_at) {
    return null;
  }
  return formatRelativeShortWithTooltip(props.channel.last_embed_at);
});
</script>

<template>
  <span
    v-if="isEmbedded"
    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
    :title="
      lastEmbedTime ? `${t('最近一次加载')} · ${lastEmbedTime.full}` : undefined
    "
  >
    <code class="font-mono text-foreground">{{
      props.channel.last_embed_host
    }}</code>
    <span v-if="lastEmbedTime">· {{ lastEmbedTime.short }}</span>
  </span>
</template>
