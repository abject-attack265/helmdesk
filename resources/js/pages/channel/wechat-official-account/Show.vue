<!--
  微信公众号渠道详情页，使用 ShowWechatOfficialAccountChannelDetailPagePropsData 管理直连配置。
-->
<script setup lang="ts">
import WechatOfficialAccount from '@/actions/App/Actions/Channel/WechatOfficialAccount';
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import SecretInput from '@/components/common/SecretInput.vue';
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
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import { useUrlTab } from '@/composables/useUrlTab';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ShowWechatOfficialAccountChannelDetailPagePropsData } from '@/types/generated';
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Copy } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props =
  defineProps<ShowWechatOfficialAccountChannelDetailPagePropsData>();
const { t } = useI18n();
const { toast } = useToast();

const channelName = ref(props.wechat_channel.name);
const channelDescription = ref(props.wechat_channel.description ?? '');
const appId = ref(props.wechat_channel.app_id);
const appSecret = ref(props.wechat_channel.app_secret ?? '');
const token = ref(props.wechat_channel.token ?? '');
const aesKey = ref(props.wechat_channel.aes_key ?? '');
const messageMode = ref(props.wechat_channel.message_mode ?? 'plain');
const receptionPlanId = ref(props.wechat_channel.reception_plan_id ?? '');
const defaultVisitorLocale = ref(props.wechat_channel.default_visitor_locale);
const visitorMessageAiTranslationEnabled = ref(
  props.wechat_channel.visitor_message_ai_translation_enabled,
);
const translationContextHint = ref(
  props.wechat_channel.translation_context_hint ?? '',
);
const copiedWebhookUrl = ref(false);
const formProcessing = ref(false);

type TabKey = 'basic' | 'wechat';

const activeTab = useUrlTab<TabKey>('tab', {
  defaultValue: props.wechat_channel.webhook_active ? 'basic' : 'wechat',
  valid: ['basic', 'wechat'],
});

const tabs = computed<{ value: TabKey; label: string }[]>(() => [
  { value: 'basic', label: t('基本信息') },
  { value: 'wechat', label: t('公众号接入') },
]);

const receptionPlanSelectionInvalid = computed(() => {
  if (!receptionPlanId.value) {
    return true;
  }

  if (
    receptionPlanId.value === (props.wechat_channel.reception_plan_id ?? '')
  ) {
    return false;
  }

  return !props.form_options.reception_plan_options.find(
    (option) => option.id === receptionPlanId.value,
  )?.is_usable;
});
const isConfigured = computed(() => props.wechat_channel.webhook_active);
const messageModeLabel = computed(() => {
  if (!isConfigured.value) {
    return t('未配置');
  }

  return props.wechat_channel.message_mode === 'aes'
    ? t('安全模式')
    : t('明文模式');
});

const isFormDirty = computed(
  () =>
    channelName.value !== props.wechat_channel.name ||
    channelDescription.value !== (props.wechat_channel.description ?? '') ||
    appId.value !== props.wechat_channel.app_id ||
    appSecret.value !== (props.wechat_channel.app_secret ?? '') ||
    token.value !== (props.wechat_channel.token ?? '') ||
    aesKey.value !== (props.wechat_channel.aes_key ?? '') ||
    messageMode.value !== (props.wechat_channel.message_mode ?? 'plain') ||
    receptionPlanId.value !== (props.wechat_channel.reception_plan_id ?? '') ||
    defaultVisitorLocale.value !==
      props.wechat_channel.default_visitor_locale ||
    visitorMessageAiTranslationEnabled.value !==
      props.wechat_channel.visitor_message_ai_translation_enabled ||
    translationContextHint.value !==
      (props.wechat_channel.translation_context_hint ?? ''),
);

watch(
  () => props.wechat_channel,
  (channel) => {
    channelName.value = channel.name;
    channelDescription.value = channel.description ?? '';
    appId.value = channel.app_id;
    appSecret.value = channel.app_secret ?? '';
    token.value = channel.token ?? '';
    aesKey.value = channel.aes_key ?? '';
    messageMode.value = channel.message_mode ?? 'plain';
    receptionPlanId.value = channel.reception_plan_id ?? '';
    defaultVisitorLocale.value = channel.default_visitor_locale;
    visitorMessageAiTranslationEnabled.value =
      channel.visitor_message_ai_translation_enabled;
    translationContextHint.value = channel.translation_context_hint ?? '';
  },
);

