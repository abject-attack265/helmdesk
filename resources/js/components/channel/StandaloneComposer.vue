<!--
  文件说明：访客端聊天画布底部的输入区（附件/图片入口 + 引用条 + 自适应高度文本框 + 表情面板）。
  仅持有草稿文本（v-model）、输入框自适应高度与表情插入这类纯输入交互；发送、上传、引用解析等
  业务逻辑仍由父组件 StandaloneCanvas 处理，通过 send / paste / file-select 等事件回传。
-->
<script setup lang="ts">
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { COMPOSER_EMOJIS } from '@/lib/composerEmojis';
import { useStandaloneI18n } from '@/standalone/i18n';
import { Image as ImageIcon, Paperclip, Smile, X } from '@lucide/vue';
import { nextTick, onMounted, ref, watch } from 'vue';

interface ComposerQuoteTarget {
  id: string;
  senderName: string;
  preview: string;
}

const props = defineProps<{
  // 草稿文本（v-model 双向绑定）。
  modelValue: string;
  // 输入区是否可交互（联网态或演示态）。
  canInteract: boolean;
  // 是否禁用附件/图片/表情等操作按钮。
  actionDisabled: boolean;
  // 文本框最大字符数。
  maxContentLength: number;
  // 当前引用目标；为空时不展示引用条。
  quote: ComposerQuoteTarget | null;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'send'): void;
  (e: 'paste', event: ClipboardEvent): void;
  (e: 'file-select', event: Event, kind: 'file' | 'image'): void;
  (e: 'input'): void;
  (e: 'open-quote'): void;
  (e: 'clear-quote'): void;
}>();

const { t } = useStandaloneI18n();

const composerEl = ref<HTMLTextAreaElement | null>(null);
const fileInputEl = ref<HTMLInputElement | null>(null);
const imageInputEl = ref<HTMLInputElement | null>(null);
const emojiPopoverOpen = ref(false);

// 输入框默认约一行高度，随内容增多自适应增高，超过上限后内部滚动。
const COMPOSER_MAX_HEIGHT = 160;

function autosizeComposer(): void {
  const el = composerEl.value;
  if (!el) {
    return;
  }

  el.style.height = 'auto';
  el.style.height = `${Math.min(el.scrollHeight, COMPOSER_MAX_HEIGHT)}px`;
  el.style.overflowY =
    el.scrollHeight > COMPOSER_MAX_HEIGHT ? 'auto' : 'hidden';
}

// 覆盖真实输入、插入表情、点选建议问题与发送后清空等所有改值路径。
watch(
  () => props.modelValue,
  () => {
    void nextTick(autosizeComposer);
  },
);

onMounted(() => {
  void nextTick(autosizeComposer);
});

function handleInput(event: Event): void {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
  emit('input');
}

function handleComposerKeydown(event: KeyboardEvent): void {
  // Shift+Enter 换行；输入法组词期间的 Enter 用于上屏候选词。
  if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
    return;
  }

  event.preventDefault();
  emit('send');
}

async function insertEmoji(emoji: string): Promise<void> {
  if (!props.canInteract) return;

  const composer = composerEl.value;
  const start = composer?.selectionStart ?? props.modelValue.length;
  const end = composer?.selectionEnd ?? props.modelValue.length;

  const nextValue = [
    props.modelValue.slice(0, start),
    emoji,
    props.modelValue.slice(end),
  ].join('');
  emit('update:modelValue', nextValue);
  emojiPopoverOpen.value = false;

  await nextTick();

  const nextCursor = start + emoji.length;
  composerEl.value?.focus({ preventScroll: true });
  composerEl.value?.setSelectionRange(nextCursor, nextCursor);
}

// 暴露给父组件聚焦输入框（父组件在发送/撤回/重新编辑等流程后抢回焦点）。
function focus(): void {
  composerEl.value?.focus({ preventScroll: true });
}

defineExpose({ focus });
</script>

<template>
  <div class="-mx-4 border-t border-input bg-muted sm:-mx-6">
    <input
      ref="fileInputEl"
      type="file"
      class="sr-only"
      multiple
      :disabled="actionDisabled"
      @change="emit('file-select', $event, 'file')"
    />
    <input
      ref="imageInputEl"
      type="file"
      class="sr-only"
      multiple
      accept="image/*"
      :disabled="actionDisabled"
      @change="emit('file-select', $event, 'image')"
    />
    <div
      v-if="quote"
      class="flex items-center gap-2 border-b px-3 py-2 text-xs text-muted-foreground"
    >
      <button
        type="button"
        class="flex min-w-0 flex-1 items-center text-left transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
        @click="emit('open-quote')"
      >
        <span class="max-w-[45%] shrink-0 truncate font-medium">
          {{ quote.senderName }}：
        </span>
        <span class="min-w-0 truncate">
          {{ quote.preview }}
        </span>
      </button>
      <button
        type="button"
        class="inline-flex size-5 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
        :aria-label="t('取消引用')"
        :title="t('取消引用')"
        @click="emit('clear-quote')"
      >
        <X class="size-3.5" />
      </button>
    </div>
    <div class="flex items-end gap-1 px-2 py-2">
      <div class="flex shrink-0 items-center">
        <button
          type="button"
          :disabled="actionDisabled"
          :aria-label="t('添加附件')"
          :title="t('添加附件')"
          class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-background hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-default disabled:opacity-40"
          @click="fileInputEl?.click()"
        >
          <Paperclip class="size-6" />
        </button>
        <button
          type="button"
          :disabled="actionDisabled"
          :aria-label="t('添加图片')"
          :title="t('添加图片')"
          class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-background hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-default disabled:opacity-40"
          @click="imageInputEl?.click()"
        >
          <ImageIcon class="size-6" />
        </button>
      </div>
      <div
        class="flex min-w-0 flex-1 items-center border border-input bg-background px-3 transition-colors focus-within:border-ring"
      >
        <textarea
          ref="composerEl"
          :value="modelValue"
          :disabled="!canInteract"
          :maxlength="maxContentLength"
          rows="1"
          class="block max-h-40 w-full resize-none bg-transparent py-1.5 text-sm leading-6 outline-none placeholder:text-muted-foreground disabled:cursor-default"
          @input="handleInput"
          @keydown="handleComposerKeydown"
          @paste="emit('paste', $event)"
        ></textarea>
      </div>
      <Popover v-model:open="emojiPopoverOpen">
        <PopoverTrigger as-child>
          <button
            type="button"
            :disabled="actionDisabled"
            :aria-label="t('选择表情')"
            :title="t('选择表情')"
            class="inline-flex size-9 shrink-0 items-center justify-center rounded-xl text-muted-foreground transition-colors hover:bg-background hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-default disabled:opacity-40"
          >
            <Smile class="size-6" />
          </button>
        </PopoverTrigger>
        <PopoverContent class="w-64 p-2" align="end">
          <div class="max-h-48 overflow-y-auto">
            <div class="grid grid-cols-7 gap-1">
              <button
                v-for="emoji in COMPOSER_EMOJIS"
                :key="emoji"
                type="button"
                class="flex size-7 items-center justify-center rounded-md text-base transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                :aria-label="t('选择表情')"
                @click="insertEmoji(emoji)"
              >
                {{ emoji }}
              </button>
            </div>
          </div>
        </PopoverContent>
      </Popover>
    </div>
  </div>
</template>
