<!--
  文件说明：应用设置翻译供应商创建/编辑表单，承接凭据录入、启用开关与连通测试；
  消费 ShowCreate/ShowEdit*PagePropsData，提交到应用设置翻译供应商路由。
-->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import FormField from '@/components/common/FormField.vue';
import SecretInput from '@/components/common/SecretInput.vue';
import AiProviderIcon from '@/components/icons/AiProviderIcon.vue';
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
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import app from '@/routes/app';
import type {
  EnumOptionData,
  ShowCreateTranslationProviderPagePropsData,
  TranslationProviderData,
  TranslationProviderType,
  TranslationResult,
} from '@/types/generated';
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
};

type ProviderForm = {
  name: string;
  protocol: TranslationProviderType | '';
  is_active: boolean;
  configuration: Record<string, string>;
};

type CheckPayload = {
  text: string;
  target_lang: string;
  source_lang: string | null;
  protocol?: TranslationProviderType | '';
  configuration: Record<string, string>;
};

type CheckResponse = {
  success: boolean;
  message: string;
  result: TranslationResult | null;
};

const props = defineProps<{
  mode: 'create' | 'edit';
  provider?: TranslationProviderData | null;
  protocolOptions?: EnumOptionData[];
  protocolCredentialFields?: ShowCreateTranslationProviderPagePropsData['protocol_credential_fields'];
  protocolIcons?: ShowCreateTranslationProviderPagePropsData['protocol_icons'];
}>();

const { t } = useI18n();
const { toast } = useToast();

const isEditMode = computed(() => props.mode === 'edit');
const protocolOptions = computed(() => props.protocolOptions ?? []);

const defaultProtocol = computed(
  () =>
    (protocolOptions.value[0]?.value as TranslationProviderType | undefined) ??
    '',
);

const selectedProtocol = computed<TranslationProviderType | ''>(() =>
  isEditMode.value ? (props.provider?.protocol ?? '') : form.protocol,
);

const selectedProtocolOption = computed(() =>
  protocolOptions.value.find(
    (option) => option.value === selectedProtocol.value,
  ),
);

const selectedProtocolLabel = computed(() =>
  isEditMode.value
    ? (props.provider?.protocol_label ?? '')
    : (selectedProtocolOption.value?.label ?? ''),
);

const selectedProtocolIcon = computed(() =>
  selectedProtocol.value
    ? (props.protocolIcons?.[selectedProtocol.value] ?? null)
    : null,
);

const selectedCredentialFields = computed<CredentialField[]>(() => {
  if (!selectedProtocol.value) {
    return [];
  }

  if (isEditMode.value && props.provider) {
    return props.provider.credential_fields as CredentialField[];
  }

  return credentialFieldsForProtocol(selectedProtocol.value);
});

function credentialFieldsForProtocol(
  protocol: TranslationProviderType | '',
): CredentialField[] {
  if (!protocol) {
    return [];
  }

  return Object.values(
    props.protocolCredentialFields?.[protocol] ?? {},
  ) as CredentialField[];
}

function buildConfiguration(
  provider: TranslationProviderData | null | undefined,
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
  const protocol = isEditMode.value
    ? (props.provider?.protocol ?? '')
    : defaultProtocol.value;
  const fields = protocol
    ? isEditMode.value && props.provider
      ? (props.provider.credential_fields as CredentialField[])
      : credentialFieldsForProtocol(protocol)
    : [];

  return {
    name: props.provider?.name ?? '',
    protocol,
    is_active: props.provider?.is_active ?? true,
    configuration: buildConfiguration(props.provider, fields),
  };
}

const form = useForm<ProviderForm>(buildFormDefaults());

