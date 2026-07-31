<!--
  自定义字段列表页，使用 ShowListAttributeDefinitionPagePropsData 添加、编辑、排序和删除字段。
-->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import AttributeFormDialog from '@/components/custom-attribute/AttributeFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import app from '@/routes/app';
import type {
  FormCreateAttributeDefinitionData,
  FormUpdateAttributeDefinitionData,
  ListAttributeDefinitionItemData,
  ShowListAttributeDefinitionPagePropsData,
} from '@/types/generated';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, MoreHorizontal } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const props = defineProps<ShowListAttributeDefinitionPagePropsData>();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('自定义字段'),
    href: app.manage.attributes.index.url(),
  },
  { title: t('列表') },
]);

const SELECT_TYPES = ['single_select', 'multi_select'];
const FILTERABLE_TYPES = ['single_select', 'boolean', 'date', 'number'];

const createOpen = ref(false);
const editOpen = ref(false);
const archiveTarget = ref<ListAttributeDefinitionItemData | null>(null);
const editingDef = ref<ListAttributeDefinitionItemData | null>(null);
const keyManuallyEdited = ref(false);

const createForm = useForm<FormCreateAttributeDefinitionData>({
  key: '',
  name: '',
  description: null,
  type: '',
  config: null,
  is_filterable: false,
  is_ai_readable: false,
  is_ai_writable: false,
});

const editForm = useForm<FormUpdateAttributeDefinitionData>({
  name: '',
  description: null,
  config: null,
  is_filterable: false,
  is_ai_readable: false,
  is_ai_writable: false,
});

const archiveForm = useForm({});
const reorderForm = useForm<{ ordered_ids: string[] }>({
  ordered_ids: [],
});

const createOptions = ref<Array<{ code: string; label: string }>>([
  { code: '', label: '' },
]);
const editOptions = ref<Array<{ code: string; label: string }>>([]);

const isCreateSelectType = computed(() =>
  SELECT_TYPES.includes(createForm.type),
);
const isEditSelectType = computed(
  () => !!editingDef.value && SELECT_TYPES.includes(editingDef.value.type),
);

