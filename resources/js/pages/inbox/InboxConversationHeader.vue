<!--
  收件箱会话头部消费 InboxSelectionData、UserOptionData 和 EnumOptionData，
  展示会话状态与可执行操作。
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
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Switch } from '@/components/ui/switch';
import { useI18n } from '@/composables/useI18n';
import { useVisitorDisplay } from '@/composables/useVisitorDisplay';
import { getAvatarInitial } from '@/lib/initials';
import type {
  EnumOptionData,
  InboxSelectionData,
  UserOptionData,
} from '@/types/generated';
import {
  ArrowLeft,
  ChevronDown,
  Eye,
  EyeOff,
  PanelRight,
  RotateCcw,
  Search,
  Star,
  UserRound,
  X,
} from '@lucide/vue';
import { computed } from 'vue';

interface TranslationSourceOption {
  value: string;
  label: string;
}

const props = defineProps<{
  selection: InboxSelectionData;
  inboxStatusLabel: string | null;
  importanceProcessing: boolean;
  claimButtonLabel: string;
  transferTeammates: UserOptionData[];
  conversationCommandProcessing: boolean;
  translationEnabled: boolean;
  translationSourceOptions: TranslationSourceOption[];
  receptionLanguageOptions: EnumOptionData[];
  showTimelineEvents: boolean;
}>();

const emit = defineEmits<{
  back: [];
  'open-context': [];
  search: [];
  claim: [];
  transfer: [teammateId: string];
  'release-to-ai': [];
  translate: [];
  'toggle-timeline-events': [];
  reopen: [];
  close: [];
  'toggle-importance': [];
}>();

const translationPopoverOpen = defineModel<boolean>('translationPopoverOpen', {
  required: true,
});
const translationSourceLocale = defineModel<string>('translationSourceLocale', {
  required: true,
});
const translationTargetLocale = defineModel<string>('translationTargetLocale', {
  required: true,
});
const autoTranslateVisibleMessages = defineModel<boolean>(
  'autoTranslateVisibleMessages',
  { required: true },
);

const { t } = useI18n();
const { formatVisitorName } = useVisitorDisplay();
const canTransferToTeammate = computed(
  () =>
    props.selection.can_transfer_to_teammate &&
    props.transferTeammates.length > 0,
);
const importanceToggleTitle = computed(() =>
  props.selection.contact.is_important ? t('取消重点客户') : t('标为重点客户'),
);
const timelineEventsToggleTitle = computed(() =>
  props.showTimelineEvents ? t('隐藏处理记录') : t('显示处理记录'),
);
</script>

