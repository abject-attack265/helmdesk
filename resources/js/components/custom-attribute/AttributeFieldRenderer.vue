<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { ContactAttributeFieldData } from '@/types/generated';
import { computed } from 'vue';

const { t } = useI18n();

const props = defineProps<{
  field: ContactAttributeFieldData;
  modelValue: unknown;
  errors?: string;
  disabled?: boolean;
  hideLabel?: boolean;
  compact?: boolean;
  hideMeta?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: unknown];
}>();

const EMPTY_SELECT_VALUE = '__helmdesk_empty__';
const BOOLEAN_TRUE_VALUE = '__helmdesk_boolean_true__';
const BOOLEAN_FALSE_VALUE = '__helmdesk_boolean_false__';

const value = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
});

const stringValue = computed({
  get: () => (value.value as string) ?? '',
  set: (v: string | number) => {
    value.value = v === '' ? null : String(v);
  },
});

const numberValue = computed({
  get: () => (value.value as string) ?? '',
  set: (v: string | number) => {
    const s = String(v);
    value.value = s === '' ? null : s;
  },
});

const boolValue = computed({
  get: () => value.value as boolean | null,
  set: (v: boolean) => {
    value.value = v;
  },
});

const selectValue = computed({
  get: () => (value.value as string | null) ?? EMPTY_SELECT_VALUE,
  set: (v: string) => {
    value.value = v === EMPTY_SELECT_VALUE ? null : v;
  },
});

const multiSelectValue = computed({
  get: () => (value.value as string[]) ?? [],
  set: (v: string[]) => {
    value.value = v;
  },
});

const updateStringValue = (nextValue: string | number) => {
  stringValue.value = nextValue;
};

const updateNumberValue = (nextValue: string | number) => {
  numberValue.value = nextValue;
};

const updateBooleanSelectValue = (nextValue: unknown) => {
  booleanSelectValue.value =
    typeof nextValue === 'string' ? nextValue : EMPTY_SELECT_VALUE;
};

const updateSingleSelectValue = (nextValue: unknown) => {
  selectValue.value =
    typeof nextValue === 'string' ? nextValue : EMPTY_SELECT_VALUE;
};

const toggleMultiSelectOption = (code: string) => {
  const current = multiSelectValue.value;
  if (current.includes(code)) {
    multiSelectValue.value = current.filter((c) => c !== code);
  } else {
    multiSelectValue.value = [...current, code];
  }
};

const booleanSelectValue = computed({
  get: () => {
    if (boolValue.value === true) {
      return BOOLEAN_TRUE_VALUE;
    }

    if (boolValue.value === false) {
      return BOOLEAN_FALSE_VALUE;
    }

    return EMPTY_SELECT_VALUE;
  },
  set: (v: string) => {
    if (v === BOOLEAN_TRUE_VALUE) {
      boolValue.value = true;

      return;
    }

    if (v === BOOLEAN_FALSE_VALUE) {
      boolValue.value = false;

      return;
    }

    value.value = null;
  },
});

const options = computed(() => {
  return (props.field.config?.options ?? []) as Array<{
    code: string;
    label: string;
  }>;
});
</script>

<template>
  <div class="space-y-1.5">
    <Label v-if="!hideLabel" :class="{ 'text-muted-foreground': disabled }">
      {{ field.name }}
    </Label>

    <template v-if="field.type === 'text'">
      <Input
        :model-value="stringValue"
        :disabled="disabled"
        @update:model-value="updateStringValue"
      />
    </template>

    <template v-else-if="field.type === 'textarea'">
      <Textarea
        v-model="stringValue"
        :disabled="disabled"
        :class="compact ? 'resize-y' : 'min-h-20'"
      />
    </template>

    <template v-else-if="field.type === 'number'">
      <Input
        type="number"
        :model-value="numberValue"
        :disabled="disabled"
        @update:model-value="updateNumberValue"
      />
    </template>

    <template v-else-if="field.type === 'date'">
      <Input
        type="date"
        :model-value="stringValue"
        :disabled="disabled"
        @update:model-value="updateStringValue"
      />
    </template>

    <template v-else-if="field.type === 'boolean'">
      <Select
        :model-value="booleanSelectValue"
        :disabled="disabled"
        @update:model-value="updateBooleanSelectValue"
      >
        <SelectTrigger>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="EMPTY_SELECT_VALUE">
            {{ t('未设置') }}
          </SelectItem>
          <SelectItem :value="BOOLEAN_TRUE_VALUE">
            {{ t('是') }}
          </SelectItem>
          <SelectItem :value="BOOLEAN_FALSE_VALUE">
            {{ t('否') }}
          </SelectItem>
        </SelectContent>
      </Select>
    </template>

    <template v-else-if="field.type === 'single_select'">
      <Select
        :model-value="selectValue"
        :disabled="disabled"
        @update:model-value="updateSingleSelectValue"
      >
        <SelectTrigger>
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem :value="EMPTY_SELECT_VALUE">
            {{ t('未设置') }}
          </SelectItem>
          <SelectItem v-for="opt in options" :key="opt.code" :value="opt.code">
            {{ opt.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </template>

    <template v-else-if="field.type === 'multi_select'">
      <div class="flex flex-wrap gap-2 pt-1">
        <label
          v-for="opt in options"
          :key="opt.code"
          class="flex cursor-pointer items-center gap-1.5 text-sm"
          :class="{ 'opacity-50': disabled }"
        >
          <Checkbox
            :checked="multiSelectValue.includes(opt.code)"
            :disabled="disabled"
            @update:checked="toggleMultiSelectOption(opt.code)"
          />
          {{ opt.label }}
        </label>
      </div>
    </template>

    <p
      v-if="field.description && !hideMeta"
      class="text-xs text-muted-foreground"
    >
      {{ field.description }}
    </p>
    <p
      v-if="
        field.source_label && value !== null && value !== undefined && !hideMeta
      "
      class="text-xs text-muted-foreground"
    >
      {{ t('来源') }}: {{ field.source_label }}
    </p>
    <InputError :message="errors" />
  </div>
</template>
