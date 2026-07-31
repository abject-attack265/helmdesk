<!--
  收件箱消息搜索结果展示联系人、发送者与命中片段，消费 InboxInstanceMessageSearchResultData。
-->
<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { getAvatarInitial } from '@/lib/initials';
import { highlightMessageContent } from '@/lib/messageSearchHighlight';
import type { InboxInstanceMessageSearchResultData } from '@/types/generated';

const props = defineProps<{
  results: InboxInstanceMessageSearchResultData[];
  search: string;
}>();

const emit = defineEmits<{
  (event: 'select', result: InboxInstanceMessageSearchResultData): void;
}>();

const { t } = useI18n();
const { formatRelativeShortWithTooltip } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

function contactDisplayName(
  result: InboxInstanceMessageSearchResultData,
): string {
  return formatVisitorName(result.contact_name, result.contact_id);
}

function senderPrefix(result: InboxInstanceMessageSearchResultData): string {
  return result.sender_name || result.role_label || '';
}
</script>

<template>
  <div>
    <div
      class="sticky top-0 z-10 border-y bg-muted/80 px-3 py-1.5 text-xs text-muted-foreground backdrop-blur"
    >
      {{ t('聊天记录') }}
    </div>
    <div class="divide-y">
      <button
        v-for="result in props.results"
        :key="result.id"
        type="button"
        class="flex w-full cursor-pointer gap-3 px-3 py-3 text-left transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
        @click="emit('select', result)"
      >
        <Avatar class="size-10 shrink-0 rounded-md">
          <AvatarImage
            v-if="result.contact_avatar_url"
            :src="result.contact_avatar_url"
            :alt="contactDisplayName(result)"
          />
          <AvatarFallback class="rounded-md bg-muted-foreground/10 text-xs">
            {{ getAvatarInitial(result.contact_name) }}
          </AvatarFallback>
        </Avatar>
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
          <div class="flex min-w-0 items-baseline gap-2">
            <span class="min-w-0 flex-1 truncate text-sm leading-5 font-medium">
              {{ contactDisplayName(result) }}
            </span>
            <span
              class="shrink-0 text-[10px] leading-4 text-muted-foreground tabular-nums"
              :title="formatRelativeShortWithTooltip(result.occurred_at).full"
            >
              {{ formatRelativeShortWithTooltip(result.occurred_at).short }}
            </span>
          </div>
          <div
            class="line-clamp-2 min-w-0 text-xs leading-5 break-words text-muted-foreground"
          >
            <span v-if="senderPrefix(result)">
              {{ senderPrefix(result) }}：
            </span>
            <!-- eslint-disable-next-line vue/no-v-html -->
            <span
              v-html="highlightMessageContent(result.matched_content, search)"
            />
          </div>
        </div>
      </button>
    </div>
  </div>
</template>
