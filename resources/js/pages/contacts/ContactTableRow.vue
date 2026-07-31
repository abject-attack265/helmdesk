<!--
  联系人列表行，展示头像、标签、类型、来源和可用操作。
-->
<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useDateTime } from '@/composables/useDateTime';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { getAvatarInitial } from '@/lib/initials';
import type { ListContactItemData } from '@/types/generated';
import { MoreHorizontal, Star } from '@lucide/vue';

const props = defineProps<{
  contact: ListContactItemData;
  isSelected: boolean;
  deleteProcessing: boolean;
}>();

const emit = defineEmits<{
  openDetail: [contact: ListContactItemData];
  requestDelete: [contact: ListContactItemData];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateTime();
const { formatVisitorName } = useVisitorDisplay();

const displayName = (c: ListContactItemData): string => {
  return formatVisitorName(c.name, c.id);
};

const displayIdentity = (c: ListContactItemData): string => {
  return c.primary_email || c.primary_phone || '-';
};

const visibleTags = (c: ListContactItemData) => {
  return c.tags.slice(0, 2);
};

const hiddenTagCount = (c: ListContactItemData): number => {
  return Math.max(c.tags.length - 2, 0);
};

const nameInitial = (c: ListContactItemData): string =>
  getAvatarInitial(c.name);

const typeBadgeVariant = (type: string): 'default' | 'secondary' => {
  return type === 'contact' ? 'default' : 'secondary';
};
</script>

<template>
  <tr
    class="border-t bg-background transition-colors"
    :class="{
      'bg-muted/30': props.isSelected,
    }"
  >
    <td class="px-4 py-3">
      <div class="flex items-center gap-3">
        <Avatar class="h-8 w-8">
          <AvatarImage :src="props.contact.avatar_url" />
          <AvatarFallback class="text-xs">
            {{ nameInitial(props.contact) }}
          </AvatarFallback>
        </Avatar>
        <div class="min-w-0 space-y-1">
          <div class="flex min-w-0 items-center gap-1.5 font-medium">
            <Star
              v-if="props.contact.is_important"
              class="h-3.5 w-3.5 shrink-0 fill-current text-foreground"
              :title="t('重点客户')"
            />
            <span class="truncate">{{ displayName(props.contact) }}</span>
          </div>
          <div
            v-if="props.contact.tags.length > 0"
            class="flex flex-wrap items-center gap-1.5"
          >
            <Badge
              v-for="tag in visibleTags(props.contact)"
              :key="tag.id"
              class="max-w-28 border text-[11px]"
              :style="{
                backgroundColor: tag.color ?? undefined,
                borderColor: tag.color ?? undefined,
                color: tag.color ? 'white' : undefined,
              }"
            >
              <span class="truncate">
                {{ tag.name }}
              </span>
            </Badge>
            <Badge
              v-if="hiddenTagCount(props.contact) > 0"
              class="border-transparent bg-muted text-[11px] text-muted-foreground hover:bg-muted/80"
            >
              +{{ hiddenTagCount(props.contact) }}
            </Badge>
          </div>
        </div>
      </div>
    </td>
    <td class="px-4 py-3 text-muted-foreground">
      {{ displayIdentity(props.contact) }}
    </td>
    <td class="px-4 py-3 text-muted-foreground">
      {{
        props.contact.external_ids.length
          ? props.contact.external_ids.join(', ')
          : '-'
      }}
    </td>
    <td class="px-4 py-3">
      <Badge :variant="typeBadgeVariant(String(props.contact.type.value))">
        {{ props.contact.type.label }}
      </Badge>
    </td>
    <td class="px-4 py-3">
      <Badge
        class="border-transparent bg-muted text-muted-foreground hover:bg-muted/80"
      >
        {{ props.contact.source.label }}
      </Badge>
    </td>
    <td class="px-4 py-3 text-muted-foreground">
      {{
        props.contact.last_seen_at
          ? formatDateTime(props.contact.last_seen_at)
          : '-'
      }}
    </td>
    <td class="w-40 px-4 py-3 whitespace-nowrap" @click.stop>
      <div class="flex min-h-9 items-center justify-end gap-1">
        <Button
          variant="outline"
          size="sm"
          @click="emit('openDetail', props.contact)"
        >
          {{ t('查看') }}
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
          <DropdownMenuContent align="end" class="w-36">
            <DropdownMenuItem
              class="text-destructive focus:text-destructive"
              :disabled="props.deleteProcessing"
              @select="emit('requestDelete', props.contact)"
            >
              {{ t('移到回收站') }}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </td>
  </tr>
</template>
