<!--
  收件箱 AI 回复助手弹层消费 InboxReplyPolishCandidateData 及助手模式、语气枚举，
  展示可应用的候选回复。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
import { useI18n } from '@/composables/useI18n';
import type {
  EnumOptionData,
  InboxReplyPolishCandidateData,
  ReplyAssistantMode,
  ReplyPolishTone,
} from '@/types/generated';
import { Loader2, PencilLine, RefreshCw, Sparkles } from '@lucide/vue';

type ReplyPolishEnumOption<T extends string> = Omit<EnumOptionData, 'value'> & {
  value: T;
};

const props = defineProps<{
  modeOptions: ReplyPolishEnumOption<ReplyAssistantMode>[];
  toneOptions: ReplyPolishEnumOption<ReplyPolishTone>[];
  candidates: InboxReplyPolishCandidateData[];
  loading: boolean;
  canUse: boolean;
  buttonTitle: string;
  error: string | null;
}>();

const emit = defineEmits<{
  refresh: [];
  apply: [content: string];
}>();

const open = defineModel<boolean>('open', { required: true });
const selectedMode = defineModel<ReplyAssistantMode>('selectedMode', {
  required: true,
});
const selectedTone = defineModel<ReplyPolishTone>('selectedTone', {
  required: true,
});

const { t } = useI18n();

function selectTone(value: unknown): void {
  if (
    typeof value !== 'string' ||
    !props.toneOptions.some((option) => option.value === value)
  ) {
    throw new Error(`收件箱 AI 回复助手收到无效的语气选择：${String(value)}`);
  }

  selectedTone.value = value as ReplyPolishTone;
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        type="button"
        variant="ghost"
        size="icon"
        class="size-6 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground disabled:text-muted-foreground/50"
        :disabled="!props.canUse"
        :aria-label="props.buttonTitle"
        :title="props.buttonTitle"
      >
        <Loader2 v-if="props.loading" class="size-4 animate-spin" />
        <Sparkles v-else class="size-4" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-[min(30rem,calc(100vw-2rem))] p-3" align="start">
      <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2">
          <div class="shrink-0 text-sm font-medium">
            {{ t('帮我写回复') }}
          </div>
          <div class="flex shrink-0 rounded-md border bg-background p-0.5">
            <button
              v-for="option in props.modeOptions"
              :key="option.value"
              type="button"
              class="h-7 rounded-sm px-2.5 text-xs font-medium transition-colors"
              :class="
                selectedMode === option.value
                  ? 'bg-foreground text-background'
                  : 'text-muted-foreground hover:bg-muted hover:text-foreground'
              "
              @click="selectedMode = option.value"
            >
              {{ option.label }}
            </button>
          </div>
          <div class="ml-auto flex shrink-0 items-center gap-1">
            <Select
              :model-value="selectedTone"
              :disabled="props.toneOptions.length === 0"
              @update:model-value="selectTone"
            >
              <SelectTrigger
                class="h-7 w-24 px-2 text-xs shadow-none"
                :aria-label="t('语气')"
              >
                <SelectValue :placeholder="t('请选择语气')" />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  <SelectItem
                    v-for="option in props.toneOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="size-7 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
              :disabled="props.loading || !props.canUse"
              :aria-label="t('重新生成')"
              :title="t('重新生成')"
              @click="emit('refresh')"
            >
              <RefreshCw class="size-4" />
            </Button>
          </div>
        </div>

        <div class="h-56 max-h-[calc(100dvh-17rem)] overflow-y-auto pr-1">
          <div
            v-if="props.loading"
            class="flex h-full items-center justify-center"
          >
            <div class="w-28 space-y-2">
              <div
                class="h-2.5 w-full animate-pulse rounded bg-muted-foreground/25"
              />
              <div
                class="h-2.5 w-5/6 animate-pulse rounded bg-muted-foreground/20"
              />
              <div
                class="h-2.5 w-2/3 animate-pulse rounded bg-muted-foreground/15"
              />
            </div>
          </div>
          <div v-else-if="props.candidates.length > 0" class="space-y-2">
            <div
              v-for="candidate in props.candidates"
              :key="candidate.id"
              class="group flex gap-2 rounded-md border bg-background p-2.5 text-sm leading-6"
            >
              <div class="min-w-0 flex-1 whitespace-pre-wrap">
                {{ candidate.content }}
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                class="size-7 shrink-0 rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                :aria-label="t('使用这条回复')"
                :title="t('使用这条回复')"
                @click="emit('apply', candidate.content)"
              >
                <PencilLine class="size-4" />
              </Button>
            </div>
          </div>
          <div
            v-else
            class="flex h-full items-center justify-center rounded-md border border-dashed px-3 text-center text-xs text-muted-foreground"
            :class="{ 'text-destructive': props.error }"
          >
            {{ props.error ?? t('暂无可用回复') }}
          </div>
        </div>
      </div>
    </PopoverContent>
  </Popover>
</template>
