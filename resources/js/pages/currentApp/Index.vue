<!-- 常规设置页，用于修改系统名称、Logo 和注册方式。 -->
<script setup lang="ts">
import UpdateSystemSettingsAction from '@/actions/App/Actions/Manage/UpdateSystemSettingsAction';
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import ImageUploadField from '@/components/common/ImageUploadField.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useI18n } from '@/composables/useI18n';
import { useRequiredSystem } from '@/composables/useSystem';
import AppLayout from '@/layouts/AppLayout.vue';
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const currentApp = useRequiredSystem();
const registrationEnabled = ref(currentApp.value.registration_enabled);

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('设置') },
  { title: t('常规设置') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('常规设置')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <HeadingSmall
          :title="t('常规设置')"
          :description="t('修改系统名称、Logo 和注册方式。')"
        />

        <Form
          :action="UpdateSystemSettingsAction.url()"
          method="put"
          class="space-y-6"
          v-slot="{ errors, processing }"
        >
          <div class="grid gap-2">
            <Label for="name" required>{{ t('系统名称') }}</Label>
            <Input
              id="name"
              name="name"
              class="mt-1 block w-full"
              :default-value="currentApp.name"
              required
            />
            <InputError class="mt-2" :message="errors.name" />
          </div>

          <ImageUploadField
            :label="t('Logo')"
            name="logo_id"
            purpose="avatar"
            :upload-context="{}"
            :initial-preview="currentApp.logo_url || ''"
            :initial-value="currentApp.logo_id || ''"
            variant="logo"
            :error="errors.logo"
            help-text=""
          />

          <div class="grid gap-2">
            <Label for="registration-enabled">{{ t('允许自行注册') }}</Label>
            <input
              type="hidden"
              name="registration_enabled"
              :value="registrationEnabled ? '1' : '0'"
            />
            <Switch
              id="registration-enabled"
              v-model="registrationEnabled"
              :aria-label="t('允许自行注册')"
            />
            <p class="text-sm text-muted-foreground">
              {{ t('开启后，任何人都可以在登录页创建后台账号。') }}
            </p>
          </div>

          <FormActions
            :submit-label="t('保存')"
            :processing="processing"
            submit-data-test="update-app-button"
          />
        </Form>
      </div>
    </div>
  </div>
</template>
