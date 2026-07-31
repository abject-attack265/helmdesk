<!--
  收件箱快捷回复选择器消费 CannedReplyComposerItemData，
  支持搜索和键盘选择，并把渲染结果交给回复输入框。
-->
<script setup lang="ts">
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { useI18n } from '@/composables/useI18n';
import cannedReplyRoutes from '@/routes/app/canned-replies';
import type {
  CannedReplyComposerItemData,
  RenderedCannedReplyData,
} from '@/types/generated';
import axios from 'axios';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
  open: boolean;
  conversationId: string | null;
  query: string;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  rendered: [payload: RenderedCannedReplyData];
}>();

const { t } = useI18n();

const items = ref<CannedReplyComposerItemData[]>([]);
const activeIndex = ref(0);
const loading = ref(false);
const usingId = ref<string | null>(null);
const searchFailed = ref(false);
const renderFailed = ref(false);
let searchRequestToken = 0;
let renderRequestToken = 0;
let searchController: AbortController | null = null;
let renderController: AbortController | null = null;

const visibleItems = computed(() => items.value);

const open = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
});

function reportCannedReplyRequestFailure(
  stage: 'search' | 'render',
  conversationId: string | null,
  error: unknown,
): void {
  console.warn('[inbox-canned-reply] 快捷回复请求失败', {
    conversationId,
    stage,
    status: axios.isAxiosError(error) ? error.response?.status : undefined,
    code: axios.isAxiosError(error) ? error.code : undefined,
    errorType: axios.isAxiosError(error)
      ? 'AxiosError'
      : error instanceof Error
        ? error.name
        : typeof error,
  });
}

function isRequestCancellation(error: unknown): boolean {
  return (
    axios.isCancel(error) ||
    (error instanceof DOMException && error.name === 'AbortError')
  );
}

function isCannedReplyItem(
  value: unknown,
): value is CannedReplyComposerItemData {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const item = value as Record<string, unknown>;

  return (
    typeof item.id === 'string' &&
    typeof item.name === 'string' &&
    (item.shortcut === null || typeof item.shortcut === 'string') &&
    typeof item.content === 'string' &&
    typeof item.is_personal === 'boolean' &&
    typeof item.usage_count === 'number' &&
    Number.isFinite(item.usage_count) &&
    (item.last_used_at === null || typeof item.last_used_at === 'string')
  );
}

function isRenderedCannedReply(
  value: unknown,
  replyId: string,
): value is RenderedCannedReplyData {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const result = value as Record<string, unknown>;

  return (
    result.id === replyId &&
    typeof result.rendered_content === 'string' &&
    typeof result.original_content === 'string' &&
    Array.isArray(result.warnings) &&
    result.warnings.every((warning) => typeof warning === 'string') &&
    typeof result.usage_count === 'number' &&
    Number.isFinite(result.usage_count) &&
    (result.last_used_at === null || typeof result.last_used_at === 'string')
  );
}

function cancelSearch(): void {
  searchRequestToken += 1;
  searchController?.abort();
  searchController = null;
  loading.value = false;
}

function cancelRender(): void {
  renderRequestToken += 1;
  renderController?.abort();
  renderController = null;
  usingId.value = null;
  renderFailed.value = false;
}

async function search(
  rawQuery: string,
  conversationId: string | null,
): Promise<void> {
  cancelSearch();
  const requestToken = searchRequestToken;
  const controller = new AbortController();
  searchController = controller;
  loading.value = true;
  searchFailed.value = false;

  try {
    const response = await axios.get<{ items: CannedReplyComposerItemData[] }>(
      cannedReplyRoutes.search.url(),
      {
        params: {
          q: rawQuery,
          limit: 10,
        },
        signal: controller.signal,
      },
    );

    if (
      requestToken !== searchRequestToken ||
      controller.signal.aborted ||
      !props.open ||
      props.query !== rawQuery ||
      props.conversationId !== conversationId
    ) {
      return;
    }
    if (
      !Array.isArray(response.data.items) ||
      !response.data.items.every(isCannedReplyItem)
    ) {
      throw new Error('快捷回复搜索响应格式无效');
    }

    items.value = response.data.items;
    activeIndex.value = 0;
  } catch (error: unknown) {
    if (
      requestToken === searchRequestToken &&
      !controller.signal.aborted &&
      !isRequestCancellation(error)
    ) {
      items.value = [];
      searchFailed.value = true;
      reportCannedReplyRequestFailure('search', conversationId, error);
    }
  } finally {
    if (requestToken === searchRequestToken) {
      searchController = null;
      loading.value = false;
    }
  }
}

async function useReply(reply: CannedReplyComposerItemData): Promise<void> {
  const conversationId = props.conversationId;
  if (usingId.value !== null || !props.open || conversationId === null) {
    return;
  }

  const requestToken = ++renderRequestToken;
  const controller = new AbortController();
  renderController = controller;
  usingId.value = reply.id;
  renderFailed.value = false;

  try {
    const response = await axios.post<RenderedCannedReplyData>(
      cannedReplyRoutes.useAndRender.url({
        cannedReply: reply.id,
      }),
      {
        conversation_id: conversationId,
      },
      { signal: controller.signal },
    );

    if (
      requestToken !== renderRequestToken ||
      controller.signal.aborted ||
      !props.open ||
      props.conversationId !== conversationId
    ) {
      return;
    }
    if (!isRenderedCannedReply(response.data, reply.id)) {
      throw new Error('快捷回复渲染响应格式无效');
    }

    emit('rendered', response.data);
    emit('update:open', false);
  } catch (error: unknown) {
    if (
      requestToken === renderRequestToken &&
      !controller.signal.aborted &&
      !isRequestCancellation(error)
    ) {
      renderFailed.value = true;
      reportCannedReplyRequestFailure('render', conversationId, error);
    }
  } finally {
    if (requestToken === renderRequestToken) {
      renderController = null;
      usingId.value = null;
    }
  }
}

