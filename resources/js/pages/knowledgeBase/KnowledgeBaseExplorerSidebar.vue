<!--
  知识库资源管理器，展示知识库与分组树并承接管理入口。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { useI18n } from '@/composables/useI18n';
import { defaultKnowledgeBaseAvatar } from '@/lib/knowledgeBaseAvatar';
import KnowledgeGroupFormDialog from '@/pages/knowledgeBase/KnowledgeGroupFormDialog.vue';
import KnowledgeGroupRow from '@/pages/knowledgeBase/KnowledgeGroupRow.vue';
import type {
  EnumOptionData,
  KnowledgeBaseCategory,
  KnowledgeBaseData,
  KnowledgeGroupData,
} from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { Library, MoreHorizontal, PenLine } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = withDefaults(
  defineProps<{
    knowledgeBases: KnowledgeBaseData[];
    categoryOptions: EnumOptionData[];
    /** 树上高亮的知识库行；null 表示无高亮。 */
    activeKbId?: string | null;
    /** 分组级高亮（含「全部文档/问答」行，groupId 为 null）；null 表示分组不参与高亮。 */
    groupSelection?: { kbId: string; groupId: string | null } | null;
    /** 正在编辑的知识库（编辑图标呈现激活态）。 */
    editingKbId?: string | null;
    /** 「添加知识库」按钮是否处于激活态（宿主页面正打开创建表单）。 */
    createActive?: boolean;
    /** 右侧子页打开时隐藏删除入口，避免当前内容失效。 */
    destructiveActionsDisabled?: boolean;
  }>(),
  {
    activeKbId: null,
    groupSelection: null,
    editingKbId: null,
    createActive: false,
    destructiveActionsDisabled: false,
  },
);

const emit = defineEmits<{
  navigate: [];
  'select-kb': [kbId: string];
  'select-group': [kbId: string, groupId: string | null];
  'group-dialog-open': [open: boolean];
  create: [category: KnowledgeBaseCategory];
  edit: [kb: KnowledgeBaseData];
}>();

const { t } = useI18n();

function selectKnowledgeBase(kbId: string): void {
  emit('navigate');
  emit('select-kb', kbId);
}

function selectKnowledgeGroup(kbId: string, groupId: string | null): void {
  emit('navigate');
  emit('select-group', kbId, groupId);
}

function createKnowledgeBase(category: KnowledgeBaseCategory): void {
  emit('navigate');
  emit('create', category);
}

function editKnowledgeBase(knowledgeBase: KnowledgeBaseData): void {
  emit('navigate');
  emit('edit', knowledgeBase);
}

function isGroupSelected(kbId: string, groupId: string | null): boolean {
  return (
    props.groupSelection !== null &&
    props.groupSelection.kbId === kbId &&
    props.groupSelection.groupId === groupId
  );
}

const groupDialogOpen = ref(false);
const groupDialogMode = ref<'create' | 'edit'>('create');
const groupDialogKbId = ref('');
const groupDialogTarget = ref<KnowledgeGroupData | null>(null);

function updateGroupDialogOpen(open: boolean): void {
  groupDialogOpen.value = open;
  emit('group-dialog-open', open);
}

function openCreateGroupDialog(kbId: string): void {
  groupDialogMode.value = 'create';
  groupDialogKbId.value = kbId;
  groupDialogTarget.value = null;
  updateGroupDialogOpen(true);
}

function openEditGroupDialog(kbId: string, group: KnowledgeGroupData): void {
  groupDialogMode.value = 'edit';
  groupDialogKbId.value = kbId;
  groupDialogTarget.value = group;
  updateGroupDialogOpen(true);
}

const groupDialogKb = computed(
  () =>
    props.knowledgeBases.find((kb) => kb.id === groupDialogKbId.value) ?? null,
);

const deleteKbId = ref<string | null>(null);
const deleteKbForm = useForm({});

const deletingKb = computed(
  () => props.knowledgeBases.find((kb) => kb.id === deleteKbId.value) ?? null,
);

