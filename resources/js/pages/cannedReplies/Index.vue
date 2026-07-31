<!--
  快捷回复列表页，使用 ShowCannedReplyListPagePropsData 按使用范围展示个人和团队回复。
-->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import FilterPopover from '@/components/common/FilterPopover.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import AppLayout from '@/layouts/AppLayout.vue';
import CannedReplyForm from '@/pages/cannedReplies/CannedReplyForm.vue';
import appRoutes from '@/routes/app';
import cannedReplyRoutes from '@/routes/app/canned-replies';
import type { AppPageProps } from '@/types';
import type {
  ListCannedReplyItemData,
  ShowCannedReplyListPagePropsData,
} from '@/types/generated';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { MoreHorizontal, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const props = defineProps<ShowCannedReplyListPagePropsData>();

const { t } = useI18n();
const page = usePage<AppPageProps>();
const currentUserId = computed<string | null>(() => {
  const id = page.props.auth.user?.id;
  return id ? String(id) : null;
});

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('快捷回复'),
    href: appRoutes.cannedReplies.index.url(),
  },
  { title: t('列表') },
]);

const ownerLabel = (reply: ListCannedReplyItemData): string => {
  if (!reply.is_personal) {
    return t('团队共享');
  }

  if (reply.owner_user_id && reply.owner_user_id === currentUserId.value) {
    return t('仅自己使用');
  }

  return reply.owner_user_name ?? t('仅自己使用');
};

const visibilityOptions = [
  { value: 'all' as const, label: t('全部') },
  { value: 'personal' as const, label: t('仅自己使用') },
  { value: 'app' as const, label: t('团队共享') },
];

type Visibility = (typeof visibilityOptions)[number]['value'];

const currentVisibility = computed<Visibility>(() => {
  const value = props.current_visibility;
  if (value === 'personal' || value === 'app') {
    return value;
  }
  return 'all';
});

const switchVisibility = (visibility: Visibility) => {
  router.get(
    appRoutes.cannedReplies.index.url(),
    visibility === 'all' ? {} : { visibility },
    {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    },
  );
};

const hasActiveVisibilityFilter = computed(
  () => currentVisibility.value !== 'all',
);

const search = ref('');

const filteredList = computed<ListCannedReplyItemData[]>(() => {
  const keyword = search.value.trim().toLowerCase();
  if (keyword === '') {
    return props.canned_reply_list;
  }

  return props.canned_reply_list.filter((reply) => {
    return (
      reply.name.toLowerCase().includes(keyword) ||
      (reply.shortcut?.toLowerCase().includes(keyword) ?? false) ||
      reply.content.toLowerCase().includes(keyword)
    );
  });
});

const deletingReply = ref<ListCannedReplyItemData | null>(null);
const deleteForm = useForm({});
const createOpen = ref(false);
const editOpen = ref(false);
const editingReply = ref<ListCannedReplyItemData | null>(null);

const openDeleteDialog = (reply: ListCannedReplyItemData) => {
  deletingReply.value = reply;
};

const closeDeleteDialog = (open: boolean) => {
  if (open || deleteForm.processing) {
    return;
  }
  deletingReply.value = null;
};

const submitDelete = () => {
  if (!deletingReply.value) {
    return;
  }

  deleteForm.delete(
    cannedReplyRoutes.destroy.url({
      cannedReply: deletingReply.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingReply.value = null;
      },
    },
  );
};

const openEditDialog = (reply: ListCannedReplyItemData) => {
  editingReply.value = reply;
  editOpen.value = true;
};

watch(editOpen, (open) => {
  if (open) {
    return;
  }

  editingReply.value = null;
});
</script>

