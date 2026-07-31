<!--
  文件说明：将侧边栏上下文状态暴露给外壳插槽。
-->
<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';

const sidebar = useSidebar();
</script>

<template>
  <slot v-bind="sidebar" />
</template>
