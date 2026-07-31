<!--
  页面统一的面包屑导航：接收有序路径数组渲染层级。带 href 的层级渲染为 Inertia
  链接，带 onClick 的层级渲染为按钮（用于页内状态切换，如知识库右栏虚拟子页）；
  末项为当前页；两者皆无的中间层级（如仅作侧边栏分组的「接入渠道」「设置」）渲染
  为纯文本。
-->
<script setup lang="ts">
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Link } from '@inertiajs/vue3';

export interface PageBreadcrumbItem {
  title: string;
  href?: string;
  onClick?: () => void;
}

defineProps<{
  items: PageBreadcrumbItem[];
}>();
</script>

<template>
  <Breadcrumb>
    <BreadcrumbList>
      <template v-for="(item, index) in items" :key="index">
        <BreadcrumbItem>
          <BreadcrumbLink v-if="item.href" as-child>
            <Link :href="item.href">{{ item.title }}</Link>
          </BreadcrumbLink>
          <BreadcrumbLink v-else-if="item.onClick" as-child>
            <button type="button" @click="item.onClick">
              {{ item.title }}
            </button>
          </BreadcrumbLink>
          <BreadcrumbPage v-else-if="index === items.length - 1">
            {{ item.title }}
          </BreadcrumbPage>
          <span v-else>{{ item.title }}</span>
        </BreadcrumbItem>
        <BreadcrumbSeparator v-if="index < items.length - 1" />
      </template>
    </BreadcrumbList>
  </Breadcrumb>
</template>