const slugify = (name: string): string => {
  return name
    .toLowerCase()
    .replace(/[\u4e00-\u9fff]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .substring(0, 50);
};

watch(
  () => createForm.name,
  (name) => {
    if (!keyManuallyEdited.value) {
      createForm.key = slugify(name);
    }
  },
);

watch(
  () => createForm.type,
  (newType) => {
    if (!FILTERABLE_TYPES.includes(newType)) {
      createForm.is_filterable = false;
    }

    if (SELECT_TYPES.includes(newType)) {
      createForm.config = { options: createOptions.value };
    } else {
      createForm.config = null;
    }
  },
);

watch(
  createOptions,
  (opts) => {
    if (isCreateSelectType.value) {
      createForm.config = { options: opts };
    }
  },
  { deep: true },
);

watch(
  editOptions,
  (opts) => {
    if (isEditSelectType.value) {
      editForm.config = { options: opts };
    }
  },
  { deep: true },
);

watch(createOpen, (open) => {
  if (open || createForm.processing) {
    return;
  }

  createForm.reset();
  createForm.is_filterable = false;
  createForm.is_ai_readable = false;
  createForm.is_ai_writable = false;
  createOptions.value = [{ code: '', label: '' }];
  keyManuallyEdited.value = false;
  createForm.clearErrors();
});

watch(editOpen, (open) => {
  if (open || editForm.processing) {
    return;
  }

  editingDef.value = null;
  editOptions.value = [];
  editForm.reset();
  editForm.is_filterable = false;
  editForm.is_ai_readable = false;
  editForm.is_ai_writable = false;
  editForm.clearErrors();
});

const openCreate = () => {
  createForm.reset();
  createForm.clearErrors();
  keyManuallyEdited.value = false;
  createOptions.value = [{ code: '', label: '' }];
  createOpen.value = true;
};

const submitCreate = () => {
  createForm.post(app.manage.attributes.store.url(), {
    preserveScroll: true,
    onSuccess: () => {
      createOpen.value = false;
      createForm.reset();
    },
  });
};

const openEdit = (def: ListAttributeDefinitionItemData) => {
  editingDef.value = def;
  editForm.name = def.name;
  editForm.description = def.description ?? null;
  editForm.is_filterable = FILTERABLE_TYPES.includes(def.type)
    ? def.is_filterable
    : false;
  editForm.is_ai_readable = def.is_ai_readable;
  editForm.is_ai_writable = def.is_ai_writable;
  editForm.clearErrors();

  if (SELECT_TYPES.includes(def.type) && def.config?.options) {
    editOptions.value = [...def.config.options];
    editForm.config = { options: editOptions.value };
  } else {
    editOptions.value = [];
    editForm.config = def.config;
  }

  editOpen.value = true;
};

const submitEdit = () => {
  if (!editingDef.value) {
    return;
  }

  editForm.put(
    app.manage.attributes.update.url({
      id: editingDef.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        editOpen.value = false;
        editingDef.value = null;
      },
    },
  );
};

const openArchiveDialog = (def: ListAttributeDefinitionItemData) => {
  archiveTarget.value = def;
};

const closeArchiveDialog = (open: boolean) => {
  if (open || archiveForm.processing) {
    return;
  }
  archiveTarget.value = null;
};

const submitArchive = () => {
  if (!archiveTarget.value) {
    return;
  }

  archiveForm.put(
    app.manage.attributes.archive.url({
      id: archiveTarget.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        archiveTarget.value = null;
      },
    },
  );
};

const moveDefinition = (definitionId: string, direction: 'up' | 'down') => {
  const orderedDefinitions = [...props.definition_list];
  const currentIndex = orderedDefinitions.findIndex(
    (definition) => definition.id === definitionId,
  );

  if (currentIndex === -1) {
    return;
  }

  const targetIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1;

  if (targetIndex < 0 || targetIndex >= orderedDefinitions.length) {
    return;
  }

  const [movedDefinition] = orderedDefinitions.splice(currentIndex, 1);
  orderedDefinitions.splice(targetIndex, 0, movedDefinition);

  reorderForm.ordered_ids = orderedDefinitions.map(
    (definition) => definition.id,
  );
  reorderForm.put(app.manage.attributes.reorder.url(), {
    preserveScroll: true,
  });
};
</script>

<template>
  <div class="contents">
    <Head :title="t('自定义字段')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('自定义字段')"
            :description="t('给联系人增加需要记录的信息。')"
          />

          <div class="flex items-center gap-2">
            <Button @click="openCreate()">
              {{ t('添加字段') }}
            </Button>

            <Button variant="outline" as-child>
              <Link :href="app.manage.attributes.trash.url()">
                {{ t('回收站') }}
              </Link>
            </Button>
          </div>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('字段名称') }}</th>
                  <th class="px-4 py-3">{{ t('内部标识') }}</th>
                  <th class="px-4 py-3">{{ t('填写方式') }}</th>
                  <th class="px-4 py-3">
                    {{ t('可用于联系人筛选') }}
                  </th>
                  <th class="px-4 py-3">{{ t('AI 使用') }}</th>
                  <th class="px-4 py-3">{{ t('已填写联系人') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(def, index) in props.definition_list"
                  :key="def.id"
                  class="border-t bg-background"
                >
                  <td class="px-4 py-3 font-medium">
                    {{ def.name }}
                  </td>
                  <td class="px-4 py-3">
                    <code class="rounded bg-muted px-1.5 py-0.5 text-xs">
                      {{ def.key }}
                    </code>
                  </td>
                  <td class="px-4 py-3">
                    {{ def.type_label }}
                  </td>
                  <td class="px-4 py-3">
                    <Badge v-if="def.is_filterable" variant="secondary">
                      {{ t('可用于联系人筛选') }}
                    </Badge>
                    <span v-else class="text-muted-foreground">-</span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex gap-1">
                      <Badge v-if="def.is_ai_readable" variant="secondary">
                        {{ t('AI 可查看') }}
                      </Badge>
                      <Badge v-if="def.is_ai_writable" variant="secondary">
                        {{ t('AI 可填写') }}
                      </Badge>
                      <span
                        v-if="!def.is_ai_readable && !def.is_ai_writable"
                        class="text-muted-foreground"
                      >
                        -
                      </span>
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-muted-foreground">
                      {{ def.usage_count }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex justify-end gap-2">
                      <div class="flex gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          class="h-8 w-8"
                          :aria-label="t('上移')"
                          :disabled="reorderForm.processing || index === 0"
                          @click="moveDefinition(def.id, 'up')"
                        >
                          <ArrowUp class="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          class="h-8 w-8"
                          :aria-label="t('下移')"
                          :disabled="
                            reorderForm.processing ||
                            index === props.definition_list.length - 1
                          "
                          @click="moveDefinition(def.id, 'down')"
                        >
                          <ArrowDown class="h-4 w-4" />
                        </Button>
                      </div>
                      <Button
                        variant="outline"
                        size="sm"
                        :disabled="
                          editForm.processing ||
                          archiveForm.processing ||
                          reorderForm.processing
                        "
                        @click="openEdit(def)"
                      >
                        {{ t('编辑') }}
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
                            :disabled="
                              archiveForm.processing || reorderForm.processing
                            "
                            @select="openArchiveDialog(def)"
                          >
                            {{ t('删除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>

                <tr v-if="props.definition_list.length === 0">
                  <td
                    class="px-4 py-8 text-center text-muted-foreground"
                    colspan="7"
                  >
                    {{ t('还没有自定义字段') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <AttributeFormDialog
          mode="create"
          v-model:open="createOpen"
          v-model:options="createOptions"
          v-model:key-manually-edited="keyManuallyEdited"
          :form="createForm"
          :type-options="props.type_options"
          @submit="submitCreate"
        />

        <AttributeFormDialog
          mode="edit"
          v-model:open="editOpen"
          v-model:options="editOptions"
          :form="editForm"
          :type-options="props.type_options"
          :editing-def="editingDef"
          @submit="submitEdit"
        />
      </div>
    </div>

    <ConfirmDeleteDialog
      :open="archiveTarget !== null"
      :title="t('删除这个字段？')"
      :detail-title="archiveTarget?.name"
      :detail-description="
        t('删除后会移到回收站。已有联系人数据会保留，恢复字段后可继续使用。')
      "
      :processing="archiveForm.processing"
      @update:open="closeArchiveDialog"
      @confirm="submitArchive"
    />
  </div>
</template>
