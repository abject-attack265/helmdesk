<!--
  Telegram 渠道详情页，使用 ShowTelegramChannelDetailPagePropsData 管理基本信息和机器人连接。
-->
<script setup lang="ts">
import Telegram from '@/actions/App/Actions/Channel/Telegram';
import TelegramChannelPreview from '@/components/channel/TelegramChannelPreview.vue';
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
import type { ShowTelegramChannelDetailPagePropsData } from '@/types/generated';
import { Form, Head, router, useHttp } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowTelegramChannelDetailPagePropsData>();
const { t } = useI18n();
const { toast } = useToast();

const channelName = ref(props.telegram_channel.name);
const channelDescription = ref(props.telegram_channel.description ?? '');
const receptionPlanId = ref(props.telegram_channel.reception_plan_id ?? '');
const defaultVisitorLocale = ref<string>(
  props.telegram_channel.default_visitor_locale,
);
const visitorMessageAiTranslationEnabled = ref(
  props.telegram_channel.visitor_message_ai_translation_enabled,
);
const translationContextHint = ref(
  props.telegram_channel.translation_context_hint ?? '',
);
const botToken = ref('');
const gatewayManaged = ref(props.telegram_channel.webhook_mode === 'gateway');

type TabKey = 'basic' | 'telegram';

const activeTab = useUrlTab<TabKey>('tab', {
  defaultValue: props.telegram_channel.has_bot_token ? 'basic' : 'telegram',
  valid: ['basic', 'telegram'],
});

const tabs = computed<{ value: TabKey; label: string }[]>(() => [
  { value: 'basic', label: t('基本信息') },
  { value: 'telegram', label: t('机器人接入') },
]);

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('接入渠道') },
  {
    title: t('Telegram'),
    href: Telegram.ListTelegramChannelsAction.url(),
  },
  { title: props.telegram_channel.name },
]);

/** 保存后重置表单并清空 Token 输入框。 */
function syncFormFromProps(): void {
  const channel = props.telegram_channel;
  channelName.value = channel.name;
  channelDescription.value = channel.description ?? '';
  receptionPlanId.value = channel.reception_plan_id ?? '';
  defaultVisitorLocale.value = channel.default_visitor_locale;
  visitorMessageAiTranslationEnabled.value =
    channel.visitor_message_ai_translation_enabled;
  translationContextHint.value = channel.translation_context_hint ?? '';
  botToken.value = '';
  gatewayManaged.value = channel.webhook_mode === 'gateway';
}

type CheckBotTokenResponse =
  | { success: true; bot_username: string | null; message: null }
  | { success: false; bot_username: null; message: string };

const checkHttp = useHttp<{ bot_token: string }, CheckBotTokenResponse>({
  bot_token: '',
});

const formProcessing = ref(false);
const registeringWebhook = ref(false);

/** 返回服务端已经保存的消息接收方式。 */
const isGatewayManaged = computed(
  () => props.telegram_channel.webhook_mode === 'gateway',
);
const hasSavedBotToken = computed(() => props.telegram_channel.has_bot_token);
const connectionDraftMatchesSaved = computed(
  () =>
    botToken.value.trim() === '' &&
    gatewayManaged.value === isGatewayManaged.value,
);
const isFormDirty = computed(
  () =>
    channelName.value !== props.telegram_channel.name ||
    channelDescription.value !== (props.telegram_channel.description ?? '') ||
    receptionPlanId.value !==
      (props.telegram_channel.reception_plan_id ?? '') ||
    defaultVisitorLocale.value !==
      props.telegram_channel.default_visitor_locale ||
    visitorMessageAiTranslationEnabled.value !==
      props.telegram_channel.visitor_message_ai_translation_enabled ||
    translationContextHint.value !==
      (props.telegram_channel.translation_context_hint ?? '') ||
    botToken.value.trim() !== '' ||
    gatewayManaged.value !== isGatewayManaged.value,
);
const canRegisterSavedWebhook = computed(
  () =>
    hasSavedBotToken.value &&
    !isGatewayManaged.value &&
    connectionDraftMatchesSaved.value &&
    !isFormDirty.value,
);
const interactionLocked = computed(
  () =>
    formProcessing.value || checkHttp.processing || registeringWebhook.value,
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
  const telegramFields = new Set(['bot_token', 'webhook_mode']);
  const hasTelegramErrors = fields.some((field) => telegramFields.has(field));
  const hasBasicErrors = fields.some((field) => basicFields.has(field));

  if (!hasBasicErrors && !hasTelegramErrors) {
    return;
  }

  if (
    (activeTab.value === 'basic' && hasBasicErrors) ||
    (activeTab.value === 'telegram' && hasTelegramErrors)
  ) {
    return;
  }

  activeTab.value = hasBasicErrors ? 'basic' : 'telegram';
}