function confirmDeleteKb(): void {
  const targetId = deleteKbId.value;
  if (!targetId) {
    return;
  }
  deleteKbForm.delete(
    KnowledgeBase.DeleteKnowledgeBaseAction.url({
      knowledgeBase: targetId,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        deleteKbId.value = null;
      },
    },
  );
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col">
    <div class="px-3 pb-2">
      <div class="flex gap-2">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button
              type="button"
              :variant="props.createActive ? 'secondary' : 'outline'"
              size="sm"
              class="min-w-0 flex-1 justify-center"
            >
              {{ t('添加知识库') }}
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" class="w-56">
            <DropdownMenuItem
              v-for="cat in props.categoryOptions"
              :key="String(cat.value)"
              @click="
                createKnowledgeBase(String(cat.value) as KnowledgeBaseCategory)
              "
            >
              <div class="flex flex-col gap-0.5">
                <span class="font-medium">{{ cat.label }}</span>
                <span
                  v-if="cat.description"
                  class="text-xs text-muted-foreground"
                >
                  {{ cat.description }}
                </span>
              </div>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </div>

    <Separator />

    <div class="flex-1 overflow-y-auto py-2">
      <div
        v-if="props.knowledgeBases.length === 0"
        class="px-4 py-8 text-center text-sm text-muted-foreground"
      >
        {{ t('还没有知识库') }}
      </div>

      <div v-for="kb in props.knowledgeBases" :key="kb.id" class="mb-1">
        <div
          class="group flex items-center gap-0.5 px-2"
          :class="{
            'rounded-md bg-accent/40': props.activeKbId === kb.id,
          }"
        >
          <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1.5 py-1.5 text-left text-sm hover:bg-accent/40"
            :class="{
              'font-medium': props.activeKbId === kb.id,
            }"
            @click="selectKnowledgeBase(kb.id)"
          >
            <img
              :src="kb.avatar_url ?? defaultKnowledgeBaseAvatar"
              :alt="kb.name"
              class="h-5 w-5 shrink-0 rounded object-cover"
            />
            <span class="min-w-0 flex-1 truncate">{{ kb.name }}</span>
            <Badge
              variant="secondary"
              class="shrink-0 px-1.5 py-0 text-[10px] font-normal"
            >
              {{ kb.category_label }}
            </Badge>
          </button>

          <div
            class="flex shrink-0 items-center text-muted-foreground/50 hover:text-muted-foreground"
            :class="{
              'text-muted-foreground': props.activeKbId === kb.id,
            }"
          >
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="h-6 w-6"
              :class="
                props.editingKbId === kb.id
                  ? 'bg-background text-foreground shadow-sm'
                  : ''
              "
              :aria-label="t('编辑')"
              @click.stop="editKnowledgeBase(kb)"
            >
              <PenLine class="h-3.5 w-3.5" />
            </Button>

            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-6 w-6"
                  :aria-label="t('更多操作')"
                  @click.stop
                >
                  <MoreHorizontal class="h-3.5 w-3.5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-36">
                <DropdownMenuItem @click.stop="openCreateGroupDialog(kb.id)">
                  {{ t('添加分组') }}
                </DropdownMenuItem>
                <DropdownMenuSeparator
                  v-if="!props.destructiveActionsDisabled"
                />
                <DropdownMenuItem
                  v-if="!props.destructiveActionsDisabled"
                  class="text-destructive focus:text-destructive"
                  @click.stop="deleteKbId = kb.id"
                >
                  {{ t('删除') }}
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>
        </div>

        <div class="mt-0.5 ml-4 space-y-0.5 border-l border-border/60 pl-2">
          <button
            type="button"
            class="flex w-full items-center gap-1.5 rounded-md px-1 py-1 text-sm hover:bg-accent/50"
            :class="{
              'bg-accent text-accent-foreground hover:bg-accent':
                isGroupSelected(kb.id, null),
            }"
            @click="selectKnowledgeGroup(kb.id, null)"
          >
            <Library class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
            <span class="flex-1 truncate text-left">
              {{ kb.category === 'qa' ? t('全部问答') : t('全部文档') }}
            </span>
          </button>

          <div
            v-for="group in kb.document_groups"
            :key="group.id"
            class="space-y-0.5"
          >
            <KnowledgeGroupRow
              :group="group"
              :knowledge-base-id="kb.id"
              :selected="isGroupSelected(kb.id, group.id)"
              :delete-disabled="props.destructiveActionsDisabled"
              @select="selectKnowledgeGroup(kb.id, group.id)"
              @edit="openEditGroupDialog(kb.id, group)"
            />

            <div
              v-if="group.children && group.children.length > 0"
              class="ml-4 space-y-0.5"
            >
              <KnowledgeGroupRow
                v-for="child in group.children"
                :key="child.id"
                :group="child"
                :knowledge-base-id="kb.id"
                :selected="isGroupSelected(kb.id, child.id)"
                :delete-disabled="props.destructiveActionsDisabled"
                @select="selectKnowledgeGroup(kb.id, child.id)"
                @edit="openEditGroupDialog(kb.id, child)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <KnowledgeGroupFormDialog
      :open="groupDialogOpen"
      :mode="groupDialogMode"
      :knowledge-base-id="groupDialogKbId"
      :groups="groupDialogKb?.document_groups ?? []"
      :group="groupDialogTarget"
      @update:open="updateGroupDialogOpen"
    />

    <ConfirmDeleteDialog
      :open="deleteKbId !== null"
      :title="t('删除这个知识库？')"
      :detail-title="deletingKb?.name ?? ''"
      :detail-description="
        t('删除后无法恢复，其中的所有内容和分组也会一并删除。')
      "
      :processing="deleteKbForm.processing"
      @update:open="deleteKbId = null"
      @confirm="confirmDeleteKb"
    />
  </div>
</template>
