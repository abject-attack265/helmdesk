<!--
  收件箱联系人搜索结果展示高亮名称和最近活跃线程，消费 InboxContactSearchResultData。
-->
<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { getAvatarInitial } from '@/lib/initials';
import { highlightMessageContent } from '@/lib/messageSearchHighlight';
import type { InboxContactSearchResultData } from '@/types/generated';

const props = defineProps<{
  results: InboxContactSearchResultData[];
  search: string;
}>();

const emit = defineEmits<{
  (event: 'select', result: InboxContactSearchResultData): void;
}>();

const { t } = useI18n();
const { formatRelativeShortWithTooltip } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

function contactDisplayName(result: InboxContactSearchResultData): string {
  return formatVisitorName(result.name, result.id);
}
</script>

<template>
  <div>
    <div
      class="sticky top-0 z-10 border-y bg-muted/80 px-3 py-1.5 text-xs text-muted-foreground backdrop-blur"
    >
      {{ t('联系人') }}
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
            v-if="result.avatar_url"
            :src="result.avatar_url"
            :alt="contactDisplayName(result)"
          />
          <AvatarFallback class="rounded-md bg-muted-foreground/10 text-xs">
            {{ getAvatarInitial(result.name) }}
          </AvatarFallback>
        </Avatar>
        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
          <div class="flex min-w-0 items-baseline gap-2">
            <span class="min-w-0 flex-1 truncate text-sm leading-5 font-medium">
              <!-- eslint-disable-next-line vue/no-v-html -->
              <span
                v-html="
                  highlightMessageContent(contactDisplayName(result), search)
                "
              />
            </span>
            <span
              v-if="result.last_message_at"
              class="shrink-0 text-[10px] leading-4 text-muted-foreground tabular-nums"
              :title="
                formatRelativeShortWithTooltip(result.last_message_at).full
              "
            >
              {{ formatRelativeShortWithTooltip(result.last_message_at).short }}
            </span>
          </div>
          <div
            v-if="result.last_message_preview"
            class="line-clamp-1 min-w-0 text-xs leading-5 break-words text-muted-foreground"
          >
            {{ result.last_message_preview }}
          </div>
        </div>
      </button>
    </div>
  </div>
</template>
