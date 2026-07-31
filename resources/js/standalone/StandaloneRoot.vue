<!--
  文件说明：独立访客端页面代码，承接嵌入式或公开访问场景。
-->
<script setup lang="ts">
import StandaloneCanvas from '@/components/channel/StandaloneCanvas.vue';
import {
  createStandaloneCredentials,
  RECEPTION_CREDENTIALS_INJECTION_KEY,
} from '@/standalone/receptionCredentials';
import type { PublicStandaloneChannelData } from '@/types/generated';
import { onMounted, provide, watch } from 'vue';

const props = defineProps<{
  channel: PublicStandaloneChannelData;
  // 可选签名身份：可通过 bootstrap 注入，也可由凭证从页面 URL 读取。
  userToken?: string | null;
}>();

// 独立页凭证：签名身份取注入值或页面 URL，业务参数取 URL，会话 token 由响应回填。
provide(
  RECEPTION_CREDENTIALS_INJECTION_KEY,
  createStandaloneCredentials(props.userToken),
);

const syncPageTitle = () => {
  if (typeof document === 'undefined') {
    return;
  }

  document.title = props.channel.site_name;
};

onMounted(() => {
  syncPageTitle();
});

watch(() => props.channel.site_name, syncPageTitle);
</script>

<template>
  <div class="fixed inset-0 flex bg-background">
    <StandaloneCanvas :channel="props.channel" interactive />
  </div>
</template>
