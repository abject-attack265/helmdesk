<!-- 集成创建和编辑表单，用于填写连接信息并测试连接。 -->
<script setup lang="ts">
import Integration from '@/actions/App/Actions/Integration';
import FormActions from '@/components/common/FormActions.vue';
import FormField from '@/components/common/FormField.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import SecretInput from '@/components/common/SecretInput.vue';
import { Badge } from '@/components/ui/badge';
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
import type {
  EnumOptionData,
  IntegrationConnectionCheckData,
  IntegrationData,
  IntegrationProvider,
} from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

type AuthPreset = 'none' | 'bearer' | 'header';

type IntegrationForm = {
  name: string;
  endpoint_url: string;
  provider: IntegrationProvider;
  auth_header_name: string | null;
  auth_header_value: string | null;
  headers: Record<string, string> | null;
  timeout_seconds: number;
};

type IntegrationFormPanelProps = {
  returnHref: string;
} & (
  | {
      mode: 'create';
      providerOptions: EnumOptionData[];
      server?: never;
    }
  | {
      mode: 'edit';
      server: IntegrationData;
      providerOptions?: never;
    }
);

const props = defineProps<IntegrationFormPanelProps>();

const { t } = useI18n();
const { toast } = useToast();

const isEditMode = computed(() => props.mode === 'edit');

const title = computed(() =>
  isEditMode.value ? t('编辑集成') : t('添加集成'),
);

const description = computed(() =>
  isEditMode.value
    ? t('修改外部系统的连接信息。')
    : t('连接外部系统，让 AI 和客服使用其中的工具和数据。'),
);

const submitLabel = computed(() =>
  isEditMode.value ? t('保存') : t('添加集成'),
);

const defaultProvider = computed<IntegrationProvider>(() => {
  if (props.mode === 'edit') {
    return props.server.provider;
  }

  const firstProvider = props.providerOptions[0];
  if (!firstProvider) {
    throw new Error('集成类型选项不能为空。');
  }

  return firstProvider.value as IntegrationProvider;
});

const presetOptions = computed(() => [
  { value: 'none', label: t('无需验证') },
  { value: 'bearer', label: t('访问令牌') },
  { value: 'header', label: t('自定义请求头') },
]);

function detectPreset(server: IntegrationData | null | undefined): AuthPreset {
  if (!server?.auth_header_name || !server.auth_header_value) {
    return 'none';
  }

  if (server.auth_header_name.toLowerCase() === 'authorization') {
    return 'bearer';
  }

  return 'header';
}

function buildFormDefaults(): IntegrationForm {
  if (props.mode === 'edit') {
    return {
      name: props.server.name,
      endpoint_url: props.server.endpoint_url,
      provider: props.server.provider,
      auth_header_name: null,
      auth_header_value: null,
      headers: props.server.headers,
      timeout_seconds: props.server.timeout_seconds,
    };
  }

  return {
    name: '',
    endpoint_url: '',
    provider: defaultProvider.value,
    auth_header_name: null,
    auth_header_value: null,
    headers: null,
    timeout_seconds: 30,
  };
}

const authPreset = ref<AuthPreset>(
  detectPreset(props.mode === 'edit' ? props.server : null),
);
const bearerToken = ref('');
const customHeaderName = ref('');
const customHeaderValue = ref('');
const isChecking = ref(false);

const form = useForm<IntegrationForm>(buildFormDefaults());

const isBusinessSystem = computed(() => form.provider === 'business_system');

const endpointLabel = computed(() =>
  isBusinessSystem.value ? t('服务地址') : t('MCP 服务地址'),
);

const endpointHelp = computed(() =>
  isBusinessSystem.value
    ? t(
        '填写业务系统的基础地址。该地址需提供 /helmdesk/tools 和 /helmdesk/tools/{name}/invoke。',
      )
    : undefined,
);

watch(
  [
    () => props.mode,
    () => (props.mode === 'edit' ? props.server : null),
    defaultProvider,
  ],
  () => {
    form.defaults(buildFormDefaults());
    form.reset();
    form.clearErrors();
    form.transform((data) => data);

    const server = props.mode === 'edit' ? props.server : null;
    const preset = detectPreset(server);
    const savedValue = server?.auth_header_value ?? '';
    authPreset.value = preset;
    bearerToken.value =
      preset === 'bearer' ? savedValue.replace(/^Bearer\s+/i, '') : '';
    customHeaderName.value =
      preset === 'header' ? (server?.auth_header_name ?? '') : '';
    customHeaderValue.value = preset === 'header' ? savedValue : '';
  },
  { immediate: true },
);

function syncAuthFields(): void {
  if (authPreset.value === 'bearer') {
    if (bearerToken.value.trim() === '') {
      form.auth_header_name = null;
      form.auth_header_value = null;
    } else {
      form.auth_header_name = 'Authorization';
      form.auth_header_value = `Bearer ${bearerToken.value.trim()}`;
    }
  } else if (authPreset.value === 'header') {
    const headerName = customHeaderName.value.trim();
    const headerValue = customHeaderValue.value;

    if (headerName === '' && headerValue === '') {
      form.auth_header_name = null;
      form.auth_header_value = null;
    } else {
      form.auth_header_name = headerName;
      form.auth_header_value = headerValue;
    }
  } else {
    form.auth_header_name = null;
    form.auth_header_value = null;
  }
}

watch(
  [authPreset, bearerToken, customHeaderName, customHeaderValue],
  syncAuthFields,
);

function fieldError(field: string): string | undefined {
  return (form.errors as Record<string, string | undefined>)[field];
}