watch(
  () => form.protocol,
  () => {
    if (isEditMode.value) {
      return;
    }

    form.configuration = buildConfiguration(
      null,
      selectedCredentialFields.value,
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

function handleActionError(errors: Record<string, string | undefined>): void {
  const message = Object.values(errors).find(
    (value): value is string =>
      typeof value === 'string' && value.trim().length > 0,
  );

  if (message) {
    toast.warning(message);
  }
}

function submit(): void {
  if (isEditMode.value && props.provider) {
    form
      .transform((data) => ({
        name: data.name,
        is_active: data.is_active,
        configuration: data.configuration,
      }))
      .put(app.manage.translationProviders.update.url(props.provider.id), {
        preserveScroll: true,
        onError: (errors) =>
          handleActionError(errors as Record<string, string | undefined>),
      });
    return;
  }

  form
    .transform((data) => ({
      name: data.name,
      protocol: data.protocol,
      is_active: data.is_active,
      configuration: data.configuration,
    }))
    .post(app.manage.translationProviders.store.url(), {
      preserveScroll: true,
      onError: (errors) =>
        handleActionError(errors as Record<string, string | undefined>),
    });
}

const checkHttp = useHttp<CheckPayload, CheckResponse>({
  text: 'Hello',
  target_lang: 'zh-CN',
  source_lang: null,
  protocol: '',
  configuration: {},
});

function checkConnection(): void {
  const sampleText = 'Hello';
  checkHttp.text = sampleText;
  checkHttp.target_lang = 'zh-CN';
  checkHttp.source_lang = null;
  checkHttp.configuration = form.configuration;

  const errorHandlers = {
    onSuccess: (response: CheckResponse) => {
      if (response.success && response.result) {
        toast.success(
          `${sampleText} → ${response.result.text}（${response.result.latency_ms}ms）`,
        );
      } else {
        toast.error(response.message || t('翻译测试失败'));
      }
    },
    onHttpException: () => {
      toast.error(t('请求失败，请稍后再试'));
    },
    onNetworkError: () => {
      toast.error(t('网络异常，请检查连接'));
    },
  };

  if (isEditMode.value && props.provider) {
    checkHttp.post(
      app.manage.translationProviders.check.url(props.provider.id),
      errorHandlers,
    );
    return;
  }

  checkHttp.protocol = form.protocol;
  checkHttp.post(app.manage.translationProviders.checkNew.url(), errorHandlers);
}
</script>

<template>
  <form class="space-y-6" @submit.prevent="submit">
    <FormField
      v-if="!isEditMode"
      :label="t('协议')"
      label-for="translation-provider-protocol"
      :error="form.errors.protocol"
      required
    >
      <Select v-model="form.protocol" required>
        <SelectTrigger id="translation-provider-protocol" class="mt-1 w-full">
          <div class="flex min-w-0 items-center gap-2">
            <AiProviderIcon
              v-if="form.protocol"
              :icon="selectedProtocolIcon"
              class="h-4 w-4 shrink-0"
            />
            <SelectValue />
          </div>
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="option in protocolOptions"
            :key="option.value"
            :value="String(option.value)"
          >
            <div class="flex items-center gap-2">
              <AiProviderIcon
                :icon="props.protocolIcons?.[String(option.value)] ?? null"
                class="h-4 w-4 shrink-0"
              />
              <span>{{ option.label }}</span>
            </div>
          </SelectItem>
        </SelectContent>
      </Select>
    </FormField>

    <FormField v-else :label="t('协议')">
      <div
        class="mt-1 flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
      >
        <AiProviderIcon :icon="props.provider?.icon" class="h-4 w-4 shrink-0" />
        <span>{{ selectedProtocolLabel }}</span>
      </div>
    </FormField>

    <FormField
      :label="t('名称')"
      label-for="translation-provider-name"
      :error="form.errors.name"
      required
    >
      <Input
        id="translation-provider-name"
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
      :label-for="`translation-provider-${field.field}`"
      :error="fieldError(field.field)"
      :required="field.required"
    >
      <SecretInput
        v-if="field.secret"
        :id="`translation-provider-${field.field}`"
        :model-value="fieldValue(field.field)"
        autocomplete="off"
        :required="field.required"
        @update:model-value="
          (value) => setConfigurationValue(field.field, value)
        "
      />

      <Input
        v-else
        :id="`translation-provider-${field.field}`"
        :model-value="fieldValue(field.field)"
        :type="field.type === 'url' ? 'url' : 'text'"
        autocomplete="off"
        :required="field.required"
        @update:model-value="
          (value) => setConfigurationValue(field.field, value)
        "
      />
    </FormField>

    <div class="grid gap-2">
      <Label for="translation-provider-active">{{ t('启用') }}</Label>
      <div class="flex items-center gap-3">
        <Switch id="translation-provider-active" v-model="form.is_active" />
        <span class="text-sm text-muted-foreground">
          {{ t('仅启用的供应商进入运行时翻译轮询池。') }}
        </span>
      </div>
    </div>

    <FormActions
      :submit-label="isEditMode ? t('保存') : t('创建')"
      :processing="form.processing"
      :submit-disabled="checkHttp.processing || !selectedProtocol"
      :cancel-href="app.manage.translationProviders.index.url()"
    >
      <Button
        type="button"
        variant="outline"
        :disabled="checkHttp.processing || form.processing || !selectedProtocol"
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
