<!--
  收件箱会话列表项消费 ListConversationItemData，展示联系人、渠道、最近消息和未读数量。
-->
<script setup lang="ts">
import WechatIcon from '@/components/icons/WechatIcon.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { getAvatarInitial } from '@/lib/initials';
import type { ChannelType, ListConversationItemData } from '@/types/generated';
import { Globe, Send, Star } from '@lucide/vue';
import type { Component } from 'vue';

const props = defineProps<{
  conversation: ListConversationItemData;
  active: boolean;
  preview: string;
  unreadCount: number;
}>();

const emit = defineEmits<{
  select: [conversation: ListConversationItemData];
}>();

const { t } = useI18n();
const { formatRelativeShortWithTooltip } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

const channelIconMap: Record<ChannelType, Component> = {
  web: Globe,
  telegram: Send,
  wechat_oa: WechatIcon,
};

function channelIcon(): Component {
  return channelIconMap[props.conversation.channel_type];
}

function contactInitial(): string {
  return getAvatarInitial(props.conversation.contact_name);
}

function formatLastActivity(): {
  short: string;
  full: string;
} {
  const timestamp =
    props.conversation.last_message_at ?? props.conversation.created_at;

  return formatRelativeShortWithTooltip(timestamp);
}

function formatUnreadCount(): string {
  return props.unreadCount > 99 ? '99+' : String(props.unreadCount);
}
</script>

<template>
  <button
    type="button"
    class="flex w-full cursor-pointer gap-3 border-l-2 border-transparent px-3 py-3 text-left transition-colors"
    :class="
      props.active ? 'border-l-foreground bg-secondary' : 'hover:bg-muted/50'
    "
    @click="emit('select', props.conversation)"
  >
    <Avatar class="size-10 shrink-0 rounded-md">
      <AvatarImage
        v-if="props.conversation.contact_avatar_url"
        :src="props.conversation.contact_avatar_url"
        :alt="
          formatVisitorName(
            props.conversation.contact_name,
            props.conversation.contact_id,
          )
        "
      />
      <AvatarFallback class="rounded-md bg-muted-foreground/10 text-xs">
        {{ contactInitial() }}
      </AvatarFallback>
    </Avatar>

    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
      <div class="flex min-w-0 items-baseline gap-2">
        <div class="flex min-w-0 flex-1 items-center gap-1.5">
          <Star
            v-if="props.conversation.contact_is_important"
            class="size-3.5 shrink-0 fill-current text-foreground"
            :title="t('重点客户')"
          />
          <span class="min-w-0 truncate text-sm leading-5 font-medium">
            {{
              formatVisitorName(
                props.conversation.contact_name,
                props.conversation.contact_id,
              )
            }}
          </span>
        </div>
        <div
          class="relative -top-1 flex shrink-0 items-center gap-1 text-[10px] leading-4 text-muted-foreground"
          :title="`${props.conversation.channel_type_label} · ${props.conversation.channel_name}`"
        >
          <component :is="channelIcon()" class="size-3 shrink-0" />
          <span class="max-w-24 truncate">
            {{ props.conversation.channel_name }}
          </span>
        </div>
        <div
          class="relative -top-1 shrink-0 text-[10px] leading-4 text-muted-foreground tabular-nums"
          :title="formatLastActivity().full"
        >
          {{ formatLastActivity().short }}
        </div>
      </div>

      <div class="flex min-w-0 items-center gap-1.5">
        <div
          class="min-w-0 flex-1 truncate text-xs leading-5 text-muted-foreground"
        >
          {{ props.preview }}
        </div>
        <Badge
          v-if="props.unreadCount > 0"
          variant="default"
          class="h-4 shrink-0 px-1.5 text-[10px] tabular-nums"
          :title="t('未读访客消息')"
        >
          {{ formatUnreadCount() }}
        </Badge>
      </div>
    </div>
  </button>
</template>
