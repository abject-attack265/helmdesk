<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/invitations/accept';
import type { ShowAcceptInvitationPagePropsData } from '@/types/generated';
import { Form, Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
  invitation?: ShowAcceptInvitationPagePropsData;
  invalid?: boolean;
}>();

const { t } = useI18n();
</script>

<template>
  <AuthLayout
    :title="t('接受邀请')"
    :description="
      invalid
        ? t('邀请链接无效或已过期')
        : t('设置你的密码即可加入应用「{app}」', {
            app: props.invitation?.app_name ?? '',
          })
    "
  >
    <Head :title="t('接受邀请')" />

    <div v-if="invalid || !invitation" class="grid gap-6 text-center">
      <p class="text-sm text-muted-foreground">
        {{ t('该邀请链接无效或已过期，请联系邀请你的成员重新发送。') }}
      </p>
      <Button as-child variant="outline" class="w-full">
        <Link :href="login.url()">{{ t('前往登录') }}</Link>
      </Button>
    </div>

    <Form
      v-else
      :action="store.url(invitation.token)"
      method="post"
      :reset-on-success="['password', 'password_confirmation']"
      v-slot="{ errors, processing }"
    >
      <div class="grid gap-6">
        <div class="grid gap-2">
          <Label for="email">{{ t('电子邮件') }}</Label>
          <Input
            id="email"
            type="email"
            :model-value="invitation.email"
            class="mt-1 block w-full"
            readonly
          />
        </div>

        <div class="grid gap-2">
          <Label for="name" required>{{ t('姓名') }}</Label>
          <Input
            id="name"
            type="text"
            name="name"
            autocomplete="name"
            class="mt-1 block w-full"
            required
            autofocus
          />
          <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="password" required>{{ t('密码') }}</Label>
          <Input
            id="password"
            type="password"
            name="password"
            autocomplete="new-password"
            class="mt-1 block w-full"
            required
          />
          <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
          <Label for="password_confirmation" required>
            {{ t('确认密码') }}
          </Label>
          <Input
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            autocomplete="new-password"
            class="mt-1 block w-full"
            required
          />
          <InputError :message="errors.password_confirmation" />
        </div>

        <Button type="submit" class="mt-4 w-full" :disabled="processing">
          <Spinner v-if="processing" />
          {{ t('接受邀请并加入') }}
        </Button>
      </div>
    </Form>
  </AuthLayout>
</template>
