<!--
  经验提炼结果审核页，消费 ShowExperienceExtractionResultsPagePropsData。
  展示候选问答、证据会话和任务实时状态，并承接采纳与丢弃操作。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import ConversationDetailSheet from '@/components/conversation/ConversationDetailSheet.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useKnowledgeBaseExplorerNavigation } from '@/composables/useKnowledgeBaseExplorerNavigation';
import KnowledgeBasesLayout from '@/layouts/KnowledgeBasesLayout.vue';
import { appContentLayout } from '@/layouts/pageLayouts';
import { subscribeReceptionInstance } from '@/lib/mercure';
import KnowledgeBaseExplorerSidebar from '@/pages/knowledgeBase/KnowledgeBaseExplorerSidebar.vue';
import experienceExtraction from '@/routes/app/manage/experience-extraction';
import type {
  ListExperienceCandidateItemData,
  ShowExperienceExtractionResultsPagePropsData,
} from '@/types/generated';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

defineOptions({ layout: appContentLayout });

const props = defineProps<ShowExperienceExtractionResultsPagePropsData>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const explorerNavigation = useKnowledgeBaseExplorerNavigation();

const isRunning = computed(() => props.extraction.status === 'running');

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('知识库'),
    href: KnowledgeBase.ListKnowledgeBasesAction.url(),
  },
  {
    title: props.extraction.knowledge_base.name,
    href: KnowledgeBase.ListKnowledgeBasesAction.url({
      query: { kb: props.extraction.knowledge_base.id },
    }),
  },
  {
    title: t('经验提炼'),
    href: experienceExtraction.index.url({
      knowledgeBase: props.extraction.knowledge_base.id,
    }),
  },
  { title: t('提炼结果') },
]);

/** 状态 Tab 定义；计数来自后端 status_counts（任务内）。 */
const statusTabs = computed(() => [
  { value: 'pending', label: t('待处理') },
  { value: 'adopted', label: t('已采纳') },
  { value: 'discarded', label: t('已丢弃') },
]);

const switchStatus = (status: string) => {
  router.get(
    experienceExtraction.results.url({
      extraction: props.extraction.id,
    }),
    status !== 'pending' ? { status } : {},
    { preserveScroll: true, preserveState: true },
  );
};

/** 当前选中的候选；列表变化（切 Tab / 刷新）时回落到第一条。 */
const selectedCandidateId = ref<string | null>(props.candidates[0]?.id ?? null);
const selectedCandidate = computed<ListExperienceCandidateItemData | null>(
  () =>
    props.candidates.find((c) => c.id === selectedCandidateId.value) ?? null,
);
watch(
  () => props.candidates,
  (candidates) => {
    if (!candidates.some((c) => c.id === selectedCandidateId.value)) {
      selectedCandidateId.value = candidates[0]?.id ?? null;
    }
  },
);

/** 右侧编辑区表单；切换候选时用其内容回填，供管理员直接润色。采纳落库目标即任务绑定的问答库。 */
const adoptForm = useForm({
  question: '',
  similar_questions_text: '',
  answer: '',
});
watch(
  selectedCandidate,
  (candidate) => {
    adoptForm.clearErrors();
    adoptForm.question = candidate?.question ?? '';
    adoptForm.similar_questions_text =
      candidate?.similar_questions.join('\n') ?? '';
    adoptForm.answer = candidate?.answer ?? '';
  },
  { immediate: true },
);

const submitAdopt = () => {
  const candidate = selectedCandidate.value;
  if (!candidate) {
    return;
  }

  adoptForm
    .transform((data) => ({
      question: data.question,
      answer: data.answer,
      similar_questions: data.similar_questions_text
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== ''),
    }))
    .put(
      experienceExtraction.candidates.adopt.url({
        candidate: candidate.id,
      }),
      { preserveScroll: true, preserveState: true },
    );
};

/** 丢弃候选（状态留档，UI 即时反馈，无需二次确认）。 */
const discardForm = useForm({});
const discardCandidate = () => {
  const candidate = selectedCandidate.value;
  if (!candidate) {
    return;
  }

  discardForm.put(
    experienceExtraction.candidates.discard.url({
      candidate: candidate.id,
    }),
    { preserveScroll: true, preserveState: true },
  );
};

