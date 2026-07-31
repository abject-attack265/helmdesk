<!--
  文件说明：网站渠道详情页右侧常驻实时预览。
  读取贯穿各 tab 的预览草稿，合成访客端公开数据，通过同源 iframe 复用真实渲染出口；
  草稿变化即时经 postMessage 推给 iframe，无需保存即可看到外观变化。
  当前只呈现 PC 端网站嵌入挂件形态。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import {
  CHANNEL_PREVIEW_READY,
  CHANNEL_PREVIEW_RENDER,
  type ChannelPreviewReadyPayload,
} from '@/channel/previewBridge';
import { useChannelPreviewDraft } from '@/composables/useChannelPreviewDraft';
import { useI18n } from '@/composables/useI18n';
import type { PublicStandaloneChannelData } from '@/types/generated';
import { MessageCircle, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const { t } = useI18n();
const draft = useChannelPreviewDraft();

// 预览 iframe 的同源地址（哑壳，渠道草稿由本组件 postMessage 注入）。
const previewUrl = Web.ShowWebChannelPreviewFrameAction.url();
const frameEl = ref<HTMLIFrameElement | null>(null);

// 网站嵌入形态：点击入口像真实挂件一样展开/收起聊天面板。
const widgetOpen = ref(true);

const isUnifiedService = computed(
  () => draft.visitorIdentityMode === 'unified_service',
);
const effectiveSiteName = computed(
  () => (draft.headerEnabled && draft.siteName.trim()) || draft.channelName,
);

// 用草稿合成访客端公开数据；与后端 PublicStandaloneChannelData::fromModel 的取值口径保持一致。
const previewChannel = computed<PublicStandaloneChannelData>(() => ({
  code: draft.code,
  site_name: effectiveSiteName.value,
  subtitle:
    draft.headerEnabled && draft.subtitle.trim() ? draft.subtitle.trim() : null,
  assistant_name:
    isUnifiedService.value && draft.serviceDisplayName.trim()
      ? draft.serviceDisplayName.trim()
      : effectiveSiteName.value,
  assistant_avatar_url: isUnifiedService.value ? draft.serviceAvatarUrl : null,
  icon_url: draft.iconUrl,
  greeting_message: draft.greetingMessage.trim() || null,
  theme_color: draft.themeColor,
  home_mode_enabled: draft.homeModeEnabled,
  home_welcome_message: draft.homeModeEnabled
    ? draft.homeWelcomeMessage.trim() || null
    : null,
  header: { enabled: draft.headerEnabled },
  composer: { placeholder: draft.composerPlaceholder.trim() || null },
  suggestions: {
    enabled: draft.suggestionsEnabled,
    items: draft.suggestionItems,
  },
  entry: null,
  unread_badge_enabled: null,
  inline_toast_enabled: null,
  mobile_fullscreen_enabled: null,
  paused: false,
}));

// 实时预览不连后端：启用演示模式，输入区可交互、本地回显消息与附件以查看气泡样式。
const demo = true;

// home 模式切换时重置 iframe 内 StandaloneCanvas 的视图状态。
const resetKey = computed(
  () => `widget-desktop-${draft.homeModeEnabled ? 'home' : 'thread'}`,
);

const entrySize = computed(() => {
  if (draft.entryIconSize === 'small') return 36;
  if (draft.entryIconSize === 'medium') return 48;
  return 52;
});

// 把当前草稿合成的一帧推给 iframe；iframe 未就绪时静默跳过，握手完成后会补发。
function postRender(): void {
  const target = frameEl.value?.contentWindow;
  if (!target) {
    return;
  }

  target.postMessage(
    {
      type: CHANNEL_PREVIEW_RENDER,
      // 结构化克隆无法携带 Vue reactive 代理，先转成纯对象再发送。
      channel: JSON.parse(JSON.stringify(previewChannel.value)),
      demo,
      resetKey: resetKey.value,
    },
    window.location.origin,
  );
}

// iframe 挂载完成后会发来就绪通知，此时下发首帧。
function handleMessage(event: MessageEvent): void {
  if (event.origin !== window.location.origin) {
    return;
  }

  const data = event.data as ChannelPreviewReadyPayload | undefined;
  if (data?.type === CHANNEL_PREVIEW_READY) {
    postRender();
  }
}

// 草稿或视图键变化时实时推送给 iframe。
watch([previewChannel, resetKey], () => postRender(), { deep: true });

onMounted(() => {
  window.addEventListener('message', handleMessage);
});

onBeforeUnmount(() => {
  window.removeEventListener('message', handleMessage);
});

// 自定义入口图标需成对出现：展开态用选中图标、收起态用默认图标。
const isCustomEntryMode = computed(() => draft.entryMode === 'custom');
const hasCustomEntryIcons = computed(
  () =>
    !isCustomEntryMode.value &&
    draft.entryStyle === 'custom' &&
    Boolean(draft.entryDefaultIconUrl) &&
    Boolean(draft.entrySelectedIconUrl),
);
const activeEntryIconUrl = computed<string | null>(() => {
  if (!hasCustomEntryIcons.value) {
    return null;
  }

  return widgetOpen.value
    ? draft.entrySelectedIconUrl
    : draft.entryDefaultIconUrl;
});

// 默认气泡入口示意：尺寸跟随入口配置（实际入口由宿主页脚本注入，这里仅作样式预览）。
// 自定义图标直接作为入口本身，去掉主题色圆圈背景与阴影；系统图标则保留圆形气泡。
const entryButtonClass = computed(() =>
  activeEntryIconUrl.value ? 'rounded-full' : 'rounded-full shadow-lg',
);
const entryStyle = computed(() => ({
  width: `${entrySize.value}px`,
  height: `${entrySize.value}px`,
  backgroundColor: activeEntryIconUrl.value ? 'transparent' : draft.themeColor,
}));
// 系统图标显示为入口内的小图标；自定义图标铺满整个入口。
const entryIconSize = computed(() =>
  Math.max(18, Math.min(28, Math.floor(entrySize.value * 0.5))),
);
const widgetAlignClass = computed(() =>
  draft.entryPosition === 'left' ? 'items-start' : 'items-end',
);
// 舞台内挂件整体贴向对应底角（默认右下），更贴近真实网站嵌入的悬浮位置。
const stageJustifyClass = computed(() =>
  draft.entryPosition === 'left' ? 'justify-start' : 'justify-end',
);
</script>

<template>
  <div class="space-y-3">
    <!-- 舞台背景区：在预览外围留出更大的浅灰底，模拟网站嵌入在访客端的观感。 -->
    <div
      class="flex rounded-2xl border bg-muted/40 px-6 py-10 sm:px-10 sm:py-12"
      :class="stageJustifyClass"
    >
      <div
        class="flex h-[660px] w-full max-w-[400px] flex-col justify-end gap-3"
        :class="widgetAlignClass"
      >
        <div
          v-show="widgetOpen"
          class="h-[560px] w-full overflow-hidden rounded-2xl border bg-card shadow-lg"
        >
          <iframe
            ref="frameEl"
            :src="previewUrl"
            :title="t('实时预览')"
            class="h-full w-full border-0"
            @load="postRender"
          ></iframe>
        </div>
        <button
          v-if="isCustomEntryMode"
          type="button"
          class="shrink-0 rounded-full border bg-background px-4 py-2 text-sm font-medium text-foreground shadow-sm transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
          :aria-label="widgetOpen ? t('收起聊天') : t('展开聊天')"
          :title="widgetOpen ? t('收起聊天') : t('展开聊天')"
          @click="widgetOpen = !widgetOpen"
        >
          {{ t('客户自有按钮') }}
        </button>
        <button
          v-else
          type="button"
          class="flex shrink-0 items-center justify-center text-white transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
          :class="entryButtonClass"
          :style="entryStyle"
          :aria-label="widgetOpen ? t('收起聊天') : t('展开聊天')"
          :title="widgetOpen ? t('收起聊天') : t('展开聊天')"
          @click="widgetOpen = !widgetOpen"
        >
          <img
            v-if="activeEntryIconUrl"
            :src="activeEntryIconUrl"
            :alt="widgetOpen ? t('收起聊天') : t('展开聊天')"
            class="h-full w-full object-contain"
          />
          <X
            v-else-if="widgetOpen"
            :style="{
              width: `${entryIconSize}px`,
              height: `${entryIconSize}px`,
            }"
          />
          <MessageCircle
            v-else
            :style="{
              width: `${entryIconSize}px`,
              height: `${entryIconSize}px`,
            }"
          />
        </button>
      </div>
    </div>
  </div>
</template>
