<!--
  标签管理页，使用 ShowListTagPagePropsData 按用途分组管理会话标签和联系人标签。
-->
<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import PageBreadcrumb from '@/components/common/PageBreadcrumb.vue';
import type { PageBreadcrumbItem } from '@/components/common/PageBreadcrumb.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import type {
  ListTagGroupItemData,
  ListTagItemData,
  ShowListTagPagePropsData,
} from '@/types/generated';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Lock, MoreHorizontal } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const props = defineProps<ShowListTagPagePropsData>();
const page = usePage();

const breadcrumbItems = computed<PageBreadcrumbItem[]>(() => [
  {
    title: t('标签'),
    href: app.manage.tags.index.url(),
  },
  { title: t('列表') },
]);

const DEFAULT_COLOR = '#64748b';
const CONVERSATION_SCOPE = 'conversation';

// 标签用途同步到地址栏，刷新后保持当前视图。
const scopeFromUrl = new URL(
  page.url,
  'http://helmdesk.local',
).searchParams.get('scope');
const activeScope = ref<string>(
  props.scope_options.some((option) => String(option.value) === scopeFromUrl)
    ? (scopeFromUrl as string)
    : CONVERSATION_SCOPE,
);

watch(activeScope, (scope) => {
  if (typeof window === 'undefined') {
    return;
  }

  const url = new URL(window.location.href);
  url.searchParams.set('scope', scope);
  window.history.replaceState(window.history.state, '', url);
});

const groupsForScope = computed<ListTagGroupItemData[]>(() =>
  props.tag_group_list.filter((group) => group.scope === activeScope.value),
);

const scopeTags = computed<ListTagItemData[]>(() =>
  groupsForScope.value.flatMap((group) => group.tags),
);

const isConversationScope = computed(
  () => activeScope.value === CONVERSATION_SCOPE,
);

// 会话标签的说明用于判断什么时候自动添加标签。
const descriptionLabel = computed(() =>
  isConversationScope.value ? t('什么时候使用') : t('描述'),
);
const descriptionHint = computed(() =>
  isConversationScope.value
    ? t(
        '写清楚哪些会话应该使用这个标签，例如：客户明确提出退款、退货或退费。AI 会根据这段说明自动添加。',
      )
    : null,
);

const createGroupOpen = ref(false);
const editGroupOpen = ref(false);
const editingGroup = ref<ListTagGroupItemData | null>(null);
const deletingGroup = ref<ListTagGroupItemData | null>(null);

const createGroupForm = useForm<{ name: string; scope: string }>({
  name: '',
  scope: CONVERSATION_SCOPE,
});
const editGroupForm = useForm<{ name: string }>({ name: '' });
const deleteGroupForm = useForm({});

const createTagOpen = ref(false);
const editTagOpen = ref(false);
const createTargetGroup = ref<ListTagGroupItemData | null>(null);
const editingTag = ref<ListTagItemData | null>(null);
const deletingTag = ref<ListTagItemData | null>(null);

const createTagForm = useForm<{
  tag_group_id: string;
  name: string;
  color: string | null;
  description: string | null;
}>({ tag_group_id: '', name: '', color: DEFAULT_COLOR, description: null });

const editTagForm = useForm<{
  tag_group_id: string;
  name: string;
  color: string | null;
  description: string | null;
}>({ tag_group_id: '', name: '', color: DEFAULT_COLOR, description: null });

const deleteTagForm = useForm({});

const mergeOpen = ref(false);
const mergeForm = useForm({ target_tag_id: '', merged_tag_id: '' });
const mergeableTags = computed(() =>
  scopeTags.value.filter((tag) => !tag.is_locked),
);
const mergeTargetTags = computed(() =>
  scopeTags.value.filter(
    (tag) =>
      tag.id !== mergeForm.merged_tag_id &&
      mergeableTags.value.some((mergedTag) => mergedTag.id !== tag.id),
  ),
);
const mergeDisabledReason = computed<string | undefined>(() => {
  if (scopeTags.value.length < 2) {
    return t('至少需要两个标签才能合并');
  }

  if (mergeableTags.value.length === 0) {
    return t('当前没有可以合并的标签');
  }

  return undefined;
});

