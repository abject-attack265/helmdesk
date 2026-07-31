<!--
  网站渠道详情页，使用 ShowWebChannelDetailPagePropsData 管理渠道设置并预览访客端效果。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import ChannelLivePreview from '@/components/channel/ChannelLivePreview.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import {
  createChannelPreviewDraft,
  provideChannelPreviewDraft,
} from '@/composables/useChannelPreviewDraft';
import { useI18n } from '@/composables/useI18n';
import { useUrlTab } from '@/composables/useUrlTab';
import AppLayout from '@/layouts/AppLayout.vue';
import AccessTab from '@/pages/channel/web/tabs/AccessTab.vue';
import BasicTab from '@/pages/channel/web/tabs/BasicTab.vue';
import EntryDeviceTab from '@/pages/channel/web/tabs/EntryDeviceTab.vue';
import ParamMappingTab from '@/pages/channel/web/tabs/ParamMappingTab.vue';
import VisitorInterfaceTab from '@/pages/channel/web/tabs/VisitorInterfaceTab.vue';
import type { ShowWebChannelDetailPagePropsData } from '@/types/generated';
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ layout: AppLayout });
const props = defineProps<ShowWebChannelDetailPagePropsData>();
const { t } = useI18n();

type GuardedTabForm = {
  hasUnsavedChanges: () => boolean;
  isProcessing: () => boolean;
};

const basicTabRef = ref<GuardedTabForm | null>(null);
const visitorInterfaceTabRef = ref<GuardedTabForm | null>(null);
const accessTabRef = ref<GuardedTabForm | null>(null);
const entryDeviceTabRef = ref<GuardedTabForm | null>(null);
const paramMappingTabRef = ref<GuardedTabForm | null>(null);

/** 返回当前页面内已经挂载的设置表单。 */
function guardedForms(): GuardedTabForm[] {
  return [
    basicTabRef.value,
    visitorInterfaceTabRef.value,
    accessTabRef.value,
    entryDeviceTabRef.value,
    paramMappingTabRef.value,
  ].filter((form): form is GuardedTabForm => form !== null);
}

/** 离开前确认是否放弃尚未保存的渠道设置。 */
function confirmLeaveIfDirty(): boolean {
  if (guardedForms().some((form) => form.isProcessing())) {
    return false;
  }
  if (!guardedForms().some((form) => form.hasUnsavedChanges())) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

/** 刷新或关闭页面时交由浏览器提示未保存内容。 */
function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (
    !guardedForms().some(
      (form) => form.hasUnsavedChanges() || form.isProcessing(),
    )
  ) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

let removeBeforeListener: (() => void) | null = null;

onMounted(() => {
  removeBeforeListener = router.on('before', (event) => {
    if (event.detail.visit.method === 'get' && !confirmLeaveIfDirty()) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
});

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('网站'),
    href: Web.ListWebChannelsAction.url(),
  },
  { title: props.web_channel.name },
]);

// 各设置页签共享预览内容，修改后立即更新右侧预览。
const previewDraft = createChannelPreviewDraft(props.web_channel);
provideChannelPreviewDraft(previewDraft);

type TabKey =
  'basic' | 'visitor-interface' | 'access' | 'entry-device' | 'params';

const TAB_VALUES = [
  'basic',
  'visitor-interface',
  'access',
  'entry-device',
  'params',
] as const satisfies readonly TabKey[];

const activeTab = useUrlTab<TabKey>('tab', {
  defaultValue: 'basic',
  valid: TAB_VALUES,
});

const tabs = computed<{ value: TabKey; label: string }[]>(() => [
  { value: 'basic', label: t('基本信息') },
  { value: 'visitor-interface', label: t('聊天界面') },
  { value: 'access', label: t('使用方式') },
  { value: 'entry-device', label: t('聊天入口') },
  { value: 'params', label: t('访客信息') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="props.web_channel.name" />

    <div class="px-4 py-6 sm:px-6">
      <PageBreadcrumb :items="breadcrumbItems" class="mb-6" />

      <div class="space-y-6">
        <HeadingSmall
          :title="props.web_channel.name"
          :description="t('设置访客如何进入聊天，以及聊天页面显示什么。')"
        />

        <div class="border-b border-border">
          <nav class="-mb-px flex flex-wrap gap-6">
            <button
              v-for="tab in tabs"
              :key="tab.value"
              type="button"
              class="relative -mb-px border-b-2 px-1 pb-3 text-base font-semibold transition-colors"
              :class="
                activeTab === tab.value
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              "
              :aria-pressed="activeTab === tab.value"
              @click="activeTab = tab.value"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_27rem]">
          <div class="min-w-0">
            <BasicTab
              v-show="activeTab === 'basic'"
              ref="basicTabRef"
              :channel="props.web_channel"
              :form-options="props.form_options"
            />
            <VisitorInterfaceTab
              v-show="activeTab === 'visitor-interface'"
              ref="visitorInterfaceTabRef"
              :channel="props.web_channel"
              :form-options="props.form_options"
            />
            <AccessTab
              v-show="activeTab === 'access'"
              ref="accessTabRef"
              :channel="props.web_channel"
            />
            <EntryDeviceTab
              v-show="activeTab === 'entry-device'"
              ref="entryDeviceTabRef"
              :channel="props.web_channel"
              :form-options="props.form_options"
            />
            <ParamMappingTab
              v-show="activeTab === 'params'"
              ref="paramMappingTabRef"
              :channel="props.web_channel"
              :form-options="props.form_options"
            />
          </div>

          <aside class="xl:sticky xl:top-6 xl:self-start">
            <div class="space-y-3">
              <p class="text-sm font-medium">{{ t('实时预览') }}</p>
              <ChannelLivePreview />
            </div>
          </aside>
        </div>
      </div>
    </div>
  </div>
</template>