/** 根据校验错误切换到对应标签，确保错误直接可见。 */
function handleFormErrors(errors: Record<string, string>): void {
  const fields = Object.keys(errors);
  const basicFields = new Set([
    'name',
    'description',
    'reception_plan_id',
    'default_visitor_locale',
    'visitor_message_ai_translation_enabled',
    'translation_context_hint',
  ]);
  const wechatFields = new Set([
    'app_id',
    'app_secret',
    'token',
    'message_mode',
    'aes_key',
  ]);
  const hasBasicErrors = fields.some((field) => basicFields.has(field));
  const hasWechatErrors = fields.some((field) => wechatFields.has(field));

  if (!hasBasicErrors && !hasWechatErrors) {
    return;
  }

  if (
    (activeTab.value === 'basic' && hasBasicErrors) ||
    (activeTab.value === 'wechat' && hasWechatErrors)
  ) {
    return;
  }

  activeTab.value = hasBasicErrors ? 'basic' : 'wechat';
}

/** 离开前确认是否放弃尚未保存的渠道设置。 */
function confirmLeaveIfDirty(): boolean {
  if (formProcessing.value) {
    return false;
  }

  if (!isFormDirty.value) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

/** 刷新或关闭页面时交由浏览器提示未保存内容。 */
function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!isFormDirty.value && !formProcessing.value) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

let removeBeforeListener: (() => void) | null = null;

onMounted(() => {
  removeBeforeListener = router.on('before', (event) => {
    if (event.detail.visit.method === 'get' && !confirmLeaveIfDirty()) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
});

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('微信公众号'),
    href: WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url(),
  },
  { title: props.wechat_channel.name },
]);

async function copyWebhookUrl(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.wechat_channel.webhook_url);
    copiedWebhookUrl.value = true;
    toast.success(t('已复制'));
    window.setTimeout(() => {
      copiedWebhookUrl.value = false;
    }, 1600);
  } catch {
    toast.error(t('复制失败，请手动复制'));
  }
}
</script>

