<!--
  文件说明：个人设置页面，消费后端设置数据并提交用户偏好表单。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { settingsPageLayout } from '@/layouts/pageLayouts';
import {
  confirm,
  disable,
  enable,
  regenerateRecoveryCodes,
} from '@/routes/two-factor';
import { Form, Head } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import {
  AlertCircle,
  Check,
  Copy,
  Eye,
  EyeOff,
  LockKeyhole,
  RefreshCw,
  ScanLine,
  ShieldBan,
  ShieldCheck,
} from '@lucide/vue';
import {
  computed,
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  useTemplateRef,
  watch,
} from 'vue';

defineOptions({ layout: settingsPageLayout });

interface Props {
  requires_confirmation?: boolean;
  two_factor_enabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  requires_confirmation: false,
  two_factor_enabled: false,
});

const { t } = useI18n();

const {
  hasSetupData,
  clearTwoFactorAuthData,
  clearSetupData,
  fetchSetupData,
  qrCodeSvg,
  manualSetupKey,
  recoveryCodesList,
  fetchRecoveryCodes,
  errors: twoFactorErrors,
} = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

const uniqueErrors = computed(() => Array.from(new Set(twoFactorErrors.value)));

const isRecoveryCodesVisible = ref<boolean>(false);
const recoveryCodeSectionRef = useTemplateRef('recoveryCodeSectionRef');

const viewHideButtonText = computed(() =>
  isRecoveryCodesVisible.value ? t('隐藏恢复码') : t('查看恢复码'),
);

const toggleRecoveryCodesVisibility = async () => {
  if (!isRecoveryCodesVisible.value && !recoveryCodesList.value.length) {
    await fetchRecoveryCodes();
  }

  isRecoveryCodesVisible.value = !isRecoveryCodesVisible.value;

  if (isRecoveryCodesVisible.value) {
    await nextTick();
    recoveryCodeSectionRef.value?.scrollIntoView({ behavior: 'smooth' });
  }
};

onMounted(async () => {
  if (!recoveryCodesList.value.length) {
    await fetchRecoveryCodes();
  }
});

const { copy, copied } = useClipboard();
const showVerificationStep = ref(false);
const code = ref<string>('');
const pinInputContainerRef = useTemplateRef('pinInputContainerRef');

const modalConfig = computed<{
  title: string;
  description: string;
  buttonText: string;
}>(() => {
  if (props.two_factor_enabled) {
    return {
      title: t('两步验证现已启用'),
      description: t(
        '两步验证现已启用。扫描二维码或在身份验证器应用中输入设置密钥。',
      ),
      buttonText: t('关闭'),
    };
  }

  if (showVerificationStep.value) {
    return {
      title: t('验证身份验证码'),
      description: t('输入来自身份验证器应用的 6 位数字验证码'),
      buttonText: t('继续'),
    };
  }

  return {
    title: t('启用两步验证'),
    description: t(
      '要完成两步验证的启用，请扫描二维码或在身份验证器应用中输入设置密钥',
    ),
    buttonText: t('继续'),
  };
});

const handleModalNextStep = () => {
  if (props.requires_confirmation) {
    showVerificationStep.value = true;

    nextTick(() => {
      pinInputContainerRef.value?.querySelector('input')?.focus();
    });

    return;
  }

  clearSetupData();
  showSetupModal.value = false;
};

const resetModalState = () => {
  if (props.two_factor_enabled) {
    clearSetupData();
  }

  showVerificationStep.value = false;
  code.value = '';
};

watch(
  () => showSetupModal.value,
  async (isOpen) => {
    if (!isOpen) {
      resetModalState();
      return;
    }

    if (!qrCodeSvg.value) {
      await fetchSetupData();
    }
  },
);

onUnmounted(() => {
  clearTwoFactorAuthData();
});
</script>