/** 来源会话详情抽屉：核对原文时编辑内容保留在原处。 */
const viewingConversationId = ref<string | null>(null);

/** 任务运行结束的应用信号 → 回源刷新候选列表。 */
let unsubscribe: (() => void) | null = null;
onMounted(() => {
  unsubscribe = subscribeReceptionInstance((payload) => {
    if (payload.event === 'experience_extraction_finished') {
      router.reload();
    }
  });
});
onUnmounted(() => {
  unsubscribe?.();
  unsubscribe = null;
});
</script>

<template>
  <div class="contents">
    <Head :title="t('经验提炼-结果')" />

    <KnowledgeBasesLayout>
      <template #sidebar="{ closeMobileExplorer }">
        <KnowledgeBaseExplorerSidebar
          :knowledge-bases="props.sidebar.knowledge_base_list"
          :category-options="props.sidebar.category_options"
          :active-kb-id="props.extraction.knowledge_base.id"
          @navigate="closeMobileExplorer"
          @select-kb="explorerNavigation.openKb"
          @select-group="explorerNavigation.openGroup"
          @create="explorerNavigation.openCreateKb"
          @edit="explorerNavigation.openEditKb"
        />
      </template>

      <div class="flex h-full flex-col gap-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="space-y-1.5">
          <HeadingSmall
            :title="t('经验提炼-结果')"
            :description="`${formatDateTime(extraction.created_at)} · ${t('会话 {count} 个', { count: extraction.conversation_count })}`"
          />
          <p
            v-if="isRunning"
            class="flex items-center gap-1.5 text-xs text-muted-foreground"
          >
            <Spinner class="h-3 w-3" />
            {{
              t('正在分析 {count} 个会话…', {
                count: extraction.conversation_count,
              })
            }}
          </p>
          <p
            v-else-if="extraction.status === 'failed'"
            class="text-xs text-muted-foreground"
          >
            {{ t('提炼失败') }}：{{ extraction.error }}
          </p>
        </div>

        <!-- 审核后台：左侧候选队列 + 右侧内联编辑区 -->
        <div
          class="grid min-h-0 flex-1 gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]"
        >
          <div class="flex min-h-0 flex-col gap-3">
            <div
              class="flex w-fit rounded-md border bg-background p-0.5 text-xs"
            >
              <button
                v-for="tab in statusTabs"
                :key="tab.value"
                type="button"
                class="rounded px-3 py-1.5 transition-colors"
                :class="
                  active_status === tab.value
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted'
                "
                @click="switchStatus(tab.value)"
              >
                {{ tab.label }}
                <span
                  v-if="(status_counts[tab.value] ?? 0) > 0"
                  class="ml-1 opacity-70"
                >
                  {{ status_counts[tab.value] }}
                </span>
              </button>
            </div>

            <p
              v-if="candidates.length === 0"
              class="rounded-lg border py-10 text-center text-sm text-muted-foreground"
            >
              {{
                isRunning ? t('提炼完成后候选经验将出现在这里') : t('暂无内容')
              }}
            </p>
            <ul v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
              <li v-for="candidate in candidates" :key="candidate.id">
                <button
                  type="button"
                  class="w-full rounded-lg border p-3 text-left transition-colors"
                  :class="
                    candidate.id === selectedCandidateId
                      ? 'border-foreground'
                      : 'hover:bg-muted/50'
                  "
                  @click="selectedCandidateId = candidate.id"
                >
                  <p class="line-clamp-2 text-sm font-medium">
                    {{ candidate.question }}
                  </p>
                  <p class="mt-1 text-xs text-muted-foreground">
                    {{
                      t('来源会话 {count} 个', {
                        count: candidate.conversation_count,
                      })
                    }}
                  </p>
                </button>
              </li>
            </ul>
          </div>

          <!-- 右侧：内联编辑 / 只读详情 -->
          <div class="min-h-0 overflow-y-auto rounded-lg border p-6">
            <p
              v-if="!selectedCandidate"
              class="py-16 text-center text-sm text-muted-foreground"
            >
              {{ t('在左侧选择一条候选经验') }}
            </p>
            <template v-else>
              <form
                v-if="selectedCandidate.status === 'pending'"
                class="space-y-5"
                @submit.prevent="submitAdopt"
              >
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <p class="text-xs text-muted-foreground">
                    {{
                      t('采纳后写入「{name}」', {
                        name: props.extraction.knowledge_base.name,
                      })
                    }}
                  </p>
                  <div class="flex gap-2">
                    <Button
                      type="button"
                      variant="ghost"
                      :disabled="discardForm.processing"
                      @click="discardCandidate"
                    >
                      {{ t('丢弃') }}
                    </Button>
                    <Button type="submit" :disabled="adoptForm.processing">
                      {{ t('采纳并写入知识库') }}
                    </Button>
                  </div>
                </div>
                <div class="grid gap-2">
                  <Label for="adopt-question" required>{{ t('主问题') }}</Label>
                  <Input
                    id="adopt-question"
                    v-model="adoptForm.question"
                    class="w-full"
                    required
                  />
                  <InputError
                    class="mt-2"
                    :message="adoptForm.errors.question"
                  />
                </div>
                <div class="grid gap-2">
                  <Label for="adopt-similar">{{ t('相似问法') }}</Label>
                  <Textarea
                    id="adopt-similar"
                    v-model="adoptForm.similar_questions_text"
                    :rows="6"
                    :placeholder="t('每行一条')"
                  />
                </div>
                <div class="grid gap-2">
                  <Label for="adopt-answer" required>{{ t('答案') }}</Label>
                  <Textarea
                    id="adopt-answer"
                    v-model="adoptForm.answer"
                    :rows="18"
                    required
                  />
                  <InputError class="mt-2" :message="adoptForm.errors.answer" />
                </div>
                <div
                  v-if="selectedCandidate.source_conversation_ids.length > 0"
                  class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                >
                  <span>{{ t('来源会话') }}</span>
                  <button
                    v-for="(
                      conversationId, index
                    ) in selectedCandidate.source_conversation_ids"
                    :key="conversationId"
                    type="button"
                    class="rounded border px-1.5 py-0.5 hover:bg-muted hover:text-foreground"
                    @click="viewingConversationId = conversationId"
                  >
                    {{ index + 1 }}
                  </button>
                  <span>{{ t('（点击核对原始对话）') }}</span>
                </div>
              </form>

              <!-- 已处理候选：只读展示 -->
              <div v-else class="space-y-5">
                <div class="flex items-start justify-between gap-3">
                  <p class="font-medium">{{ selectedCandidate.question }}</p>
                  <Badge variant="outline" class="shrink-0">
                    {{ selectedCandidate.status_label }}
                  </Badge>
                </div>
                <div
                  v-if="selectedCandidate.similar_questions.length > 0"
                  class="flex flex-wrap gap-1.5"
                >
                  <Badge
                    v-for="similar in selectedCandidate.similar_questions"
                    :key="similar"
                    variant="secondary"
                    class="font-normal"
                  >
                    {{ similar }}
                  </Badge>
                </div>
                <p class="text-sm whitespace-pre-wrap text-muted-foreground">
                  {{ selectedCandidate.answer }}
                </p>
                <div
                  v-if="selectedCandidate.source_conversation_ids.length > 0"
                  class="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground"
                >
                  <span>{{ t('来源会话') }}</span>
                  <button
                    v-for="(
                      conversationId, index
                    ) in selectedCandidate.source_conversation_ids"
                    :key="conversationId"
                    type="button"
                    class="rounded border px-1.5 py-0.5 hover:bg-muted hover:text-foreground"
                    @click="viewingConversationId = conversationId"
                  >
                    {{ index + 1 }}
                  </button>
                </div>
              </div>
            </template>
          </div>
        </div>
      </div>
    </KnowledgeBasesLayout>

    <ConversationDetailSheet v-model="viewingConversationId" />
  </div>
</template>