const createTagColor = computed<string>({
  get: () => createTagForm.color ?? DEFAULT_COLOR,
  set: (v) => {
    createTagForm.color = v === '' ? null : v;
  },
});
const createTagDescription = computed<string>({
  get: () => createTagForm.description ?? '',
  set: (v) => {
    createTagForm.description = v === '' ? null : v;
  },
});
const editTagColor = computed<string>({
  get: () => editTagForm.color ?? DEFAULT_COLOR,
  set: (v) => {
    editTagForm.color = v === '' ? null : v;
  },
});
const editTagDescription = computed<string>({
  get: () => editTagForm.description ?? '',
  set: (v) => {
    editTagForm.description = v === '' ? null : v;
  },
});

// 编辑标签时只能选择用途相同的标签组。
const editTagGroupOptions = computed<ListTagGroupItemData[]>(() => {
  const scope = props.tag_group_list.find(
    (group) => group.id === editingTag.value?.tag_group_id,
  )?.scope;
  return props.tag_group_list.filter((group) => group.scope === scope);
});

const tagGroupName = (tag: ListTagItemData): string => {
  const group = groupsForScope.value.find(
    (item) => item.id === tag.tag_group_id,
  );

  if (!group) {
    throw new Error(`标签 ${tag.id} 缺少所属标签组`);
  }

  return group.name;
};

const tagUsageLabel = (tag: ListTagItemData): string =>
  isConversationScope.value
    ? t('用于 {count} 个会话', { count: tag.conversation_usage_count })
    : t('用于 {count} 个联系人', { count: tag.contact_usage_count });

const openCreateGroup = () => {
  createGroupForm.reset();
  createGroupForm.scope = activeScope.value;
  createGroupForm.clearErrors();
  createGroupOpen.value = true;
};

const submitCreateGroup = () => {
  createGroupForm.post(app.manage.tags.groups.store.url(), {
    preserveScroll: true,
    onSuccess: () => {
      createGroupOpen.value = false;
      createGroupForm.reset();
    },
  });
};

const openEditGroup = (group: ListTagGroupItemData) => {
  editingGroup.value = group;
  editGroupForm.name = group.name;
  editGroupForm.clearErrors();
  editGroupOpen.value = true;
};

const submitEditGroup = () => {
  if (!editingGroup.value) {
    return;
  }
  editGroupForm.put(
    app.manage.tags.groups.update.url({
      id: editingGroup.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        editGroupOpen.value = false;
        editingGroup.value = null;
      },
    },
  );
};

const submitDeleteGroup = () => {
  if (!deletingGroup.value) {
    return;
  }
  deleteGroupForm.delete(
    app.manage.tags.groups.destroy.url({
      id: deletingGroup.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingGroup.value = null;
      },
    },
  );
};

const openCreateTag = (group: ListTagGroupItemData) => {
  createTargetGroup.value = group;
  createTagForm.reset();
  createTagForm.tag_group_id = group.id;
  createTagForm.color = DEFAULT_COLOR;
  createTagForm.clearErrors();
  createTagOpen.value = true;
};

const submitCreateTag = () => {
  createTagForm.post(app.manage.tags.store.url(), {
    preserveScroll: true,
    onSuccess: () => {
      createTagOpen.value = false;
      createTagForm.reset();
    },
  });
};

const openEditTag = (tag: ListTagItemData) => {
  editingTag.value = tag;
  editTagForm.tag_group_id = tag.tag_group_id;
  editTagForm.name = tag.name;
  editTagForm.color = tag.color ?? DEFAULT_COLOR;
  editTagForm.description = tag.description ?? null;
  editTagForm.clearErrors();
  editTagOpen.value = true;
};

const submitEditTag = () => {
  if (!editingTag.value) {
    return;
  }
  editTagForm.put(
    app.manage.tags.update.url({
      id: editingTag.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        editTagOpen.value = false;
        editingTag.value = null;
      },
    },
  );
};

const submitDeleteTag = () => {
  if (!deletingTag.value) {
    return;
  }
  deleteTagForm.delete(
    app.manage.tags.destroy.url({
      id: deletingTag.value.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deletingTag.value = null;
      },
    },
  );
};

const submitMerge = () => {
  mergeForm.post(app.manage.tags.merge.url(), {
    preserveScroll: true,
    onSuccess: () => {
      mergeOpen.value = false;
      mergeForm.reset();
    },
  });
};

const closeTagDeleteDialog = (open: boolean) => {
  if (open || deleteTagForm.processing) {
    return;
  }
  deletingTag.value = null;
};

const closeGroupDeleteDialog = (open: boolean) => {
  if (open || deleteGroupForm.processing) {
    return;
  }
  deletingGroup.value = null;
};

watch(mergeOpen, (open) => {
  if (!open && !mergeForm.processing) {
    mergeForm.reset();
    mergeForm.clearErrors();
  }
});
</script>

