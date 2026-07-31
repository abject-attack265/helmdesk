<!-- 接待方案详情页，使用 ShowReceptionPlanDetailPagePropsData 编辑基础信息、接待方式、服务时间、知识库和集成。 -->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import ChecklistSelectItem from '@/components/reception/ChecklistSelectItem.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { appContentLayout } from '@/layouts/pageLayouts';
import PlanBasicsForm, {
  type PlanBasicsFormShape,
} from '@/pages/reception/plans/PlanBasicsForm.vue';
import PlanBusinessHoursForm from '@/pages/reception/plans/PlanBusinessHoursForm.vue';
import PlanStrategyForm, {
  type ReceptionStrategyConfigDraft,
} from '@/pages/reception/plans/PlanStrategyForm.vue';
import app from '@/routes/app';
import type {
  PlanIntegrationGrantData,
  PlanKnowledgeBaseOptionData,
  ShowReceptionPlanDetailPagePropsData,
} from '@/types/generated';
import { Head, router, useForm } from '@inertiajs/vue3';
import { BookOpen, HelpCircle, Smartphone, X } from '@lucide/vue';
import {
  computed,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
  type Component,
} from 'vue';

defineOptions({ layout: appContentLayout });

type PlanFormTab =
  'basic' | 'strategy' | 'business_hours' | 'knowledge_bases' | 'integrations';

const props = defineProps<ShowReceptionPlanDetailPagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('接待方案'),
    href: app.manage.reception.plans.index.url(),
  },
  { title: props.plan.name },
]);

const planFormTabs: Array<{ value: PlanFormTab; label: string }> = [
  { value: 'basic', label: '基础信息' },
  { value: 'strategy', label: '接待方式' },
  { value: 'business_hours', label: '人工服务时间' },
  { value: 'knowledge_bases', label: '知识库' },
  { value: 'integrations', label: '集成' },
];

const tabQueryParam = 'tab';

const readPlanFormTabFromUrl = (): PlanFormTab => {
  if (typeof window === 'undefined') {
    return 'basic';
  }

  const url = new URL(window.location.href);
  const requested = url.searchParams.get(tabQueryParam);
  return planFormTabs.some((tab) => tab.value === requested)
    ? (requested as PlanFormTab)
    : 'basic';
};

const writeTabToUrl = (tab: PlanFormTab): void => {
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);
  if (tab === 'basic') {
    url.searchParams.delete(tabQueryParam);
  } else {
    url.searchParams.set(tabQueryParam, tab);
  }
  window.history.replaceState(window.history.state, '', url.toString());
};

const activePlanFormTab = ref<PlanFormTab>(readPlanFormTabFromUrl());
watch(activePlanFormTab, (tab) => writeTabToUrl(tab));

const listUrl = computed(() => app.manage.reception.plans.index.url());

type PlanFormState = PlanBasicsFormShape & {
  knowledge_base_ids: string[];
  integration_grants: PlanIntegrationGrantData[];
  strategy_config: ReceptionStrategyConfigDraft;
};

function planStrategyConfig(): ReceptionStrategyConfigDraft {
  const plan = props.plan;
  return {
    reception_mode: plan.strategy_config.reception_mode,
    unassigned_ai_takeover_enabled:
      plan.strategy_config.unassigned_ai_takeover_enabled,
    unassigned_ai_takeover_timeout_seconds:
      plan.strategy_config.unassigned_ai_takeover_timeout_seconds,
    teammate_no_response_ai_takeover_enabled:
      plan.strategy_config.teammate_no_response_ai_takeover_enabled,
    teammate_no_response_ai_takeover_timeout_seconds:
      plan.strategy_config.teammate_no_response_ai_takeover_timeout_seconds,
    auto_close_enabled: plan.strategy_config.auto_close_enabled,
    auto_close_idle_minutes: plan.strategy_config.auto_close_idle_minutes,
    important_contact_ai_careful_reply_enabled:
      plan.strategy_config.important_contact_ai_careful_reply_enabled,
    important_contact_ai_handoff_hint_enabled:
      plan.strategy_config.important_contact_ai_handoff_hint_enabled,
    important_contact_human_first_when_online_enabled:
      plan.strategy_config.important_contact_human_first_when_online_enabled,
    quote_visitor_message_enabled:
      plan.strategy_config.quote_visitor_message_enabled,
    handoff_available_notice: plan.strategy_config.handoff_available_notice,
    handoff_no_teammate_notice: plan.strategy_config.handoff_no_teammate_notice,
    ai_unavailable_notice: plan.strategy_config.ai_unavailable_notice,
    business_hours: plan.strategy_config.business_hours
      ? {
          timezone: plan.strategy_config.business_hours.timezone,
          outside_hours_notice:
            plan.strategy_config.business_hours.outside_hours_notice,
          schedule: plan.strategy_config.business_hours.schedule.map((day) => ({
            day: day.day,
            enabled: day.enabled,
            open: day.open,
            close: day.close,
          })),
        }
      : null,
  };
}

