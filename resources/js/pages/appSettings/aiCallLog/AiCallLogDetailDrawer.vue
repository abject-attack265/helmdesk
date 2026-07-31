<!-- 应用设置 AI 调用日志详情抽屉，展示对话、工具调用和 token 统计。 -->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent } from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import type {
  AiCallLogDetailData,
  AiCallLogImageData,
  AiCallLogSegmentData,
} from '@/types/generated';
import { Bot, Check, ChevronDown, Copy, Wrench, X } from '@lucide/vue';
import { ref, watch } from 'vue';

const props = defineProps<{
  detail: AiCallLogDetailData | null;
  loading: boolean;
  error: string;
}>();

const open = defineModel<boolean>('open', { default: false });
const { t } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

const copiedKey = ref('');
const copy = async (value: string, key: string): Promise<void> => {
  await navigator.clipboard.writeText(value);
  copiedKey.value = key;
  setTimeout(() => {
    if (copiedKey.value === key) copiedKey.value = '';
  }, 1500);
};

const pretty = (value: unknown): string => JSON.stringify(value, null, 2);
const hasInputs = (segment: AiCallLogSegmentData): boolean => {
  const inputs = segment.inputs;
  if (inputs === null || inputs === undefined) return false;
  return typeof inputs === 'object'
    ? Object.keys(inputs as Record<string, unknown>).length > 0
    : true;
};

const userName = (detail: AiCallLogDetailData): string =>
  detail.purpose === 'reception_reply'
    ? formatVisitorName(detail.visitor_name, detail.contact_id)
    : t('输入');

const locators = (detail: AiCallLogDetailData) =>
  [
    { label: t('会话 ID'), value: detail.conversation_id },
    { label: t('联系人 ID'), value: detail.contact_id },
    { label: t('记录 ID'), value: detail.id },
  ].filter((item) => item.value);

const previewSrc = ref('');
const previewOpen = ref(false);
const openPreview = (image: AiCallLogImageData): void => {
  previewSrc.value = image.url;
  previewOpen.value = true;
};

const systemPromptOpen = ref<Record<number, boolean>>({});
const toolsOpen = ref(false);
const toolDetailOpen = ref<Record<string, boolean>>({});

// 切换日志时恢复各详情区块的默认折叠状态。
watch(
  () => props.detail,
  (detail, previous) => {
    if (detail?.id !== previous?.id) {
      systemPromptOpen.value = {};
      toolsOpen.value = false;
      toolDetailOpen.value = {};
    }
  },
);

const toggleSystemPrompt = (index: number): void => {
  systemPromptOpen.value[index] = !systemPromptOpen.value[index];
};
const toggleToolDetail = (key: string): void => {
  toolDetailOpen.value[key] = !toolDetailOpen.value[key];
};
const systemPromptLabel = (index: number, total: number): string => {
  if (total <= 1) return t('系统提示词');
  return index === 0 ? t('系统提示词') : t('联系人历史会话上下文');
};
</script>

