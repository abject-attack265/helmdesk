<!--
  文件说明：收件箱会话摘要块，展示会话摘要、目标语言译文和会话标签。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { useI18n } from '@/composables/useI18n';
import app from '@/routes/app';
import type {
  ConversationSummaryData,
  ConversationTagData,
  TagOptionData,
} from '@/types/generated';
import axios from 'axios';
import {
  ChevronDown,
  FileText,
  LoaderCircle,
  Plus,
  Sparkles,
  X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
  defineProps<{
    conversation: ConversationSummaryData;
    translationLocale: string;
    translationEnabled: boolean;
    isTranslating?: boolean;
    translationAvailable?: boolean;
    variant?: 'current' | 'boundary';
    availableTags?: TagOptionData[];
  }>(),
  {
    isTranslating: false,
    translationAvailable: false,
    variant: 'boundary',
    availableTags: () => [],
  },
);

const emit = defineEmits<{
  (event: 'translate', force: boolean): void;
}>();

const { t } = useI18n();

type SummaryTranslationPayload = Record<string, { text?: unknown }>;

const expanded = ref(false);
const showOriginal = ref(false);
const tagPopoverOpen = ref(false);
const tagSearch = ref('');
const tagProcessing = ref(false);

const sourceText = computed(() => props.conversation.summary?.trim() ?? '');
const translations = computed<SummaryTranslationPayload>(
  () =>
    (props.conversation.summary_translations ??
      {}) as SummaryTranslationPayload,
);
const translatedText = computed(() => {
  const text = translations.value[props.translationLocale]?.text;

  return typeof text === 'string' && text.trim() !== '' ? text.trim() : null;
});
const hasTranslation = computed(() => translatedText.value !== null);
const translationActive = computed(
  () => props.translationEnabled && hasTranslation.value,
);
const canTranslate = computed(
  () =>
    props.translationEnabled &&
    props.translationAvailable &&
    sourceText.value !== '',
);
// 请求翻译时显示目标语言译文。
function onTranslateClick(): void {
  showOriginal.value = false;
  emit('translate', hasTranslation.value);
}
const displayText = computed(() => {
  if (!translationActive.value || showOriginal.value) {
    return sourceText.value;
  }

  return translatedText.value ?? sourceText.value;
});
const canExpand = computed(
  () => displayText.value.length > 120 || displayText.value.includes('\n'),
);
const title = computed(() =>
  props.variant === 'current' ? t('本次会话总结') : t('会话总结'),
);

// 本地维护标签，attach/detach 走乐观更新；不整体 reload selection，避免触发消息区滚到底。
const localTags = ref<ConversationTagData[]>([
  ...(props.conversation.tags ?? []),
]);
watch(
  () => props.conversation.tags,
  (tags) => {
    localTags.value = [...(tags ?? [])];
  },
);
const conversationTags = computed(() => localTags.value);
const selectedTagIds = computed(() => localTags.value.map((tag) => tag.id));
const canEditTags = computed(() => props.availableTags.length > 0);
const filteredTagOptions = computed(() => {
  const query = tagSearch.value.toLowerCase().trim();
  return props.availableTags
    .filter((option) => !selectedTagIds.value.includes(option.id))
    .filter((option) => !query || option.name.toLowerCase().includes(query));
});

async function attachTag(tagId: string): Promise<void> {
  if (tagProcessing.value) return;
  const option = props.availableTags.find((item) => item.id === tagId);
  if (!option || localTags.value.some((tag) => tag.id === tagId)) return;

  tagProcessing.value = true;
  tagSearch.value = '';
  tagPopoverOpen.value = false;
  // 乐观插入人工标签，请求失败再回滚。
  localTags.value = [
    ...localTags.value,
    {
      id: option.id,
      name: option.name,
      color: option.color,
      source: 'manual',
      source_label: '',
      confidence: null,
      reason: null,
    },
  ];
  try {
    await axios.post(
      app.inbox.conversations.tags.attach.url({
        conversation: props.conversation.id,
      }),
      { tag_id: tagId },
    );
  } catch (error) {
    localTags.value = localTags.value.filter((tag) => tag.id !== tagId);
    throw error;
  } finally {
    tagProcessing.value = false;
  }
}

async function detachTag(tagId: string): Promise<void> {
  if (tagProcessing.value) return;
  const removed = localTags.value.find((tag) => tag.id === tagId);
  if (!removed) return;

  tagProcessing.value = true;
  localTags.value = localTags.value.filter((tag) => tag.id !== tagId);
  try {
    await axios.delete(
      app.inbox.conversations.tags.detach.url({
        conversation: props.conversation.id,
        tagId,
      }),
    );
  } catch (error) {
    localTags.value = [...localTags.value, removed];
    throw error;
  } finally {
    tagProcessing.value = false;
  }
}