function submit(): void {
  syncAuthFields();

  if (props.mode === 'edit') {
    form
      .transform((data) => ({
        name: data.name,
        endpoint_url: data.endpoint_url,
        auth_header_name: data.auth_header_name,
        auth_header_value: data.auth_header_value,
        headers: data.headers,
        timeout_seconds: data.timeout_seconds,
      }))
      .put(
        Integration.UpdateIntegrationAction.url({
          server: props.server.slug,
        }),
        {
          preserveScroll: true,
        },
      );
    return;
  }

  form
    .transform((data) => ({
      name: data.name,
      endpoint_url: data.endpoint_url,
      provider: data.provider,
      auth_header_name: data.auth_header_name,
      auth_header_value: data.auth_header_value,
      headers: data.headers,
      timeout_seconds: data.timeout_seconds,
    }))
    .post(Integration.CreateIntegrationAction.url(), {
      preserveScroll: true,
    });
}

async function checkConnection(): Promise<void> {
  syncAuthFields();
  isChecking.value = true;

  try {
    const payload = {
      name: form.name,
      endpoint_url: form.endpoint_url,
      provider: form.provider,
      auth_header_name: form.auth_header_name,
      auth_header_value: form.auth_header_value,
      headers: form.headers,
      timeout_seconds: form.timeout_seconds,
    };

    const { data } =
      props.mode === 'edit'
        ? await axios.post<IntegrationConnectionCheckData>(
            Integration.CheckIntegrationAction[
              '/app/manage/integrations/{server}/check'
            ].url({ server: props.server.slug }),
            payload,
          )
        : await axios.post<IntegrationConnectionCheckData>(
            Integration.CheckIntegrationAction[
              '/app/manage/integrations/check'
            ].url(),
            payload,
          );

    if (data.success) {
      toast.success(data.message);
    } else {
      toast.error(data.message);
    }
  } catch {
    // 请求错误由统一响应处理器提示。
  } finally {
    isChecking.value = false;
  }
}
</script>

<template>
  <div class="w-full space-y-6">
    <HeadingSmall :title="title" :description="description" />

    <form class="space-y-6" @submit.prevent="submit">
      <FormField
        :label="t('集成名称')"
        label-for="integration-name"
        :error="fieldError('name')"
        required
      >
        <Input
          id="integration-name"
          v-model="form.name"
          class="mt-1 block w-full"
          autocomplete="off"
          maxlength="128"
          required
        />
      </FormField>

      <FormField
        v-if="props.mode === 'create'"
        :label="t('类型')"
        label-for="integration-provider"
        :error="fieldError('provider')"
        required
      >
        <Select
          :model-value="form.provider"
          @update:model-value="
            (value) => (form.provider = String(value) as IntegrationProvider)
          "
        >
          <SelectTrigger id="integration-provider" class="mt-1 w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in props.providerOptions"
              :key="option.value"
              :value="String(option.value)"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>

      <FormField v-else :label="t('类型')">
        <div class="mt-1">
          <Badge variant="secondary">{{ props.server.provider_label }}</Badge>
        </div>
      </FormField>

      <FormField
        :label="endpointLabel"
        label-for="integration-endpoint-url"
        :error="fieldError('endpoint_url')"
        :help="endpointHelp"
        required
      >
        <Input
          id="integration-endpoint-url"
          v-model="form.endpoint_url"
          class="mt-1 block w-full"
          type="url"
          autocomplete="off"
          maxlength="2048"
          required
        />
      </FormField>

      <FormField :label="t('验证方式')" label-for="integration-auth-preset">
        <Select
          :model-value="authPreset"
          @update:model-value="
            (value) => (authPreset = String(value) as AuthPreset)
          "
        >
          <SelectTrigger id="integration-auth-preset" class="mt-1 w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in presetOptions"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
      </FormField>

      <FormField
        v-if="authPreset === 'bearer'"
        :label="t('访问令牌')"
        label-for="integration-bearer-token"
        :error="fieldError('auth_header_value')"
      >
        <SecretInput
          id="integration-bearer-token"
          v-model="bearerToken"
          class="mt-1"
          autocomplete="off"
        />
      </FormField>

      <template v-if="authPreset === 'header'">
        <FormField
          :label="t('请求头名称')"
          label-for="integration-auth-header-name"
          :error="fieldError('auth_header_name')"
        >
          <Input
            id="integration-auth-header-name"
            v-model="customHeaderName"
            class="mt-1 block w-full"
            autocomplete="off"
            maxlength="128"
          />
        </FormField>

        <FormField
          :label="t('请求头内容')"
          label-for="integration-auth-header-value"
          :error="fieldError('auth_header_value')"
        >
          <SecretInput
            id="integration-auth-header-value"
            v-model="customHeaderValue"
            class="mt-1"
            autocomplete="off"
          />
        </FormField>
      </template>

      <FormField
        :label="t('最长等待时间（秒）')"
        label-for="integration-timeout"
        :error="fieldError('timeout_seconds')"
      >
        <Input
          id="integration-timeout"
          v-model.number="form.timeout_seconds"
          class="mt-1 block w-full"
          type="number"
          min="1"
          max="120"
        />
      </FormField>

      <FormActions
        :submit-label="submitLabel"
        :processing="form.processing"
        :submit-disabled="isChecking"
        :cancel-href="props.returnHref"
        :cancel-label="t('取消')"
      >
        <Button
          type="button"
          variant="outline"
          :disabled="isChecking || form.processing"
          @click="checkConnection"
        >
          <LoaderCircle v-if="isChecking" class="mr-2 h-4 w-4 animate-spin" />
          {{ t('测试连接') }}
        </Button>
      </FormActions>
    </form>
  </div>
</template>
