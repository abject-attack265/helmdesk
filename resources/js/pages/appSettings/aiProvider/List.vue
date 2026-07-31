<!--
  文件说明：应用设置 AI 供应商列表页，消费 ShowAiProviderListPagePropsData。
  表格展示全局供应商（名称 / 品牌 / Base URL），承接新增、编辑与删除。模型在「AI 模型管理」页维护。
-->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type {
  AiProviderData,
  ShowAiProviderListPagePropsData,
} from '@/types/generated';
import { Head, Link, router } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const props = defineProps<ShowAiProviderListPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('AI 供应商'), href: app.manage.aiProviders.index.url() },
  { title: t('列表') },
]);

const deleteTarget = ref<AiProviderData | null>(null);
const isDeleting = ref(false);

const confirmDelete = (): void => {
  if (!deleteTarget.value) {
    return;
  }
  isDeleting.value = true;
  router.delete(app.manage.aiProviders.destroy.url(deleteTarget.value.id), {
    preserveScroll: true,
    onFinish: () => {
      isDeleting.value = false;
      deleteTarget.value = null;
    },
  });
};
</script>

<template>
  <div class="contents">
    <Head :title="t('AI 供应商')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('AI 供应商')"
            :description="t('当前应用的 AI 服务凭据，供本应用模型使用。')"
          />
          <Button as-child>
            <Link :href="app.manage.aiProviders.create.url()">
              {{ t('新增 AI 供应商') }}
            </Link>
          </Button>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('名称') }}</th>
                  <th class="px-4 py-3">{{ t('品牌') }}</th>
                  <th class="px-4 py-3">Base URL</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="p in props.providers"
                  :key="p.id"
                  class="border-t bg-background"
                >
                  <td class="px-4 py-3 font-medium">{{ p.name }}</td>
                  <td class="px-4 py-3">{{ p.brand_label }}</td>
                  <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                    {{ p.base_url ?? '—' }}
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="inline-flex items-center justify-end gap-2">
                      <Button as-child variant="outline" size="sm">
                        <Link :href="app.manage.aiProviders.edit.url(p.id)">
                          {{ t('编辑') }}
                        </Link>
                      </Button>

                      <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                          <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            :aria-label="t('更多操作')"
                          >
                            <MoreHorizontal class="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-36">
                          <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            :disabled="isDeleting"
                            @select="deleteTarget = p"
                          >
                            {{ t('删除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>

                <tr v-if="props.providers.length === 0">
                  <td
                    class="px-4 py-8 text-center text-muted-foreground"
                    colspan="4"
                  >
                    {{ t('暂无 AI 供应商') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <ConfirmDeleteDialog
      :open="deleteTarget !== null"
      :title="t('确认删除该 AI 供应商？')"
      :detail-title="deleteTarget?.name"
      :detail-description="
        t('删除后该供应商及其下所有模型立即移出当前应用取用池，且无法恢复。')
      "
      :processing="isDeleting"
      @confirm="confirmDelete"
      @update:open="(value) => !value && (deleteTarget = null)"
    />
  </div>
</template>