function planFormDefaults(): PlanFormState {
  const plan = props.plan;
  return {
    name: plan.name,
    description: plan.description ?? '',
    persona_display_name: plan.persona_config.display_name,
    persona_tone: plan.persona_config.tone,
    global_instructions: plan.global_instructions ?? '',
    knowledge_base_ids: plan.knowledge_base_ids,
    integration_grants: plan.integration_grants.map((grant) => ({
      integration_id: grant.integration_id,
      tool_names: [...grant.tool_names],
    })),
    strategy_config: planStrategyConfig(),
  };
}

const planForm = useForm<PlanFormState>(planFormDefaults());
const formDisabled = computed(() => planForm.processing);

function resetPlanFormFromProps(): void {
  const defaults = planFormDefaults();
  planForm.defaults(defaults);
  planForm.reset();
  planForm.clearErrors();
}

watch(() => props.plan, resetPlanFormFromProps, { deep: true });

function tabForErrors(errors: Record<string, string>): PlanFormTab | null {
  const fields = Object.keys(errors);
  const basicFields = new Set([
    'name',
    'description',
    'persona_display_name',
    'persona_tone',
    'global_instructions',
  ]);

  if (fields.some((field) => basicFields.has(field))) {
    return 'basic';
  }

  if (
    fields.some(
      (field) =>
        field.startsWith('strategy_config') &&
        !field.startsWith('strategy_config.business_hours'),
    )
  ) {
    return 'strategy';
  }

  if (
    fields.some((field) => field.startsWith('strategy_config.business_hours'))
  ) {
    return 'business_hours';
  }

  if (fields.some((field) => field.startsWith('knowledge_base_ids'))) {
    return 'knowledge_bases';
  }

  if (fields.some((field) => field.startsWith('integration_grants'))) {
    return 'integrations';
  }

  return null;
}

function savePlan(): void {
  if (formDisabled.value) {
    return;
  }

  planForm.put(
    app.manage.reception.plans.update.url({
      plan: props.plan.id,
    }),
    {
      preserveScroll: true,
      onSuccess: resetPlanFormFromProps,
      onError: (errors) => {
        const targetTab = tabForErrors(errors);
        if (targetTab) {
          activePlanFormTab.value = targetTab;
        }
      },
    },
  );
}

function confirmLeaveIfDirty(): boolean {
  if (planForm.processing) {
    return false;
  }

  if (!planForm.isDirty) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!planForm.isDirty && !planForm.processing) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

let removeBeforeListener: (() => void) | null = null;

