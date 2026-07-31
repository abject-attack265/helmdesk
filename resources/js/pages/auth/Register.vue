<!--
  注册页面，提交账号、语言和时区信息以加入当前单租户实例。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import SecretInput from '@/components/common/SecretInput.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import { useTimezone } from '@/composables/useTimezone';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';

const { locale, t } = useI18n();
const { timezone } = useTimezone();
</script>

<template>
  <AuthBase
    :title="t('创建账户')"
    :description="t('在下方输入你的详细信息以创建账户')"
  >
    <Head :title="t('注册')" />

    <Form
      :action="store.url()"
      method="post"
      :reset-on-success="['password', 'password_confirmation']"
      v-slot="{ errors, processing }"
      class="flex flex-col gap-6"
    >
      <input type="hidden" name="locale" :value="locale" />
      <input type="hidden" name="timezone" :value="timezone" />

      <div class="grid gap-6">
        <div class="grid gap-2">
          <Label for="name" required>{{ t('姓名') }}</Label>
          <Input
            id="name"
            type="text"
            required
            autofocus
            autocomplete="name"
            name="name"
            :aria-invalid="Boolean(errors.name)"
            :aria-describedby="errors.name ? 'name-error' : undefined"
          />
          <InputError id="name-error" :message="errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="email" required>{{ t('电子邮件地址') }}</Label>
          <Input
            id="email"
            type="email"
            required
            autocomplete="email"
            name="email"
            :aria-invalid="Boolean(errors.email)"
            :aria-describedby="errors.email ? 'email-error' : undefined"
          />
          <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="grid gap-2">
          <Label for="password" required>{{ t('密码') }}</Label>
          <SecretInput
            id="password"
            required
            autocomplete="new-password"
            name="password"
            :aria-invalid="Boolean(errors.password)"
            :aria-describedby="errors.password ? 'password-error' : undefined"
          />
          <InputError id="password-error" :message="errors.password" />
        </div>

        <div class="grid gap-2">
          <Label for="password_confirmation" required>{{
            t('确认密码')
          }}</Label>
          <SecretInput
            id="password_confirmation"
            required
            autocomplete="new-password"
            name="password_confirmation"
            :aria-invalid="Boolean(errors.password_confirmation)"
            :aria-describedby="
              errors.password_confirmation
                ? 'password-confirmation-error'
                : undefined
            "
          />
          <InputError
            id="password-confirmation-error"
            :message="errors.password_confirmation"
          />
        </div>

        <Button
          type="submit"
          class="mt-2 w-full"
          :disabled="processing"
          data-test="register-user-button"
        >
          <Spinner v-if="processing" />
          {{ t('创建账户') }}
        </Button>
      </div>

      <div class="text-center text-sm text-muted-foreground">
        {{ t('已有账户？') }}
        <TextLink :href="login()" class="underline underline-offset-4">{{
          t('登录')
        }}</TextLink>
      </div>
    </Form>
  </AuthBase>
</template>
