<!--
  知识库问答列表，展示问题、分组、处理状态和可用操作。
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
import type { ListKnowledgeQaEntryItemData } from '@/types/generated';
import { MoreHorizontal } from '@lucide/vue';

defineProps<{
  entries: ListKnowledgeQaEntryItemData[];
  deleteProcessing: boolean;
  overflowTooltipKey: string | null;
  groupLabel: (groupId: string | null) => string;
  canMove: (groupId: string | null) => boolean;
  badgeVariant: (
    status: string,
  ) => 'default' | 'secondary' | 'destructive' | 'outline';
}>();

const emit = defineEmits<{
  setOverflowTooltip: [event: MouseEvent, key: string];
  clearOverflowTooltip: [key: string];
  edit: [entry: ListKnowledgeQaEntryItemData];
  move: [entry: ListKnowledgeQaEntryItemData];
  delete: [entry: ListKnowledgeQaEntryItemData];
}>();

const { t } = useI18n();
</script>

<template>
  <tr
    v-for="entry in entries"
    :key="entry.id"
    class="border-b last:border-b-0 hover:bg-muted/20"
  >
    <td class="max-w-0 px-4 py-3">
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger as-child>
            <span
              class="block truncate font-medium"
              @mouseenter="emit('setOverflowTooltip', $event, `qa:${entry.id}`)"
              @mouseleave="emit('clearOverflowTooltip', `qa:${entry.id}`)"
            >
              {{ entry.question }}
            </span>
          </TooltipTrigger>
          <TooltipContent
            v-if="overflowTooltipKey === `qa:${entry.id}`"
            class="max-w-96 break-words"
          >
            {{ entry.question }}
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    </td>
    <td class="max-w-0 px-4 py-3 text-muted-foreground">
      <span class="block truncate">
        {{ groupLabel(entry.group_id) }}
      </span>
    </td>
    <td class="px-4 py-3 whitespace-nowrap">
      <Badge :variant="badgeVariant(entry.status)">
        {{ entry.status_label }}
      </Badge>
    </td>
    <td class="px-4 py-3 text-right whitespace-nowrap">
      <div class="flex justify-end gap-1">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          @click="emit('edit', entry)"
        >
          {{ t('编辑') }}
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
          <DropdownMenuContent align="end" class="w-32">
            <DropdownMenuItem
              v-if="canMove(entry.group_id)"
              @select="emit('move', entry)"
            >
              {{ t('移动到其他分组') }}
            </DropdownMenuItem>
            <DropdownMenuSeparator v-if="canMove(entry.group_id)" />
            <DropdownMenuItem
              class="text-destructive focus:text-destructive"
              :disabled="deleteProcessing"
              @select="emit('delete', entry)"
            >
              {{ t('删除') }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </td>
  </tr>
</template>