/** 验证输入框中的机器人 Token 是否可用。 */
function checkBotToken(): void {
  const token = botToken.value.trim();
  if (interactionLocked.value || token === '') {
    return;
  }

  checkHttp.bot_token = token;
  checkHttp.post(Telegram.CheckTelegramBotTokenAction.url(), {
    onSuccess: (response: CheckBotTokenResponse) => {
      if (response.success) {
        toast.success(t('Token 验证通过，保存后生效。'));
      } else {
        toast.error(response.message);
      }
    },
    onHttpException: () => {
      toast.error(t('请核对机器人 Token 格式后再验证。'));
    },
    onNetworkError: () => {
      toast.error(t('网络异常，请检查连接'));
    },
  });
}

const isConfigured = computed(
  () =>
    hasSavedBotToken.value &&
    (props.telegram_channel.webhook_mode === 'gateway' ||
      props.telegram_channel.webhook_active),
);

/** 使用已保存的 Token 重新连接 Telegram。 */
function registerWebhook(): void {
  if (
    formProcessing.value ||
    checkHttp.processing ||
    registeringWebhook.value ||
    !canRegisterSavedWebhook.value
  ) {
    return;
  }

  router.post(
    Telegram.RegisterTelegramWebhookAction.url({
      channel: props.telegram_channel.id,
    }),
    {},
    {
      preserveScroll: true,
      onStart: () => {
        registeringWebhook.value = true;
      },
      onFinish: () => {
        registeringWebhook.value = false;
      },
    },
  );
}

