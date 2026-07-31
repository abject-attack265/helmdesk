<!-- 创建客服账号页，使用 ShowCreateTeammatePagePropsData 填写账号资料和权限。 -->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import ImageUploadField from '@/components/common/ImageUploadField.vue';
import InputError from '@/components/common/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowCreateTeammatePagePropsData } from '@/types/generated';
import { Form, Head } from '@inertiajs/vue3';
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';
import PermissionSelector from './PermissionSelector.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowCreateTeammatePagePropsData>();
const { t } = useI18n();
const passwordVisible = ref(false);
const passwordConfirmationVisible = ref(false);
const selectedPermissions = ref<string[]>([]);
</script>

<template>
  <div class="contents">
    <Head :title="t('创建客服账号')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <HeadingSmall
          :title="t('创建客服账号')"
          :description="
            t(
              props.can_assign_permissions
                ? '填写账号信息，并选择可以使用的功能。'
                : '填写账号信息。',
            )
          "
        />

        <Form
          :action="app.manage.teammates.store.url()"
          method="post"
          class="space-y-6"
          v-slot="{ errors, processing }"
        >
          <div class="grid gap-2">
            <Label for="name" required>{{ t('姓名') }}</Label>
            <Input id="name" name="name" required />
            <InputError :message="errors.name" />
          </div>

          <div class="grid gap-2">
            <Label for="email" required>{{ t('邮箱') }}</Label>
            <Input id="email" name="email" type="email" required />
            <InputError :message="errors.email" />
          </div>

          <div class="grid gap-2">
            <Label for="nickname">{{ t('接待昵称') }}</Label>
            <Input id="nickname" name="nickname" />
            <InputError :message="errors.nickname" />
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

          <ImageUploadField
            :label="t('头像')"
            name="avatar_id"
            purpose="avatar"
            :initial-preview="''"
            :initial-value="''"
            variant="avatar"
            :error="errors.avatar_id"
          />

          <div class="grid gap-2">
            <Label for="password" required>{{ t('登录密码') }}</Label>
            <div class="relative">
              <Input
                id="password"
                name="password"
                :type="passwordVisible ? 'text' : 'password'"
                class="pr-10"
                required
              />
              <button
                type="button"
                class="absolute top-1/2 right-2 -translate-y-1/2"
                @click="passwordVisible = !passwordVisible"
              >
                <EyeOff v-if="passwordVisible" class="h-4 w-4" />
                <Eye v-else class="h-4 w-4" />
              </button>
            </div>
            <InputError :message="errors.password" />
          </div>

          <div class="grid gap-2">
            <Label for="password_confirmation" required>{{
              t('确认密码')
            }}</Label>
            <div class="relative">
              <Input
                id="password_confirmation"
                name="password_confirmation"
                :type="passwordConfirmationVisible ? 'text' : 'password'"
                class="pr-10"
                required
              />
              <button
                type="button"
                class="absolute top-1/2 right-2 -translate-y-1/2"
                @click="
                  passwordConfirmationVisible = !passwordConfirmationVisible
                "
              >
                <EyeOff v-if="passwordConfirmationVisible" class="h-4 w-4" />
                <Eye v-else class="h-4 w-4" />
              </button>
            </div>
            <InputError :message="errors.password_confirmation" />
          </div>

          <FormActions
            :submit-label="t('创建账号')"
            :processing="processing"
            :cancel-href="app.manage.teammates.index.url()"
          />
        </Form>
      </div>
    </div>
  </div>
</template>
