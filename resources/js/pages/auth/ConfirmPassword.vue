<!--
  文件说明：认证页面，承接 Fortify 登录、注册、重置密码和邮箱验证流程。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/vue3';

const { t } = useI18n();
</script>

<template>
  <AuthLayout
    :title="t('确认你的密码')"
    :description="t('这是应用程序的安全区域。请在继续之前确认你的密码。')"
  >
    <Head :title="t('确认密码页面')" />

    <Form
      :action="store.url()"
      method="post"
      reset-on-success
      v-slot="{ errors, processing }"
    >
      <div class="space-y-6">
        <div class="grid gap-2">
          <Label htmlFor="password" required>{{ t('密码') }}</Label>
          <Input
            id="password"
            type="password"
            name="password"
            class="mt-1 block w-full"
            required
            autocomplete="current-password"
            autofocus
          />

          <InputError :message="errors.password" />
        </div>

        <div class="flex items-center">
          <Button
            class="w-full"
            :disabled="processing"
            data-test="confirm-password-button"
          >
            <Spinner v-if="processing" />
            {{ t('确认密码') }}
          </Button>
        </div>
      </div>
    </Form>
  </AuthLayout>
</template>
