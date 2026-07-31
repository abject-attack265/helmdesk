<!--
  文件说明：创建对象存储配置页面，消费 ShowCreateStorageProfilePagePropsData。
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import storage from '@/routes/app/manage/storage';
import storageProfile from '@/routes/app/manage/storage/profiles';
import type {
  FormCheckStorageSettingData,
  FormCreateStorageProfileData,
  ShowCreateStorageProfilePagePropsData,
} from '@/types/generated';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowCreateStorageProfilePagePropsData>();
const { t } = useI18n();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('设置'), href: app.manage.system.settings.show.url() },
  { title: t('存储设置'), href: storage.index.url() },
  { title: t('新增配置') },
]);

const providerValue = String(props.providers[0]?.provider.value ?? 'generic');
const form = useForm<FormCreateStorageProfileData>({
  name: '',
  provider: providerValue,
  region: '',
  endpoint: '',
  upload_endpoint: null,
  bucket: '',
  key: '',
  secret: '',
  url: null,
});
const checkForm = useForm<FormCheckStorageSettingData>({
  provider: providerValue,
  region: '',
  endpoint: '',
  upload_endpoint: null,
  bucket: '',
  key: '',
  secret: null,
  url: null,
});
const useInternalEndpoint = ref(false);

const currentProvider = computed(() =>
  props.providers.find((item) => String(item.provider.value) === form.provider),
);
const currentRegions = computed(() => currentProvider.value?.regions ?? []);
const currentRegion = computed(() =>
  currentRegions.value.find((item) => item.id === form.region),
);
const hasInternalEndpoint = computed(() =>
  Boolean(currentRegion.value?.internal_endpoint),
);
const customUrl = computed<string>({
  get: () => form.url ?? '',
  set: (value) => {
    form.url = value === '' ? null : value;
  },
});
const errorFor = (field: string): string | undefined =>
  (form.errors as Record<string, string | undefined>)[field] ??
  (checkForm.errors as Record<string, string | undefined>)[field];
const submitting = computed(() => form.processing || checkForm.processing);

watch(
  () => form.provider,
  () => {
    form.region = '';
    form.endpoint = '';
    form.upload_endpoint = null;
    useInternalEndpoint.value = false;
  },
);

watch(
  () => form.region,
  (regionId) => {
    const region = currentRegions.value.find((item) => item.id === regionId);
    if (region) {
      form.endpoint = region.endpoint;
      form.upload_endpoint = null;
      useInternalEndpoint.value = false;
    }
  },
);

const toggleInternalEndpoint = () => {
  if (!currentRegion.value) {
    return;
  }

  useInternalEndpoint.value = !useInternalEndpoint.value;
  form.endpoint = useInternalEndpoint.value
    ? (currentRegion.value.internal_endpoint ?? currentRegion.value.endpoint)
    : currentRegion.value.endpoint;
  form.upload_endpoint = useInternalEndpoint.value
    ? currentRegion.value.endpoint
    : null;
};

const checkConnection = () => {
  checkForm.clearErrors();
  Object.assign(checkForm, {
    provider: form.provider,
    region: form.region,
    endpoint: form.endpoint,
    upload_endpoint: form.upload_endpoint,
    bucket: form.bucket,
    key: form.key,
    secret: form.secret,
    url: form.url,
  });
  checkForm.put(storage.check.url(), { preserveScroll: true });
};

const submit = () => {
  form.post(storageProfile.store.url(), { preserveScroll: true });
};
</script>

<template>
  <div class="contents">
    <Head :title="t('新增存储配置')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />
        <HeadingSmall
          :title="t('新增存储配置')"
          :description="t('填写对象存储连接信息。')"
        />

        <form class="max-w-3xl space-y-6" @submit.prevent="submit">
          <div class="grid gap-2">
            <Label for="name">{{ t('配置名称') }}</Label>
            <Input id="name" v-model="form.name" autocomplete="off" />
            <InputError :message="errorFor('name')" />
          </div>

          <div class="grid gap-2">
            <Label for="provider">{{ t('存储提供商') }}</Label>
            <Select v-model="form.provider">
              <SelectTrigger id="provider"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="item in props.providers"
                  :key="String(item.provider.value)"
                  :value="String(item.provider.value)"
                >
                  {{ item.provider.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p
              v-if="currentProvider?.help_link"
              class="text-xs text-muted-foreground"
            >
              <a
                :href="currentProvider.help_link"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary hover:underline"
              >
                {{
                  t('查看 {provider} 接入文档', {
                    provider: currentProvider.provider.label,
                  })
                }}
              </a>
            </p>
            <InputError :message="errorFor('provider')" />
          </div>

          <div class="grid gap-2">
            <Label for="region">{{ t('区域 (Region)') }}</Label>
            <Select v-model="form.region">
              <SelectTrigger id="region"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="item in currentRegions"
                  :key="item.id"
                  :value="item.id"
                >
                  <span>{{ item.name }}</span
                  ><span class="ml-2 font-mono text-xs text-muted-foreground">{{
                    item.id
                  }}</span>
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="errorFor('region')" />
          </div>

          <div class="grid gap-2">
            <div class="flex items-center justify-between">
              <Label for="endpoint">{{ t('服务端 Endpoint 地址') }}</Label>
              <Button
                v-if="hasInternalEndpoint"
                type="button"
                variant="outline"
                size="sm"
                @click="toggleInternalEndpoint"
              >
                {{
                  useInternalEndpoint
                    ? t('使用外网 Endpoint')
                    : t('使用内网 Endpoint')
                }}
              </Button>
            </div>
            <Input id="endpoint" v-model="form.endpoint" type="url" />
            <p v-if="useInternalEndpoint" class="text-xs text-muted-foreground">
              {{
                t('浏览器直传使用外网 Endpoint：{endpoint}', {
                  endpoint: form.upload_endpoint ?? '',
                })
              }}
            </p>
            <InputError :message="errorFor('endpoint')" />
          </div>

          <div class="grid gap-2">
            <Label for="bucket">{{ t('Bucket 名称') }}</Label>
            <Input id="bucket" v-model="form.bucket" />
            <InputError :message="errorFor('bucket')" />
          </div>

          <div class="grid gap-2">
            <Label for="key">{{ t('Access Key / Access Key ID') }}</Label>
            <Input id="key" v-model="form.key" autocomplete="off" />
            <InputError :message="errorFor('key')" />
          </div>

          <div class="grid gap-2">
            <Label for="secret">{{
              t('Secret Key / Access Key Secret')
            }}</Label>
            <Input
              id="secret"
              v-model="form.secret"
              type="password"
              autocomplete="off"
            />
            <InputError :message="errorFor('secret')" />
          </div>

          <div class="grid gap-2">
            <Label for="url">{{ t('自定义域名 (可选)') }}</Label>
            <Input id="url" v-model="customUrl" type="url" />
            <InputError :message="errorFor('url')" />
          </div>

          <FormActions
            :submit-label="t('创建')"
            :processing="submitting"
            :cancel-href="storage.index.url()"
          >
            <template #submit>
              <LoaderCircle
                v-if="form.processing"
                class="mr-2 h-4 w-4 animate-spin"
              />
              {{ form.processing ? t('创建中...') : t('创建') }}
            </template>
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