<template>
  <div class="contents">
    <Head :title="props.wechat_channel.name" />

    <div class="px-4 py-6 sm:px-6">
      <PageBreadcrumb :items="breadcrumbItems" class="mb-6" />

      <div class="space-y-6">
        <HeadingSmall
          :title="props.wechat_channel.name"
          :description="t('设置渠道信息和微信公众号开发者配置。')"
        />

        <div class="border-b border-border">
          <nav class="-mb-px flex flex-wrap gap-6">
            <button
              v-for="tab in tabs"
              :key="tab.value"
              type="button"
              class="relative -mb-px border-b-2 px-1 pb-3 text-base font-semibold transition-colors"
              :disabled="formProcessing"
              :aria-pressed="activeTab === tab.value"
              :class="[
                activeTab === tab.value
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
                formProcessing ? 'cursor-not-allowed opacity-60' : '',
              ]"
              @click="activeTab = tab.value"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_30rem]">
          <div class="min-w-0">
            <Form
              :action="
                WechatOfficialAccount.UpdateWechatOfficialAccountChannelBasicAction.url(
                  { channel: props.wechat_channel.id },
                )
              "
              method="put"
              class="space-y-6"
              disable-while-processing
              @start="formProcessing = true"
              @finish="formProcessing = false"
              @error="handleFormErrors"
              v-slot="{ errors, processing }"
            >
              <div class="space-y-5">
                <div v-show="activeTab === 'basic'" class="space-y-5">
                  <div class="grid gap-2">
                    <Label for="wx_name" required>{{ t('渠道名称') }}</Label>
                    <Input
                      id="wx_name"
                      v-model="channelName"
                      name="name"
                      maxlength="100"
                      :disabled="processing"
                      required
                    />
                    <InputError :message="errors.name" />
                  </div>

                  <div class="grid gap-2">
                    <Label for="wx_description">
                      {{ t('用途说明（选填）') }}
                    </Label>
                    <Textarea
                      id="wx_description"
                      v-model="channelDescription"
                      name="description"
                      rows="3"
                      maxlength="2000"
                      :disabled="processing"
                    />
                    <InputError :message="errors.description" />
                  </div>

                  <div class="grid gap-2">
                    <Label for="wx_reception_plan" required>{{
                      t('接待方案')
                    }}</Label>
                    <Select v-model="receptionPlanId" :disabled="processing">
                      <SelectTrigger id="wx_reception_plan" class="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="option in props.form_options
                            .reception_plan_options"
                          :key="option.id"
                          :value="option.id"
                          :disabled="!option.is_usable"
                        >
                          {{ option.name }}
                          <span
                            v-if="
                              !option.is_usable && option.unusable_reason_label
                            "
                            class="ml-2 text-xs text-muted-foreground"
                          >
                            ({{ option.unusable_reason_label }})
                          </span>
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <input
                      type="hidden"
                      name="reception_plan_id"
                      :value="receptionPlanId"
                    />
                    <InputError :message="errors.reception_plan_id" />
                  </div>

                  <div class="grid gap-2">
                    <Label for="wx_default_visitor_locale" required>
                      {{ t('访客默认语言') }}
                    </Label>
                    <Select
                      v-model="defaultVisitorLocale"
                      :disabled="processing"
                    >
                      <SelectTrigger
                        id="wx_default_visitor_locale"
                        class="w-full"
                      >
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem
                          v-for="option in props.form_options
                            .reception_language_options"
                          :key="String(option.value)"
                          :value="String(option.value)"
                        >
                          {{ option.label }}
                        </SelectItem>
                      </SelectContent>
                    </Select>
                    <input
                      type="hidden"
                      name="default_visitor_locale"
                      :value="defaultVisitorLocale"
                    />
                    <InputError :message="errors.default_visitor_locale" />
                  </div>

                  <div class="grid gap-2">
                    <div class="flex items-start justify-between gap-4">
                      <div class="space-y-1">
                        <Label for="wx_visitor_message_ai_translation_enabled">
                          {{ t('访客消息优先使用 AI 增强翻译') }}
                        </Label>
                        <p class="text-xs leading-5 text-muted-foreground">
                          {{
                            t(
                              '适合多语言混写、罗马音或俚语场景。机器翻译通常更快、更稳定。',
                            )
                          }}
                        </p>
                      </div>
                      <Switch
                        id="wx_visitor_message_ai_translation_enabled"
                        v-model="visitorMessageAiTranslationEnabled"
                        class="mt-0.5 shrink-0"
                        :disabled="processing"
                      />
                    </div>
                    <input
                      type="hidden"
                      name="visitor_message_ai_translation_enabled"
                      :value="visitorMessageAiTranslationEnabled ? '1' : '0'"
                    />
                    <InputError
                      :message="errors.visitor_message_ai_translation_enabled"
                    />
                  </div>

                  <div
                    v-show="visitorMessageAiTranslationEnabled"
                    class="grid gap-2"
                  >
                    <Label for="wx_translation_context_hint">
                      {{ t('AI 翻译补充说明（选填）') }}
                    </Label>
                    <Textarea
                      id="wx_translation_context_hint"
                      v-model="translationContextHint"
                      name="translation_context_hint"
                      rows="3"
                      maxlength="2000"
                      :disabled="processing"
                      :placeholder="
                        t('例如：访客常用中英混合表达，产品名称请保留英文。')
                      "
                    />
                    <InputError :message="errors.translation_context_hint" />
                  </div>
                </div>

                <div v-show="activeTab === 'wechat'" class="space-y-5">
                  <div class="grid gap-2">
                    <Label for="wx_app_id" required>{{ t('AppID') }}</Label>
                    <Input
                      id="wx_app_id"
                      v-model="appId"
                      name="app_id"
                      required
                      class="font-mono"
                      autocomplete="off"
                      placeholder="wx..."
                      maxlength="64"
                      :disabled="processing"
                    />
                    <InputError :message="errors.app_id" />
                  </div>

                  <div class="grid gap-2">
                    <Label for="wx_app_secret" required>{{
                      t('AppSecret')
                    }}</Label>
                    <SecretInput
                      id="wx_app_secret"
                      v-model="appSecret"
                      name="app_secret"
                      class="w-full font-mono"
                      autocomplete="off"
                      maxlength="128"
                      :disabled="processing"
                      required
                    />
                    <InputError :message="errors.app_secret" />
                  </div>

                  <div class="grid gap-2">
                    <Label for="wx_token" required>{{ t('Token') }}</Label>
                    <SecretInput
                      id="wx_token"
                      v-model="token"
                      name="token"
                      class="w-full font-mono"
                      autocomplete="off"
                      maxlength="128"
                      :disabled="processing"
                      required
                    />
                    <InputError :message="errors.token" />
                  </div>

                  <div class="grid gap-2">
                    <Label for="wx_message_mode" required>{{
                      t('消息加密方式')
                    }}</Label>
                    <Select v-model="messageMode" :disabled="processing">
                      <SelectTrigger id="wx_message_mode" class="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="plain">{{
                          t('明文模式')
                        }}</SelectItem>
                        <SelectItem value="aes">{{ t('安全模式') }}</SelectItem>
                      </SelectContent>
                    </Select>
                    <input
                      type="hidden"
                      name="message_mode"
                      :value="messageMode"
                    />
                    <InputError :message="errors.message_mode" />
                  </div>

                  <div class="grid gap-2">
                    <Label for="wx_aes_key" :required="messageMode === 'aes'">
                      {{ t('EncodingAESKey') }}
                    </Label>
                    <SecretInput
                      id="wx_aes_key"
                      v-model="aesKey"
                      name="aes_key"
                      class="w-full font-mono"
                      autocomplete="off"
                      maxlength="43"
                      :disabled="processing || messageMode !== 'aes'"
                      :required="messageMode === 'aes'"
                    />
                    <p class="text-xs text-muted-foreground">
                      {{
                        messageMode === 'aes'
                          ? t('安全模式需要填写 43 位 EncodingAESKey。')
                          : t('明文模式不需要填写 EncodingAESKey。')
                      }}
                    </p>
                    <InputError :message="errors.aes_key" />
                  </div>
                </div>
              </div>

              <FormActions
                :submit-label="t('保存')"
                :processing="processing"
                :submit-disabled="receptionPlanSelectionInvalid"
                :cancel-href="
                  WechatOfficialAccount.ListWechatOfficialAccountChannelsAction.url()
                "
                :cancel-label="t('返回')"
              />
            </Form>
          </div>

          <aside class="xl:sticky xl:top-6 xl:self-start">
            <div class="rounded-lg border bg-muted/20 p-5">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h2 class="font-semibold">{{ t('微信公众号连接状态') }}</h2>
                  <p class="mt-1 text-sm text-muted-foreground">
                    {{
                      isConfigured
                        ? t(
                            '配置信息已保存，可以到微信公众平台启用服务器配置。',
                          )
                        : t(
                            '填写并保存公众号配置，然后到微信公众平台启用服务器配置。',
                          )
                    }}
                  </p>
                </div>
                <span
                  class="shrink-0 rounded-full border border-border bg-background px-2.5 py-1 text-xs font-medium"
                  :class="
                    isConfigured ? 'text-foreground' : 'text-muted-foreground'
                  "
                >
                  {{ isConfigured ? t('已配置') : t('未配置') }}
                </span>
              </div>

              <div class="mt-5 grid gap-2">
                <div class="flex items-center justify-between gap-3">
                  <Label for="wx_webhook_url">
                    {{ t('服务器地址（URL）') }}
                  </Label>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="shrink-0"
                    @click="copyWebhookUrl"
                  >
                    <Check v-if="copiedWebhookUrl" class="mr-1.5 h-4 w-4" />
                    <Copy v-else class="mr-1.5 h-4 w-4" />
                    {{ copiedWebhookUrl ? t('已复制') : t('复制') }}
                  </Button>
                </div>
                <Input
                  id="wx_webhook_url"
                  :model-value="props.wechat_channel.webhook_url"
                  readonly
                  class="font-mono text-xs"
                />
                <p class="text-xs text-muted-foreground">
                  {{
                    t(
                      '将这个地址与左侧填写的 Token、EncodingAESKey 填入微信公众平台的服务器配置。',
                    )
                  }}
                </p>
              </div>

              <dl class="mt-5 grid gap-3 border-t pt-4 text-sm">
                <div class="flex items-center justify-between gap-4">
                  <dt class="text-muted-foreground">{{ t('AppID') }}</dt>
                  <dd class="max-w-[15rem] text-right font-mono break-all">
                    {{ props.wechat_channel.app_id || '—' }}
                  </dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                  <dt class="text-muted-foreground">{{ t('消息模式') }}</dt>
                  <dd>{{ messageModeLabel }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                  <dt class="text-muted-foreground">{{ t('渠道编号') }}</dt>
                  <dd class="max-w-[15rem] text-right font-mono break-all">
                    {{ props.wechat_channel.code }}
                  </dd>
                </div>
              </dl>
            </div>
          </aside>
        </div>
      </div>
    </div>
  </div>
</template>
