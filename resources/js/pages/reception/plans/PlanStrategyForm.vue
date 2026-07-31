<!--
  接待方案接待方式表单，设置接待顺序、自动切换和会话结束条件。
-->
<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- 控件直接写入共享的 Inertia Form */
import InputError from '@/components/common/InputError.vue';
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
import { useI18n } from '@/composables/useI18n';
import type { InertiaForm } from '@inertiajs/vue3';

export type ReceptionRoutingModeDraft = 'ai_first' | 'teammate_first';

export type ReceptionBusinessHoursDayDraft = {
  day: number;
  enabled: boolean;
  open: string;
  close: string;
};

export type ReceptionBusinessHoursDraft = {
  timezone: string;
  outside_hours_notice: string;
  schedule: ReceptionBusinessHoursDayDraft[];
};

export type ReceptionStrategyConfigDraft = {
  reception_mode: ReceptionRoutingModeDraft;
  unassigned_ai_takeover_enabled: boolean;
  unassigned_ai_takeover_timeout_seconds: number;
  teammate_no_response_ai_takeover_enabled: boolean;
  teammate_no_response_ai_takeover_timeout_seconds: number;
  auto_close_enabled: boolean;
  auto_close_idle_minutes: number;
  important_contact_ai_careful_reply_enabled: boolean;
  important_contact_ai_handoff_hint_enabled: boolean;
  important_contact_human_first_when_online_enabled: boolean;
  quote_visitor_message_enabled: boolean;
  handoff_available_notice: string;
  handoff_no_teammate_notice: string;
  ai_unavailable_notice: string;
  business_hours: ReceptionBusinessHoursDraft | null;
};

export type PlanStrategyFormShape = {
  strategy_config: ReceptionStrategyConfigDraft;
};

const props = defineProps<{
  form: InertiaForm<PlanStrategyFormShape>;
  disabled: boolean;
}>();

const { t } = useI18n();

const routingModeOptions: Array<{
  value: ReceptionRoutingModeDraft;
  label: string;
}> = [
  { value: 'ai_first', label: t('AI 先接待') },
  { value: 'teammate_first', label: t('客服先接待') },
];

function strategyError(field: string): string | undefined {
  return (props.form.errors as Record<string, string | undefined>)[
    `strategy_config.${field}`
  ];
}

type TakeoverTimeoutField =
  | 'unassigned_ai_takeover_timeout_seconds'
  | 'teammate_no_response_ai_takeover_timeout_seconds';

function takeoverMinutes(field: TakeoverTimeoutField): number {
  return props.form.strategy_config[field] / 60;
}

