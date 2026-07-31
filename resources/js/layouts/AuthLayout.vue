<!--
  文件说明：认证页面的标题、说明与单列内容布局。
-->
<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

defineProps<{
  title?: string;
  description?: string;
}>();
</script>

<template>
  <AuthLayout :title="title" :description="description">
    <slot />
  </AuthLayout>
</template>
