<!--
  忘记密码页面，提交邮箱并展示统一的重置链接受理结果。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
  status?: string;
}>();

const { t } = useI18n();
</script>

<template>
  <AuthLayout
    :title="t('忘记密码')"
    :description="t('输入你的电子邮件以接收密码重置链接')"
  >
    <Head :title="t('忘记密码')" />

    <div
      v-if="status"
      class="mb-4 text-center text-sm font-medium text-muted-foreground"
      role="status"
      aria-live="polite"
      aria-atomic="true"
    >
      {{ status }}
    </div>

    <div class="space-y-6">
      <Form
        :action="email.url()"
        method="post"
        reset-on-success
        v-slot="{ errors, processing }"
      >
        <div class="grid gap-2">
          <Label for="email" required>{{ t('电子邮件地址') }}</Label>
          <Input
            id="email"
            type="email"
            name="email"
            autocomplete="email"
            :aria-invalid="Boolean(errors.email)"
            :aria-describedby="errors.email ? 'email-error' : undefined"
            autofocus
            required
          />
          <InputError id="email-error" :message="errors.email" />
        </div>

        <div class="my-6 flex items-center justify-start">
          <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="email-password-reset-link-button"
          >
            <Spinner v-if="processing" />
            {{ processing ? t('正在发送重置链接…') : t('发送密码重置链接') }}
          </Button>
        </div>
      </Form>

      <div class="space-x-1 text-center text-sm text-muted-foreground">
        <span>{{ t('或者，返回') }}</span>
        <TextLink :href="login()">{{ t('登录') }}</TextLink>
      </div>
    </div>
  </AuthLayout>
</template>