/** 离开前确认是否放弃尚未保存的渠道设置。 */
function confirmLeaveIfDirty(): boolean {
  if (formProcessing.value || registeringWebhook.value) {
    return false;
  }

  if (!isFormDirty.value) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

/** 刷新或关闭页面时交由浏览器提示未保存内容。 */
function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (
    !isFormDirty.value &&
    !formProcessing.value &&
    !registeringWebhook.value
  ) {
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
</script>

<template>
  <div class="contents">
    <Head :title="props.telegram_channel.name" />

    <div class="px-4 py-6 sm:px-6">
      <PageBreadcrumb :items="breadcrumbItems" class="mb-6" />

      <div class="space-y-6">
        <HeadingSmall
          :title="props.telegram_channel.name"
          :description="t('设置渠道信息、接待方案和 Telegram 机器人。')"
        />

        <div class="border-b border-border">
          <nav class="-mb-px flex flex-wrap gap-6">
            <button
              v-for="tab in tabs"
              :key="tab.value"
              type="button"
              class="relative -mb-px border-b-2 px-1 pb-3 text-base font-semibold transition-colors"
              :disabled="
                formProcessing || checkHttp.processing || registeringWebhook
              "
              :aria-pressed="activeTab === tab.value"
              :class="[
                activeTab === tab.value
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
                formProcessing || checkHttp.processing || registeringWebhook
                  ? 'cursor-not-allowed opacity-60'
                  : '',
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
                Telegram.UpdateTelegramChannelBasicAction.url({
                  channel: props.telegram_channel.id,
                })
              "
              method="put"
              class="space-y-6"
              disable-while-processing
              @start="formProcessing = true"
              @finish="formProcessing = false"
              @error="handleFormErrors"
              @success="syncFormFromProps"
            >
              <template #default="{ errors, processing }">
                <div class="space-y-5">
                  <div v-show="activeTab === 'basic'" class="space-y-5">
                    <div class="grid gap-2">
                      <Label for="tg_name" required>{{ t('渠道名称') }}</Label>
                      <Input
                        id="tg_name"
                        v-model="channelName"
                        name="name"
                        required
                        maxlength="100"
                        :disabled="interactionLocked"
                      />
                      <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                      <Label for="tg_description">
                        {{ t('用途说明（选填）') }}
                      </Label>
                      <Textarea
                        id="tg_description"
                        v-model="channelDescription"
                        name="description"
                        rows="3"
                        maxlength="2000"
                        :disabled="interactionLocked"
                      />
                      <InputError :message="errors.description" />
                    </div>

                    <div class="grid gap-2">
                      <Label for="tg_reception_plan_id" required>
                        {{ t('接待方案') }}
                      </Label>
                      <Select
                        v-model="receptionPlanId"
                        :disabled="interactionLocked"
                      >
                        <SelectTrigger id="tg_reception_plan_id" class="w-full">
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
                            <span class="text-sm">
                              {{ option.name }}
                              <span
                                v-if="
                                  !option.is_usable &&
                                  option.unusable_reason_label
                                "
                                class="ml-2 text-xs text-muted-foreground"
                              >
                                ({{ option.unusable_reason_label }})
                              </span>
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
                      <Label for="tg_default_visitor_locale" required>
                        {{ t('访客默认语言') }}
                      </Label>
                      <Select
                        v-model="defaultVisitorLocale"
                        :disabled="interactionLocked"
                      >
                        <SelectTrigger
                          id="tg_default_visitor_locale"
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
                          <Label
                            for="tg_visitor_message_ai_translation_enabled"
                          >
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
                          id="tg_visitor_message_ai_translation_enabled"
                          v-model="visitorMessageAiTranslationEnabled"
                          class="mt-0.5 shrink-0"
                          :disabled="interactionLocked"
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
                      <Label for="tg_translation_context_hint">
                        {{ t('AI 翻译补充说明（选填）') }}
                      </Label>
                      <Textarea
                        id="tg_translation_context_hint"
                        v-model="translationContextHint"
                        name="translation_context_hint"
                        rows="3"
                        maxlength="2000"
                        :disabled="interactionLocked"
                        :placeholder="
                          t(
                            '例如：访客经常混用中文和英文，产品名称通常使用英文。',
                          )
                        "
                      />
                      <InputError :message="errors.translation_context_hint" />
                    </div>
                  </div>

                  <div v-show="activeTab === 'telegram'" class="space-y-5">
                    <div class="grid gap-2">
                      <div class="flex items-center gap-3">
                        <Label for="tg_bot_token" :required="!hasSavedBotToken">
                          {{ t('机器人 Token（Bot Token）') }}
                        </Label>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          :disabled="
                            interactionLocked || botToken.trim().length === 0
                          "
                          @click="checkBotToken"
                        >
                          <LoaderCircle
                            v-if="checkHttp.processing"
                            class="mr-1.5 h-4 w-4 animate-spin"
                          />
                          {{ t('验证 Token') }}
                        </Button>
                      </div>
                      <SecretInput
                        id="tg_bot_token"
                        v-model="botToken"
                        name="bot_token"
                        class="w-full font-mono"
                        autocomplete="off"
                        :placeholder="
                          hasSavedBotToken
                            ? t('已设置，留空表示不更换')
                            : '123456789:AA...'
                        "
                        :required="!hasSavedBotToken"
                        maxlength="200"
                        :disabled="interactionLocked"
                      />
                      <p class="text-xs text-muted-foreground">
                        {{
                          t(
                            '在 Telegram 的 @BotFather 中创建机器人并复制 Token。请勿分享给他人。已设置时留空即可保留。',
                          )
                        }}
                      </p>
                      <InputError :message="errors.bot_token" />
                    </div>

                    <div class="grid gap-2">
                      <Label for="tg_webhook_gateway_mode">
                        {{ t('由外部系统转发消息') }}
                      </Label>
                      <Switch
                        id="tg_webhook_gateway_mode"
                        v-model="gatewayManaged"
                        :disabled="interactionLocked"
                        :title="
                          t(
                            '只有当 Telegram 消息先进入你自己的系统，再转发到 HelmDesk 时才开启。一般情况请保持关闭。',
                          )
                        "
                      />
                      <input
                        type="hidden"
                        name="webhook_mode"
                        :value="gatewayManaged ? 'gateway' : 'direct'"
                      />
                      <InputError :message="errors.webhook_mode" />
                      <p class="text-xs text-muted-foreground">
                        {{
                          t(
                            '只有当 Telegram 消息先进入你自己的系统，再转发到 HelmDesk 时才开启。一般情况请保持关闭，保存后生效。',
                          )
                        }}
                      </p>
                    </div>

                    <p
                      v-if="isGatewayManaged"
                      class="text-sm text-muted-foreground"
                    >
                      {{
                        t(
                          '当前通过外部系统接收消息。请勿在这里重新连接 Telegram，否则外部系统将无法继续接收消息。',
                        )
                      }}
                    </p>
                  </div>
                </div>

                <FormActions
                  :submit-label="t('保存')"
                  :processing="processing"
                  :submit-disabled="checkHttp.processing || registeringWebhook"
                  :cancel-href="Telegram.ListTelegramChannelsAction.url()"
                  :cancel-label="t('返回')"
                />
              </template>
            </Form>
          </div>

          <aside class="xl:sticky xl:top-6 xl:self-start">
            <TelegramChannelPreview
              :can-manage="!formProcessing && !checkHttp.processing"
              :channel-code="props.telegram_channel.code"
              :can-register-webhook="canRegisterSavedWebhook"
              :configured="isConfigured"
              :gateway-managed="isGatewayManaged"
              :has-bot-token="hasSavedBotToken"
              :registering-webhook="registeringWebhook"
              :webhook-active="props.telegram_channel.webhook_active"
              :webhook-url="props.telegram_channel.webhook_url"
              :bot-username="props.telegram_channel.bot_username"
              :webhook-secret="
                isGatewayManaged ? props.telegram_channel.webhook_secret : null
              "
              @register="registerWebhook"
            />
          </aside>
        </div>
      </div>
    </div>
  </div>
</template>
