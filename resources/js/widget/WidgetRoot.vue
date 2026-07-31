<!--
  文件说明：网站渠道小部件 iframe 根组件，复用独立访客端聊天画布，并建立与宿主页的 postMessage 桥接。
-->
<script setup lang="ts">
import StandaloneCanvas from '@/components/channel/StandaloneCanvas.vue';
import {
  createWidgetCredentials,
  RECEPTION_CREDENTIALS_INJECTION_KEY,
} from '@/standalone/receptionCredentials';
import type { PublicStandaloneChannelData } from '@/types/generated';
import { provide } from 'vue';
import {
  useWidgetHostBridge,
  WIDGET_HOST_BRIDGE_INJECTION_KEY,
} from './useWidgetHostBridge';

const props = defineProps<{
  channel: PublicStandaloneChannelData;
}>();

const hostBridge = useWidgetHostBridge();
provide(WIDGET_HOST_BRIDGE_INJECTION_KEY, hostBridge);
// Widget 凭证：签名身份与业务参数来自宿主页 postMessage 下发的 host context。
provide(
  RECEPTION_CREDENTIALS_INJECTION_KEY,
  createWidgetCredentials(hostBridge),
);
</script>

<template>
  <div class="fixed inset-0 flex bg-background">
    <StandaloneCanvas
      :channel="props.channel"
      entry-mode="widget"
      interactive
    />
  </div>
</template>
