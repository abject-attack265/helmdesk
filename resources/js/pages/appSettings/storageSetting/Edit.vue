<!--
  文件说明：编辑对象存储配置页面，消费 ShowEditStorageProfilePagePropsData。
-->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import storage from '@/routes/app/manage/storage';
import storageProfile from '@/routes/app/manage/storage/profiles';
import type {
  FormCheckStorageSettingData,
  FormUpdateStorageProfileData,
  ShowEditStorageProfilePagePropsData,
} from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowEditStorageProfilePagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('设置'), href: app.manage.system.settings.show.url() },
  { title: t('存储设置'), href: storage.index.url() },
  { title: t('编辑存储配置') },
]);

const form = useForm<FormUpdateStorageProfileData>({
  name: props.profile.name,
  url: props.profile.url,
  key: null,
  secret: null,
});
const checkForm = useForm<FormCheckStorageSettingData>({
  provider: props.profile.provider?.value?.toString() ?? '',
  region: props.profile.region ?? '',
  endpoint: props.profile.endpoint ?? '',
  upload_endpoint: props.profile.upload_endpoint ?? null,
  bucket: props.profile.bucket ?? '',
  key: '',
  secret: null,
  url: props.profile.url,
});

const currentProvider = computed(() =>
  props.providers.find(
    (item) =>
      String(item.provider.value) === String(props.profile.provider?.value),
  ),
);
const currentRegion = computed(() =>
  currentProvider.value?.regions.find(
    (item) => item.id === props.profile.region,
  ),
);
const providerLabel = computed(
  () => currentProvider.value?.provider.label ?? t('S3 兼容存储'),
);
const regionLabel = computed(() =>
  currentRegion.value
    ? `${currentRegion.value.name} (${currentRegion.value.id})`
    : (props.profile.region ?? ''),
);
const editUrl = computed<string>({
  get: () => form.url ?? '',
  set: (value) => {
    form.url = value === '' ? null : value;
  },
});
const editKey = computed<string>({
  get: () => form.key ?? '',
  set: (value) => {
    form.key = value === '' ? null : value;
  },
});
const editSecret = computed<string>({
  get: () => form.secret ?? '',
  set: (value) => {
    form.secret = value === '' ? null : value;
  },
});
const errorFor = (field: string): string | undefined =>
  (form.errors as Record<string, string | undefined>)[field] ??
  (checkForm.errors as Record<string, string | undefined>)[field];
const submitting = computed(() => form.processing || checkForm.processing);

const checkConnection = () => {
  checkForm.clearErrors();

  if (!form.key && !form.secret) {
    checkForm.put(storageProfile.check.url(props.profile.id), {
      preserveScroll: true,
    });
    return;
  }

  Object.assign(checkForm, {
    provider: props.profile.provider?.value?.toString() ?? '',
    region: props.profile.region ?? '',
    endpoint: props.profile.endpoint ?? '',
    upload_endpoint: props.profile.upload_endpoint ?? null,
    bucket: props.profile.bucket ?? '',
    key: form.key ?? '',
    secret: form.secret,
    url: form.url,
  });
  checkForm.put(storage.check.url(), { preserveScroll: true });
};

const submit = () => {
  form.put(storageProfile.update.url(props.profile.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <div class="contents">
    <Head :title="t('编辑存储配置')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />
        <HeadingSmall
          :title="t('编辑：{name}', { name: props.profile.name })"
          :description="t('更新配置名称、访问凭据和自定义域名。')"
        />

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
          <div class="grid gap-2">
            <Label for="name">{{ t('配置名称') }}</Label>
            <Input id="name" v-model="form.name" autocomplete="off" />
            <InputError :message="errorFor('name')" />
          </div>
          <div class="grid gap-2">
            <Label for="provider">{{ t('存储提供商') }}</Label>
            <Input
              id="provider"
              :model-value="providerLabel"
              readonly
              class="bg-muted/40 text-muted-foreground"
            />
          </div>
          <div class="grid gap-2">
            <Label for="region">{{ t('区域 (Region)') }}</Label>
            <Input
              id="region"
              :model-value="regionLabel"
              readonly
              class="bg-muted/40 text-muted-foreground"
            />
          </div>
          <div class="grid gap-2">
            <Label for="endpoint">{{ t('服务端 Endpoint 地址') }}</Label>
            <Input
              id="endpoint"
              :model-value="props.profile.endpoint ?? ''"
              readonly
              class="bg-muted/40 text-muted-foreground"
            />
          </div>
          <div class="grid gap-2">
            <Label for="upload_endpoint">{{
              t('浏览器上传 Endpoint 地址')
            }}</Label>
            <Input
              id="upload_endpoint"
              :model-value="
                props.profile.upload_endpoint ?? props.profile.endpoint ?? ''
              "
              readonly
              class="bg-muted/40 text-muted-foreground"
            />
          </div>
          <div class="grid gap-2">
            <Label for="bucket">{{ t('Bucket 名称') }}</Label>
            <Input
              id="bucket"
              :model-value="props.profile.bucket ?? ''"
              readonly
              class="bg-muted/40 text-muted-foreground"
            />
          </div>
          <div class="grid gap-2">
            <Label for="key">{{ t('Access Key / Access Key ID') }}</Label>
            <Input
              id="key"
              v-model="editKey"
              autocomplete="off"
              :placeholder="props.profile.key_masked ?? ''"
            />
            <InputError :message="errorFor('key')" />
          </div>
          <div class="grid gap-2">
            <Label for="secret">{{
              t('Secret Key / Access Key Secret')
            }}</Label>
            <Input
              id="secret"
              v-model="editSecret"
              type="password"
              autocomplete="off"
            />
            <InputError :message="errorFor('secret')" />
          </div>
          <div class="grid gap-2">
            <Label for="url">{{ t('自定义域名 (可选)') }}</Label>
            <Input id="url" v-model="editUrl" type="url" />
            <InputError :message="errorFor('url')" />
          </div>

          <FormActions
            :submit-label="t('保存')"
            :processing="submitting"
            :cancel-href="storage.index.url()"
          >
            <Button
              type="button"
              variant="outline"
              :disabled="submitting"
              @click="checkConnection"
            >
              <LoaderCircle
                v-if="checkForm.processing"
                class="mr-2 h-4 w-4 animate-spin"
              />
              {{ checkForm.processing ? t('检测中...') : t('检测连接') }}
            </Button>
          </FormActions>
        </form>
      </div>
    </div>
  </div>
</template>
