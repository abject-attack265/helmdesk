<!-- 编辑客服账号页，使用 ShowEditTeammatePagePropsData 修改账号资料和权限。 -->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import ImageUploadField from '@/components/common/ImageUploadField.vue';
import InputError from '@/components/common/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { availableLocales } from '@/locales';
import app from '@/routes/app';
import type { ShowEditTeammatePagePropsData } from '@/types/generated';
import { Form, Head } from '@inertiajs/vue3';
import { Eye, EyeOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import PermissionSelector from './PermissionSelector.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowEditTeammatePagePropsData>();
const { t } = useI18n();
const passwordVisible = ref(false);
const passwordConfirmationVisible = ref(false);
const selectedLocale = ref(props.user_form.locale);
const selectedPermissions = ref(
  props.user_form.permissions.map((permission) => String(permission)),
);
const currentLocaleLabel = computed(
  () =>
    availableLocales.find((item) => item.value === selectedLocale.value)
      ?.label ?? '',
);
</script>

<template>
  <div class="contents">
    <Head :title="t('编辑客服')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <HeadingSmall
          :title="t('编辑客服')"
          :description="
            t(
              props.can_assign_permissions
                ? '修改账号信息和权限'
                : '修改账号信息',
            )
          "
        />

        <Form
          :action="app.manage.teammates.update.url({ id: props.user_form.id })"
          method="put"
          class="space-y-6"
          v-slot="{ errors, processing }"
        >
          <div class="grid gap-2">
            <Label for="name" required>{{ t('姓名') }}</Label>
            <Input
              id="name"
              name="name"
              required
              :default-value="props.user_form.name"
              :disabled="!props.can_update_profile"
            />
            <input
              v-if="!props.can_update_profile"
              type="hidden"
              name="name"
              :value="props.user_form.name"
            />
            <InputError :message="errors.name" />
          </div>

          <div class="grid gap-2">
            <Label for="email" required>{{ t('邮箱') }}</Label>
            <Input
              id="email"
              name="email"
              type="email"
              required
              :default-value="props.user_form.email"
              :disabled="!props.can_update_credentials"
            />
            <input
              v-if="!props.can_update_credentials"
              type="hidden"
              name="email"
              :value="props.user_form.email"
            />
            <InputError :message="errors.email" />
          </div>

          <div class="grid gap-2">
            <Label for="nickname">{{ t('接待昵称') }}</Label>
            <Input
              id="nickname"
              name="nickname"
              :default-value="props.user_form.nickname || ''"
              :disabled="!props.can_update_profile"
            />
            <input
              v-if="!props.can_update_profile"
              type="hidden"
              name="nickname"
              :value="props.user_form.nickname || ''"
            />
            <InputError :message="errors.nickname" />
          </div>

          <div class="grid gap-2">
            <Label>{{ t('权限') }}</Label>
            <template v-if="props.can_assign_permissions">
              <input
                v-for="permission in selectedPermissions"
                :key="permission"
                type="hidden"
                name="permissions[]"
                :value="permission"
              />
            </template>
            <PermissionSelector
              v-model="selectedPermissions"
              :groups="props.permission_groups"
              :disabled="
                !props.can_update_profile || !props.can_assign_permissions
              "
            />
            <InputError :message="errors.permissions" />
          </div>

          <ImageUploadField
            v-if="props.can_update_profile"
            :label="t('头像')"
            name="avatar_id"
            purpose="avatar"
            :initial-preview="props.user_form.avatar || ''"
            :initial-value="''"
            variant="avatar"
            :error="errors.avatar_id"
          />
          <input v-else type="hidden" name="avatar_id" value="" />

          <div class="grid gap-2">
            <Label for="locale" required>{{ t('默认语言') }}</Label>
            <input type="hidden" name="locale" :value="selectedLocale" />
            <Select
              v-model="selectedLocale"
              :disabled="!props.can_update_profile"
            >
              <SelectTrigger id="locale" class="w-full">
                <SelectValue>{{ currentLocaleLabel }}</SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in availableLocales"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="errors.locale" />
          </div>

          <template v-if="props.can_update_credentials">
            <div class="grid gap-2">
              <Label for="password">{{ t('登录密码') }}</Label>
              <div class="relative">
                <Input
                  id="password"
                  name="password"
                  :type="passwordVisible ? 'text' : 'password'"
                  class="pr-10"
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
              <Label for="password_confirmation">{{ t('确认密码') }}</Label>
              <div class="relative">
                <Input
                  id="password_confirmation"
                  name="password_confirmation"
                  :type="passwordConfirmationVisible ? 'text' : 'password'"
                  class="pr-10"
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
          </template>
          <template v-else>
            <input type="hidden" name="password" value="" />
            <input type="hidden" name="password_confirmation" value="" />
          </template>

          <FormActions
            :submit-label="t('保存')"
            :processing="processing"
            :submit-disabled="processing || !props.can_update_profile"
            :cancel-href="app.manage.teammates.index.url()"
          />
        </Form>
      </div>
    </div>
  </div>
</template>
