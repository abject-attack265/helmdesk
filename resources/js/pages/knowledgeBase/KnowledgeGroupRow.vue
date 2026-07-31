<!--
  知识库侧边栏中的分组行，提供选择、编辑和删除入口。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import ConfirmDeleteDialog from '@/components/common/ConfirmDeleteDialog.vue';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useI18n } from '@/composables/useI18n';
import type { KnowledgeGroupData } from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { Folder, MoreHorizontal } from '@lucide/vue';
import { ref } from 'vue';

const props = defineProps<{
  group: KnowledgeGroupData;
  knowledgeBaseId: string;
  selected: boolean;
  deleteDisabled?: boolean;
}>();

const emit = defineEmits<{
  select: [];
  edit: [];
}>();

const { t } = useI18n();

const deleteForm = useForm({});
const confirmDeleteOpen = ref(false);

function deleteGroup(): void {
  deleteForm.delete(
    KnowledgeBase.Group.DeleteKnowledgeGroupAction.url({
      knowledgeBase: props.knowledgeBaseId,
      group: props.group.id,
    }),
    {
      preserveScroll: true,
      onSuccess: () => {
        confirmDeleteOpen.value = false;
      },
    },
  );
}
</script>

<template>
  <div class="flex items-center gap-0.5">
    <button
      type="button"
      class="flex min-w-0 flex-1 items-center gap-1.5 rounded-md px-1 py-1 text-sm hover:bg-accent/50"
      :class="{
        'bg-accent text-accent-foreground hover:bg-accent': selected,
      }"
      @click="emit('select')"
    >
      <Folder class="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
      <span class="flex-1 truncate text-left">{{ group.name }}</span>
    </button>

    <div
      class="flex shrink-0 items-center text-muted-foreground/50 hover:text-muted-foreground"
      :class="{ 'text-muted-foreground': selected }"
    >
      <DropdownMenu v-if="!group.is_default">
        <DropdownMenuTrigger as-child>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            class="h-5 w-5"
            :aria-label="t('更多操作')"
            @click.stop
          >
            <MoreHorizontal class="h-3 w-3" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-28">
          <DropdownMenuItem @click.stop="emit('edit')">
            {{ t('编辑') }}
          </DropdownMenuItem>
          <DropdownMenuItem
            v-if="!deleteDisabled"
            class="text-destructive focus:text-destructive"
            @click.stop="confirmDeleteOpen = true"
          >
            {{ t('删除') }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </div>

  <ConfirmDeleteDialog
    :open="confirmDeleteOpen"
    :title="t('删除这个分组？')"
    :detail-title="group.name"
    :detail-description="
      t('只能删除空分组，请先移走其中的内容，并移走或删除子分组。')
    "
    :processing="deleteForm.processing"
    @update:open="confirmDeleteOpen = $event"
    @confirm="deleteGroup"
  />
</template>
