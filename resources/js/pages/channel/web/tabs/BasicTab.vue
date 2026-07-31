<!--
  网站渠道基本信息页签，用于修改名称、描述和接待方案。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import Plan from '@/actions/App/Actions/Reception/Plan';
import FormActions from '@/components/common/FormActions.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useChannelPreviewDraft } from '@/composables/useChannelPreviewDraft';
import { useI18n } from '@/composables/useI18n';
import type {
  WebChannelData,
  WebChannelFormOptionsData,
} from '@/types/generated';
import type { FormComponentRef } from '@inertiajs/core';
import { Form, Link } from '@inertiajs/vue3';
import { AlertCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  channel: WebChannelData;
  formOptions: WebChannelFormOptionsData;
}>();

const { t } = useI18n();
const draft = useChannelPreviewDraft();
const formRef = ref<FormComponentRef | null>(null);
const manualDirty = ref(false);

/** 返回基本信息表单是否含有未保存内容。 */
function hasUnsavedChanges(): boolean {
  return Boolean(formRef.value?.isDirty || manualDirty.value);
}

/** 返回基本信息表单是否正在提交。 */
function isProcessing(): boolean {
  return Boolean(formRef.value?.processing);
}

defineExpose({ hasUnsavedChanges, isProcessing });

const allOptions = computed(() => props.formOptions.reception_plan_options);
const usableOptions = computed(() =>
  allOptions.value.filter((option) => option.is_usable),
);
const hasUsableOptions = computed(() => usableOptions.value.length > 0);
const hasAnyOptions = computed(() => allOptions.value.length > 0);

const currentBindingExistsInOptions = computed(() =>
  allOptions.value.some(
    (option) => option.id === props.channel.reception_plan_id,
  ),
);

const currentBindingIsUsable = computed(
  () =>
    props.channel.reception_plan_id !== null &&
    Boolean(props.channel.reception_plan_status_detail?.is_valid),
);

const hasStaleBinding = computed(
  () =>
    Boolean(props.channel.reception_plan_id) && !currentBindingIsUsable.value,
);

const selectedPlanId = ref(props.channel.reception_plan_id ?? '');
const defaultVisitorLocale = ref(props.channel.default_visitor_locale);
const visitorMessageAiTranslationEnabled = ref(
  props.channel.visitor_message_ai_translation_enabled,
);
const submittedPlanId = computed(() => selectedPlanId.value);
const canSubmitForm = computed(
  () =>
    submittedPlanId.value !== '' &&
    (hasUsableOptions.value || hasStaleBinding.value),
);

const staleReasonLabel = computed(
  () =>
    props.channel.reception_plan_status_detail?.reason_label ??
    t('接待方案不可用'),
);

const staleBindingLabel = computed(
  () => props.channel.reception_plan_name ?? t('当前接待方案'),
);

watch(
  () => props.channel,
  (channel) => {
    selectedPlanId.value = channel.reception_plan_id ?? '';
    defaultVisitorLocale.value = channel.default_visitor_locale;
    visitorMessageAiTranslationEnabled.value =
      channel.visitor_message_ai_translation_enabled;
  },
);
</script>

