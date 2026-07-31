<!--
  文件说明：应用设置「AI 模型管理」列表页，消费 ShowAiModelListPagePropsData。
  按用途分 Tab 展示模型、加权权重、媒体输入能力和启用状态。
-->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Switch } from '@/components/ui/switch';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type {
  AiModelListItemData,
  ShowAiModelListPagePropsData,
} from '@/types/generated';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { MoreHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowAiModelListPagePropsData>();
const { t } = useI18n();
const page = usePage();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  { title: t('AI 模型'), href: app.manage.aiModels.index.url() },
  { title: t('列表') },
]);

const tabValues = computed<string[]>(() =>
  props.purpose_tabs.map((tab) => String(tab.value)),
);

// 选中用途以 URL ?purpose= 为准（刷新/前进后退保持），非法值回落到首个 Tab。
const activePurpose = computed<string>(() => {
  const query = page.url.split('?')[1] ?? '';
  const purpose = new URLSearchParams(query).get('purpose') ?? '';
  return tabValues.value.includes(purpose)
    ? purpose
    : (tabValues.value[0] ?? 'reception_chat');
});

const selectPurpose = (value: string): void => {
  if (value === activePurpose.value) {
    return;
  }
  router.get(
    app.manage.aiModels.index.url(),
    { purpose: value },
    { preserveState: true, preserveScroll: true, replace: true },
  );
};

const modelsForPurpose = (purpose: string): AiModelListItemData[] =>
  props.models.filter((m) => m.purpose === purpose);

const activeModels = computed(() => modelsForPurpose(activePurpose.value));

const deleteTarget = ref<AiModelListItemData | null>(null);
const isDeleting = ref(false);

const toggleModel = (model: AiModelListItemData): void => {
  router.put(
    app.manage.aiModels.toggle.url(model.id),
    {},
    { preserveScroll: true },
  );
};

const confirmDelete = (): void => {
  if (!deleteTarget.value) {
    return;
  }
  isDeleting.value = true;
  router.delete(app.manage.aiModels.destroy.url(deleteTarget.value.id), {
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
    <Head :title="t('AI 模型管理')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="mx-auto w-full max-w-none space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('AI 模型管理')"
            :description="
              t(
                '按用途管理模型，同用途内按权重加权随机选择，失败自动降级到其他模型。',
              )
            "
          />
          <Button as-child>
            <Link :href="app.manage.aiModels.create.url()">{{
              t('添加模型')
            }}</Link>
          </Button>
        </div>

        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="tab in props.purpose_tabs"
            :key="String(tab.value)"
            type="button"
            class="rounded-md border px-3 py-1.5 text-sm"
            :class="
              activePurpose === String(tab.value)
                ? 'border-foreground bg-foreground text-background'
                : 'text-muted-foreground hover:bg-muted'
            "
            @click="selectPurpose(String(tab.value))"
          >
            {{ tab.label }}
            <span class="ml-1 opacity-70">
              {{ modelsForPurpose(String(tab.value)).length }}
            </span>
          </button>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('名称') }}</th>
                  <th class="px-4 py-3">{{ t('模型 ID') }}</th>
                  <th class="px-4 py-3">{{ t('供应商') }}</th>
                  <th class="px-4 py-3">{{ t('权重') }}</th>
                  <th class="px-4 py-3">{{ t('媒体输入能力') }}</th>
                  <th class="px-4 py-3">{{ t('启用') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="model in activeModels"
                  :key="model.id"
                  class="border-t bg-background"
                >
                  <td class="px-4 py-3 font-medium">{{ model.name }}</td>
                  <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                    {{ model.model_id }}
                  </td>
                  <td class="px-4 py-3">{{ model.provider_name }}</td>
                  <td class="px-4 py-3 tabular-nums">{{ model.weight }}</td>
                  <td class="px-4 py-3">
                    <div
                      v-if="model.type === 'llm'"
                      class="flex flex-wrap gap-1"
                    >
                      <Badge
                        v-if="model.supports_image_input"
                        variant="secondary"
                      >
                        {{ t('图片') }}
                      </Badge>
                      <Badge
                        v-if="model.supports_video_input"
                        variant="secondary"
                      >
                        {{ t('视频') }}
                      </Badge>
                      <span
                        v-if="
                          !model.supports_image_input &&
                          !model.supports_video_input
                        "
                        class="text-muted-foreground"
                      >
                        —
                      </span>
                    </div>
                    <span v-else class="text-muted-foreground">—</span>
                  </td>
                  <td class="px-4 py-3">
                    <Switch
                      :model-value="model.is_active"
                      :aria-label="model.is_active ? t('停用') : t('启用')"
                      @update:model-value="() => toggleModel(model)"
                    />
                  </td>
                  <td class="px-4 py-3 text-right">
                    <div class="inline-flex items-center justify-end gap-1">
                      <Button as-child variant="outline" size="sm">
                        <Link :href="app.manage.aiModels.edit.url(model.id)">
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
                            @select="deleteTarget = model"
                          >
                            {{ t('删除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>

                <tr v-if="activeModels.length === 0">
                  <td
                    class="px-4 py-8 text-center text-muted-foreground"
                    colspan="7"
                  >
                    {{ t('该用途暂无模型') }}
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
      :title="t('确认删除模型？')"
      :detail-title="deleteTarget?.name"
      :detail-description="
        t('删除后该模型立即移出当前应用取用池，且无法恢复。')
      "
      :processing="isDeleting"
      @confirm="confirmDelete"
      @update:open="(value) => !value && (deleteTarget = null)"
    />
  </div>
</template>
