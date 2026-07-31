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
import { update } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
  token: string;
  email: string;
}>();

const inputEmail = ref(props.email);
const { t } = useI18n();
</script>

<template>
  <AuthLayout :title="t('重置密码')" :description="t('请在下方输入你的新密码')">
    <Head :title="t('重置密码')" />

    <Form
      :action="update.url()"
      method="post"
      :transform="(data) => ({ ...data, token, email })"
      :reset-on-success="['password', 'password_confirmation']"
      v-slot="{ errors, processing }"
    >
      <div class="grid gap-6">
        <div class="grid gap-2">
          <Label for="email">{{ t('电子邮件') }}</Label>
          <Input
            id="email"
            type="email"
            name="email"
            autocomplete="email"
            v-model="inputEmail"
            class="mt-1 block w-full"
            readonly
          />
          <InputError :message="errors.email" class="mt-2" />
        </div>

        <div class="grid gap-2">
          <Label for="password">{{ t('密码') }}</Label>
          <Input
            id="password"
            type="password"
            name="password"
            autocomplete="new-password"
            class="mt-1 block w-full"
            autofocus
          />
          <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
          <Label for="password_confirmation">
            {{ t('确认密码') }}
          </Label>
          <Input
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            autocomplete="new-password"
            class="mt-1 block w-full"
          />
          <InputError :message="errors.password_confirmation" />
        </div>

        <Button
          type="submit"
          class="mt-4 w-full"
          :disabled="processing"
          data-test="reset-password-button"
        >
          <Spinner v-if="processing" />
          {{ t('重置密码') }}
        </Button>
      </div>
    </Form>
  </AuthLayout>
</template>
