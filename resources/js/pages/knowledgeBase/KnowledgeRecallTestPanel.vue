<!--
  知识库测试面板，供管理员输入客户问题并查看当前知识库能够找到的内容。
  使用 KnowledgeRecallTestResultData 按查找方式展示文档或问答来源。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import type {
  EnumOptionData,
  KnowledgeRecallTestResultData,
  KnowledgeSearchMode,
} from '@/types/generated';
import { useHttp } from '@inertiajs/vue3';
import { FileText, MessageSquareText, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  knowledgeBaseId: string;
  modeOptions: EnumOptionData[];
}>();

const { t } = useI18n();
const { toast } = useToast();

const defaultMode: KnowledgeSearchMode = props.modeOptions.some(
  (option) => option.value === 'semantic',
)
  ? 'semantic'
  : ((props.modeOptions[0]?.value ?? 'semantic') as KnowledgeSearchMode);

const http = useHttp<
  { query: string; mode: KnowledgeSearchMode },
  KnowledgeRecallTestResultData
>({
  query: '',
  mode: defaultMode,
});

const result = ref<KnowledgeRecallTestResultData | null>(null);
const hasSearched = ref(false);

const canSubmit = computed(
  () => http.query.trim().length > 0 && !http.processing,
);

const displayedMode = computed(() => result.value?.mode ?? http.mode);
const showSemanticSection = computed(
  () => displayedMode.value === 'semantic' || displayedMode.value === 'hybrid',
);
const showGrepSection = computed(
  () => displayedMode.value === 'grep' || displayedMode.value === 'hybrid',
);

function runSearch(): void {
  const trimmed = http.query.trim();
  if (trimmed.length === 0 || http.processing) {
    return;
  }
  http.query = trimmed;
  hasSearched.value = true;
  result.value = null;

  http.post(
    KnowledgeBase.RunKnowledgeRecallTestAction.url({
      knowledgeBase: props.knowledgeBaseId,
    }),
    {
      onSuccess: (response: KnowledgeRecallTestResultData) => {
        result.value = response;
      },
      onHttpException: () => {
        hasSearched.value = false;
        toast.error(t('测试失败，请稍后再试。'));
      },
      onNetworkError: () => {
        hasSearched.value = false;
        toast.error(t('网络异常，请检查网络后重试。'));
      },
    },
  );
}

/**
 * 文本域内 Cmd/Ctrl + Enter 直接发起检索。
 */
function onTextareaKeydown(event: KeyboardEvent): void {
  if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
    event.preventDefault();
    runSearch();
  }
}

function originIcon(originType: string) {
  return originType === 'qa' ? MessageSquareText : FileText;
}
</script>

