<!-- 邀请客服页，使用 ShowInviteTeammatePagePropsData 发送加入系统的邀请。 -->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import appRoutes from '@/routes/app';
import type { ShowInviteTeammatePagePropsData } from '@/types/generated';
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PermissionSelector from './PermissionSelector.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowInviteTeammatePagePropsData>();
const { t } = useI18n();
const selectedPermissions = ref<string[]>([]);

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('客服'), href: appRoutes.manage.teammates.index.url() },
  { title: t('邀请新客服') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('邀请新客服')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <HeadingSmall
            :title="t('邀请新客服')"
            :description="
              t(
                '输入对方邮箱，我们会发送邀请邮件。对方设置密码后即可加入系统。',
              )
            "
          />

          <Form
            :action="appRoutes.manage.teammates.invitations.store.url()"
            method="post"
            class="space-y-6"
            v-slot="{ errors, processing }"
          >
            <div class="grid gap-2">
              <Label for="email" required>{{ t('邮箱') }}</Label>
              <Input
                id="email"
                name="email"
                type="email"
                class="mt-1 block w-full"
                required
              />
              <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="grid gap-2">
              <Label for="nickname">{{ t('接待昵称') }}</Label>
              <Input id="nickname" name="nickname" class="mt-1 block w-full" />
              <InputError class="mt-2" :message="errors.nickname" />
            </div>

            <div v-if="props.can_assign_permissions" class="grid gap-2">
              <Label>{{ t('权限') }}</Label>
              <input
                v-for="permission in selectedPermissions"
                :key="permission"
                type="hidden"
                name="permissions[]"
                :value="permission"
              />
              <PermissionSelector
                v-model="selectedPermissions"
                :groups="props.permission_groups"
              />
              <InputError :message="errors.permissions" />
            </div>

            <FormActions
              :submit-label="t('发送邀请')"
              :processing="processing"
              :cancel-href="appRoutes.manage.teammates.index.url()"
            />
          </Form>
        </div>
      </div>
    </div>
  </div>
</template>
