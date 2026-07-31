<!-- Telegram 渠道详情页右侧的接入状态卡片。 -->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { useToast } from '@/composables/useToast';
import { Check, Copy, Eye, EyeOff, LoaderCircle } from '@lucide/vue';
import { ref, watch } from 'vue';

const props = withDefaults(
  defineProps<{
    canManage: boolean;
    channelCode: string;
    canRegisterWebhook: boolean;
    configured: boolean;
    gatewayManaged: boolean;
    hasBotToken: boolean;
    registeringWebhook: boolean;
    webhookActive: boolean;
    webhookSecret?: string | null;
    webhookUrl: string;
    botUsername?: string | null;
  }>(),
  {
    botUsername: null,
    webhookSecret: null,
  },
);

const emit = defineEmits<{
  register: [];
}>();

const { t } = useI18n();
const { toast } = useToast();
const copiedWebhookUrl = ref(false);
const copiedWebhookSecret = ref(false);
const webhookSecretVisible = ref(false);

watch(
  () => props.webhookSecret,
  () => {
    webhookSecretVisible.value = false;
  },
);

async function copyWebhookUrl(): Promise<void> {
  try {
    await navigator.clipboard.writeText(props.webhookUrl);
    copiedWebhookUrl.value = true;
    toast.success(t('已复制'));
    window.setTimeout(() => {
      copiedWebhookUrl.value = false;
    }, 1600);
  } catch {
    toast.error(t('复制失败，请手动复制'));
  }
}

async function copyWebhookSecret(secret: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(secret);
    copiedWebhookSecret.value = true;
    toast.success(t('已复制'));
    window.setTimeout(() => {
      copiedWebhookSecret.value = false;
    }, 1600);
  } catch {
    toast.error(t('复制失败，请手动复制'));
  }
}
</script>

<template>
  <div class="rounded-lg border bg-muted/20 p-5">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="font-semibold">{{ t('机器人连接状态') }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">
          {{
            props.gatewayManaged
              ? t('当前设置为由外部系统转发消息。')
              : props.configured
                ? t('机器人已连接，访客消息会进入收件箱。')
                : props.hasBotToken
                  ? t('机器人尚未连接，请点击连接后重试。')
                  : t('填写机器人 Token 并保存，系统会自动连接。')
          }}
        </p>
      </div>
      <span
        class="shrink-0 rounded-full border border-border bg-background px-2.5 py-1 text-xs font-medium"
        :class="props.configured ? 'text-foreground' : 'text-muted-foreground'"
      >
        {{
          props.gatewayManaged
            ? t('已设置')
            : props.configured
              ? t('已连接')
              : t('未连接')
        }}
      </span>
    </div>

    <div
      v-if="!props.gatewayManaged"
      class="mt-5 flex items-center justify-between gap-4 border-t pt-4"
    >
      <p class="text-sm text-muted-foreground">
        {{ t('连接后才能接收 Telegram 消息。') }}
      </p>
      <Button
        type="button"
        variant="outline"
        size="sm"
        :disabled="
          !props.canManage ||
          !props.canRegisterWebhook ||
          props.registeringWebhook
        "
        @click="emit('register')"
      >
        <LoaderCircle
          v-if="props.registeringWebhook"
          class="mr-1.5 h-4 w-4 animate-spin"
        />
        {{ props.webhookActive ? t('重新连接') : t('连接') }}
      </Button>
    </div>

    <div v-else class="mt-5 grid gap-4 border-t pt-4">
      <p class="text-sm text-muted-foreground">
        {{ t('请将以下信息填写到负责转发 Telegram 消息的外部系统中。') }}
      </p>

      <div class="grid gap-2">
        <div class="flex items-center justify-between gap-3">
          <Label for="tg_preview_webhook_url">
            {{ t('消息接收地址（Webhook URL）') }}
          </Label>
          <Button
            type="button"
            variant="outline"
            size="sm"
            @click="copyWebhookUrl"
          >
            <Check v-if="copiedWebhookUrl" class="mr-1.5 h-4 w-4" />
            <Copy v-else class="mr-1.5 h-4 w-4" />
            {{ copiedWebhookUrl ? t('已复制') : t('复制') }}
          </Button>
        </div>
        <Input
          id="tg_preview_webhook_url"
          :model-value="props.webhookUrl"
          readonly
          class="font-mono text-xs"
        />
      </div>

      <div class="grid gap-2">
        <div class="flex items-center justify-between gap-3">
          <Label for="tg_preview_webhook_secret">{{ t('消息转发密钥') }}</Label>
          <div v-if="props.webhookSecret" class="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="icon"
              :aria-label="webhookSecretVisible ? t('隐藏密钥') : t('显示密钥')"
              :title="webhookSecretVisible ? t('隐藏密钥') : t('显示密钥')"
              @click="webhookSecretVisible = !webhookSecretVisible"
            >
              <EyeOff v-if="webhookSecretVisible" class="h-4 w-4" />
              <Eye v-else class="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              @click="copyWebhookSecret(props.webhookSecret)"
            >
              <Check v-if="copiedWebhookSecret" class="mr-1.5 h-4 w-4" />
              <Copy v-else class="mr-1.5 h-4 w-4" />
              {{ copiedWebhookSecret ? t('已复制') : t('复制') }}
            </Button>
          </div>
        </div>
        <Input
          id="tg_preview_webhook_secret"
          :model-value="props.webhookSecret ?? '—'"
          :type="
            props.webhookSecret && !webhookSecretVisible ? 'password' : 'text'
          "
          readonly
          class="font-mono text-xs"
        />
      </div>

      <dl class="grid gap-3 text-sm">
        <div class="flex items-center justify-between gap-4">
          <dt class="text-muted-foreground">{{ t('渠道编号') }}</dt>
          <dd class="max-w-[15rem] text-right font-mono text-xs break-all">
            {{ props.channelCode }}
          </dd>
        </div>
      </dl>
    </div>

    <dl class="mt-5 grid gap-3 border-t pt-4 text-sm">
      <div class="flex items-center justify-between gap-4">
        <dt class="text-muted-foreground">{{ t('机器人') }}</dt>
        <dd>{{ props.botUsername ? `@${props.botUsername}` : '—' }}</dd>
      </div>
    </dl>
  </div>
</template>