const moveActive = (delta: number) => {
  if (visibleItems.value.length === 0) {
    return;
  }
  const next = activeIndex.value + delta;
  const total = visibleItems.value.length;
  activeIndex.value = ((next % total) + total) % total;
};

const handleKeydown = (event: KeyboardEvent) => {
  if (!props.open) {
    return;
  }

  if (event.key === 'ArrowDown') {
    event.preventDefault();
    moveActive(1);
    return;
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault();
    moveActive(-1);
    return;
  }

  if (event.key === 'Enter' && !event.isComposing) {
    if (visibleItems.value[activeIndex.value]) {
      event.preventDefault();
      useReply(visibleItems.value[activeIndex.value]);
    }
    return;
  }

  if (event.key === 'Escape') {
    event.preventDefault();
    emit('update:open', false);
  }
};

defineExpose({ handleKeydown });

watch(
  () => [props.open, props.query, props.conversationId] as const,
  ([nextOpen, nextQuery, nextConversationId]) => {
    cancelSearch();
    items.value = [];
    activeIndex.value = 0;
    searchFailed.value = false;
    if (!nextOpen) {
      return;
    }

    void search(nextQuery, nextConversationId);
  },
  { immediate: true },
);

watch(
  () => [props.open, props.conversationId, props.query] as const,
  ([nextOpen, nextConversationId, nextQuery], previous) => {
    if (
      !nextOpen ||
      (previous !== undefined &&
        (previous[1] !== nextConversationId || previous[2] !== nextQuery))
    ) {
      cancelRender();
    }
  },
);

onUnmounted(() => {
  cancelSearch();
  cancelRender();
});

const truncate = (text: string, max = 90) => {
  if (text.length <= max) {
    return text;
  }
  return `${text.slice(0, max)}…`;
};
</script>

<template>
  <Popover :open="open" @update:open="emit('update:open', $event)">
    <PopoverTrigger as-child>
      <slot name="trigger" />
    </PopoverTrigger>
    <PopoverContent
      class="w-[28rem] p-0"
      align="start"
      side="top"
      :side-offset="8"
      @open-auto-focus="(event) => event.preventDefault()"
      @close-auto-focus="(event) => event.preventDefault()"
    >
      <div class="border-b px-3 py-2 text-xs text-muted-foreground">
        {{
          query ? t('搜索"{query}"的快捷回复', { query }) : t('选择快捷回复')
        }}
      </div>
      <div class="max-h-72 overflow-y-auto">
        <div
          v-if="loading && visibleItems.length === 0"
          class="px-3 py-6 text-center text-xs text-muted-foreground"
        >
          {{ t('加载中…') }}
        </div>
        <div
          v-else-if="searchFailed"
          class="px-3 py-6 text-center text-xs text-destructive"
        >
          {{ t('加载快捷回复失败') }}
        </div>
        <div
          v-else-if="visibleItems.length === 0"
          class="px-3 py-6 text-center text-xs text-muted-foreground"
        >
          {{ query ? t('没有找到相关快捷回复') : t('还没有快捷回复') }}
        </div>
        <button
          v-for="(item, index) in visibleItems"
          :key="item.id"
          type="button"
          class="flex w-full flex-col gap-1 px-3 py-2 text-left transition-colors hover:bg-muted"
          :class="{ 'bg-muted': index === activeIndex }"
          @mouseenter="activeIndex = index"
          @click="useReply(item)"
        >
          <div class="flex items-center gap-2">
            <span class="font-medium">{{ item.name }}</span>
            <span
              v-if="item.shortcut"
              class="rounded bg-background px-1 font-mono text-[11px] text-muted-foreground"
            >
              /{{ item.shortcut }}
            </span>
            <span
              v-if="item.is_personal"
              class="rounded bg-muted px-1 text-[11px] text-muted-foreground"
            >
              {{ t('仅自己使用') }}
            </span>
            <span
              v-else
              class="rounded bg-muted px-1 text-[11px] text-muted-foreground"
            >
              {{ t('团队共享') }}
            </span>
          </div>
          <span class="line-clamp-2 text-xs text-muted-foreground">
            {{ truncate(item.content, 140) }}
          </span>
        </button>
      </div>
      <div
        class="flex items-center justify-between border-t px-3 py-1.5 text-[11px] text-muted-foreground"
      >
        <span v-if="renderFailed" class="text-destructive">
          {{ t('使用快捷回复失败') }}
        </span>
        <span v-else>{{ t('↑↓ 选择，Enter 确认') }}</span>
        <span v-if="usingId" class="text-primary">
          {{ t('插入中…') }}
        </span>
      </div>
    </PopoverContent>
  </Popover>
</template>