<template>
  <div class="contents">
    <Head :title="t('快捷回复')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex flex-wrap items-start justify-between gap-4">
          <HeadingSmall
            :title="t('快捷回复')"
            :description="
              t(
                '保存常用回复，在收件箱回复客户时可以直接使用，也可以与同事共享。',
              )
            "
          />

          <div class="flex items-center gap-2">
            <Dialog v-model:open="createOpen">
              <DialogTrigger as-child>
                <Button>{{ t('添加快捷回复') }}</Button>
              </DialogTrigger>
              <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader class="space-y-3">
                  <DialogTitle>{{ t('添加快捷回复') }}</DialogTitle>
                </DialogHeader>
                <CannedReplyForm
                  v-if="createOpen"
                  mode="create"
                  variant="dialog"
                  :available-tokens="props.available_tokens"
                  :default-is-personal="true"
                  @saved="createOpen = false"
                  @cancel="createOpen = false"
                />
              </DialogContent>
            </Dialog>
          </div>
        </div>

        <div class="flex flex-wrap items-end justify-end gap-3">
          <div class="flex items-center gap-3">
            <div class="relative">
              <Search
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
              />
              <Input v-model="search" class="h-9 w-48 pl-9 lg:w-64" />
            </div>

            <FilterPopover
              :active-count="hasActiveVisibilityFilter ? 1 : 0"
              :title="t('筛选条件')"
              content-class="w-72"
              @clear="switchVisibility('all')"
            >
              <div class="space-y-3 p-3">
                <div class="text-xs font-medium text-muted-foreground">
                  {{ t('使用范围') }}
                </div>
                <Select
                  :model-value="currentVisibility"
                  @update:model-value="
                    (value) => switchVisibility(value as Visibility)
                  "
                >
                  <SelectTrigger class="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem
                      v-for="option in visibilityOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </FilterPopover>
          </div>
        </div>

        <div class="rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="px-4 py-3">{{ t('名称') }}</th>
                  <th class="px-4 py-3">{{ t('快捷指令') }}</th>
                  <th class="px-4 py-3">{{ t('使用范围') }}</th>
                  <th class="px-4 py-3 text-right">{{ t('操作') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="reply in filteredList"
                  :key="reply.id"
                  class="border-t bg-background"
                >
                  <td class="px-4 py-3 align-top">
                    <span class="font-medium">{{ reply.name }}</span>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <span
                      v-if="reply.shortcut"
                      class="inline-flex items-center rounded bg-muted px-1.5 py-0.5 font-mono text-xs"
                    >
                      /{{ reply.shortcut }}
                    </span>
                    <span v-else class="text-xs text-muted-foreground">-</span>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <Badge variant="secondary">
                      {{ ownerLabel(reply) }}
                    </Badge>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="flex justify-end gap-2">
                      <Button
                        v-if="reply.can_edit"
                        variant="outline"
                        size="sm"
                        @click="openEditDialog(reply)"
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
                        <DropdownMenuContent align="end" class="w-32">
                          <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            :disabled="!reply.can_delete"
                            @select="openDeleteDialog(reply)"
                          >
                            {{ t('删除') }}
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                  </td>
                </tr>
                <tr v-if="filteredList.length === 0">
                  <td
                    class="px-4 py-10 text-center text-muted-foreground"
                    colspan="4"
                  >
                    {{
                      props.canned_reply_list.length === 0
                        ? t('还没有快捷回复')
                        : t('没有找到相关快捷回复')
                    }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <ConfirmDeleteDialog
      :open="deletingReply !== null"
      :title="t('删除这个快捷回复？')"
      :detail-title="deletingReply?.name"
      :detail-description="t('删除后不能再使用，已经发送的消息不会受影响。')"
      :processing="deleteForm.processing"
      @update:open="closeDeleteDialog"
      @confirm="submitDelete"
    />

    <Dialog v-model:open="editOpen">
      <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('编辑快捷回复') }}</DialogTitle>
        </DialogHeader>
        <CannedReplyForm
          v-if="editOpen && editingReply"
          :key="editingReply.id"
          mode="edit"
          variant="dialog"
          :canned-reply="editingReply"
          :available-tokens="props.available_tokens"
          @saved="editOpen = false"
          @cancel="editOpen = false"
        />
      </DialogContent>
    </Dialog>
  </div>
</template>
