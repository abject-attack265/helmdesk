<!--
  接待方案基础信息表单，填写方案名称、用途和客服接待要求。
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
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import type { EnumOptionData } from '@/types/generated';
import type { InertiaForm } from '@inertiajs/vue3';

export type PlanBasicsFormShape = {
  name: string;
  description: string;
  persona_display_name: string;
  persona_tone: string;
  global_instructions: string;
};

const props = defineProps<{
  /** 接待方案的基础信息表单 */
  form: InertiaForm<PlanBasicsFormShape>;
  personaToneOptions: EnumOptionData[];
  disabled: boolean;
}>();

const { t } = useI18n();
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="grid gap-2">
      <Label for="plan_basics_name" required>{{ t('方案名称') }}</Label>
      <Input
        id="plan_basics_name"
        v-model="props.form.name"
        maxlength="100"
        :disabled="props.disabled"
        required
      />
      <InputError :message="props.form.errors.name" />
    </div>

    <div class="grid gap-2">
      <Label for="plan_basics_description">{{ t('用途说明（选填）') }}</Label>
      <Textarea
        id="plan_basics_description"
        v-model="props.form.description"
        rows="2"
        class="min-h-16"
        maxlength="500"
        :disabled="props.disabled"
      />
      <InputError :message="props.form.errors.description" />
    </div>

    <div class="grid gap-2">
      <Label for="plan_basics_persona_display_name" required>
        {{ t('客服昵称') }}
      </Label>
      <Input
        id="plan_basics_persona_display_name"
        v-model="props.form.persona_display_name"
        maxlength="100"
        :disabled="props.disabled"
        required
      />
      <InputError :message="props.form.errors.persona_display_name" />
    </div>

    <div class="grid gap-2">
      <Label for="plan_basics_persona_tone" required>
        {{ t('回复语气') }}
      </Label>
      <Select v-model="props.form.persona_tone" :disabled="props.disabled">
        <SelectTrigger
          id="plan_basics_persona_tone"
          class="w-full"
          aria-required="true"
          :disabled="props.disabled"
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
      <InputError :message="props.form.errors.persona_tone" />
    </div>

    <div class="flex min-h-0 flex-1 flex-col gap-2">
      <Label for="plan_basics_global_instructions" required>
        {{ t('接待要求') }}
      </Label>
      <Textarea
        id="plan_basics_global_instructions"
        v-model="props.form.global_instructions"
        class="min-h-56 flex-1 resize-none"
        maxlength="20000"
        :disabled="props.disabled"
        required
      />
      <InputError :message="props.form.errors.global_instructions" />
    </div>
  </div>
</template>
