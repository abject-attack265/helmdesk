<!--
  文件说明：网站渠道实时预览 iframe 的根组件。
  本身不含任何渠道数据，挂载后向父页面（同源）发出就绪通知，再按父页面 postMessage 下发的草稿，
  在隔离文档里复用真实访客出口 StandaloneCanvas 渲染外观；无需保存即可实时反映配置变化。
-->
<script setup lang="ts">
import {
  CHANNEL_PREVIEW_READY,
  CHANNEL_PREVIEW_RENDER,
  type ChannelPreviewRenderPayload,
} from '@/channel/previewBridge';
import StandaloneCanvas from '@/components/channel/StandaloneCanvas.vue';
import type { PublicStandaloneChannelData } from '@/types/generated';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const channel = ref<PublicStandaloneChannelData | null>(null);
const demo = ref(false);
const resetKey = ref('initial');

// 仅接收同源父页面下发的渲染指令，忽略其它来源消息，避免被第三方页面注入数据。
function handleMessage(event: MessageEvent): void {
  if (event.origin !== window.location.origin) {
    return;
  }

  const data = event.data as ChannelPreviewRenderPayload | undefined;
  if (!data || data.type !== CHANNEL_PREVIEW_RENDER) {
    return;
  }

  channel.value = data.channel;
  demo.value = data.demo;
  resetKey.value = data.resetKey;
}

onMounted(() => {
  window.addEventListener('message', handleMessage);
  // 通知父页面 iframe 已就绪，请下发首帧草稿。
  window.parent?.postMessage(
    { type: CHANNEL_PREVIEW_READY },
    window.location.origin,
  );
});

onBeforeUnmount(() => {
  window.removeEventListener('message', handleMessage);
});
</script>

<template>
  <StandaloneCanvas
    v-if="channel"
    :key="resetKey"
    :channel="channel"
    :demo="demo"
  />
</template>