<template>
  <Form
    ref="formRef"
    :action="
      Web.UpdateWebChannelBasicAction.url({
        channel: props.channel.id,
      })
    "
    method="put"
    class="space-y-6"
    disable-while-processing
    set-defaults-on-success
    @input.capture="manualDirty = true"
    @change.capture="manualDirty = true"
    @success="manualDirty = false"
  >
    <template #default="{ errors, processing }">
      <div class="space-y-5">
        <div class="grid gap-2">
          <Label for="basic_name" required>{{ t('渠道名称') }}</Label>
          <Input
            id="basic_name"
            v-model="draft.channelName"
            name="name"
            maxlength="100"
            :disabled="processing"
            required
          />
          <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="basic_description">{{ t('用途说明（选填）') }}</Label>
          <Textarea
            id="basic_description"
            name="description"
            rows="3"
            maxlength="2000"
            :disabled="processing"
            :default-value="props.channel.description ?? ''"
          />
          <InputError :message="errors.description" />
        </div>

        <div class="grid gap-2">
          <Label for="basic_reception_plan_id" required>
            {{ t('接待方案') }}
          </Label>
          <Select
            v-model="selectedPlanId"
            :disabled="processing || (!hasUsableOptions && !hasStaleBinding)"
            @update:model-value="manualDirty = true"
          >
            <SelectTrigger id="basic_reception_plan_id" class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-if="
                  hasStaleBinding &&
                  !currentBindingExistsInOptions &&
                  props.channel.reception_plan_id
                "
                :value="props.channel.reception_plan_id"
                disabled
              >
                {{ staleBindingLabel }} · {{ staleReasonLabel }}
              </SelectItem>
              <SelectItem
                v-for="option in allOptions"
                :key="option.id"
                :value="option.id"
                :disabled="!option.is_usable"
              >
                <span class="text-sm">
                  {{ option.name }}
                  <span
                    v-if="!option.is_usable && option.unusable_reason_label"
                    class="ml-2 text-xs text-muted-foreground"
                  >
                    ({{ option.unusable_reason_label }})
                  </span>
                </span>
              </SelectItem>
            </SelectContent>
          </Select>
          <input
            type="hidden"
            name="reception_plan_id"
            :value="submittedPlanId"
          />
          <div
            v-if="hasStaleBinding"
            class="flex flex-wrap items-start gap-2 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
          >
            <AlertCircle class="mt-0.5 size-4 shrink-0" />
            <div class="space-y-1">
              <p class="font-medium">
                {{ t('当前接待方案不可用') }} ·
                {{ staleReasonLabel }}
              </p>
              <p>
                {{
                  t(
                    '其他内容仍可保存，但访客暂时无法发起新的咨询。请选择可用的接待方案。',
                  )
                }}
              </p>
            </div>
          </div>
          <div
            v-else-if="!hasAnyOptions"
            class="flex flex-wrap items-center gap-2"
          >
            <Button size="sm" variant="outline" as-child>
              <Link :href="Plan.ShowReceptionPlanIndexPageAction.url()">
                {{ t('查看接待方案') }}
              </Link>
            </Button>
          </div>
          <InputError :message="errors.reception_plan_id" />
        </div>

        <div class="grid gap-2">
          <Label for="basic_default_visitor_locale" required>
            {{ t('访客默认语言') }}
          </Label>
          <Select
            v-model="defaultVisitorLocale"
            :disabled="processing"
            @update:model-value="manualDirty = true"
          >
            <SelectTrigger id="basic_default_visitor_locale" class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in props.formOptions.reception_language_options"
                :key="option.value"
                :value="String(option.value)"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
          <input
            type="hidden"
            name="default_visitor_locale"
            :value="defaultVisitorLocale"
          />
          <InputError :message="errors.default_visitor_locale" />
        </div>

        <div class="grid gap-2">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-1">
              <Label for="basic_visitor_message_ai_translation_enabled">
                {{ t('访客消息优先使用 AI 增强翻译') }}
              </Label>
              <p class="text-xs leading-5 text-muted-foreground">
                {{
                  t(
                    '适合多语言混写、罗马音或俚语场景。机器翻译通常更快、更稳定。',
                  )
                }}
              </p>
            </div>
            <Switch
              id="basic_visitor_message_ai_translation_enabled"
              v-model="visitorMessageAiTranslationEnabled"
              class="mt-0.5 shrink-0"
              :disabled="processing"
              @update:model-value="manualDirty = true"
            />
          </div>
          <input
            type="hidden"
            name="visitor_message_ai_translation_enabled"
            :value="visitorMessageAiTranslationEnabled ? '1' : '0'"
          />
          <InputError
            :message="errors.visitor_message_ai_translation_enabled"
          />
        </div>

        <div v-show="visitorMessageAiTranslationEnabled" class="grid gap-2">
          <Label for="basic_translation_context_hint">
            {{ t('AI 翻译补充说明（选填）') }}
          </Label>
          <Textarea
            id="basic_translation_context_hint"
            name="translation_context_hint"
            rows="3"
            maxlength="2000"
            :disabled="processing"
            :default-value="props.channel.translation_context_hint ?? ''"
            :placeholder="t('例如：访客常用中英混合表达，产品名称请保留英文。')"
          />
          <InputError :message="errors.translation_context_hint" />
        </div>
      </div>

      <FormActions
        :submit-label="t('保存')"
        :processing="processing"
        :submit-disabled="!canSubmitForm"
        :cancel-href="Web.ListWebChannelsAction.url()"
        :cancel-label="t('返回')"
      />
    </template>
  </Form>
</template>
