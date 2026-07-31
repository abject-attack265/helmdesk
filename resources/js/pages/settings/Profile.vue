<!--
  文件说明：个人设置页面，消费后端设置数据并提交用户偏好表单。
-->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import { settingsPageLayout } from '@/layouts/pageLayouts';
import { update } from '@/routes/settings/profile';
import { send } from '@/routes/verification';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';

defineOptions({ layout: settingsPageLayout });

interface Props {
  must_verify_email: boolean;
  status?: string;
}

defineProps<Props>();

const { t } = useI18n();
const page = usePage();
const user = page.props.auth.user;
</script>

<template>
  <div class="contents">
    <Head :title="t('个人资料设置')" />

    <div class="flex flex-col space-y-6">
      <HeadingSmall
        :title="t('个人信息')"
        :description="t('更新你的姓名和电子邮件地址')"
      />

      <Form
        :action="update.url()"
        method="patch"
        class="space-y-6"
        v-slot="{ errors, processing }"
      >
        <div class="grid gap-2">
          <Label for="name" required>{{ t('姓名') }}</Label>
          <Input
            id="name"
            class="mt-1 block w-full"
            name="name"
            :default-value="user.name"
            required
            autocomplete="name"
          />
          <InputError class="mt-2" :message="errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="email" required>{{ t('电子邮件地址') }}</Label>
          <Input
            id="email"
            type="email"
            class="mt-1 block w-full"
            name="email"
            :default-value="user.email"
            required
            autocomplete="username"
          />
          <InputError class="mt-2" :message="errors.email" />
        </div>

        <div v-if="must_verify_email && !user.email_verified_at">
          <p class="-mt-4 text-sm text-muted-foreground">
            {{ t('你的电子邮件地址未验证。') }}
            <Link
              :href="send.url()"
              method="post"
              as="button"
              class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
            >
              {{ t('点击这里重新发送验证邮件。') }}
            </Link>
          </p>

          <div
            v-if="status === 'verification-link-sent'"
            class="mt-2 text-sm font-medium text-muted-foreground"
          >
            {{ t('新的验证链接已发送到你的电子邮件地址。') }}
          </div>
        </div>

        <FormActions
          :submit-label="t('保存')"
          :processing="processing"
          submit-data-test="update-profile-button"
        />
      </Form>
    </div>
  </div>
</template>
