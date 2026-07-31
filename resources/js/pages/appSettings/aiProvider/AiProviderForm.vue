<!--
  文件说明：应用设置 AI 供应商创建/编辑表单，承接品牌选择、动态凭据录入与连通测试；
  消费 ShowCreate/ShowEdit*PagePropsData，提交到 app.manage.aiProviders.* 路由。
-->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import FormField from '@/components/common/FormField.vue';
import SecretInput from '@/components/common/SecretInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import app from '@/routes/app';
import type { AiProviderData, BrandOptionData } from '@/types/generated';
import { useForm, useHttp } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, watch } from 'vue';

type CredentialField = {
  field: string;
  label: string;
  type?: 'text' | 'password' | 'url';
  required?: boolean;
  secret?: boolean;
  default?: string | null;
  placeholder?: string | null;
};

type ProviderForm = {
  name: string;
  brand: string;
  configuration: Record<string, string>;
};

type CheckPayload = { configuration: Record<string, string> };
type CheckResponse = { success: boolean; message: string };

const props = defineProps<{
  mode: 'create' | 'edit';
  provider?: AiProviderData | null;
  brandOptions?: BrandOptionData[];
}>();

const { t } = useI18n();
const { toast } = useToast();

const isEditMode = computed(() => props.mode === 'edit');
const brandOptions = computed(() => props.brandOptions ?? []);

const defaultBrand = computed(() => brandOptions.value[0]?.brand ?? '');

const selectedBrand = computed<string>(() =>
  isEditMode.value ? (props.provider?.brand ?? '') : form.brand,
);

const selectedBrandOption = computed(() =>
  brandOptions.value.find((option) => option.brand === selectedBrand.value),
);

const selectedBrandLabel = computed(() =>
  isEditMode.value
    ? (props.provider?.brand_label ?? '')
    : (selectedBrandOption.value?.label ?? ''),
);

// 预设品牌（非自定义）的 Base URL 已内置，凭据里的 url 字段只读、不允许修改。
const isCustomBrand = computed<boolean>(() =>
  isEditMode.value
    ? (props.provider?.is_custom ?? false)
    : (selectedBrandOption.value?.is_custom ?? false),
);

const isReadonlyField = (field: { type?: string | null }): boolean =>
  field.type === 'url' && !isCustomBrand.value;

function credentialFieldsForBrand(brand: string): CredentialField[] {
  return (brandOptions.value.find((option) => option.brand === brand)
    ?.credential_fields ?? []) as CredentialField[];
}

const selectedCredentialFields = computed<CredentialField[]>(() => {
  if (isEditMode.value && props.provider) {
    return props.provider.credential_fields as CredentialField[];
  }
  return credentialFieldsForBrand(form.brand);
});

function buildConfiguration(
  provider: AiProviderData | null | undefined,
  fields: CredentialField[],
): Record<string, string> {
  const configuration: Record<string, string> = {};
  for (const field of fields) {
    const value = provider?.credential_values[field.field];
    configuration[field.field] =
      value ?? (field.default as string | null) ?? '';
  }
  return configuration;
}

function buildFormDefaults(): ProviderForm {
  const brand = isEditMode.value
    ? (props.provider?.brand ?? '')
    : defaultBrand.value;
  const fields =
    isEditMode.value && props.provider
      ? (props.provider.credential_fields as CredentialField[])
      : credentialFieldsForBrand(brand);

  return {
    name: props.provider?.name ?? '',
    brand,
    configuration: buildConfiguration(props.provider, fields),
  };
}

const form = useForm<ProviderForm>(buildFormDefaults());

watch(
  () => form.brand,
  () => {
    if (isEditMode.value) {
      return;
    }
    form.configuration = buildConfiguration(
      null,
      credentialFieldsForBrand(form.brand),
    );
    form.clearErrors();
  },
);

function setConfigurationValue(fieldName: string, value: unknown): void {
  form.configuration[fieldName] =
    typeof value === 'string' || typeof value === 'number' ? String(value) : '';
}

function fieldValue(fieldName: string): string {
  return form.configuration[fieldName] ?? '';
}

