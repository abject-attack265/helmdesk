<!--
  渲染可展开的 AI 助手工具调用与结果。
-->
<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { CheckCircle2, ChevronRight, Wrench } from '@lucide/vue';
import { computed } from 'vue';

interface ToolMessage {
  id: string;
  kind: 'tool_call' | 'tool_result';
  tool: string;
  display?: string;
  detail: string;
  expanded: boolean;
}

const props = defineProps<{ message: ToolMessage }>();

const emit = defineEmits<{ toggle: [message: ToolMessage] }>();

const { t } = useI18n();

// 使用已提供的名称；没有名称时显示已知工具译名或原始名称。
const resolveToolLabel = (message: ToolMessage): string | null => {
  if (message.display && message.display.trim()) {
    return message.display;
  }
  const key = `工具.${message.tool}`;
  const translated = t(key);
  return translated && translated !== key ? translated : null;
};

const toolLabel = computed(() => resolveToolLabel(props.message));

const onToggle = () => {
  if (!props.message.detail.trim()) {
    return;
  }
  emit('toggle', props.message);
};
</script>

<template>
  <div class="flex justify-start">
    <div
      class="flex max-w-[90%] flex-col rounded-lg border border-border/60 bg-muted/40 text-xs text-foreground"
    >
      <button
        type="button"
        :class="[
          'flex w-full items-start gap-2 px-2.5 py-2 text-left',
          props.message.detail.trim()
            ? 'cursor-pointer hover:bg-background/40'
            : 'cursor-default',
        ]"
        :disabled="!props.message.detail.trim()"
        :aria-expanded="props.message.expanded"
        :aria-label="props.message.expanded ? t('收起详情') : t('查看详情')"
        @click="onToggle"
      >
        <component
          :is="props.message.kind === 'tool_call' ? Wrench : CheckCircle2"
          class="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground"
        />
        <div class="flex min-w-0 flex-1 items-center gap-1.5">
          <span class="shrink-0 font-medium">
            {{
              props.message.kind === 'tool_call' ? t('正在处理') : t('处理结果')
            }}
          </span>
          <span
            v-if="toolLabel"
            class="min-w-0 truncate text-foreground"
            :title="props.message.tool"
          >
            {{ toolLabel }}
          </span>
          <code
            v-else
            class="min-w-0 truncate rounded bg-background/60 px-1 py-0.5 font-mono text-[11px]"
          >
            {{ props.message.tool }}
          </code>
        </div>
        <ChevronRight
          v-if="props.message.detail.trim()"
          :class="[
            'mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform',
            props.message.expanded ? 'rotate-90' : '',
          ]"
          aria-hidden="true"
        />
      </button>
      <pre
        v-if="props.message.detail.trim() && props.message.expanded"
        class="max-h-40 overflow-auto border-t border-current/10 px-2.5 py-2 font-mono text-[11px] leading-snug break-words whitespace-pre-wrap text-muted-foreground"
        >{{ props.message.detail }}</pre>
    </div>
  </div>
</template>
