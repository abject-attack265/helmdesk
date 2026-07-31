<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import type { ListExtractableConversationItemData } from '@/types/generated';

/**
 * 任务会话清单页的会话表格行：主题（含已提炼标记）、访客、最后消息、关闭时间、人工消息数与查看操作。
 * 清单跨联系人平铺，故带访客列。
 */
defineProps<{
  conversation: ListExtractableConversationItemData;
  showExtractedBadge?: boolean;
}>();

const emit = defineEmits<{
  view: [conversationId: string];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();
</script>

<template>
  <tr class="border-t bg-background align-middle">
    <slot name="leading" />

    <td class="px-4 py-3">
      <span class="inline-flex items-center gap-2">
        <span class="font-medium">
          {{ conversation.subject || t('（无主题）') }}
        </span>
        <Badge
          v-if="showExtractedBadge && conversation.already_extracted"
          variant="secondary"
          class="font-normal"
        >
          {{ t('已提炼过') }}
        </Badge>
      </span>
    </td>

    <td class="px-4 py-3 text-muted-foreground">
      {{
        formatVisitorName(conversation.contact_name, conversation.contact_id)
      }}
    </td>

    <td class="max-w-64 truncate px-4 py-3 text-muted-foreground">
      {{ conversation.last_message_preview ?? '-' }}
    </td>

    <td class="px-4 py-3 text-muted-foreground">
      {{ formatDateTime(conversation.closed_at) }}
    </td>

    <td class="px-4 py-3 text-muted-foreground">
      {{ t('{count} 条', { count: conversation.teammate_message_count }) }}
    </td>

    <td class="px-4 py-3">
      <div class="flex justify-end gap-2">
        <Button
          size="sm"
          variant="outline"
          @click="emit('view', conversation.id)"
        >
          {{ t('查看') }}
        </Button>
      </div>
    </td>
  </tr>
</template>
