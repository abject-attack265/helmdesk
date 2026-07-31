<!--
  文件说明：个人设置页面，消费后端设置数据并提交用户偏好表单。
-->
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useI18n } from '@/composables/useI18n';
import { settingsPageLayout } from '@/layouts/pageLayouts';
import { Monitor, Moon, Sun } from '@lucide/vue';

defineOptions({ layout: settingsPageLayout });

const { t } = useI18n();
const { appearance, updateAppearance } = useAppearance();

const tabs = computed(
  () =>
    [
      { value: 'light', Icon: Sun, label: t('浅色') },
      { value: 'dark', Icon: Moon, label: t('深色') },
      { value: 'system', Icon: Monitor, label: t('跟随系统') },
    ] as const,
);
</script>

<template>
  <div class="contents">
    <Head :title="t('外观设置')" />

    <div class="space-y-6">
      <HeadingSmall
        :title="t('外观设置')"
        :description="t('更新你账户的外观设置')"
      />
      <div
        class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
      >
        <button
          v-for="tab in tabs"
          :key="tab.value"
          @click="updateAppearance(tab.value)"
          :class="[
            'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
            appearance === tab.value
              ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
              : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
          ]"
        >
          <component :is="tab.Icon" class="-ml-1 h-4 w-4" />
          <span class="ml-1.5 text-sm">{{ tab.label }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
