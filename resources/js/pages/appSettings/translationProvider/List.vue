<!--
  文件说明：应用设置翻译供应商列表页，消费 ShowTranslationProviderListPagePropsData；
  表格展示系统供应商（协议 / 启用开关），承接新增、编辑、内联启停与删除。
-->
<script setup lang="ts">
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import PaginationNavigator from '@/components/common/PaginationNavigator.vue';
import AiProviderIcon from '@/components/icons/AiProviderIcon.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type {
  ShowTranslationProviderListPagePropsData,
  TranslationProviderData,
} from '@/types/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

defineOptions({ layout: AppLayout });
const { t } = useI18n();
const props = defineProps<ShowTranslationProviderListPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('翻译'), href: app.manage.translationProviders.index.url() },
  { title: t('列表') },
]);

const buildListPageUrl = (page: number): string =>
  app.manage.translationProviders.index.url({ query: { page } });

const toggleActive = (
  provider: TranslationProviderData,
  value: boolean,
): void => {
  router.put(
    app.manage.translationProviders.toggleActive.url(provider.id),
    { is_active: value },
    { preserveScroll: true },
  );
};

const destroy = (provider: TranslationProviderData): void => {
  router.delete(app.manage.translationProviders.destroy.url(provider.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <div class="contents">
    <Head :title="t('翻译供应商')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-12">
        <div class="space-y-6">
          <PageBreadcrumb :items="breadcrumbItems" />

          <div class="flex items-start justify-between gap-4">
            <HeadingSmall
              :title="t('翻译供应商')"
              :description="
                t(
                  '统一管理机器翻译与 AI 翻译凭据；系统按渠道策略优先选择，失败时自动轮询。',
                )
              "
            />

            <Button as-child>
              <Link :href="app.manage.translationProviders.create.url()">
                {{ t('新增翻译供应商') }}
              </Link>
            </Button>
          </div>

          <div class="rounded-lg border">
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="border-b bg-muted/30 text-muted-foreground">
                  <tr class="text-left">
                    <th class="px-4 py-3">{{ t('名称') }}</th>
                    <th class="px-4 py-3">{{ t('协议') }}</th>
                    <th class="px-4 py-3">{{ t('启用') }}</th>
                    <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="p in props.provider_list"
                    :key="p.id"
                    class="border-t bg-background"
                  >
                    <td class="px-4 py-3 font-medium">{{ p.name }}</td>
                    <td class="px-4 py-3">
                      <div class="flex items-center gap-2">
                        <AiProviderIcon
                          :icon="p.icon"
                          class="h-4 w-4 shrink-0"
                        />
                        <span>{{ p.protocol_label }}</span>
                      </div>
                    </td>
                    <td class="px-4 py-3">
                      <Switch
                        :model-value="p.is_active"
                        :aria-label="p.is_active ? t('已启用') : t('未启用')"
                        @update:model-value="(value) => toggleActive(p, value)"
                      />
                    </td>
                    <td class="px-4 py-3 text-right">
                      <div class="inline-flex items-center gap-2">
                        <Button as-child variant="outline" size="sm">
                          <Link
                            :href="
                              app.manage.translationProviders.edit.url(p.id)
                            "
                          >
                            {{ t('编辑') }}
                          </Link>
                        </Button>

                        <Dialog>
                          <DialogTrigger as-child>
                            <Button variant="outline" size="sm">
                              {{ t('删除') }}
                            </Button>
                          </DialogTrigger>
                          <DialogContent>
                            <DialogHeader class="space-y-3">
                              <DialogTitle>{{
                                t('确认删除该翻译供应商？')
                              }}</DialogTitle>
                              <DialogDescription>
                                {{
                                  t(
                                    '删除后该供应商立即移出翻译轮询池，且无法恢复。',
                                  )
                                }}
                              </DialogDescription>
                            </DialogHeader>

                            <div class="rounded-md bg-muted/30 p-3 text-sm">
                              <div class="font-medium">{{ p.name }}</div>
                              <div class="text-muted-foreground">
                                {{ p.protocol_label }}
                              </div>
                            </div>

                            <DialogFooter class="gap-2">
                              <DialogClose as-child>
                                <Button variant="secondary">
                                  {{ t('取消') }}
                                </Button>
                              </DialogClose>
                              <DialogClose as-child>
                                <Button
                                  variant="destructive"
                                  @click="destroy(p)"
                                >
                                  {{ t('确认删除') }}
                                </Button>
                              </DialogClose>
                            </DialogFooter>
                          </DialogContent>
                        </Dialog>
                      </div>
                    </td>
                  </tr>

                  <tr v-if="props.provider_list.length === 0">
                    <td
                      class="px-4 py-8 text-center text-muted-foreground"
                      colspan="4"
                    >
                      {{ t('暂无翻译供应商') }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div
              v-if="props.provider_list_pagination.last_page > 1"
              class="border-t p-4"
            >
              <PaginationNavigator
                :pagination="props.provider_list_pagination"
                :page-url="buildListPageUrl"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