<template>
  <div class="mx-auto w-full max-w-none space-y-6">
    <HeadingSmall
      :title="t('测试知识库')"
      :description="t('输入客户可能提出的问题，查看知识库能找到哪些内容。')"
    />

    <form class="space-y-4" @submit.prevent="runSearch">
      <div class="grid gap-2">
        <Label for="recall-test-query" required>{{ t('问题或关键词') }}</Label>
        <Textarea
          id="recall-test-query"
          v-model="http.query"
          class="min-h-20 w-full"
          :placeholder="t('输入问题或关键词')"
          :aria-invalid="Boolean(http.errors.query)"
          :disabled="http.processing"
          required
          @keydown="onTextareaKeydown"
        />
        <p v-if="http.errors.query" class="text-xs text-destructive">
          {{ http.errors.query }}
        </p>
      </div>

      <div class="flex flex-wrap items-end gap-3">
        <div class="grid w-full gap-2 sm:w-56">
          <Label for="recall-test-mode" required>{{ t('查找方式') }}</Label>
          <Select v-model="http.mode" :disabled="http.processing">
            <SelectTrigger
              id="recall-test-mode"
              class="w-full"
              aria-required="true"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in modeOptions"
                :key="String(option.value)"
                :value="String(option.value)"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Button type="submit" :disabled="!canSubmit" class="shrink-0">
          <Spinner v-if="http.processing" class="mr-1.5 h-4 w-4" />
          <Search v-else class="mr-1.5 h-4 w-4" />
          {{ t('开始测试') }}
        </Button>
      </div>
    </form>

    <div v-if="http.processing && !result" class="space-y-3">
      <div class="h-20 animate-pulse rounded-lg bg-muted/50" />
      <div class="h-20 animate-pulse rounded-lg bg-muted/50" />
      <div class="h-20 animate-pulse rounded-lg bg-muted/50" />
    </div>

    <div v-else-if="result" class="space-y-6">
      <div
        class="rounded-lg border bg-muted/20 px-3 py-2.5 text-sm text-muted-foreground"
      >
        {{
          t('共显示 {count} 条结果', {
            count:
              result.diagnostics.semantic_count + result.diagnostics.grep_count,
          })
        }}
      </div>

      <section v-if="showSemanticSection" class="space-y-3">
        <h4 class="text-sm font-medium">
          {{ t('按意思找到的内容') }}
          <span class="font-normal text-muted-foreground">
            （{{ result.semantic_hits.length }}）
          </span>
        </h4>
        <p
          v-if="result.semantic_hits.length === 0"
          class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
        >
          {{ t('没有找到相关内容') }}
        </p>
        <ul v-else class="space-y-2">
          <li
            v-for="(hit, index) in result.semantic_hits"
            :key="`semantic:${index}`"
            class="rounded-lg border p-3"
          >
            <div class="mb-1.5 flex flex-wrap items-center gap-2">
              <Badge variant="secondary">{{ hit.source_label }}</Badge>
              <span
                class="inline-flex min-w-0 items-center gap-1 text-xs text-muted-foreground"
              >
                <component
                  :is="originIcon(hit.origin_type)"
                  class="h-3.5 w-3.5 shrink-0"
                />
                <span class="truncate">
                  {{ hit.origin_title ?? t('未标明来源') }}
                </span>
              </span>
            </div>
            <p
              v-if="hit.heading_path"
              class="mb-1 truncate text-xs text-muted-foreground"
            >
              {{ hit.heading_path }}
            </p>
            <p
              class="line-clamp-4 text-sm whitespace-pre-wrap text-foreground/90"
            >
              {{ hit.content }}
            </p>
          </li>
        </ul>
      </section>

      <section v-if="showGrepSection" class="space-y-3">
        <h4 class="text-sm font-medium">
          {{ t('按关键词找到的内容') }}
          <span class="font-normal text-muted-foreground">
            （{{ result.grep_matches.length }}）
          </span>
        </h4>
        <p
          v-if="result.grep_matches.length === 0"
          class="rounded-lg border border-dashed px-4 py-6 text-center text-sm text-muted-foreground"
        >
          {{ t('没有找到相关内容') }}
        </p>
        <ul v-else class="space-y-2">
          <li
            v-for="(match, index) in result.grep_matches"
            :key="`grep:${index}`"
            class="rounded-lg border p-3"
          >
            <div class="mb-1.5 flex flex-wrap items-center gap-2">
              <Badge variant="outline">{{ match.field_label }}</Badge>
              <span
                class="inline-flex min-w-0 items-center gap-1 text-xs text-muted-foreground"
              >
                <component
                  :is="originIcon(match.origin_type)"
                  class="h-3.5 w-3.5 shrink-0"
                />
                <span class="truncate">
                  {{ match.origin_title ?? t('未标明来源') }}
                </span>
              </span>
            </div>
            <p class="text-sm break-words text-foreground/90">
              <span class="text-muted-foreground">{{
                match.context_before
              }}</span>
              <span
                class="rounded-sm bg-foreground px-0.5 font-medium text-background"
              >
                {{ match.match }}
              </span>
              <span class="text-muted-foreground">{{
                match.context_after
              }}</span>
            </p>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
