<!--
  文件说明：个人设置页面，消费后端设置数据并提交用户偏好表单。
-->
<script setup lang="ts">
import UpdateLanguageSettingsAction from '@/actions/App/Actions/User/UpdateLanguageSettingsAction';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { useI18n } from '@/composables/useI18n';
import { useTimezone, type Timezone } from '@/composables/useTimezone';
import { settingsPageLayout } from '@/layouts/pageLayouts';
import { availableLocales, type Locale } from '@/locales';
import type { FormUpdateLanguageSettingsData } from '@/types/generated';

defineOptions({ layout: settingsPageLayout });

const { locale, updateLocale, t } = useI18n();
const { timezone, updateTimezone, getTimezones, getCurrentTimezoneInfo } =
  useTimezone();
const form = useForm<FormUpdateLanguageSettingsData>({
  locale: locale.value,
  timezone: timezone.value,
});

const currentLanguageLabel = computed(() => {
  const current = availableLocales.find((l) => l.value === locale.value);
  return current?.label || locale.value;
});

const timezoneSearch = ref('');
const timezones = computed(() => {
  const list = getTimezones(locale.value);
  const q = timezoneSearch.value.trim().toLowerCase();
  if (!q) return list;
  return list.filter((tz) => {
    const hay = `${tz.label} ${tz.value} ${tz.offset}`.toLowerCase();
    return hay.includes(q);
  });
});

const currentTimezoneLabel = computed(
  () => getCurrentTimezoneInfo(locale.value).label,
);

function handleLanguageChange(value: any) {
  if (typeof value !== 'string') return;
  updateLocale(value as Locale);
  form.locale = value as Locale;
  persistPreferences();
}

function handleTimezoneChange(value: any) {
  if (typeof value !== 'string') return;
  updateTimezone(value as Timezone);
  form.timezone = value;
  persistPreferences();
}

function persistPreferences() {
  form.put(UpdateLanguageSettingsAction.url(), {
    preserveScroll: true,
  });
}
</script>

<template>
  <div class="contents">
    <Head :title="t('语言和时区设置')" />

    <div class="flex flex-col space-y-6">
      <HeadingSmall
        :title="t('语言偏好')"
        :description="t('选择你的首选语言')"
      />

      <div class="space-y-4">
        <div class="grid gap-2">
          <Label for="language-select" required>{{ t('选择语言') }}</Label>
          <Select
            :model-value="locale"
            @update:model-value="handleLanguageChange"
          >
            <SelectTrigger id="language-select" class="w-full">
              <SelectValue>
                {{ currentLanguageLabel }}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="lang in availableLocales"
                :key="lang.value"
                :value="lang.value"
              >
                {{ lang.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      <Separator class="my-6" />

      <HeadingSmall
        :title="t('时区设置')"
        :description="t('选择你的时区，用于正确显示时间')"
      />

      <div class="space-y-4">
        <div class="grid gap-2">
          <Label for="timezone-select" required>{{ t('选择时区') }}</Label>
          <Select
            :model-value="timezone"
            @update:model-value="handleTimezoneChange"
          >
            <SelectTrigger id="timezone-select" class="w-full">
              <SelectValue>
                {{ currentTimezoneLabel }}
              </SelectValue>
            </SelectTrigger>
            <SelectContent>
              <template #header>
                <div class="border-b bg-popover p-2">
                  <Input v-model="timezoneSearch" @keydown.stop />
                </div>
              </template>
              <SelectItem
                v-for="tz in timezones"
                :key="tz.value"
                :value="tz.value"
              >
                {{ tz.label }}
              </SelectItem>
              <div
                v-if="timezones.length === 0"
                class="px-3 py-2 text-sm text-muted-foreground"
              >
                {{ t('未找到匹配的时区') }}
              </div>
            </SelectContent>
          </Select>
        </div>
      </div>
    </div>
  </div>
</template>
