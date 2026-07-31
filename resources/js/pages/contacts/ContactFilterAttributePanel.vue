<!--
  联系人筛选中的自定义字段面板。
-->
<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import type { FilterAttributeDefinitionData } from '@/types/generated';
import { onBeforeUnmount } from 'vue';

const { t } = useI18n();

const props = defineProps<{
  definitions: FilterAttributeDefinitionData[];
  modelValue: Record<string, unknown>;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, unknown>];
  navigate: [];
}>();

const FILTER_EMPTY_VALUE = '__helmdesk_filter_empty__';
const BOOLEAN_TRUE_FILTER_VALUE = '__helmdesk_filter_true__';
const BOOLEAN_FALSE_FILTER_VALUE = '__helmdesk_filter_false__';

let rangeNavigateTimer: ReturnType<typeof setTimeout> | null = null;

const clearRangeTimer = () => {
  if (rangeNavigateTimer) {
    clearTimeout(rangeNavigateTimer);
    rangeNavigateTimer = null;
  }
};

const scheduleRangeNavigate = () => {
  clearRangeTimer();
  rangeNavigateTimer = setTimeout(() => {
    emit('navigate');
    rangeNavigateTimer = null;
  }, 300);
};

onBeforeUnmount(() => {
  clearRangeTimer();
});

const commitValues = (next: Record<string, unknown>) => {
  emit('update:modelValue', next);
};

const getSingleSelectValue = (key: string): string => {
  const value = props.modelValue[key];

  return typeof value === 'string' && value !== '' ? value : FILTER_EMPTY_VALUE;
};

const updateSingleSelectValue = (key: string, value: string) => {
  const next = { ...props.modelValue };

  if (value === FILTER_EMPTY_VALUE) {
    delete next[key];
  } else {
    next[key] = value;
  }

  commitValues(next);
  emit('navigate');
};

const getBooleanValue = (key: string): string => {
  const value = props.modelValue[key];

  if (value === true) {
    return BOOLEAN_TRUE_FILTER_VALUE;
  }

  if (value === false) {
    return BOOLEAN_FALSE_FILTER_VALUE;
  }

  return FILTER_EMPTY_VALUE;
};

const updateBooleanValue = (key: string, value: string) => {
  const next = { ...props.modelValue };

  if (value === BOOLEAN_TRUE_FILTER_VALUE) {
    next[key] = true;
  } else if (value === BOOLEAN_FALSE_FILTER_VALUE) {
    next[key] = false;
  } else {
    delete next[key];
  }

  commitValues(next);
  emit('navigate');
};

const getRangeValue = (key: string, boundary: string): string => {
  const currentValue = props.modelValue[key];

  if (
    !currentValue ||
    typeof currentValue !== 'object' ||
    Array.isArray(currentValue)
  ) {
    return '';
  }

  const rangeValue = (currentValue as Record<string, unknown>)[boundary];

  return typeof rangeValue === 'string' || typeof rangeValue === 'number'
    ? String(rangeValue)
    : '';
};

const updateRangeValue = (key: string, boundary: string, value: string) => {
  const next = { ...props.modelValue };
  const currentValue = next[key];
  const nextRange =
    !currentValue ||
    typeof currentValue !== 'object' ||
    Array.isArray(currentValue)
      ? {}
      : { ...(currentValue as Record<string, unknown>) };

  if (value === '') {
    delete nextRange[boundary];
  } else {
    nextRange[boundary] = value;
  }

  if (Object.keys(nextRange).length === 0) {
    delete next[key];
  } else {
    next[key] = nextRange;
  }

  commitValues(next);
  scheduleRangeNavigate();
};
</script>

<template>
  <div class="max-h-112 overflow-y-auto p-3">
    <div class="grid gap-4 md:grid-cols-2">
      <div
        v-for="definition in definitions"
        :key="definition.key"
        :class="[
          'space-y-2',
          (definition.type === 'number' || definition.type === 'date') &&
            'md:col-span-2',
        ]"
      >
        <Label>{{ definition.name }}</Label>

        <Select
          v-if="definition.type === 'single_select'"
          :model-value="getSingleSelectValue(definition.key)"
          @update:model-value="
            updateSingleSelectValue(definition.key, String($event))
          "
        >
          <SelectTrigger class="h-9">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="FILTER_EMPTY_VALUE">
              {{ t('不限') }}
            </SelectItem>
            <SelectItem
              v-for="option in definition.config?.options ?? []"
              :key="String(option.code)"
              :value="String(option.code)"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>

        <Select
          v-else-if="definition.type === 'boolean'"
          :model-value="getBooleanValue(definition.key)"
          @update:model-value="
            updateBooleanValue(definition.key, String($event))
          "
        >
          <SelectTrigger class="h-9">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem :value="FILTER_EMPTY_VALUE">
              {{ t('不限') }}
            </SelectItem>
            <SelectItem :value="BOOLEAN_TRUE_FILTER_VALUE">
              {{ t('是') }}
            </SelectItem>
            <SelectItem :value="BOOLEAN_FALSE_FILTER_VALUE">
              {{ t('否') }}
            </SelectItem>
          </SelectContent>
        </Select>

        <div
          v-else-if="definition.type === 'number'"
          class="grid grid-cols-2 gap-2"
        >
          <Input
            class="min-w-0"
            :model-value="getRangeValue(definition.key, 'min')"
            type="number"
            @update:model-value="
              updateRangeValue(definition.key, 'min', String($event))
            "
          />
          <Input
            class="min-w-0"
            :model-value="getRangeValue(definition.key, 'max')"
            type="number"
            @update:model-value="
              updateRangeValue(definition.key, 'max', String($event))
            "
          />
        </div>

        <div
          v-else-if="definition.type === 'date'"
          class="grid grid-cols-2 gap-2"
        >
          <Input
            class="min-w-0"
            :model-value="getRangeValue(definition.key, 'from')"
            type="date"
            @update:model-value="
              updateRangeValue(definition.key, 'from', String($event))
            "
          />
          <Input
            class="min-w-0"
            :model-value="getRangeValue(definition.key, 'to')"
            type="date"
            @update:model-value="
              updateRangeValue(definition.key, 'to', String($event))
            "
          />
        </div>
      </div>
    </div>
  </div>
</template>