<template>
  <header class="flex shrink-0 items-center gap-3 border-b px-4 py-3">
    <SidebarTrigger class="-ml-2 size-8 shrink-0 md:hidden" />
    <Button
      variant="ghost"
      size="icon"
      class="size-8 shrink-0 md:hidden"
      :aria-label="t('返回会话列表')"
      :title="t('返回会话列表')"
      @click="emit('back')"
    >
      <ArrowLeft class="size-4" />
    </Button>
    <Avatar class="size-9 shrink-0">
      <AvatarImage
        v-if="props.selection.contact.avatar_url"
        :src="props.selection.contact.avatar_url"
        :alt="props.selection.contact.name ?? ''"
      />
      <AvatarFallback class="bg-muted-foreground/10 text-xs">
        {{ getAvatarInitial(props.selection.contact.name) }}
      </AvatarFallback>
    </Avatar>
    <div class="min-w-0 flex-1">
      <div class="flex min-w-0 items-center gap-2">
        <button
          type="button"
          class="inline-flex size-5 shrink-0 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:text-foreground disabled:pointer-events-none disabled:opacity-50"
          :aria-label="importanceToggleTitle"
          :aria-pressed="props.selection.contact.is_important"
          :title="importanceToggleTitle"
          :disabled="
            props.importanceProcessing || props.conversationCommandProcessing
          "
          :class="
            props.selection.contact.is_important
              ? 'text-foreground'
              : 'text-muted-foreground'
          "
          @click="emit('toggle-importance')"
        >
          <Star
            class="size-3.5"
            :class="{ 'fill-current': props.selection.contact.is_important }"
          />
        </button>
        <div class="min-w-0 truncate text-sm font-semibold">
          {{
            formatVisitorName(
              props.selection.contact.name,
              props.selection.contact.id,
            )
          }}
        </div>
      </div>
      <div class="flex items-center gap-2 text-xs text-muted-foreground">
        <Badge
          v-if="props.inboxStatusLabel"
          variant="outline"
          class="h-5 px-1.5 text-[10px]"
        >
          {{ props.inboxStatusLabel }}
        </Badge>
        <Badge variant="outline" class="h-5 px-1.5 text-[10px]">
          {{ props.selection.conversation.status_label }}
        </Badge>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <Button
        variant="outline"
        size="sm"
        class="text-muted-foreground lg:hidden"
        :aria-label="t('客户资料')"
        :title="t('客户资料')"
        @click="emit('open-context')"
      >
        <PanelRight class="size-3.5" />
      </Button>
      <Button
        variant="outline"
        size="sm"
        class="gap-1.5 text-muted-foreground"
        :aria-label="t('搜索聊天记录')"
        :title="t('搜索聊天记录')"
        @click="emit('search')"
      >
        <Search class="size-3.5" />
      </Button>
      <Button
        v-if="props.selection.can_claim"
        variant="outline"
        size="sm"
        :disabled="props.conversationCommandProcessing"
        @click="emit('claim')"
      >
        {{ props.claimButtonLabel }}
      </Button>
      <DropdownMenu v-if="canTransferToTeammate">
        <DropdownMenuTrigger as-child>
          <Button
            variant="outline"
            size="sm"
            :disabled="props.conversationCommandProcessing"
          >
            <UserRound class="mr-1 size-3.5" />
            {{ t('转给同事') }}
            <ChevronDown class="ml-1 size-3.5 opacity-60" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-52">
          <DropdownMenuItem
            v-for="teammate in props.transferTeammates"
            :key="teammate.id"
            class="flex flex-col items-start gap-0.5"
            @select="emit('transfer', teammate.id)"
          >
            <span class="max-w-full truncate">{{ teammate.name }}</span>
            <span
              v-if="teammate.email"
              class="max-w-full truncate text-xs text-muted-foreground"
            >
              {{ teammate.email }}
            </span>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <Button
        v-if="props.selection.can_release_to_ai"
        variant="outline"
        size="sm"
        :disabled="props.conversationCommandProcessing"
        @click="emit('release-to-ai')"
      >
        {{
          props.selection.release_to_ai_will_use_ai
            ? t('交给 AI')
            : t('放回待接待')
        }}
      </Button>
      <Popover
        v-if="props.selection.can_translate_messages"
        v-model:open="translationPopoverOpen"
      >
        <PopoverTrigger as-child>
          <Button
            variant="outline"
            size="sm"
            class="gap-1.5"
            :class="
              props.translationEnabled
                ? 'text-foreground'
                : 'text-muted-foreground'
            "
            :aria-pressed="props.translationEnabled"
          >
            {{ t('翻译') }}
          </Button>
        </PopoverTrigger>
        <PopoverContent align="end" class="w-80 space-y-3 p-3">
          <div class="flex items-center gap-2">
            <Select v-model="translationSourceLocale">
              <SelectTrigger class="min-w-0 flex-1" :aria-label="t('原文语言')">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="option in props.translationSourceOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <span class="shrink-0 text-muted-foreground" aria-hidden="true">
              →
            </span>
            <Select v-model="translationTargetLocale">
              <SelectTrigger class="min-w-0 flex-1" :aria-label="t('翻译为')">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="option in props.receptionLanguageOptions"
                    :key="String(option.value)"
                    :value="String(option.value)"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>
          <Button class="w-full" size="sm" @click="emit('translate')">
            {{ props.translationEnabled ? t('显示原文') : t('翻译') }}
          </Button>
          <div class="flex items-center justify-between">
            <span class="text-sm">{{ t('自动翻译') }}</span>
            <Switch
              v-model="autoTranslateVisibleMessages"
              :aria-label="t('自动翻译')"
            />
          </div>
        </PopoverContent>
      </Popover>
      <DropdownMenu>
        <DropdownMenuTrigger as-child>
          <Button variant="outline" size="sm" :aria-label="t('更多')">
            {{ t('更多') }}
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-48">
          <DropdownMenuItem
            class="gap-2"
            @select="emit('toggle-timeline-events')"
          >
            <Eye v-if="props.showTimelineEvents" class="size-3.5" />
            <EyeOff v-else class="size-3.5" />
            {{ timelineEventsToggleTitle }}
          </DropdownMenuItem>
          <DropdownMenuItem
            v-if="props.selection.can_reopen"
            class="gap-2"
            :disabled="props.conversationCommandProcessing"
            @select="emit('reopen')"
          >
            <RotateCcw class="size-3.5" />
            {{ t('重新打开') }}
          </DropdownMenuItem>
          <DropdownMenuItem
            v-if="props.selection.can_close"
            class="gap-2"
            :disabled="props.conversationCommandProcessing"
            @select="emit('close')"
          >
            <X class="size-3.5" />
            {{ t('结束会话') }}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  </header>
</template>