watch(
  () => [props.conversation.id, props.conversation.summary],
  () => {
    expanded.value = false;
    showOriginal.value = false;
  },
);
</script>

<template>
  <section
    v-if="sourceText"
    class="rounded-md border bg-muted/25 px-3 py-2 text-sm"
  >
    <div class="mb-1 flex items-center justify-between gap-2">
      <div
        class="flex min-w-0 items-center gap-1.5 text-xs font-medium text-muted-foreground"
      >
        <FileText class="size-3.5 shrink-0" />
        <span class="truncate">{{ title }}</span>
        <LoaderCircle
          v-if="props.isTranslating"
          class="size-3 animate-spin"
          :title="t('翻译中')"
        />
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <button
          v-if="translationActive"
          type="button"
          class="text-xs text-muted-foreground hover:text-foreground"
          @click="showOriginal = !showOriginal"
        >
          {{ showOriginal ? t('显示译文') : t('显示原文') }}
        </button>
        <button
          v-if="canTranslate"
          type="button"
          class="text-xs text-muted-foreground hover:text-foreground disabled:opacity-50"
          :disabled="props.isTranslating"
          @click="onTranslateClick"
        >
          {{ hasTranslation ? t('重新翻译') : t('翻译') }}
        </button>
      </div>
    </div>

    <p
      class="leading-6 break-words whitespace-pre-wrap text-foreground"
      :class="{ 'line-clamp-2': !expanded }"
    >
      {{ displayText }}
    </p>

    <button
      v-if="canExpand"
      type="button"
      class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
      @click="expanded = !expanded"
    >
      <ChevronDown
        class="size-3 transition-transform"
        :class="{ 'rotate-180': expanded }"
      />
      {{ expanded ? t('收起') : t('展开') }}
    </button>

    <!-- 会话标签：AI 自动或人工，可人工增删 -->
    <div
      v-if="conversationTags.length > 0 || canEditTags"
      class="mt-2 flex flex-wrap items-center gap-1.5 border-t pt-2"
    >
      <span
        v-for="tag in conversationTags"
        :key="tag.id"
        class="flex items-center gap-1 rounded-full border bg-background py-0.5 pr-1 pl-2 text-xs text-foreground"
        :title="tag.source === 'ai' && tag.reason ? tag.reason : undefined"
      >
        <span
          class="h-1.5 w-1.5 shrink-0 rounded-full"
          :style="{ backgroundColor: tag.color ?? '#94a3b8' }"
        />
        <Sparkles
          v-if="tag.source === 'ai'"
          class="size-3 text-muted-foreground"
          :title="t('AI 自动标记')"
        />
        {{ tag.name }}
        <button
          type="button"
          class="ml-0.5 rounded-sm text-muted-foreground opacity-70 hover:text-foreground hover:opacity-100"
          :disabled="tagProcessing"
          :aria-label="t('移除标签')"
          @click="detachTag(tag.id)"
        >
          <X class="size-3" />
        </button>
      </span>

      <Popover v-if="canEditTags" v-model:open="tagPopoverOpen">
        <PopoverTrigger as-child>
          <Button
            variant="outline"
            size="sm"
            class="h-6 gap-1 rounded-full px-2 text-xs"
            :disabled="tagProcessing"
          >
            <Plus class="size-3" />
            {{ t('标签') }}
          </Button>
        </PopoverTrigger>
        <PopoverContent class="w-64 p-0" align="start">
          <div class="border-b p-2">
            <Input
              v-model="tagSearch"
              class="h-8"
              :placeholder="t('搜索标签')"
            />
          </div>
          <div class="max-h-56 overflow-y-auto p-1">
            <button
              v-for="option in filteredTagOptions"
              :key="option.id"
              type="button"
              class="flex w-full items-center gap-1.5 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted"
              :disabled="tagProcessing"
              @click="attachTag(option.id)"
            >
              <span
                class="h-2 w-2 shrink-0 rounded-full"
                :style="{ backgroundColor: option.color ?? '#94a3b8' }"
              />
              {{ option.name }}
            </button>
            <p
              v-if="filteredTagOptions.length === 0"
              class="px-2 py-3 text-center text-xs text-muted-foreground"
            >
              {{ t('暂无可选标签') }}
            </p>
          </div>
        </PopoverContent>
      </Popover>
    </div>
  </section>
</template>