onMounted(() => {
  removeBeforeListener = router.on('before', (event) => {
    if (event.detail.visit.method === 'get' && !confirmLeaveIfDirty()) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
});

const kbAssociateDialogOpen = ref(false);
const kbDialogSelection = ref<string[]>([]);
const kbRemoveTarget = ref<string | null>(null);

const integrationAssociateDialogOpen = ref(false);
const integrationDialogSelection = ref<Record<string, string[]>>({});
const integrationRemoveTarget = ref<string | null>(null);

const knowledgeBaseOptionsById = computed(
  () =>
    new Map(props.knowledge_base_options.map((option) => [option.id, option])),
);

const integrationOptionsById = computed(
  () => new Map(props.integration_options.map((option) => [option.id, option])),
);

function knowledgeBaseName(knowledgeBaseId: string): string {
  return knowledgeBaseOption(knowledgeBaseId).name;
}

function knowledgeBaseOption(
  knowledgeBaseId: string,
): PlanKnowledgeBaseOptionData {
  const option = knowledgeBaseOptionsById.value.get(knowledgeBaseId);
  if (!option) {
    throw new Error(`知识库选项不存在：${knowledgeBaseId}`);
  }

  return option;
}

function integrationName(integrationId: string): string {
  return (
    integrationOptionsById.value.get(integrationId)?.name ?? t('集成已不可用')
  );
}

function grantToolSummary(grant: PlanIntegrationGrantData): string {
  if (grant.tool_names.length === 0) {
    return t('全部可用工具');
  }
  return grant.tool_names.join('、');
}

function kbCategoryIcon(category: string | null): Component {
  switch (category) {
    case 'qa':
      return HelpCircle;
    case 'wechat_public':
      return Smartphone;
    default:
      return BookOpen;
  }
}

function openKbDialog(): void {
  if (formDisabled.value) {
    return;
  }

  kbDialogSelection.value = [...planForm.knowledge_base_ids];
  kbAssociateDialogOpen.value = true;
}

function toggleKbDialogSelection(id: string): void {
  if (formDisabled.value) {
    return;
  }

  if (kbDialogSelection.value.includes(id)) {
    kbDialogSelection.value = kbDialogSelection.value.filter((v) => v !== id);
  } else {
    kbDialogSelection.value = [...kbDialogSelection.value, id];
  }
}

function applyKbSelection(): void {
  if (formDisabled.value) {
    return;
  }

  planForm.knowledge_base_ids = [...kbDialogSelection.value];
  kbAssociateDialogOpen.value = false;
}

function confirmRemoveKb(): void {
  if (formDisabled.value) {
    return;
  }

  if (kbRemoveTarget.value) {
    planForm.knowledge_base_ids = planForm.knowledge_base_ids.filter(
      (id) => id !== kbRemoveTarget.value,
    );
    kbRemoveTarget.value = null;
  }
}

function openIntegrationDialog(): void {
  if (formDisabled.value) {
    return;
  }

  const selection: Record<string, string[]> = {};
  for (const grant of planForm.integration_grants) {
    selection[grant.integration_id] = [...grant.tool_names];
  }
  integrationDialogSelection.value = selection;
  integrationAssociateDialogOpen.value = true;
}

function isIntegrationSelected(integrationId: string): boolean {
  return integrationId in integrationDialogSelection.value;
}

function toggleIntegrationSelection(integrationId: string): void {
  if (formDisabled.value) {
    return;
  }

  const next = { ...integrationDialogSelection.value };
  if (integrationId in next) {
    delete next[integrationId];
  } else {
    next[integrationId] = [];
  }
  integrationDialogSelection.value = next;
}

function isToolSelected(integrationId: string, toolName: string): boolean {
  return (
    integrationDialogSelection.value[integrationId]?.includes(toolName) ?? false
  );
}

function toggleToolSelection(integrationId: string, toolName: string): void {
  if (formDisabled.value) {
    return;
  }

  const next = { ...integrationDialogSelection.value };
  const current = next[integrationId] ?? [];
  next[integrationId] = current.includes(toolName)
    ? current.filter((name) => name !== toolName)
    : [...current, toolName];
  integrationDialogSelection.value = next;
}

function applyIntegrationSelection(): void {
  if (formDisabled.value) {
    return;
  }

  planForm.integration_grants = Object.entries(
    integrationDialogSelection.value,
  ).map(([integration_id, tool_names]) => ({
    integration_id,
    tool_names: [...tool_names],
  }));
  integrationAssociateDialogOpen.value = false;
}

function confirmRemoveIntegration(): void {
  if (formDisabled.value) {
    return;
  }

  if (integrationRemoveTarget.value) {
    planForm.integration_grants = planForm.integration_grants.filter(
      (grant) => grant.integration_id !== integrationRemoveTarget.value,
    );
    integrationRemoveTarget.value = null;
  }
}
</script>

<template>
  <div class="contents">
    <Head :title="props.plan.name" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="props.plan.name"
          :description="t('设置这个方案如何接待访客。')"
        />

        <form class="space-y-6" @submit.prevent="savePlan">
          <div class="border-b">
            <div
              class="flex gap-1 overflow-x-auto"
              role="tablist"
              :aria-label="t('接待方案表单')"
            >
              <button
                v-for="tab in planFormTabs"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="activePlanFormTab === tab.value"
                :disabled="planForm.processing"
                :class="[
                  'border-b-2 px-3 py-2 text-sm font-medium whitespace-nowrap transition-colors disabled:cursor-not-allowed disabled:opacity-60',
                  activePlanFormTab === tab.value
                    ? 'border-foreground text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground',
                ]"
                @click="activePlanFormTab = tab.value"
              >
                {{ t(tab.label) }}
              </button>
            </div>
          </div>

          <div
            v-if="activePlanFormTab === 'basic'"
            class="flex min-h-[calc(100dvh-20rem)] flex-col gap-6"
          >
            <p class="text-sm text-muted-foreground">
              {{ t('填写方案名称和访客看到的客服信息。') }}
            </p>
            <PlanBasicsForm
              class="min-h-0 flex-1"
              :form="planForm"
              :persona-tone-options="props.persona_tone_options"
              :disabled="formDisabled"
            />
          </div>

          <div v-else-if="activePlanFormTab === 'strategy'" class="space-y-6">
            <p class="text-sm text-muted-foreground">
              {{ t('设置由 AI 还是客服先接待，以及何时切换或结束会话。') }}
            </p>
            <PlanStrategyForm :form="planForm" :disabled="formDisabled" />
          </div>

          <div
            v-else-if="activePlanFormTab === 'business_hours'"
            class="space-y-6"
          >
            <p class="text-sm text-muted-foreground">
              {{ t('设置客服每周提供人工服务的时间。') }}
            </p>
            <PlanBusinessHoursForm :form="planForm" :disabled="formDisabled" />
          </div>

          <div
            v-else-if="activePlanFormTab === 'knowledge_bases'"
            class="space-y-4"
          >
            <div class="flex items-start justify-between gap-3">
              <p class="text-sm text-muted-foreground">
                {{ t('选择接待时可以使用的知识库。') }}
              </p>

              <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="formDisabled"
                @click="openKbDialog"
              >
                {{ t('选择知识库') }}
              </Button>
            </div>

            <div
              v-if="planForm.knowledge_base_ids.length > 0"
              class="grid gap-3 sm:grid-cols-2"
            >
              <div
                v-for="kbId in planForm.knowledge_base_ids"
                :key="kbId"
                class="flex items-start gap-3 rounded-lg border p-3"
              >
                <div class="mt-0.5 shrink-0">
                  <component
                    :is="kbCategoryIcon(knowledgeBaseOption(kbId).category)"
                    class="h-4 w-4 text-muted-foreground"
                  />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium">
                    {{ knowledgeBaseName(kbId) }}
                  </p>
                  <p class="text-xs text-muted-foreground">
                    {{ knowledgeBaseOption(kbId).category_label }}
                  </p>
                </div>

                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-7 w-7 shrink-0 text-muted-foreground hover:text-destructive"
                  :aria-label="t('移除知识库')"
                  :disabled="formDisabled"
                  @click="kbRemoveTarget = kbId"
                >
                  <X class="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
            <p
              v-else
              class="rounded-lg border border-dashed py-8 text-center text-xs text-muted-foreground"
            >
              {{ t('还没有选择知识库') }}
            </p>
            <InputError
              v-if="
                typeof (planForm.errors as Record<string, unknown>)
                  .knowledge_base_ids === 'string'
              "
              :message="
                (planForm.errors as Record<string, string>).knowledge_base_ids
              "
            />
          </div>

          <div
            v-else-if="activePlanFormTab === 'integrations'"
            class="space-y-4"
          >
            <div class="flex items-start justify-between gap-3">
              <p class="text-sm text-muted-foreground">
                {{ t('选择接待时可以使用的集成和工具。') }}
              </p>

              <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="formDisabled"
                @click="openIntegrationDialog"
              >
                {{ t('选择集成') }}
              </Button>
            </div>

            <div
              v-if="planForm.integration_grants.length > 0"
              class="grid gap-3 sm:grid-cols-2"
            >
              <div
                v-for="grant in planForm.integration_grants"
                :key="grant.integration_id"
                class="flex items-start justify-between gap-2 rounded-lg border p-3"
              >
                <div class="min-w-0">
                  <p class="truncate text-sm font-medium">
                    {{ integrationName(grant.integration_id) }}
                  </p>
                  <p class="mt-0.5 line-clamp-2 text-xs text-muted-foreground">
                    {{ grantToolSummary(grant) }}
                  </p>
                </div>

                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-7 w-7 shrink-0 text-muted-foreground hover:text-destructive"
                  :aria-label="t('移除集成')"
                  :disabled="formDisabled"
                  @click="integrationRemoveTarget = grant.integration_id"
                >
                  <X class="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
            <p
              v-else
              class="rounded-lg border border-dashed py-8 text-center text-xs text-muted-foreground"
            >
              {{ t('还没有选择集成') }}
            </p>
            <InputError
              v-if="
                typeof (planForm.errors as Record<string, unknown>)
                  .integration_grants === 'string'
              "
              :message="
                (planForm.errors as Record<string, string>).integration_grants
              "
            />
          </div>

          <FormActions
            class="pt-6"
            :submit-label="t('保存')"
            :processing="planForm.processing"
            :submit-disabled="formDisabled"
            :cancel-href="listUrl"
            :cancel-label="t('返回')"
          />
        </form>
      </div>
    </div>

    <Dialog v-model:open="kbAssociateDialogOpen">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{{ t('选择知识库') }}</DialogTitle>
          <DialogDescription>
            {{ t('选择接待时需要使用的知识库，保存方案后生效。') }}
          </DialogDescription>
        </DialogHeader>
        <div class="max-h-80 space-y-1.5 overflow-y-auto py-1">
          <p
            v-if="props.knowledge_base_options.length === 0"
            class="py-4 text-center text-sm text-muted-foreground"
          >
            {{ t('暂无可用知识库') }}
          </p>
          <ChecklistSelectItem
            v-for="kb in props.knowledge_base_options"
            :key="kb.id"
            :title="kb.name"
            :subtitle="kb.category_label"
            :selected="kbDialogSelection.includes(kb.id)"
            :icon="kbCategoryIcon(kb.category)"
            :disabled="formDisabled"
            highlight-selected
            @select="toggleKbDialogSelection(kb.id)"
          />
        </div>
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            :disabled="planForm.processing"
            @click="kbAssociateDialogOpen = false"
          >
            {{ t('取消') }}
          </Button>
          <Button
            type="button"
            :disabled="formDisabled"
            @click="applyKbSelection"
          >
            {{ t('完成选择') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="integrationAssociateDialogOpen">
      <DialogContent class="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{{ t('选择集成') }}</DialogTitle>
          <DialogDescription>
            {{ t('选择接待时需要使用的集成和工具，保存方案后生效。') }}
          </DialogDescription>
        </DialogHeader>
        <div class="max-h-96 space-y-2 overflow-y-auto py-1">
          <p
            v-if="props.integration_options.length === 0"
            class="py-4 text-center text-sm text-muted-foreground"
          >
            {{ t('暂无可用集成') }}
          </p>
          <ChecklistSelectItem
            v-for="integration in props.integration_options"
            :key="integration.id"
            :title="integration.name"
            :subtitle="integration.provider_label"
            :selected="isIntegrationSelected(integration.id)"
            :disabled="formDisabled"
            @select="toggleIntegrationSelection(integration.id)"
          >
            <div
              v-if="
                isIntegrationSelected(integration.id) &&
                integration.tools.length > 0
              "
              class="border-t px-3 py-2"
            >
              <p class="mb-1.5 text-xs text-muted-foreground">
                {{
                  t('可用工具（未选择时使用全部 {count} 个工具）', {
                    count: integration.tools.length,
                  })
                }}
              </p>
              <div class="flex flex-wrap gap-1.5">
                <button
                  v-for="tool in integration.tools"
                  :key="tool.name"
                  type="button"
                  :class="[
                    'rounded-md border px-2 py-1 text-xs transition-colors',
                    isToolSelected(integration.id, tool.name)
                      ? 'border-foreground bg-foreground text-background'
                      : 'text-muted-foreground hover:bg-muted',
                  ]"
                  :title="tool.description ?? undefined"
                  :aria-pressed="isToolSelected(integration.id, tool.name)"
                  :disabled="formDisabled"
                  @click="toggleToolSelection(integration.id, tool.name)"
                >
                  {{ tool.name }}
                </button>
              </div>
            </div>
            <p
              v-else-if="
                isIntegrationSelected(integration.id) &&
                integration.tools.length === 0
              "
              class="border-t px-3 py-2 text-xs text-muted-foreground"
            >
              {{ t('当前没有可用工具') }}
            </p>
          </ChecklistSelectItem>
        </div>
        <DialogFooter>
          <Button
            type="button"
            variant="outline"
            :disabled="planForm.processing"
            @click="integrationAssociateDialogOpen = false"
          >
            {{ t('取消') }}
          </Button>
          <Button
            type="button"
            :disabled="formDisabled"
            @click="applyIntegrationSelection"
          >
            {{ t('完成选择') }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
      :open="kbRemoveTarget !== null"
      :title="t('移除这个知识库？')"
      :detail-title="
        props.knowledge_base_options.find((kb) => kb.id === kbRemoveTarget)
          ?.name
      "
      :detail-description="t('移除并保存后，接待时将不再使用这个知识库。')"
      :confirm-label="t('移除')"
      :processing-label="t('处理中...')"
      :processing="false"
      @update:open="(v) => !v && (kbRemoveTarget = null)"
      @confirm="confirmRemoveKb"
    />

    <ConfirmDeleteDialog
      :open="integrationRemoveTarget !== null"
      :title="t('移除这个集成？')"
      :detail-title="
        integrationRemoveTarget
          ? integrationName(integrationRemoveTarget)
          : undefined
      "
      :detail-description="t('移除并保存后，接待时将不再使用这个集成。')"
      :confirm-label="t('移除')"
      :processing-label="t('处理中...')"
      :processing="false"
      @update:open="(v) => !v && (integrationRemoveTarget = null)"
      @confirm="confirmRemoveIntegration"
    />
  </div>
</template>
