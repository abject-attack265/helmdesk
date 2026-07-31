<!--
  文件说明：应用后台的根布局，提供侧边栏和主内容区域。
-->
<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';

defineOptions({ inheritAttrs: false });

interface Props {
  hideHeader?: boolean;
}

withDefaults(defineProps<Props>(), {
  hideHeader: false,
});
</script>

<template>
  <AppLayout :hide-header="hideHeader">
    <slot />
  </AppLayout>
</template>
