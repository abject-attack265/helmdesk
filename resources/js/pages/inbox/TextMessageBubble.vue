<!--
  渲染 AI 助手对话的文本消息、图片和错误状态。
-->
<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { renderMarkdownToSafeHtml } from '@/lib/markdown';
import { Loader2 } from '@lucide/vue';

interface TextMessage {
  id: string;
  kind: 'text';
  role: 'user' | 'assistant';
  content: string;
  attachmentIds?: string[];
  imageUrls?: string[];
  pending?: boolean;
  error?: string;
}

const props = defineProps<{ message: TextMessage }>();

const { t } = useI18n();

const renderAssistantHtml = (message: TextMessage): string =>
  renderMarkdownToSafeHtml(message.content);
</script>

<template>
  <div
    :class="[
      'flex',
      props.message.role === 'user' ? 'justify-end' : 'justify-start',
    ]"
  >
    <div
      :class="[
        'max-w-[85%] rounded-2xl px-3 py-2 text-sm break-words shadow-sm',
        props.message.role === 'user'
          ? 'rounded-br-sm bg-primary whitespace-pre-wrap text-primary-foreground'
          : 'rounded-bl-sm bg-muted text-foreground',
        props.message.error ? 'border border-destructive/40' : '',
      ]"
    >
      <template
        v-if="props.message.role === 'assistant' && props.message.error"
      >
        <span class="text-destructive">
          {{ props.message.error }}
        </span>
      </template>
      <template v-else>
        <div
          v-if="
            props.message.role === 'user' &&
            props.message.imageUrls &&
            props.message.imageUrls.length > 0
          "
          class="flex flex-wrap gap-1.5"
          :class="props.message.content ? 'mb-1.5' : ''"
        >
          <img
            v-for="(src, index) in props.message.imageUrls"
            :key="index"
            :src="src"
            alt=""
            class="h-20 w-20 rounded-lg object-cover"
          />
        </div>
        <div
          v-if="props.message.role === 'assistant' && props.message.content"
          :class="[
            'ai-markdown space-y-2',
            { 'ai-markdown--streaming': props.message.pending },
          ]"
          v-html="renderAssistantHtml(props.message)"
        />
        <span v-else-if="props.message.content" class="whitespace-pre-wrap">{{
          props.message.content
        }}</span>
        <span
          v-if="
            props.message.role === 'assistant' &&
            props.message.pending &&
            !props.message.content
          "
          class="inline-flex items-center gap-1 text-muted-foreground"
        >
          <Loader2 class="h-3.5 w-3.5 animate-spin" />
          {{ t('正在生成回复…') }}
        </span>
      </template>
    </div>
  </div>
</template>
