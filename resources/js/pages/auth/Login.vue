<!--
  登录页面，提交账号凭据并展示认证错误与辅助入口。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import SecretInput from '@/components/common/SecretInput.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { nextTick } from 'vue';

defineProps<{
  status?: string;
  canResetPassword: boolean;
  canRegister: boolean;
}>();

const { t } = useI18n();

const focusFirstError = async (
  errors: Record<string, string>,
): Promise<void> => {
  await nextTick();

  const firstInvalidField = ['email', 'password'].find(
    (field) => errors[field],
  );

  if (firstInvalidField) {
    document.getElementById(firstInvalidField)?.focus();
  }
};
</script>

<template>
  <AuthBase
    :title="t('登录你的账户')"
    :description="t('在下方输入你的邮箱和密码以登录')"
  >
    <Head :title="t('登录')" />

    <div
      v-if="status"
      role="status"
      aria-live="polite"
      aria-atomic="true"
      class="mb-4 text-center text-sm font-medium text-muted-foreground"
    >
      {{ status }}
    </div>

    <Form
      :action="store.url()"
      method="post"
      :reset-on-success="['password']"
      @error="focusFirstError"
      v-slot="{ errors, processing }"
      class="flex flex-col gap-6"
    >
      <div class="grid gap-6">
        <div class="grid gap-2">
          <Label for="email" required>{{ t('电子邮件地址') }}</Label>
          <Input
            id="email"
            type="email"
            name="email"
            required
            autofocus
            autocomplete="email"
            :aria-invalid="Boolean(errors.email)"
            :aria-describedby="errors.email ? 'email-error' : undefined"
          />
          <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2">
          <Label for="password" required class="col-start-1 row-start-1">
            {{ t('密码') }}
          </Label>
          <SecretInput
            id="password"
            name="password"
            required
            autocomplete="current-password"
            class="col-span-2 col-start-1 row-start-2"
            :aria-invalid="Boolean(errors.password)"
            :aria-describedby="errors.password ? 'password-error' : undefined"
          />
          <TextLink
            v-if="canResetPassword"
            :href="request()"
            class="col-start-2 row-start-1 self-center text-sm"
          >
            {{ t('忘记密码？') }}
          </TextLink>
          <InputError
            id="password-error"
            class="col-span-2 col-start-1 row-start-3"
            :message="errors.password"
          />
        </div>

        <div class="flex items-center justify-between">
          <Label for="remember" class="flex items-center space-x-3">
            <Checkbox id="remember" name="remember" />
            <span>{{ t('记住我') }}</span>
          </Label>
        </div>

        <Button
          type="submit"
          class="mt-4 w-full"
          :disabled="processing"
          data-test="login-button"
        >
          <Spinner v-if="processing" />
          {{ t('登录') }}
        </Button>
      </div>

      <div class="text-center text-sm text-muted-foreground" v-if="canRegister">
        {{ t('没有账户？') }}
        <TextLink :href="register()">{{ t('注册') }}</TextLink>
      </div>
    </Form>
  </AuthBase>
</template>
