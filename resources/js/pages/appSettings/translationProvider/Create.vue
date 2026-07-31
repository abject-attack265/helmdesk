<!--
  文件说明：应用设置「新增翻译供应商」页，消费 ShowCreateTranslationProviderPagePropsData，承接凭据录入。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type { ShowCreateTranslationProviderPagePropsData } from '@/types/generated';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import TranslationProviderForm from './TranslationProviderForm.vue';

defineOptions({ layout: AppLayout });
const { t } = useI18n();
const props = defineProps<ShowCreateTranslationProviderPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('翻译'), href: app.manage.translationProviders.index.url() },
  { title: t('新增翻译供应商') },
]);
</script>

<template>
  <div class="contents">
    <Head :title="t('新增翻译供应商')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <HeadingSmall
            :title="t('新增翻译供应商')"
            :description="t('配置翻译服务凭据；保存后用于收件箱自动翻译。')"
          />

          <TranslationProviderForm
            mode="create"
            :protocol-options="props.protocol_options"
            :protocol-credential-fields="props.protocol_credential_fields"
            :protocol-icons="props.protocol_icons"
          />
        </div>
      </div>
    </div>
  </div>
</template>