function updateTakeoverMinutes(
  field: TakeoverTimeoutField,
  value: string | number,
): void {
  const minutes = Number(value);
  if (!Number.isFinite(minutes)) {
    return;
  }

  props.form.strategy_config[field] = Math.round(minutes * 60);
}
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-2">
      <Label for="plan_strategy_reception_mode" required>
        {{ t('接待方式') }}
      </Label>
      <Select
        v-model="props.form.strategy_config.reception_mode"
        :disabled="props.disabled"
      >
        <SelectTrigger
          id="plan_strategy_reception_mode"
          class="w-full"
          aria-required="true"
          :disabled="props.disabled"
        >
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in routingModeOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
      <InputError :message="strategyError('reception_mode')" />
    </div>

    <div
      v-if="props.form.strategy_config.reception_mode === 'teammate_first'"
      class="space-y-5"
    >
      <div class="grid gap-2">
        <Label for="plan_strategy_unassigned_ai_takeover_enabled">
          {{ t('无人接待时转由 AI 接待') }}
        </Label>
        <Switch
          id="plan_strategy_unassigned_ai_takeover_enabled"
          v-model="props.form.strategy_config.unassigned_ai_takeover_enabled"
          :disabled="props.disabled"
        />
        <InputError
          :message="strategyError('unassigned_ai_takeover_enabled')"
        />
      </div>
      <div
        v-if="props.form.strategy_config.unassigned_ai_takeover_enabled"
        class="grid gap-2"
      >
        <Label
          for="plan_strategy_unassigned_ai_takeover_timeout_seconds"
          required
        >
          {{ t('等待多久后转由 AI 接待（分钟）') }}
        </Label>
        <Input
          id="plan_strategy_unassigned_ai_takeover_timeout_seconds"
          :model-value="
            takeoverMinutes('unassigned_ai_takeover_timeout_seconds')
          "
          type="number"
          min="0"
          max="1440"
          step="0.5"
          :disabled="props.disabled"
          required
          @update:model-value="
            updateTakeoverMinutes(
              'unassigned_ai_takeover_timeout_seconds',
              $event,
            )
          "
        />
        <InputError
          :message="strategyError('unassigned_ai_takeover_timeout_seconds')"
        />
      </div>
    </div>

    <div class="space-y-5">
      <div class="grid gap-2">
        <Label for="plan_strategy_teammate_no_response_ai_takeover_enabled">
          {{ t('客服长时间未回复时转由 AI 接待') }}
        </Label>
        <Switch
          id="plan_strategy_teammate_no_response_ai_takeover_enabled"
          v-model="
            props.form.strategy_config.teammate_no_response_ai_takeover_enabled
          "
          :disabled="props.disabled"
        />
        <InputError
          :message="strategyError('teammate_no_response_ai_takeover_enabled')"
        />
      </div>
      <div
        v-if="
          props.form.strategy_config.teammate_no_response_ai_takeover_enabled
        "
        class="grid gap-2"
      >
        <Label
          for="plan_strategy_teammate_no_response_ai_takeover_timeout_seconds"
          required
        >
          {{ t('客服多久未回复后转由 AI 接待（分钟）') }}
        </Label>
        <Input
          id="plan_strategy_teammate_no_response_ai_takeover_timeout_seconds"
          :model-value="
            takeoverMinutes('teammate_no_response_ai_takeover_timeout_seconds')
          "
          type="number"
          min="0"
          max="1440"
          step="0.5"
          :disabled="props.disabled"
          required
          @update:model-value="
            updateTakeoverMinutes(
              'teammate_no_response_ai_takeover_timeout_seconds',
              $event,
            )
          "
        />
        <InputError
          :message="
            strategyError('teammate_no_response_ai_takeover_timeout_seconds')
          "
        />
      </div>
    </div>

    <div class="space-y-5">
      <div class="grid gap-2">
        <Label for="plan_strategy_auto_close_enabled">
          {{ t('长时间无消息时结束会话') }}
        </Label>
        <Switch
          id="plan_strategy_auto_close_enabled"
          v-model="props.form.strategy_config.auto_close_enabled"
          :disabled="props.disabled"
        />
        <InputError :message="strategyError('auto_close_enabled')" />
      </div>
      <div
        v-if="props.form.strategy_config.auto_close_enabled"
        class="grid gap-2"
      >
        <Label for="plan_strategy_auto_close_idle_minutes" required>
          {{ t('多久没有消息后结束会话（分钟）') }}
        </Label>
        <Input
          id="plan_strategy_auto_close_idle_minutes"
          v-model.number="props.form.strategy_config.auto_close_idle_minutes"
          type="number"
          min="1"
          max="1440"
          :disabled="props.disabled"
          required
        />
        <InputError :message="strategyError('auto_close_idle_minutes')" />
      </div>
    </div>

    <div class="space-y-5">
      <div class="grid gap-2">
        <Label for="plan_strategy_important_contact_ai_careful_reply_enabled">
          {{ t('重点客户谨慎回复') }}
        </Label>
        <Switch
          id="plan_strategy_important_contact_ai_careful_reply_enabled"
          v-model="
            props.form.strategy_config
              .important_contact_ai_careful_reply_enabled
          "
          :disabled="props.disabled"
        />
        <InputError
          :message="strategyError('important_contact_ai_careful_reply_enabled')"
        />
      </div>

      <div class="grid gap-2">
        <Label for="plan_strategy_important_contact_ai_handoff_hint_enabled">
          {{ t('重点客户有风险时转人工') }}
        </Label>
        <Switch
          id="plan_strategy_important_contact_ai_handoff_hint_enabled"
          v-model="
            props.form.strategy_config.important_contact_ai_handoff_hint_enabled
          "
          :disabled="props.disabled"
        />
        <InputError
          :message="strategyError('important_contact_ai_handoff_hint_enabled')"
        />
      </div>

      <div class="grid gap-2">
        <Label
          for="plan_strategy_important_contact_human_first_when_online_enabled"
        >
          {{ t('有客服在线时优先接待重点客户') }}
        </Label>
        <Switch
          id="plan_strategy_important_contact_human_first_when_online_enabled"
          v-model="
            props.form.strategy_config
              .important_contact_human_first_when_online_enabled
          "
          :disabled="props.disabled"
        />
        <InputError
          :message="
            strategyError('important_contact_human_first_when_online_enabled')
          "
        />
      </div>
    </div>

    <div class="grid gap-2">
      <Label for="plan_strategy_quote_visitor_message_enabled">
        {{ t('回复时引用访客消息') }}
      </Label>
      <Switch
        id="plan_strategy_quote_visitor_message_enabled"
        v-model="props.form.strategy_config.quote_visitor_message_enabled"
        :disabled="props.disabled"
      />
      <InputError :message="strategyError('quote_visitor_message_enabled')" />
    </div>

    <div class="grid gap-2">
      <Label for="plan_strategy_handoff_available_notice" required>
        {{ t('正在转接客服时的提示') }}
      </Label>
      <Textarea
        id="plan_strategy_handoff_available_notice"
        v-model="props.form.strategy_config.handoff_available_notice"
        rows="2"
        maxlength="500"
        :disabled="props.disabled"
        required
      />
      <InputError :message="strategyError('handoff_available_notice')" />
    </div>

    <div class="grid gap-2">
      <Label for="plan_strategy_handoff_no_teammate_notice" required>
        {{ t('暂时无法转接客服时的提示') }}
      </Label>
      <Textarea
        id="plan_strategy_handoff_no_teammate_notice"
        v-model="props.form.strategy_config.handoff_no_teammate_notice"
        rows="2"
        maxlength="500"
        :disabled="props.disabled"
        required
      />
      <InputError :message="strategyError('handoff_no_teammate_notice')" />
    </div>

    <div class="grid gap-2">
      <Label for="plan_strategy_ai_unavailable_notice" required>
        {{ t('AI 暂时不可用时的提示') }}
      </Label>
      <Textarea
        id="plan_strategy_ai_unavailable_notice"
        v-model="props.form.strategy_config.ai_unavailable_notice"
        rows="2"
        maxlength="500"
        :disabled="props.disabled"
        required
      />
      <InputError :message="strategyError('ai_unavailable_notice')" />
    </div>
  </div>
</template>
