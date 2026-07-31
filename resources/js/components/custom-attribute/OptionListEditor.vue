<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

const { t } = useI18n();

interface Option {
  code: string;
  label: string;
}

const props = defineProps<{
  modelValue: Option[];
  disabled?: boolean;
  errors?: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Option[]];
}>();

const options = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
});

const duplicateCodes = computed(() => {
  const codes = options.value.map((o) => o.code).filter(Boolean);
  const seen = new Set<string>();
  const dupes = new Set<string>();
  for (const c of codes) {
    if (seen.has(c)) {
      dupes.add(c);
    }
    seen.add(c);
  }
  return dupes;
});

const addOption = () => {
  options.value = [...options.value, { code: '', label: '' }];
};

const removeOption = (index: number) => {
  if (options.value.length <= 1) {
    return;
  }
  options.value = options.value.filter((_, i) => i !== index);
};

const updateOption = (
  index: number,
  field: 'code' | 'label',
  value: string,
) => {
  const updated = [...options.value];
  updated[index] = { ...updated[index], [field]: value };
  options.value = updated;
};
</script>

<template>
  <div class="space-y-3">
    <Label>{{ t('选项管理') }}</Label>
    <p class="text-xs text-muted-foreground">
      {{
        t(
          '选项标识用于区分每个选项，显示名称是页面上看到的文字。已有联系人使用后，选项标识不能修改或删除。',
        )
      }}
    </p>

    <div class="space-y-2">
      <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_2.25rem] gap-2">
        <Label required>{{ t('选项标识') }}</Label>
        <Label required>{{ t('显示名称') }}</Label>
        <span />
      </div>
      <div
        v-for="(option, index) in options"
        :key="index"
        class="flex items-start gap-2"
      >
        <div class="flex-1 space-y-1">
          <Input
            :model-value="option.code"
            :disabled="props.disabled"
            :class="{ 'border-destructive': duplicateCodes.has(option.code) }"
            :aria-label="t('选项标识')"
            required
            @update:model-value="
              (v: string | number) => updateOption(index, 'code', String(v))
            "
          />
        </div>
        <div class="flex-1 space-y-1">
          <Input
            :model-value="option.label"
            :disabled="props.disabled"
            :aria-label="t('显示名称')"
            required
            @update:model-value="
              (v: string | number) => updateOption(index, 'label', String(v))
            "
          />
        </div>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          class="shrink-0"
          :disabled="props.disabled || options.length <= 1"
          :aria-label="t('删除')"
          @click="removeOption(index)"
        >
          <Trash2 class="h-4 w-4 text-muted-foreground" />
        </Button>
      </div>
    </div>

    <Button
      type="button"
      variant="outline"
      size="sm"
      :disabled="props.disabled"
      @click="addOption"
    >
      <Plus class="mr-1 h-4 w-4" />
      {{ t('添加一项') }}
    </Button>

    <InputError :message="props.errors" />
  </div>
</template>
