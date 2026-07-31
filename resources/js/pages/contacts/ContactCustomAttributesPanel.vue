<!--
  联系人自定义字段面板，展示并保存当前字段值。
  已删除字段保留为只读记录。
-->
<script setup lang="ts">
import AttributeFieldRenderer from '@/components/custom-attribute/AttributeFieldRenderer.vue';
import { Badge } from '@/components/ui/badge';
import { useI18n } from '@/composables/useI18n';
import app from '@/routes/app';
import type {
  ContactAttributeFieldData,
  ContactDetailData,
  FormUpdateContactAttributeValuesData,
} from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
  contactId: string;
  contactDetail: ContactDetailData;
  readOnly?: boolean;
}>();

const emit = defineEmits<{
  requestRefresh: [];
}>();

const { t } = useI18n();

const attrForm = useForm<FormUpdateContactAttributeValuesData>({
  attributes: {},
});

const attrValues = reactive<Record<string, unknown>>({});
const attrSaving = ref(false);
const syncingAttributesFromDetail = ref(false);
const lastSavedAttributes = ref('');
let attributeSaveTimer: number | null = null;

const clearAttributeSaveTimer = () => {
  if (attributeSaveTimer) {
    window.clearTimeout(attributeSaveTimer);
    attributeSaveTimer = null;
  }
};

const initAttrValues = (fields: ContactAttributeFieldData[]) => {
  clearAttributeSaveTimer();
  syncingAttributesFromDetail.value = true;

  for (const key of Object.keys(attrValues)) {
    delete attrValues[key];
  }

  for (const field of fields.filter((item) => item.is_editable)) {
    attrValues[field.key] =
      field.value ?? (field.type === 'multi_select' ? [] : null);
  }

  attrForm.attributes = { ...attrValues };
  lastSavedAttributes.value = JSON.stringify(attrForm.attributes);
  attrForm.clearErrors();
  void nextTick(() => {
    syncingAttributesFromDetail.value = false;
  });
};

const editableAttributes = computed(() => {
  return (props.contactDetail.custom_attributes ?? []).filter(
    (f) => f.is_editable,
  );
});

const deletedAttributes = computed(() => {
  return (props.contactDetail.custom_attributes ?? []).filter(
    (f) => !f.is_editable && f.value !== null && f.value !== undefined,
  );
});

const saveCustomAttributes = (silent = false) => {
  if (props.readOnly) {
    return;
  }

  if (attrSaving.value || attrForm.processing) {
    scheduleCustomAttributesSave();
    return;
  }

  attrSaving.value = true;
  attrForm.attributes = { ...attrValues };
  const serializedAttributes = JSON.stringify(attrForm.attributes);

  if (serializedAttributes === lastSavedAttributes.value) {
    attrSaving.value = false;
    return;
  }

  attrForm.put(
    app.contacts.attributes.update.url({
      id: props.contactId,
    }),
    {
      preserveScroll: true,
      showProgress: !silent,
      onSuccess: () => {
        lastSavedAttributes.value = serializedAttributes;
        emit('requestRefresh');
      },
      onFinish: () => {
        attrSaving.value = false;
      },
    },
  );
};

const scheduleCustomAttributesSave = () => {
  if (props.readOnly || syncingAttributesFromDetail.value) {
    return;
  }

  clearAttributeSaveTimer();
  attributeSaveTimer = window.setTimeout(() => {
    attributeSaveTimer = null;
    saveCustomAttributes(true);
  }, 700);
};

const attrFieldError = (key: string): string | undefined => {
  const errors = attrForm.errors as Record<string, string | undefined>;

  return errors[`attributes.${key}`];
};

const customAttributeOptionLabel = (
  field: ContactAttributeFieldData,
  code: string,
): string => {
  const options = field.config?.options as
    Array<{ code: string; label: string }> | undefined;

  return options?.find((option) => option.code === code)?.label ?? code;
};

const formatCustomAttributeValue = (
  field: ContactAttributeFieldData,
): string => {
  if (field.value === null || field.value === undefined || field.value === '') {
    return '-';
  }

  if (field.type === 'boolean') {
    return field.value === true ? t('是') : t('否');
  }

  if (field.type === 'single_select' && typeof field.value === 'string') {
    return customAttributeOptionLabel(field, field.value);
  }

  if (field.type === 'multi_select' && Array.isArray(field.value)) {
    return field.value
      .map((code) => customAttributeOptionLabel(field, String(code)))
      .join(', ');
  }

  return String(field.value);
};

watch(attrValues, () => scheduleCustomAttributesSave(), { deep: true });

watch(
  () => props.contactDetail.custom_attributes,
  (fields) => initAttrValues(fields ?? []),
  { immediate: true },
);

onUnmounted(() => {
  clearAttributeSaveTimer();
});
</script>

<template>
  <div>
    <div class="mb-3 flex items-center justify-between">
      <h5 class="text-sm font-semibold">{{ t('自定义字段') }}</h5>
    </div>

    <div v-if="editableAttributes.length > 0" class="space-y-3">
      <AttributeFieldRenderer
        v-for="field in editableAttributes"
        :key="field.definition_id"
        :field="field"
        :model-value="attrValues[field.key]"
        :errors="attrFieldError(field.key)"
        :disabled="readOnly || attrSaving"
        @update:model-value="attrValues[field.key] = $event"
      />
      <div v-if="attrSaving" class="text-right text-xs text-muted-foreground">
        {{ t('保存中...') }}
      </div>
    </div>

    <div
      v-if="deletedAttributes.length > 0"
      :class="{ 'mt-4': editableAttributes.length > 0 }"
    >
      <div
        v-for="field in deletedAttributes"
        :key="field.definition_id"
        class="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
      >
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <span class="text-muted-foreground">{{ field.name }}</span>
            <Badge variant="outline" class="text-muted-foreground">{{
              t('已删除')
            }}</Badge>
            <Badge v-if="field.source_label" variant="secondary">
              {{ field.source_label }}
            </Badge>
          </div>
          <p v-if="field.description" class="text-xs text-muted-foreground">
            {{ field.description }}
          </p>
        </div>
        <span
          class="max-w-56 truncate text-right"
          :title="formatCustomAttributeValue(field)"
        >
          {{ formatCustomAttributeValue(field) }}
        </span>
      </div>
    </div>

    <div
      v-if="editableAttributes.length === 0 && deletedAttributes.length === 0"
      class="py-4 text-center text-sm text-muted-foreground"
    >
      {{ t('暂无自定义字段') }}
    </div>
  </div>
</template>
