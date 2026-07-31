<!--
  网站渠道使用方式页签。
  页面提供网站安装代码、可用网站限制和聊天链接。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import EmbedStatusCard from '@/components/channel/EmbedStatusCard.vue';
import FormActions from '@/components/common/FormActions.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import { useUrlTab } from '@/composables/useUrlTab';
import type { WebChannelData } from '@/types/generated';
import type { FormComponentRef } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { Check, CircleHelp, Copy } from '@lucide/vue';
import * as QRCode from 'qrcode';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
  channel: WebChannelData;
}>();

const { t } = useI18n();
const formRef = ref<FormComponentRef | null>(null);
const manualDirty = ref(false);

/** 返回使用方式表单是否含有未保存内容。 */
function hasUnsavedChanges(): boolean {
  return Boolean(formRef.value?.isDirty || manualDirty.value);
}

/** 返回使用方式表单是否正在提交。 */
function isProcessing(): boolean {
  return Boolean(formRef.value?.processing);
}

defineExpose({ hasUnsavedChanges, isProcessing });

type AccessSubTab = 'embed' | 'standalone';
// 使用方式同步到地址栏，刷新或分享链接后保持当前页签。
const accessSubTab = useUrlTab<AccessSubTab>('access_tab', {
  defaultValue: 'embed',
  valid: ['embed', 'standalone'],
});
const subTabs = computed<{ value: AccessSubTab; label: string }[]>(() => [
  { value: 'embed', label: t('网站嵌入') },
  { value: 'standalone', label: t('聊天链接') },
]);

const copiedWidgetCode = ref(false);
const copiedChatLink = ref(false);
const widgetCopyFailed = ref(false);
const chatLinkCopyFailed = ref(false);
const chatLinkQrCodeDataUrl = ref('');
const qrCodeFailed = ref(false);

// 聊天链接仅允许编辑问号后的附加参数。
const standaloneLinkQuery = ref(props.channel.standalone_link_query ?? '');
const normalizedLinkQuery = computed(() =>
  standaloneLinkQuery.value.trim().replace(/^\?+/, ''),
);
const composedChatLink = computed(() =>
  normalizedLinkQuery.value
    ? `${props.channel.standalone_url}?${normalizedLinkQuery.value}`
    : props.channel.standalone_url,
);

const initialAllowedHosts = computed<string[]>(() =>
  Object.values(props.channel.allowed_embed_hosts ?? {}),
);
const allowedHostsText = ref(initialAllowedHosts.value.join('\n'));
const allowedHostsLines = computed<string[]>(() =>
  allowedHostsText.value
    .split(/\r?\n/)
    .map((line: string) => line.trim())
    .filter((line: string) => line.length > 0),
);

const chatLinkParamUrl = computed(
  () => `${props.channel.standalone_url}?utm_source=homepage&campaign=spring`,
);
const chatLinkSignedUrl = computed(
  () => `${props.channel.standalone_url}?user_token=<在你后端签发的 JWT>`,
);
const scriptCloseTag = '</' + 'script>';
const widgetAdvancedSnippet = computed(
  () => `${props.channel.widget_snippet}
<script>
window.HelmDeskWidget = {
  // 登录用户：你的后端用签名密钥签发的 JWT，作为可信身份接入
  user_token: '<在你后端签发的 JWT>',
  // 非敏感补充信息：按「访客信息」中的规则保存
  visitor: {
    external_id: 'user_123',
    email: 'user@example.com',
    phone: '+8613800138000',
    name: '张三',
  },
  params: {
    utm_source: 'homepage',
    campaign: 'spring',
  },
};
${scriptCloseTag}`,
);
const declarativeTriggerSnippet = `<button data-helmdesk-open>${t('联系客服')}</button>`;

let qrCodeRequestId = 0;
const qrCodeGenerating = ref(false);

