<!--
  知识库文档列表，展示文件信息、处理状态和可用操作。
-->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import { useI18n } from '@/composables/useI18n';
import { formatFileSize } from '@/lib/format';
import type { ListKnowledgeDocumentItemData } from '@/types/generated';
import { MoreHorizontal } from '@lucide/vue';

defineProps<{
  documents: ListKnowledgeDocumentItemData[];
  deleteProcessing: boolean;
  reindexProcessing: boolean;
  overflowTooltipKey: string | null;
  groupLabel: (groupId: string | null) => string;
  canMove: (groupId: string | null) => boolean;
  typeLabel: (doc: ListKnowledgeDocumentItemData) => string;
  badgeVariant: (
    status: string,
  ) => 'default' | 'secondary' | 'destructive' | 'outline';
}>();

const emit = defineEmits<{
  setOverflowTooltip: [event: MouseEvent, key: string];
  clearOverflowTooltip: [key: string];
  preview: [doc: ListKnowledgeDocumentItemData];
  edit: [doc: ListKnowledgeDocumentItemData];
  move: [doc: ListKnowledgeDocumentItemData];
  reindex: [doc: ListKnowledgeDocumentItemData];
  delete: [doc: ListKnowledgeDocumentItemData];
}>();

const { t } = useI18n();
</script>

<template>
  <tr
    v-for="doc in documents"
    :key="doc.id"
    class="border-b last:border-b-0 hover:bg-muted/20"
  >
    <td class="max-w-0 px-4 py-3">
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger as-child>
            <span
              class="block truncate font-medium"
              @mouseenter="
                emit('setOverflowTooltip', $event, `filename:${doc.id}`)
              "
              @mouseleave="emit('clearOverflowTooltip', `filename:${doc.id}`)"
            >
              {{ doc.original_filename }}
            </span>
          </TooltipTrigger>
          <TooltipContent
            v-if="overflowTooltipKey === `filename:${doc.id}`"
            class="max-w-96 break-words"
          >
            {{ doc.original_filename }}
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </td>
    <td class="px-4 py-3 whitespace-nowrap">
      <Badge variant="secondary">
        {{ typeLabel(doc) }}
      </Badge>
    </td>
    <td class="max-w-0 px-4 py-3 text-muted-foreground">
      <span class="block truncate">
        {{ groupLabel(doc.group_id) }}
      </span>
    </td>
    <td class="px-4 py-3 whitespace-nowrap">
      {{ formatFileSize(doc.byte_size) }}
    </td>
    <td class="px-4 py-3 whitespace-nowrap">
      <Badge :variant="badgeVariant(doc.indexing.overall_status)">
        {{ doc.indexing.overall_status_label }}
      </Badge>
    </td>
    <td class="px-4 py-3 text-right whitespace-nowrap">
      <div class="flex justify-end gap-1">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          @click="emit('preview', doc)"
        >
          {{ t('预览') }}
        </Button>
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button
              type="button"
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
              v-if="doc.source_type === 'manual'"
              @select="emit('edit', doc)"
            >
              {{ t('编辑') }}
            </DropdownMenuItem>
            <DropdownMenuItem
              v-if="canMove(doc.group_id)"
              @select="emit('move', doc)"
            >
              {{ t('移动到其他分组') }}
            </DropdownMenuItem>
            <DropdownMenuItem
              :disabled="reindexProcessing"
              @select="emit('reindex', doc)"
            >
              {{ t('重新处理') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem
              class="text-destructive focus:text-destructive"
              :disabled="deleteProcessing"
              @select="emit('delete', doc)"
            >
              {{ t('删除') }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </td>
  </tr>
</template>