<template>
  <div class="contents">
    <Head :title="t('标签')" />

    <div class="px-4 py-6 sm:px-6">
      <div class="space-y-6">
        <PageBreadcrumb :items="breadcrumbItems" />

        <div class="flex items-start justify-between gap-4">
          <HeadingSmall
            :title="t('标签')"
            :description="t('分别管理用于会话和联系人的标签。')"
          />

          <div class="flex gap-2">
            <span :title="mergeDisabledReason">
              <Button
                variant="outline"
                :disabled="mergeDisabledReason !== undefined"
                @click="mergeDisabledReason === undefined && (mergeOpen = true)"
              >
                {{ t('合并标签') }}
              </Button>
            </span>
            <Button @click="openCreateGroup()">
              {{ t('添加标签组') }}
            </Button>
            <Button variant="outline" as-child>
              <Link :href="app.manage.tags.trash.url()">
                {{ t('回收站') }}
              </Link>
            </Button>
          </div>
        </div>

        <div class="flex w-fit rounded-md border bg-background p-0.5 text-sm">
          <button
            v-for="option in props.scope_options"
            :key="option.value"
            type="button"
            class="rounded px-3 py-1.5 transition-colors"
            :class="
              activeScope === String(option.value)
                ? 'bg-foreground text-background'
                : 'text-muted-foreground hover:bg-muted'
            "
            @click="activeScope = String(option.value)"
          >
            {{ option.label }}
          </button>
        </div>

        <div class="space-y-4">
          <div
            v-for="group in groupsForScope"
            :key="group.id"
            class="rounded-lg border"
          >
            <div
              class="flex items-center justify-between gap-2 border-b px-4 py-2.5"
            >
              <div class="font-medium">{{ group.name }}</div>
              <div class="flex items-center gap-1.5">
                <Button
                  variant="outline"
                  size="sm"
                  @click="openCreateTag(group)"
                >
                  {{ t('添加标签') }}
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
                  <DropdownMenuContent align="end" class="w-80">
                    <DropdownMenuItem @select="openEditGroup(group)">
                      {{ t('重命名') }}
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      class="text-destructive focus:text-destructive"
                      :disabled="group.tags.length > 0"
                      @select="
                        group.tags.length === 0 && (deletingGroup = group)
                      "
                    >
                      <div class="flex flex-col items-start gap-0.5">
                        <span>{{ t('删除标签组') }}</span>
                        <span
                          v-if="group.tags.length > 0"
                          class="text-xs text-muted-foreground"
                        >
                          {{ t('标签组中还有标签，请先移动或删除组内标签。') }}
                        </span>
                      </div>
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>

            <div class="flex flex-wrap gap-2 px-4 py-3">
              <button
                v-for="tag in group.tags"
                :key="tag.id"
                type="button"
                class="group flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm hover:bg-muted"
                @click="openEditTag(tag)"
              >
                <span
                  class="h-2 w-2 shrink-0 rounded-full"
                  :style="{ backgroundColor: tag.color ?? '#94a3b8' }"
                />
                {{ tag.name }}
                <Lock
                  v-if="tag.is_locked"
                  class="h-3 w-3 text-muted-foreground"
                />
                <span class="text-xs text-muted-foreground">
                  {{ tagUsageLabel(tag) }}
                </span>
              </button>
              <span
                v-if="group.tags.length === 0"
                class="py-1 text-sm text-muted-foreground"
              >
                {{ t('这个标签组还没有标签') }}
              </span>
            </div>
          </div>

          <div
            v-if="groupsForScope.length === 0"
            class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
          >
            {{ t('还没有标签组') }}
          </div>
        </div>
      </div>
    </div>

    <Dialog v-model:open="createGroupOpen">
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('添加标签组') }}</DialogTitle>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submitCreateGroup">
          <div class="grid gap-2">
            <Label for="group-name" required>{{ t('标签组名称') }}</Label>
            <Input
              id="group-name"
              v-model="createGroupForm.name"
              class="w-full"
              required
              :disabled="createGroupForm.processing"
            />
            <InputError :message="createGroupForm.errors.name" />
          </div>
          <div class="grid gap-2">
            <Label for="group-scope" required>{{ t('标签用于') }}</Label>
            <Select v-model="createGroupForm.scope" required>
              <SelectTrigger id="group-scope" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in props.scope_options"
                  :key="option.value"
                  :value="String(option.value)"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <p class="text-xs text-muted-foreground">
              {{ t('选择这些标签用于会话还是联系人，创建后不能修改。') }}
            </p>
            <InputError
              :message="
                (createGroupForm.errors as Record<string, string>).scope
              "
            />
          </div>
          <DialogFooter class="gap-2">
            <DialogClose as-child>
              <Button
                type="button"
                variant="secondary"
                :disabled="createGroupForm.processing"
              >
                {{ t('取消') }}
              </Button>
            </DialogClose>
            <Button type="submit" :disabled="createGroupForm.processing">
              {{ t('保存') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="editGroupOpen">
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('重命名标签组') }}</DialogTitle>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submitEditGroup">
          <div class="grid gap-2">
            <Label for="edit-group-name" required>{{ t('标签组名称') }}</Label>
            <Input
              id="edit-group-name"
              v-model="editGroupForm.name"
              class="w-full"
              required
              :disabled="editGroupForm.processing"
            />
            <InputError :message="editGroupForm.errors.name" />
          </div>
          <DialogFooter class="gap-2">
            <DialogClose as-child>
              <Button
                type="button"
                variant="secondary"
                :disabled="editGroupForm.processing"
              >
                {{ t('取消') }}
              </Button>
            </DialogClose>
            <Button type="submit" :disabled="editGroupForm.processing">
              {{ t('保存') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="createTagOpen">
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>
            {{ t('添加标签') }}
            <span v-if="createTargetGroup" class="text-muted-foreground">
              · {{ createTargetGroup.name }}
            </span>
          </DialogTitle>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submitCreateTag">
          <div class="grid gap-2">
            <Label for="create-tag-name" required>{{ t('名称') }}</Label>
            <Input
              id="create-tag-name"
              v-model="createTagForm.name"
              class="w-full"
              required
              :disabled="createTagForm.processing"
            />
            <InputError :message="createTagForm.errors.name" />
          </div>
          <div class="grid gap-2">
            <Label for="create-tag-color">{{ t('颜色') }}</Label>
            <div class="flex items-center gap-2">
              <input
                id="create-tag-color"
                v-model="createTagColor"
                class="h-9 w-12 cursor-pointer rounded-md border bg-background p-1"
                type="color"
                :disabled="createTagForm.processing"
              />
              <Input
                v-model="createTagColor"
                class="h-9 w-full"
                :aria-label="t('颜色')"
                :disabled="createTagForm.processing"
              />
            </div>
            <InputError :message="createTagForm.errors.color" />
          </div>
          <div class="grid gap-2">
            <Label for="create-tag-desc">{{ descriptionLabel }}</Label>
            <Input
              id="create-tag-desc"
              v-model="createTagDescription"
              class="w-full"
              :disabled="createTagForm.processing"
            />
            <p v-if="descriptionHint" class="text-xs text-muted-foreground">
              {{ descriptionHint }}
            </p>
            <InputError :message="createTagForm.errors.description" />
          </div>
          <DialogFooter class="gap-2">
            <DialogClose as-child>
              <Button
                type="button"
                variant="secondary"
                :disabled="createTagForm.processing"
              >
                {{ t('取消') }}
              </Button>
            </DialogClose>
            <Button type="submit" :disabled="createTagForm.processing">
              {{ t('保存') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="editTagOpen">
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('编辑标签') }}</DialogTitle>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submitEditTag">
          <div class="grid gap-2">
            <Label for="edit-tag-name" required>{{ t('名称') }}</Label>
            <Input
              id="edit-tag-name"
              v-model="editTagForm.name"
              class="w-full"
              required
              :disabled="editTagForm.processing"
            />
            <InputError :message="editTagForm.errors.name" />
          </div>
          <div class="grid gap-2">
            <Label for="edit-tag-group" required>{{ t('标签组') }}</Label>
            <Select v-model="editTagForm.tag_group_id" required>
              <SelectTrigger id="edit-tag-group" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="group in editTagGroupOptions"
                  :key="group.id"
                  :value="group.id"
                >
                  {{ group.name }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError
              :message="
                (editTagForm.errors as Record<string, string>).tag_group_id
              "
            />
          </div>
          <div class="grid gap-2">
            <Label for="edit-tag-color">{{ t('颜色') }}</Label>
            <div class="flex items-center gap-2">
              <input
                id="edit-tag-color"
                v-model="editTagColor"
                class="h-9 w-12 cursor-pointer rounded-md border bg-background p-1"
                type="color"
                :disabled="editTagForm.processing"
              />
              <Input
                v-model="editTagColor"
                class="h-9 w-full"
                :aria-label="t('颜色')"
                :disabled="editTagForm.processing"
              />
            </div>
            <InputError :message="editTagForm.errors.color" />
          </div>
          <div class="grid gap-2">
            <Label for="edit-tag-desc">{{ descriptionLabel }}</Label>
            <Input
              id="edit-tag-desc"
              v-model="editTagDescription"
              class="w-full"
              :disabled="editTagForm.processing"
            />
            <p v-if="descriptionHint" class="text-xs text-muted-foreground">
              {{ descriptionHint }}
            </p>
            <InputError :message="editTagForm.errors.description" />
          </div>
          <DialogFooter class="items-center justify-between gap-2">
            <div class="space-y-1">
              <Button
                type="button"
                variant="ghost"
                class="text-destructive hover:text-destructive"
                :disabled="editTagForm.processing || editingTag?.is_locked"
                :title="
                  editingTag?.is_locked
                    ? t('这个标签已锁定，不能删除。')
                    : undefined
                "
                @click="
                  deletingTag = editingTag;
                  editTagOpen = false;
                "
              >
                {{ t('删除') }}
              </Button>
              <p
                v-if="editingTag?.is_locked"
                class="text-xs text-muted-foreground"
              >
                {{ t('这个标签已锁定，不能删除。') }}
              </p>
            </div>
            <div class="flex gap-2">
              <DialogClose as-child>
                <Button
                  type="button"
                  variant="secondary"
                  :disabled="editTagForm.processing"
                >
                  {{ t('取消') }}
                </Button>
              </DialogClose>
              <Button type="submit" :disabled="editTagForm.processing">
                {{ t('保存') }}
              </Button>
            </div>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <Dialog v-model:open="mergeOpen">
      <DialogContent>
        <DialogHeader class="space-y-3">
          <DialogTitle>{{ t('合并标签') }}</DialogTitle>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submitMerge">
          <p class="text-sm text-muted-foreground">
            {{
              t(
                '合并后，“要合并的标签”会移到回收站，之前使用它的联系人或会话会改用“保留的标签”。即使之后恢复，原来的关联也不会自动移回。',
              )
            }}
          </p>
          <div class="grid gap-2">
            <Label for="merge-target-tag" required>{{ t('保留的标签') }}</Label>
            <Select v-model="mergeForm.target_tag_id" required>
              <SelectTrigger id="merge-target-tag" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="tag in mergeTargetTags"
                  :key="tag.id"
                  :value="tag.id"
                >
                  {{ tag.name }} · {{ tagGroupName(tag) }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError
              :message="
                (mergeForm.errors as Record<string, string>).target_tag_id
              "
            />
          </div>
          <div class="grid gap-2">
            <Label for="merge-source-tag" required>{{
              t('要合并的标签')
            }}</Label>
            <Select v-model="mergeForm.merged_tag_id" required>
              <SelectTrigger id="merge-source-tag" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="tag in mergeableTags.filter(
                    (tg) => tg.id !== mergeForm.target_tag_id,
                  )"
                  :key="tag.id"
                  :value="tag.id"
                >
                  {{ tag.name }} · {{ tagGroupName(tag) }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError
              :message="
                (mergeForm.errors as Record<string, string>).merged_tag_id
              "
            />
          </div>
          <DialogFooter class="gap-2">
            <DialogClose as-child>
              <Button
                type="button"
                variant="secondary"
                :disabled="mergeForm.processing"
              >
                {{ t('取消') }}
              </Button>
            </DialogClose>
            <Button
              type="submit"
              :disabled="
                mergeForm.processing ||
                !mergeForm.target_tag_id ||
                !mergeForm.merged_tag_id
              "
            >
              {{ t('确认合并') }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
      :open="deletingTag !== null"
      :title="t('删除这个标签？')"
      :detail-title="deletingTag?.name"
      :detail-description="
        t(
          '删除后会移到回收站。恢复后，之前使用这个标签的联系人或会话会重新显示该标签。',
        )
      "
      :processing="deleteTagForm.processing"
      @update:open="closeTagDeleteDialog"
      @confirm="submitDeleteTag"
    />

    <ConfirmDeleteDialog
      :open="deletingGroup !== null"
      :title="t('删除这个标签组？')"
      :detail-title="deletingGroup?.name"
      :detail-description="t('删除后，这个空标签组不会出现在回收站中。')"
      :processing="deleteGroupForm.processing"
      @update:open="closeGroupDeleteDialog"
      @confirm="submitDeleteGroup"
    />
  </div>
</template>
