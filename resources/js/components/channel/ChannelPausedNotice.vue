<!--
  文件说明：网站渠道已暂停时的访客占位页，独立页和小部件 iframe 共用。
-->
<script setup lang="ts">
import { useStandaloneI18n } from '@/standalone/i18n';
import type { PublicStandaloneChannelData } from '@/types/generated';

defineProps<{
  channel: PublicStandaloneChannelData;
}>();

const { t } = useStandaloneI18n();
</script>

<template>
  <div
    class="flex h-full w-full flex-col items-center justify-center gap-4 px-6 text-center"
  >
    <img
      v-if="channel.icon_url"
      :src="channel.icon_url"
      :alt="channel.site_name"
      class="size-14 rounded-full object-cover"
    />

    <div class="space-y-1">
      <p class="text-base font-medium text-foreground">
        {{ channel.site_name }}
      </p>
      <p class="text-sm text-muted-foreground">
        {{ t('服务暂时不可用') }}
      </p>
    </div>

    <p class="max-w-sm text-xs text-muted-foreground">
      {{ t('我们正在调整这个入口，请稍后再来。') }}
    </p>
  </div>
</template>