function fieldError(fieldName: string): string | undefined {
  return form.errors[`configuration.${fieldName}`];
}

function submit(): void {
  if (isEditMode.value && props.provider) {
    form
      .transform((data) => ({
        name: data.name,
        configuration: data.configuration,
      }))
      .put(app.manage.aiProviders.update.url(props.provider.id), {
        preserveScroll: true,
      });
    return;
  }

  form
    .transform((data) => ({
      name: data.name,
      brand: data.brand,
      configuration: data.configuration,
    }))
    .post(app.manage.aiProviders.store.url(), { preserveScroll: true });
}

const checkHttp = useHttp<CheckPayload, CheckResponse>({ configuration: {} });

function checkConnection(): void {
  if (!props.provider) {
    return;
  }
  checkHttp.configuration = form.configuration;
  checkHttp.post(app.manage.aiProviders.check.url(props.provider.id), {
    onSuccess: (response: CheckResponse) => {
      if (response.success) {
        toast.success(response.message || t('连接测试成功！'));
      } else {
        toast.error(response.message || t('连接测试失败'));
      }
    },
    onHttpException: () => {
      toast.error(t('请求失败，请稍后再试'));
    },
    onNetworkError: () => {
      toast.error(t('网络异常，请检查连接'));
    },
  });
}
</script>

<template>
  <form class="space-y-6" @submit.prevent="submit">
    <FormField
      v-if="!isEditMode"
      :label="t('品牌')"
      label-for="ai-provider-brand"
      :error="form.errors.brand"
      required
    >
      <Select v-model="form.brand" required>
        <SelectTrigger id="ai-provider-brand" class="mt-1 w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in brandOptions"
            :key="option.brand"
            :value="option.brand"
            hide-indicator
          >
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </FormField>

    <FormField v-else :label="t('品牌')">
      <div class="mt-1 rounded-md border px-3 py-2 text-sm">
        {{ selectedBrandLabel }}
      </div>
    </FormField>

    <FormField
      :label="t('名称')"
      label-for="ai-provider-name"
      :error="form.errors.name"
      required
    >
      <Input
        id="ai-provider-name"
        v-model="form.name"
        class="mt-1 block w-full"
        autocomplete="off"
        maxlength="128"
        required
      />
    </FormField>

    <FormField
      v-for="field in selectedCredentialFields"
      :key="field.field"
      :label="field.label"
      :label-for="`ai-provider-${field.field}`"
      :error="fieldError(field.field)"
      :required="field.required"
    >
      <SecretInput
        v-if="field.secret"
        :id="`ai-provider-${field.field}`"
        :model-value="fieldValue(field.field)"
        autocomplete="off"
        :placeholder="field.placeholder ?? undefined"
        :required="field.required"
        @update:model-value="
          (value) => setConfigurationValue(field.field, value)
        "
      />

      <div
        v-else-if="isReadonlyField(field)"
        class="mt-1 rounded-md border bg-muted/40 px-3 py-2 font-mono text-sm text-muted-foreground"
      >
        {{ fieldValue(field.field) }}
      </div>

      <Input
        v-else
        :id="`ai-provider-${field.field}`"
        :model-value="fieldValue(field.field)"
        :type="field.type === 'url' ? 'url' : 'text'"
        autocomplete="off"
        :placeholder="field.placeholder ?? undefined"
        :required="field.required"
        @update:model-value="
          (value) => setConfigurationValue(field.field, value)
        "
      />
    </FormField>

    <FormActions
      :submit-label="isEditMode ? t('保存') : t('创建')"
      :processing="form.processing"
      :submit-disabled="checkHttp.processing || !selectedBrand"
      :cancel-href="app.manage.aiProviders.index.url()"
    >
      <Button
        v-if="isEditMode"
        type="button"
        variant="outline"
        :disabled="checkHttp.processing || form.processing"
        @click="checkConnection"
      >
        <LoaderCircle
          v-if="checkHttp.processing"
          class="mr-2 h-4 w-4 animate-spin"
        />
        {{ t('测试') }}
      </Button>
    </FormActions>
  </form>
</template>
