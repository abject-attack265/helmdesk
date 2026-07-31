<!--
  文件说明：认证页面，承接 Fortify 登录、注册、重置密码和邮箱验证流程。
-->
<script setup lang="ts">
import TextLink from '@/components/common/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/composables/useI18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import logout from '@/routes/logout';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
  status?: string;
}>();

const { t } = useI18n();
</script>

<template>
  <AuthLayout
    :title="t('验证电子邮件')"
    :description="
      t('请点击我们刚刚发送给你的电子邮件中的链接来验证你的电子邮件地址。')
    "
  >
    <Head :title="t('邮箱验证')" />

    <div
      v-if="status === 'verification-link-sent'"
      class="mb-4 text-center text-sm font-medium text-muted-foreground"
    >
      {{ t('新的验证链接已发送到你注册时提供的电子邮件地址。') }}
    </div>

    <Form
      :action="send.url()"
      method="post"
      class="space-y-6 text-center"
      v-slot="{ processing }"
    >
      <Button :disabled="processing" variant="secondary">
        <Spinner v-if="processing" />
        {{ t('重新发送验证邮件') }}
      </Button>

      <TextLink :href="logout.web()" as="button" class="mx-auto block text-sm">
        {{ t('退出登录') }}
      </TextLink>
    </Form>
  </AuthLayout>
</template>