async function generateQrCode(): Promise<void> {
  const requestId = ++qrCodeRequestId;
  qrCodeGenerating.value = true;
  qrCodeFailed.value = false;

  try {
    const dataUrl = await QRCode.toDataURL(composedChatLink.value, {
      width: 160,
      margin: 1,
      errorCorrectionLevel: 'M',
      color: {
        dark: '#111827',
        light: '#FFFFFF',
      },
    });

    if (requestId === qrCodeRequestId) {
      chatLinkQrCodeDataUrl.value = dataUrl;
      qrCodeFailed.value = false;
    }
  } catch {
    if (requestId === qrCodeRequestId) {
      chatLinkQrCodeDataUrl.value = '';
      qrCodeFailed.value = true;
    }
  } finally {
    if (requestId === qrCodeRequestId) {
      qrCodeGenerating.value = false;
    }
  }
}

// 默认即按当前完整链接（无附加参数时为渠道链接本身）预生成一次二维码，无需用户先点击「生成」。
void generateQrCode();

let qrCodeRefreshTimer: number | null = null;
watch(composedChatLink, () => {
  if (qrCodeRefreshTimer !== null) {
    window.clearTimeout(qrCodeRefreshTimer);
  }
  qrCodeRefreshTimer = window.setTimeout(() => {
    void generateQrCode();
  }, 250);
});

onBeforeUnmount(() => {
  if (qrCodeRefreshTimer !== null) {
    window.clearTimeout(qrCodeRefreshTimer);
  }
});

const copyWidgetCode = async () => {
  try {
    await navigator.clipboard.writeText(props.channel.widget_snippet);
    widgetCopyFailed.value = false;
    copiedWidgetCode.value = true;
    setTimeout(() => {
      copiedWidgetCode.value = false;
    }, 2000);
  } catch {
    copiedWidgetCode.value = false;
    widgetCopyFailed.value = true;
  }
};

// 复制完整聊天链接（渠道链接 + 自定义参数），方便用户直接发给访客。
const copyChatLink = async () => {
  try {
    await navigator.clipboard.writeText(composedChatLink.value);
    chatLinkCopyFailed.value = false;
    copiedChatLink.value = true;
    setTimeout(() => {
      copiedChatLink.value = false;
    }, 2000);
  } catch {
    copiedChatLink.value = false;
    chatLinkCopyFailed.value = true;
  }
};
</script>

