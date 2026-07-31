<!--
  两步验证挑战页面，支持动态验证码、恢复码与切换账号。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { cancel, store } from '@/routes/two-factor/login';
import { Form, Head } from '@inertiajs/vue3';
import { computed, nextTick, ref, useTemplateRef } from 'vue';

const { t } = useI18n();
const code = ref<string>('');
const recoveryCode = ref<string>('');
const showRecoveryInput = ref<boolean>(false);
const activeSubmission = ref<'verify' | 'cancel' | null>(null);
const codeInputContainerRef = useTemplateRef('codeInputContainerRef');
const recoveryInputContainerRef = useTemplateRef('recoveryInputContainerRef');

const isBusy = computed(() => activeSubmission.value !== null);

interface AuthConfigContent {
  title: string;
  description: string;
  toggleText: string;
}

const authConfigContent = computed<AuthConfigContent>(() => {
  if (showRecoveryInput.value) {
    return {
      title: t('恢复码'),
      description: t('请输入你的紧急恢复码之一来确认访问你的账户。'),
      toggleText: t('使用身份验证码登录'),
    };
  }

  return {
    title: t('身份验证码'),
    description: t('输入你的身份验证器应用程序提供的验证码。'),
    toggleText: t('使用恢复码登录'),
  };
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
  if (isBusy.value) {
    return;
  }

  showRecoveryInput.value = !showRecoveryInput.value;
  clearErrors();
  code.value = '';
  recoveryCode.value = '';

  nextTick(() => {
    const container = showRecoveryInput.value
      ? recoveryInputContainerRef.value
      : codeInputContainerRef.value;

    container?.querySelector('input')?.focus();
  });
};

const handleVerificationError = (): void => {
  code.value = '';
  recoveryCode.value = '';

  nextTick(() => {
    const container = showRecoveryInput.value
      ? recoveryInputContainerRef.value
      : codeInputContainerRef.value;

    container?.querySelector('input')?.focus();
  });
};
</script>

<template>
  <AuthLayout
    :title="authConfigContent.title"
    :description="authConfigContent.description"
  >
    <Head :title="t('两步验证')" />

    <div class="space-y-6">
      <template v-if="!showRecoveryInput">
        <Form
          :action="store.url()"
          method="post"
          class="space-y-4"
          reset-on-error
          :aria-busy="activeSubmission === 'verify'"
          @start="activeSubmission = 'verify'"
          @error="handleVerificationError"
          @finish="activeSubmission = null"
          #default="{ errors, processing, clearErrors }"
        >
          <input type="hidden" name="code" :value="code" />
          <div
            ref="codeInputContainerRef"
            class="flex flex-col items-center justify-center space-y-3 text-center"
          >
            <div class="flex w-full items-center justify-center">
              <InputOTP
                id="otp"
                v-model="code"
                :maxlength="6"
                :disabled="processing || isBusy"
                :aria-invalid="Boolean(errors.code)"
                :aria-describedby="errors.code ? 'code-error' : undefined"
                autocomplete="one-time-code"
                autofocus
              >
                <InputOTPGroup>
                  <InputOTPSlot
                    v-for="index in 6"
                    :key="index"
                    :index="index - 1"
                  />
                </InputOTPGroup>
              </InputOTP>
            </div>
            <InputError id="code-error" :message="errors.code" />
            <p
              v-if="errors.code"
              class="text-xs text-muted-foreground"
              aria-live="polite"
            >
              {{ t('验证失败后可以重新输入并重试。') }}
            </p>
          </div>
          <Button
            type="submit"
            class="w-full"
            :disabled="processing || isBusy || code.length < 6"
          >
            <Spinner v-if="processing" class="size-4" />
            {{ processing ? t('正在验证…') : t('继续') }}
          </Button>
          <div class="text-center text-sm text-muted-foreground">
            <span>{{ t('或者你可以') }} </span>
            <button
              type="button"
              class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! disabled:pointer-events-none disabled:opacity-50 dark:decoration-neutral-500"
              :disabled="isBusy"
              @click="() => toggleRecoveryMode(clearErrors)"
            >
              {{ authConfigContent.toggleText }}
            </button>
          </div>
        </Form>
      </template>

      <template v-else>
        <Form
          :action="store.url()"
          method="post"
          class="space-y-4"
          reset-on-error
          :aria-busy="activeSubmission === 'verify'"
          @start="activeSubmission = 'verify'"
          @error="handleVerificationError"
          @finish="activeSubmission = null"
          #default="{ errors, processing, clearErrors }"
        >
          <div ref="recoveryInputContainerRef" class="grid gap-2">
            <Label for="recovery-code" class="sr-only" required>
              {{ t('恢复码') }}
            </Label>
            <Input
              id="recovery-code"
              v-model="recoveryCode"
              name="recovery_code"
              type="text"
              autocomplete="one-time-code"
              autocapitalize="none"
              spellcheck="false"
              :disabled="processing || isBusy"
              :aria-invalid="Boolean(errors.recovery_code)"
              :aria-describedby="
                errors.recovery_code ? 'recovery-code-error' : undefined
              "
              :autofocus="showRecoveryInput"
              required
            />
            <InputError
              id="recovery-code-error"
              :message="errors.recovery_code"
            />
            <p
              v-if="errors.recovery_code"
              class="text-xs text-muted-foreground"
              aria-live="polite"
            >
              {{ t('验证失败后可以重新输入并重试。') }}
            </p>
          </div>
          <Button
            type="submit"
            class="w-full"
            :disabled="processing || isBusy || recoveryCode.trim() === ''"
          >
            <Spinner v-if="processing" class="size-4" />
            {{ processing ? t('正在验证…') : t('继续') }}
          </Button>

          <div class="text-center text-sm text-muted-foreground">
            <span>{{ t('或者你可以') }} </span>
            <button
              type="button"
              class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! disabled:pointer-events-none disabled:opacity-50 dark:decoration-neutral-500"
              :disabled="isBusy"
              @click="() => toggleRecoveryMode(clearErrors)"
            >
              {{ authConfigContent.toggleText }}
            </button>
          </div>
        </Form>
      </template>

      <Form
        :action="cancel.url()"
        method="post"
        class="text-center"
        @start="activeSubmission = 'cancel'"
        @finish="activeSubmission = null"
        #default="{ processing }"
      >
        <Button
          type="submit"
          variant="link"
          class="text-sm text-muted-foreground"
          :disabled="isBusy"
        >
          <Spinner v-if="processing" class="size-4" />
          {{ processing ? t('正在返回登录页…') : t('返回登录并切换账号') }}
        </Button>
      </Form>
    </div>
  </AuthLayout>
</template>