<template>
  <div class="contents">
    <Head :title="t('两步验证')" />
    <div class="space-y-6">
      <HeadingSmall
        :title="t('两步验证')"
        :description="t('管理你的两步验证设置')"
      />

      <div
        v-if="!props.two_factor_enabled"
        class="flex flex-col items-start justify-start space-y-4"
      >
        <Badge variant="destructive">{{ t('已禁用') }}</Badge>

        <p class="text-muted-foreground">
          {{
            t(
              '启用两步验证后，登录时将需要输入安全验证码。该验证码可以通过手机上支持 TOTP 的应用程序获取。',
            )
          }}
        </p>

        <div>
          <Button v-if="hasSetupData" @click="showSetupModal = true">
            <ShieldCheck />{{ t('继续设置') }}
          </Button>
          <Form
            v-else
            :action="enable.url()"
            method="post"
            @success="showSetupModal = true"
            #default="{ processing }"
          >
            <Button type="submit" :disabled="processing">
              <ShieldCheck />{{ t('启用两步验证') }}</Button
            ></Form
          >
        </div>
      </div>

      <div v-else class="flex flex-col items-start justify-start space-y-4">
        <Badge variant="default">{{ t('已启用') }}</Badge>

        <p class="text-muted-foreground">
          {{
            t(
              '启用两步验证后，登录时将需要输入安全的随机验证码，你可以通过手机上支持 TOTP 的应用程序获取该验证码。',
            )
          }}
        </p>

        <Card class="w-full">
          <CardHeader>
            <CardTitle class="flex gap-3">
              <LockKeyhole class="size-4" />{{ t('两步验证恢复码') }}
            </CardTitle>
            <CardDescription>
              {{
                t(
                  '如果丢失两步验证设备，恢复码可以让你重新访问账户。请将它们存储在安全的密码管理器中。',
                )
              }}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div
              class="flex flex-col gap-3 select-none sm:flex-row sm:items-center sm:justify-between"
            >
              <Button @click="toggleRecoveryCodesVisibility" class="w-fit">
                <component
                  :is="isRecoveryCodesVisible ? EyeOff : Eye"
                  class="size-4"
                />
                {{ viewHideButtonText }}
              </Button>

              <Form
                v-if="isRecoveryCodesVisible && recoveryCodesList.length"
                :action="regenerateRecoveryCodes.url()"
                method="post"
                :options="{ preserveScroll: true }"
                @success="fetchRecoveryCodes"
                #default="{ processing }"
              >
                <Button
                  variant="secondary"
                  type="submit"
                  :disabled="processing"
                >
                  <RefreshCw /> {{ t('重新生成恢复码') }}
                </Button>
              </Form>
            </div>

            <div
              :class="[
                'relative overflow-hidden transition-all duration-300',
                isRecoveryCodesVisible ? 'h-auto opacity-100' : 'h-0 opacity-0',
              ]"
            >
              <div v-if="uniqueErrors.length" class="mt-6">
                <Alert variant="destructive">
                  <AlertCircle class="size-4" />
                  <AlertTitle>{{ t('发生错误') }}</AlertTitle>
                  <AlertDescription>
                    <ul class="list-inside list-disc text-sm">
                      <li v-for="(error, index) in uniqueErrors" :key="index">
                        {{ error }}
                      </li>
                    </ul>
                  </AlertDescription>
                </Alert>
              </div>
              <div v-else class="mt-3 space-y-3">
                <div
                  ref="recoveryCodeSectionRef"
                  class="grid gap-1 rounded-lg bg-muted p-4 font-mono text-sm"
                >
                  <div v-if="!recoveryCodesList.length" class="space-y-2">
                    <div
                      v-for="n in 8"
                      :key="n"
                      class="h-4 animate-pulse rounded bg-muted-foreground/20"
                    ></div>
                  </div>
                  <div
                    v-else
                    v-for="(rc, index) in recoveryCodesList"
                    :key="index"
                  >
                    {{ rc }}
                  </div>
                </div>
                <p class="text-xs text-muted-foreground select-none">
                  {{
                    t(
                      '每个恢复码只能使用一次来访问你的账户，使用后将被删除。如需更多恢复码，请点击上方的',
                    )
                  }}
                  <span class="font-bold">{{ t('"重新生成恢复码"') }}</span>
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        <div class="relative inline">
          <Form
            :action="disable.url()"
            method="delete"
            #default="{ processing }"
          >
            <Button variant="destructive" type="submit" :disabled="processing">
              <ShieldBan />
              {{ t('禁用两步验证') }}
            </Button>
          </Form>
        </div>
      </div>

      <Dialog :open="showSetupModal" @update:open="showSetupModal = $event">
        <DialogContent class="sm:max-w-md">
          <DialogHeader class="flex items-center justify-center">
            <div
              class="mb-3 w-auto rounded-full border border-border bg-card p-0.5 shadow-sm"
            >
              <div
                class="relative overflow-hidden rounded-full border border-border bg-muted p-2.5"
              >
                <div class="absolute inset-0 grid grid-cols-5 opacity-50">
                  <div
                    v-for="i in 5"
                    :key="`col-${i}`"
                    class="border-r border-border last:border-r-0"
                  />
                </div>
                <div class="absolute inset-0 grid grid-rows-5 opacity-50">
                  <div
                    v-for="i in 5"
                    :key="`row-${i}`"
                    class="border-b border-border last:border-b-0"
                  />
                </div>
                <ScanLine class="relative z-20 size-6 text-foreground" />
              </div>
            </div>
            <DialogTitle>{{ modalConfig.title }}</DialogTitle>
            <DialogDescription class="text-center">
              {{ modalConfig.description }}
            </DialogDescription>
          </DialogHeader>

          <div
            class="relative flex w-auto flex-col items-center justify-center space-y-5"
          >
            <template v-if="!showVerificationStep">
              <Alert v-if="uniqueErrors.length" variant="destructive">
                <AlertCircle class="size-4" />
                <AlertTitle>{{ t('发生错误') }}</AlertTitle>
                <AlertDescription>
                  <ul class="list-inside list-disc text-sm">
                    <li v-for="(error, index) in uniqueErrors" :key="index">
                      {{ error }}
                    </li>
                  </ul>
                </AlertDescription>
              </Alert>
              <template v-else>
                <div
                  class="relative mx-auto flex max-w-md items-center overflow-hidden"
                >
                  <div
                    class="relative mx-auto aspect-square w-64 overflow-hidden rounded-lg border border-border"
                  >
                    <div
                      v-if="!qrCodeSvg"
                      class="absolute inset-0 z-10 flex aspect-square h-auto w-full animate-pulse items-center justify-center bg-background"
                    >
                      <Spinner class="size-6" />
                    </div>
                    <div
                      v-else
                      class="relative z-10 overflow-hidden border p-5"
                    >
                      <div
                        v-html="qrCodeSvg"
                        class="aspect-square w-full justify-center rounded-lg bg-white p-2 [&_svg]:size-full"
                      />
                    </div>
                  </div>
                </div>

                <div class="flex w-full items-center space-x-5">
                  <Button class="w-full" @click="handleModalNextStep">
                    {{ modalConfig.buttonText }}
                  </Button>
                </div>

                <div class="relative flex w-full items-center justify-center">
                  <div class="absolute inset-0 top-1/2 h-px w-full bg-border" />
                  <span class="relative bg-card px-2 py-1">{{
                    t('或者，手动输入密钥')
                  }}</span>
                </div>

                <div class="flex w-full items-center justify-center space-x-2">
                  <div
                    class="flex w-full items-stretch overflow-hidden rounded-xl border border-border"
                  >
                    <div
                      v-if="!manualSetupKey"
                      class="flex h-full w-full items-center justify-center bg-muted p-3"
                    >
                      <Spinner />
                    </div>
                    <template v-else>
                      <input
                        type="text"
                        readonly
                        :value="manualSetupKey"
                        class="h-full w-full bg-background p-3 text-foreground"
                      />
                      <button
                        @click="copy(manualSetupKey || '')"
                        class="relative block h-auto border-l border-border px-3 hover:bg-muted"
                      >
                        <Check v-if="copied" class="w-4 text-foreground" />
                        <Copy v-else class="w-4" />
                      </button>
                    </template>
                  </div>
                </div>
              </template>
            </template>

            <template v-else>
              <Form
                :action="confirm.url()"
                method="post"
                reset-on-error
                @finish="code = ''"
                @success="showSetupModal = false"
                v-slot="{ errors, processing }"
              >
                <input type="hidden" name="code" :value="code" />
                <div
                  ref="pinInputContainerRef"
                  class="relative w-full space-y-3"
                >
                  <div
                    class="flex w-full flex-col items-center justify-center space-y-3 py-2"
                  >
                    <InputOTP
                      id="otp"
                      v-model="code"
                      :maxlength="6"
                      :disabled="processing"
                    >
                      <InputOTPGroup>
                        <InputOTPSlot
                          v-for="index in 6"
                          :key="index"
                          :index="index - 1"
                        />
                      </InputOTPGroup>
                    </InputOTP>
                    <div v-show="errors?.code">
                      <p class="text-sm text-red-600 dark:text-red-500">
                        {{ errors?.code }}
                      </p>
                    </div>
                  </div>

                  <div class="flex w-full items-center space-x-5">
                    <Button
                      type="button"
                      variant="outline"
                      class="w-auto flex-1"
                      @click="showVerificationStep = false"
                      :disabled="processing"
                    >
                      {{ t('返回') }}
                    </Button>
                    <Button
                      type="submit"
                      class="w-auto flex-1"
                      :disabled="processing || code.length < 6"
                    >
                      {{ t('确认') }}
                    </Button>
                  </div>
                </div>
              </Form>
            </template>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  </div>
</template>
