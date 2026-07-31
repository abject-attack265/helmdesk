<!-- 添加接待方案表单，填写名称、用途说明、客服昵称和回复语气。 -->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import FormField from '@/components/common/FormField.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import type { ReceptionStrategyConfigDraft } from '@/pages/reception/plans/PlanStrategyForm.vue';
import app from '@/routes/app';
import type { EnumOptionData } from '@/types/generated';
import { router, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps<{
  personaToneOptions: EnumOptionData[];
}>();

const emit = defineEmits<{
  cancel: [];
  saved: [];
}>();

const { t } = useI18n();
let removeBeforeListener: (() => void) | null = null;

type CreatePlanForm = {
  name: string;
  description: string;
  persona_display_name: string;
  persona_tone: string;
  global_instructions: string;
  strategy_config: ReceptionStrategyConfigDraft;
};

function formDefaults(): CreatePlanForm {
  return {
    name: '',
    description: '',
    persona_display_name: '',
    persona_tone: 'concise',
    global_instructions: t(
      '回复要友好、简短、准确。先弄清访客的问题，再给出明确答复；不确定时如实说明，并询问必要信息。',
    ),
    strategy_config: {
      reception_mode: 'ai_first',
      unassigned_ai_takeover_enabled: false,
      unassigned_ai_takeover_timeout_seconds: 120,
      teammate_no_response_ai_takeover_enabled: false,
      teammate_no_response_ai_takeover_timeout_seconds: 300,
      auto_close_enabled: true,
      auto_close_idle_minutes: 10,
      important_contact_ai_careful_reply_enabled: true,
      important_contact_ai_handoff_hint_enabled: true,
      important_contact_human_first_when_online_enabled: false,
      quote_visitor_message_enabled: false,
      handoff_available_notice: t('正在为您转接客服，请稍等。'),
      handoff_no_teammate_notice: t('目前无法转接客服，我会继续为您处理。'),
      ai_unavailable_notice: t(
        '很抱歉，AI 暂时无法回复，正在为您转接客服，请稍候。',
      ),
      business_hours: null,
    },
  };
}

const form = useForm<CreatePlanForm>(formDefaults());

watch(
  () => props.personaToneOptions,
  () => {
    form.defaults(formDefaults());
    form.reset();
    form.clearErrors();
  },
  { immediate: true },
);

function submit(): void {
  if (form.processing) {
    return;
  }

  form.post(app.manage.reception.plans.store.url(), {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
  });
}

function confirmDiscardIfDirty(): boolean {
  if (form.processing) {
    return false;
  }
  if (!form.isDirty) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!form.isDirty && !form.processing) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

onMounted(() => {
  removeBeforeListener = router.on('before', (event) => {
    if (event.detail.visit.method === 'get' && !confirmDiscardIfDirty()) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
  <div class="mx-auto w-full max-w-none space-y-6">
    <HeadingSmall
      :title="t('添加接待方案')"
      :description="t('填写方案名称和客服信息，添加后可继续设置接待方式。')"
    />

    <form class="space-y-6" @submit.prevent="submit">
      <FormField
        :label="t('方案名称')"
        label-for="create_plan_name"
        :error="form.errors.name"
        required
      >
        <Input
          id="create_plan_name"
          v-model="form.name"
          class="mt-1 block w-full"
          autocomplete="off"
          maxlength="100"
          :disabled="form.processing"
          required
        />
      </FormField>

      <FormField
        :label="t('用途说明（选填）')"
        label-for="create_plan_description"
        :error="form.errors.description"
      >
        <Textarea
          id="create_plan_description"
          v-model="form.description"
          rows="3"
          class="mt-1 min-h-20"
          maxlength="500"
          :disabled="form.processing"
        />
      </FormField>

      <FormField
        :label="t('客服昵称')"
        label-for="create_plan_persona_display_name"
        :error="form.errors.persona_display_name"
        required
      >
        <Input
          id="create_plan_persona_display_name"
          v-model="form.persona_display_name"
          class="mt-1 block w-full"
          autocomplete="off"
          maxlength="100"
          :disabled="form.processing"
          required
        />
      </FormField>

      <FormField
        :label="t('回复语气')"
        label-for="create_plan_persona_tone"
        :error="form.errors.persona_tone"
        required
      >
        <Select v-model="form.persona_tone" :disabled="form.processing">
          <SelectTrigger
            id="create_plan_persona_tone"
            class="mt-1 w-full"
            :disabled="form.processing"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in props.personaToneOptions"
              :key="option.value"
              :value="String(option.value)"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>

      <FormActions :submit-label="t('添加')" :processing="form.processing">
        <template #submit>
          <LoaderCircle
            v-if="form.processing"
            class="mr-2 h-4 w-4 animate-spin"
          />
          {{ t('添加') }}
        </template>

        <Button
          type="button"
          variant="outline"
          :disabled="form.processing"
          @click="emit('cancel')"
        >
          {{ t('取消') }}
        </Button>
      </FormActions>
    </form>
  </div>
</template>
