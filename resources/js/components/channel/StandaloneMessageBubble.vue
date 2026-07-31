<!--
  文件说明：访客端聊天画布里单条消息的气泡正文（撤回提示 + 气泡 + 引用预览）。
  按消息归属（访客 visitor / 客服·AI assistant）左右对称，可交互时套右键 ContextMenu，
  非交互（如后台预览）退回纯气泡。外层时间戳、头像与 flex 布局仍由父组件 StandaloneCanvas 持有。
-->
<script setup lang="ts">
import QuotedMessagePreview from '@/components/channel/QuotedMessagePreview.vue';
import StandaloneAttachmentCard from '@/components/channel/StandaloneAttachmentCard.vue';
import {
  ContextMenu,
  ContextMenuContent,
  ContextMenuItem,
  ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { renderMarkdownToSafeHtml } from '@/lib/markdown';
import { useStandaloneI18n } from '@/standalone/i18n';
import type { ReceptionMessageData } from '@/types/generated';
import { computed } from 'vue';

const props = defineProps<{
  message: ReceptionMessageData;
  // 是否处于可交互态：true 时套右键 ContextMenu，false 时退回纯气泡。
  interactive: boolean;
  // 消息归属侧：visitor 走访客气泡（右侧），assistant 走 AI/客服气泡（左侧）。
  side: 'visitor' | 'assistant';
  // 复制菜单项是否可点（由父组件按消息内容判定）。
  canCopy: boolean;
  // 撤回菜单项是否可点（仅访客侧，父组件按 2 分钟窗口判定）。
  canRecall: boolean;
}>();

const emit = defineEmits<{
  (e: 'copy'): void;
  (e: 'quote'): void;
  (e: 'recall'): void;
  (e: 'reedit'): void;
  (e: 'open-quoted'): void;
  (e: 'menu-open-change', open: boolean): void;
}>();

const { t } = useStandaloneI18n();

const VISITOR_BUBBLE_CLASS =
  'max-w-full min-w-0 rounded-2xl rounded-tr-sm bg-[var(--standalone-visitor-bubble)] px-4 py-3 text-left text-sm leading-relaxed text-[var(--standalone-visitor-text)]';
const ASSISTANT_BUBBLE_CLASS =
  'max-w-full min-w-0 rounded-2xl rounded-tl-sm bg-[var(--standalone-assistant-bubble)] px-4 py-3 text-sm leading-relaxed text-[var(--standalone-assistant-text)]';

// 仅附件、没有正文文字的消息走"裸卡片"展示，去掉气泡背景和内边距，
// 让附件本身的卡片样式直接作为视觉边界——和 B 端的 isAttachmentOnly 一致。
const isAttachmentOnly = computed(() => {
  const hasAttachments = (props.message.attachments?.length ?? 0) > 0;
  const hasContent =
    typeof props.message.content === 'string' &&
    props.message.content.length > 0;

  return hasAttachments && !hasContent;
});

const bubbleClass = computed(() => {
  if (isAttachmentOnly.value) {
    return 'max-w-full min-w-0';
  }

  return props.side === 'visitor'
    ? VISITOR_BUBBLE_CLASS
    : ASSISTANT_BUBBLE_CLASS;
});

// AI 回复正文按 markdown 渲染（表格/列表/加粗/代码块等）；访客与人工客服消息保持纯文本，
// 避免把用户手敲的 *、# 等字符误当作 markdown 语法。
const isMarkdown = computed(() => props.message.role === 'ai');

const attachmentVariant = computed(() =>
  props.side === 'visitor' ? 'visitor' : 'assistant',
);

// 把 AI 回复的 markdown 文本转成可安全 v-html 注入的 HTML（清洗在 renderMarkdownToSafeHtml 内完成）。
function renderMessageHtml(content: string): string {
  return renderMarkdownToSafeHtml(content);
}
</script>

<template>
  <template v-if="side === 'visitor'">
    <div
      v-if="message.recalled_at"
      class="flex flex-wrap items-baseline justify-end gap-2 rounded-2xl rounded-tr-sm border border-dashed border-muted-foreground/30 bg-muted/40 px-4 py-2 text-xs text-muted-foreground italic"
    >
      <span>{{ t('你撤回了一条消息') }}</span>
      <button
        v-if="message.recalled_content && interactive"
        type="button"
        class="text-primary not-italic hover:underline"
        @click="emit('reedit')"
      >
        {{ t('重新编辑') }}
      </button>
    </div>
    <ContextMenu
      v-if="!message.recalled_at && interactive"
      @update:open="emit('menu-open-change', $event)"
    >
      <ContextMenuTrigger as-child>
        <div :class="bubbleClass">
          <p
            v-if="message.content"
            class="[overflow-wrap:anywhere] break-words whitespace-pre-wrap"
          >
            {{ message.content }}
          </p>
          <div v-if="message.attachments?.length" class="mt-2 space-y-2">
            <StandaloneAttachmentCard
              v-for="attachment in message.attachments"
              :key="attachment.id"
              :attachment="attachment"
              :variant="attachmentVariant"
            />
          </div>
        </div>
      </ContextMenuTrigger>
      <ContextMenuContent class="w-28">
        <ContextMenuItem :disabled="!canCopy" @select="emit('copy')">
          {{ t('复制') }}
        </ContextMenuItem>
        <ContextMenuItem @select="emit('quote')">
          {{ t('引用') }}
        </ContextMenuItem>
        <ContextMenuItem
          variant="destructive"
          :disabled="!canRecall"
          @select="emit('recall')"
        >
          {{ t('撤回') }}
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
    <div v-if="!message.recalled_at && !interactive" :class="bubbleClass">
      <p
        v-if="message.content"
        class="[overflow-wrap:anywhere] break-words whitespace-pre-wrap"
      >
        {{ message.content }}
      </p>
      <div v-if="message.attachments?.length" class="mt-2 space-y-2">
        <StandaloneAttachmentCard
          v-for="attachment in message.attachments"
          :key="attachment.id"
          :attachment="attachment"
          :variant="attachmentVariant"
        />
      </div>
    </div>
    <QuotedMessagePreview
      v-if="message.quoted_message && !message.recalled_at"
      :quoted="message.quoted_message"
      side="visitor"
      @open="emit('open-quoted')"
    />
  </template>
  <template v-else>
    <div
      v-if="message.recalled_at"
      class="rounded-2xl rounded-tl-sm border border-dashed border-muted-foreground/30 bg-muted/40 px-4 py-2 text-xs text-muted-foreground italic"
    >
      {{ t('对方撤回了一条消息') }}
    </div>
    <ContextMenu
      v-if="!message.recalled_at && interactive"
      @update:open="emit('menu-open-change', $event)"
    >
      <ContextMenuTrigger as-child>
        <div :class="bubbleClass">
          <div
            v-if="message.content && isMarkdown"
            class="ai-markdown [overflow-wrap:anywhere] break-words"
            v-html="renderMessageHtml(message.content)"
          />
          <p
            v-else-if="message.content"
            class="[overflow-wrap:anywhere] break-words whitespace-pre-wrap"
          >
            {{ message.content }}
          </p>
          <div v-if="message.attachments?.length" class="mt-2 space-y-2">
            <StandaloneAttachmentCard
              v-for="attachment in message.attachments"
              :key="attachment.id"
              :attachment="attachment"
              :variant="attachmentVariant"
            />
          </div>
        </div>
      </ContextMenuTrigger>
      <ContextMenuContent class="w-28">
        <ContextMenuItem :disabled="!canCopy" @select="emit('copy')">
          {{ t('复制') }}
        </ContextMenuItem>
        <ContextMenuItem @select="emit('quote')">
          {{ t('引用') }}
        </ContextMenuItem>
      </ContextMenuContent>
    </ContextMenu>
    <div v-if="!message.recalled_at && !interactive" :class="bubbleClass">
      <div
        v-if="message.content && isMarkdown"
        class="ai-markdown [overflow-wrap:anywhere] break-words"
        v-html="renderMessageHtml(message.content)"
      />
      <p
        v-else-if="message.content"
        class="[overflow-wrap:anywhere] break-words whitespace-pre-wrap"
      >
        {{ message.content }}
      </p>
      <div v-if="message.attachments?.length" class="mt-2 space-y-2">
        <StandaloneAttachmentCard
          v-for="attachment in message.attachments"
          :key="attachment.id"
          :attachment="attachment"
          :variant="attachmentVariant"
        />
      </div>
    </div>
    <QuotedMessagePreview
      v-if="message.quoted_message && !message.recalled_at"
      :quoted="message.quoted_message"
      side="assistant"
      @open="emit('open-quoted')"
    />
  </template>
</template>