<template>
  <Sheet v-model:open="open">
    <SheetContent
      side="right"
      class="w-full overflow-y-auto overscroll-contain sm:max-w-2xl"
    >
      <div v-if="loading" class="flex h-full items-center justify-center">
        <Spinner />
      </div>

      <div v-else-if="error" class="px-6 pt-10 text-center text-destructive">
        {{ error }}
      </div>

      <div v-else-if="detail" class="space-y-6 p-6">
        <div class="space-y-3">
          <div class="flex items-center gap-2 pr-8">
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-semibold">{{ detail.purpose_label }}</h2>
              <Badge
                :variant="
                  detail.status === 'error' ? 'destructive' : 'secondary'
                "
              >
                {{ detail.status === 'error' ? t('失败') : t('成功') }}
              </Badge>
              <Badge variant="outline">{{
                t('{n} 轮', { n: detail.turn_count })
              }}</Badge>
            </div>
          </div>
          <p class="text-sm text-muted-foreground">
            {{ formatDateTime(detail.last_at) }} ·
            {{ detail.model_name || '—' }} · {{ t('Token 合计') }}
            {{ detail.total_input_tokens }} / {{ detail.total_output_tokens }}
          </p>

          <div class="space-y-1 rounded-md border bg-muted/20 p-3">
            <div
              v-for="item in locators(detail)"
              :key="item.label"
              class="flex items-center justify-between gap-2 text-xs"
            >
              <span class="text-muted-foreground">{{ item.label }}</span>
              <button
                type="button"
                class="flex items-center gap-1 font-mono hover:text-foreground"
                @click="copy(String(item.value), item.label)"
              >
                <span class="max-w-64 truncate">{{ item.value }}</span>
                <Check v-if="copiedKey === item.label" class="h-3 w-3" />
                <Copy v-else class="h-3 w-3 text-muted-foreground" />
              </button>
            </div>
          </div>
        </div>

        <div v-if="detail.system_prompts.length" class="space-y-1">
          <template v-for="(prompt, pi) in detail.system_prompts" :key="pi">
            <button
              type="button"
              class="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
              @click="toggleSystemPrompt(pi)"
            >
              <ChevronDown
                class="h-3 w-3 transition-transform"
                :class="systemPromptOpen[pi] ? '' : '-rotate-90'"
              />
              {{ systemPromptLabel(pi, detail.system_prompts.length) }}
            </button>
            <pre
              v-if="systemPromptOpen[pi]"
              class="max-h-56 overflow-auto rounded bg-muted/40 p-2 text-xs break-all whitespace-pre-wrap"
              >{{ prompt }}</pre>
          </template>
        </div>

        <div v-if="detail.available_tools.length" class="space-y-1">
          <button
            type="button"
            class="flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground"
            @click="toolsOpen = !toolsOpen"
          >
            <ChevronDown
              class="h-3 w-3 transition-transform"
              :class="toolsOpen ? '' : '-rotate-90'"
            />
            {{ t('可用工具') }}（{{ detail.available_tools.length }}）
          </button>
          <div v-if="toolsOpen" class="space-y-1.5">
            <div
              v-for="(tool, i) in detail.available_tools"
              :key="i"
              class="rounded border bg-muted/20 p-2 text-xs"
              :class="tool.sent_to_visitor ? 'border-foreground' : ''"
            >
              <span class="font-mono font-medium">{{ tool.name }}</span>
              <Badge
                v-if="tool.sent_to_visitor"
                class="ml-1.5 h-4 px-1 text-[10px]"
              >
                {{ t('发送给访客') }}
              </Badge>
              <p v-if="tool.description" class="mt-0.5 text-muted-foreground">
                {{ tool.description }}
              </p>
            </div>
          </div>
        </div>

        <div class="space-y-5">
          <div v-for="(message, i) in detail.messages" :key="i">
            <div v-if="message.role === 'user'" class="flex justify-end">
              <div class="max-w-[85%] space-y-1.5">
                <div
                  class="flex items-center justify-end gap-1.5 text-xs text-muted-foreground"
                >
                  <span class="font-medium text-foreground">{{
                    userName(detail)
                  }}</span>
                  <span v-if="message.created_at" class="tabular-nums">
                    {{ formatDateTime(message.created_at) }}
                  </span>
                </div>
                <div
                  class="rounded-2xl rounded-br-sm bg-muted px-3.5 py-2 text-sm"
                >
                  <p class="break-words whitespace-pre-wrap">
                    {{ message.text || '—' }}
                  </p>
                </div>
                <div
                  v-if="message.images.length"
                  class="flex flex-wrap justify-end gap-2"
                >
                  <button
                    v-for="(img, k) in message.images"
                    :key="k"
                    type="button"
                    class="overflow-hidden rounded border hover:opacity-80"
                    @click="openPreview(img)"
                  >
                    <img
                      :src="img.url"
                      :alt="img.name ?? t('访客图片')"
                      loading="lazy"
                      class="h-24 w-24 object-cover"
                    />
                  </button>
                </div>
              </div>
            </div>

            <div v-else class="space-y-2">
              <div class="flex items-center gap-1.5">
                <span
                  class="flex h-5 w-5 items-center justify-center rounded-full border bg-background"
                >
                  <Bot class="h-3 w-3" />
                </span>
                <span class="text-xs font-medium">{{ t('AI') }}</span>
                <span
                  v-if="message.model_name"
                  class="font-mono text-[10px] text-muted-foreground"
                >
                  {{ message.model_name }}
                </span>
                <span
                  v-if="message.created_at"
                  class="text-xs text-muted-foreground tabular-nums"
                >
                  {{ formatDateTime(message.created_at) }}
                </span>
              </div>

              <p
                v-if="message.is_error"
                class="text-sm break-words whitespace-pre-wrap text-destructive"
              >
                {{ message.error_message || t('调用失败') }}
              </p>

              <div class="space-y-2">
                <template v-for="(segment, j) in message.segments" :key="j">
                  <p
                    v-if="segment.type === 'text'"
                    class="text-sm break-words whitespace-pre-wrap"
                  >
                    {{ segment.content }}
                  </p>
                  <div
                    v-else
                    class="rounded-lg border text-xs"
                    :class="
                      segment.sent_to_visitor ? 'border-foreground/40' : ''
                    "
                  >
                    <button
                      type="button"
                      class="flex w-full items-center gap-2 px-3 py-2 text-left"
                      @click="toggleToolDetail(`${i}-${j}`)"
                    >
                      <Wrench
                        class="h-3.5 w-3.5 shrink-0 text-muted-foreground"
                      />
                      <span
                        class="min-w-0 flex-1 truncate font-mono font-medium"
                      >
                        {{ segment.name }}
                      </span>
                      <Badge
                        v-if="segment.sent_to_visitor"
                        class="h-4 shrink-0 px-1 text-[10px]"
                      >
                        {{ t('发送给访客') }}
                      </Badge>
                      <span
                        class="flex shrink-0 items-center gap-1 text-muted-foreground"
                      >
                        <template v-if="segment.result !== null">
                          <Check class="h-3 w-3" />{{ t('已完成') }}
                        </template>
                        <template v-else>
                          <X class="h-3 w-3" />{{ t('无返回') }}
                        </template>
                      </span>
                      <ChevronDown
                        class="h-3 w-3 shrink-0 transition-transform"
                        :class="toolDetailOpen[`${i}-${j}`] ? 'rotate-180' : ''"
                      />
                    </button>
                    <div
                      v-if="toolDetailOpen[`${i}-${j}`]"
                      class="space-y-1.5 border-t px-3 py-2"
                    >
                      <div v-if="hasInputs(segment)">
                        <span class="text-muted-foreground"
                          >{{ t('入参') }}:</span
                        >
                        <pre
                          class="mt-0.5 max-h-56 overflow-auto break-all whitespace-pre-wrap"
                          >{{ pretty(segment.inputs) }}</pre>
                      </div>
                      <div v-if="segment.result !== null">
                        <span class="text-muted-foreground"
                          >{{ t('返回') }}:</span
                        >
                        <pre
                          class="mt-0.5 max-h-56 overflow-auto break-all whitespace-pre-wrap"
                          >{{ segment.result }}</pre>
                      </div>
                    </div>
                  </div>
                </template>
              </div>

              <div
                class="flex items-center gap-2 text-xs text-muted-foreground"
              >
                <span class="tabular-nums">
                  {{ t('输入') }} {{ message.input_tokens ?? 0 }} ·
                  {{ t('输出') }} {{ message.output_tokens ?? 0 }}
                </span>
                <button
                  v-if="message.text"
                  type="button"
                  class="hover:text-foreground"
                  :aria-label="t('复制')"
                  @click="copy(message.text, `msg-${i}`)"
                >
                  <Check v-if="copiedKey === `msg-${i}`" class="h-3 w-3" />
                  <Copy v-else class="h-3 w-3" />
                </button>
              </div>
            </div>
          </div>

          <p
            v-if="detail.messages.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            {{ t('暂无对话内容') }}
          </p>
        </div>

        <Dialog v-model:open="previewOpen">
          <DialogContent class="max-w-3xl">
            <DialogTitle class="sr-only">{{ t('图片预览') }}</DialogTitle>
            <img
              :src="previewSrc"
              :alt="t('图片预览')"
              class="max-h-[80vh] w-full object-contain"
            />
          </DialogContent>
        </Dialog>
      </div>
    </SheetContent>
  </Sheet>
</template>