<template>
  <Form
    ref="formRef"
    :action="
      Web.UpdateWebChannelAccessAction.url({
        channel: props.channel.id,
      })
    "
    method="put"
    set-defaults-on-success
    class="space-y-6"
    disable-while-processing
    @input.capture="manualDirty = true"
    @change.capture="manualDirty = true"
    @success="manualDirty = false"
  >
    <template #default="{ errors, processing }">
      <template
        v-for="(line, index) in allowedHostsLines"
        :key="`allowed-host-${index}`"
      >
        <input
          type="hidden"
          :name="`allowed_embed_hosts[${index}]`"
          :value="line"
        />
      </template>
      <input
        type="hidden"
        name="standalone_link_query"
        :value="normalizedLinkQuery"
      />

      <div class="space-y-6">
        <div class="border-b border-border">
          <nav class="-mb-px flex flex-wrap gap-6">
            <button
              v-for="tab in subTabs"
              :key="tab.value"
              type="button"
              class="relative -mb-px border-b-2 px-1 pb-2 text-sm font-medium transition-colors"
              :class="
                accessSubTab === tab.value
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              "
              :aria-pressed="accessSubTab === tab.value"
              @click="accessSubTab = tab.value"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div v-show="accessSubTab === 'embed'" class="space-y-8">
          <section class="space-y-3">
            <div class="flex items-center gap-1.5">
              <Label>{{ t('安装代码') }}</Label>
              <Dialog>
                <DialogTrigger as-child>
                  <Button
                    variant="ghost"
                    type="button"
                    size="sm"
                    class="h-7 px-2"
                  >
                    <CircleHelp class="size-4" />
                    {{ t('查看使用说明') }}
                  </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-2xl">
                  <DialogHeader>
                    <DialogTitle>{{ t('如何添加到网站') }}</DialogTitle>
                    <DialogDescription>
                      {{
                        t(
                          '把安装代码添加到网站中，访客就能通过聊天入口发起咨询。',
                        )
                      }}
                    </DialogDescription>
                  </DialogHeader>
                  <div class="space-y-4 text-sm">
                    <div class="space-y-2">
                      <p class="font-medium">{{ t('安装代码') }}</p>
                      <pre
                        class="rounded-md border bg-muted/30 p-3 break-all whitespace-pre-wrap"
                        >{{ props.channel.widget_snippet }}</pre>
                    </div>
                    <div class="space-y-2">
                      <p class="font-medium">
                        {{ t('传入访客信息（开发人员）') }}
                      </p>
                      <pre
                        class="rounded-md border bg-muted/30 p-3 break-all whitespace-pre-wrap"
                        >{{ widgetAdvancedSnippet }}</pre>
                      <p class="text-muted-foreground">
                        {{
                          t(
                            '如需识别已登录访客或记录活动来源，请让开发人员按上面的示例接入。普通参数只适合来源、活动等非敏感信息。',
                          )
                        }}
                      </p>
                    </div>
                    <div class="space-y-2">
                      <p class="font-medium">
                        {{ t('使用网站自己的按钮') }}
                      </p>
                      <pre
                        class="rounded-md border bg-muted/30 p-3 break-all whitespace-pre-wrap"
                        >{{ declarativeTriggerSnippet }}</pre>
                      <p class="text-muted-foreground">
                        {{
                          t(
                            '选择“使用网站自己的按钮”后，系统不会显示默认聊天按钮。请让开发人员使用上面的代码打开聊天。',
                          )
                        }}
                      </p>
                    </div>
                  </div>
                </DialogContent>
              </Dialog>
              <EmbedStatusCard :channel="props.channel" class="ml-auto" />
            </div>
            <div
              class="flex items-center gap-2 rounded-md border bg-muted/30 px-3 py-2 text-sm"
            >
              <code
                class="min-w-0 flex-1 font-mono break-all whitespace-pre-wrap"
              >
                {{ props.channel.widget_snippet }}
              </code>
              <Button
                variant="ghost"
                type="button"
                size="icon-sm"
                :aria-label="copiedWidgetCode ? t('已复制') : t('复制安装代码')"
                :title="copiedWidgetCode ? t('已复制') : t('复制安装代码')"
                @click="copyWidgetCode"
              >
                <Check v-if="copiedWidgetCode" class="size-4" />
                <Copy v-else class="size-4" />
              </Button>
            </div>
            <p v-if="widgetCopyFailed" class="text-sm text-destructive">
              {{ t('复制失败，请手动复制。') }}
            </p>
          </section>

          <section class="space-y-3">
            <div class="space-y-1">
              <Label for="access_allowed_embed_hosts">
                {{ t('允许使用的网站（选填）') }}
              </Label>
              <p class="text-sm text-muted-foreground">
                {{
                  t('每行填写一个域名。留空或填写 * 表示所有网站都可以使用。')
                }}
              </p>
            </div>
            <Textarea
              id="access_allowed_embed_hosts"
              v-model="allowedHostsText"
              rows="4"
              :disabled="processing"
              @update:model-value="manualDirty = true"
            />
            <InputError :message="errors.allowed_embed_hosts" />
          </section>
        </div>

        <div v-show="accessSubTab === 'standalone'" class="space-y-6">
          <section class="space-y-2">
            <div class="flex items-center gap-2">
              <Label for="standalone_link_query">{{ t('聊天链接') }}</Label>
              <Dialog>
                <DialogTrigger as-child>
                  <Button
                    variant="ghost"
                    type="button"
                    size="sm"
                    class="h-7 px-2"
                  >
                    <CircleHelp class="size-4" />
                    {{ t('查看使用说明') }}
                  </Button>
                </DialogTrigger>
                <DialogContent class="sm:max-w-2xl">
                  <DialogHeader>
                    <DialogTitle>{{ t('如何使用聊天链接') }}</DialogTitle>
                    <DialogDescription>
                      {{
                        t(
                          '可以直接把聊天链接发给访客，也可以放在网站按钮或二维码中。',
                        )
                      }}
                    </DialogDescription>
                  </DialogHeader>
                  <div class="space-y-4 text-sm">
                    <div class="space-y-2">
                      <p class="font-medium">{{ t('基本用法') }}</p>
                      <pre
                        class="rounded-md border bg-muted/30 p-3 break-all whitespace-pre-wrap"
                        >{{ props.channel.standalone_url }}</pre>
                    </div>
                    <div class="space-y-2">
                      <p class="font-medium">
                        {{ t('识别已登录访客（开发人员）') }}
                      </p>
                      <pre
                        class="rounded-md border bg-muted/30 p-3 break-all whitespace-pre-wrap"
                        >{{ chatLinkSignedUrl }}</pre>
                      <p class="text-muted-foreground">
                        {{
                          t(
                            '需要把网站登录用户识别为同一位访客时，请让开发人员使用“访客信息”中的密钥接入。密钥和生成的身份信息不要公开分享。',
                          )
                        }}
                      </p>
                    </div>
                    <div class="space-y-2">
                      <p class="font-medium">{{ t('附带访客信息') }}</p>
                      <pre
                        class="rounded-md border bg-muted/30 p-3 break-all whitespace-pre-wrap"
                        >{{ chatLinkParamUrl }}</pre>
                      <p class="text-muted-foreground">
                        {{
                          t(
                            '链接中的参数会按照“访客信息”设置填写联系人资料，只适合来源、活动等非敏感信息。',
                          )
                        }}
                      </p>
                    </div>
                  </div>
                </DialogContent>
              </Dialog>
            </div>
            <div class="grid gap-2 md:grid-cols-[2fr_1fr]">
              <Input
                :model-value="props.channel.standalone_url"
                readonly
                class="font-mono"
                :aria-label="t('渠道链接')"
              />
              <div class="flex items-center gap-2">
                <Input
                  id="standalone_link_query"
                  v-model="standaloneLinkQuery"
                  class="font-mono"
                  maxlength="1024"
                  :disabled="processing"
                  :placeholder="t('附加参数（选填）')"
                  :aria-label="t('附加参数（选填）')"
                  @update:model-value="manualDirty = true"
                />
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  class="shrink-0"
                  @click="copyChatLink"
                >
                  {{ copiedChatLink ? t('已复制') : t('复制') }}
                </Button>
              </div>
            </div>
            <p class="text-sm text-muted-foreground">
              {{ t('附加参数只填写 ? 后面的内容。') }}
            </p>
            <p v-if="chatLinkCopyFailed" class="text-sm text-destructive">
              {{ t('复制失败，请手动复制。') }}
            </p>
            <InputError :message="errors.standalone_link_query" />
          </section>

          <section class="space-y-2">
            <div class="flex items-center gap-2">
              <Label>{{ t('二维码') }}</Label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="qrCodeGenerating"
                @click="generateQrCode"
              >
                {{ qrCodeFailed ? t('重试') : t('更新二维码') }}
              </Button>
            </div>
            <div
              class="flex size-40 shrink-0 items-center justify-center rounded-md border bg-white p-2"
            >
              <img
                v-if="chatLinkQrCodeDataUrl"
                :src="chatLinkQrCodeDataUrl"
                :alt="t('聊天链接二维码')"
                class="size-full"
              />
              <span
                v-else
                class="px-3 text-center text-xs text-muted-foreground"
              >
                {{
                  qrCodeFailed ? t('二维码生成失败，请重试。') : t('生成中...')
                }}
              </span>
            </div>
          </section>
        </div>

        <FormActions
          :submit-label="t('保存')"
          :processing="processing"
          :cancel-href="Web.ListWebChannelsAction.url()"
          :cancel-label="t('返回')"
        />
      </div>
    </template>
  </Form>
</template>
