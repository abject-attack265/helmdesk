<!--
  会话时间线事件行组件。
  渲染后端下发的 event_display 数据，作为右侧低干扰活动消息展示。
-->
<script setup lang="ts">
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import type { TimelineEntryData } from '@/types/generated';
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
  entry: TimelineEntryData;
}>();

const display = computed(() => {
  if (!props.entry.event_display) {
    throw new Error(`Missing event display for ${props.entry.subtype}`);
  }

  return props.entry.event_display;
});

const hasFacts = computed(() => display.value.facts.length > 0);
const hasDetail = computed(() => Boolean(display.value.detail));
const hasExpandableContent = computed(() => hasDetail.value || hasFacts.value);
const expanded = ref(false);

const toneClass = computed<string>(() => {
  switch (display.value.tone) {
    case 'warning':
      return 'bg-muted/70 text-foreground/88 ring-1 ring-foreground/10 hover:bg-muted hover:text-foreground';
    case 'important':
      return 'bg-muted/60 text-foreground/86 ring-1 ring-foreground/10 hover:bg-muted/80 hover:text-foreground';
    case 'muted':
      return 'bg-muted/40 text-foreground/80 hover:bg-muted/55 hover:text-foreground/90';
    case 'normal':
    default:
      return 'bg-muted/45 text-foreground/84 hover:bg-muted/60 hover:text-foreground/92';
  }
});
</script>

<template>
  <div class="flex justify-end">
    <Popover v-if="hasExpandableContent" v-model:open="expanded">
      <PopoverTrigger as-child>
        <button
          type="button"
          :class="[
            'flex max-w-[72%] min-w-0 items-center justify-end gap-1 rounded-md px-2.5 py-0.5 text-right text-[11px] leading-4 font-normal italic shadow-xs transition focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none',
            toneClass,
          ]"
          :aria-expanded="expanded"
        >
          <span class="min-w-0 break-words">
            {{ display.summary }}
          </span>
          <ChevronDown
            :class="[
              'size-3 shrink-0 opacity-60 transition-transform',
              expanded ? 'rotate-180' : '',
            ]"
          />
        </button>
      </PopoverTrigger>
      <PopoverContent
        align="end"
        class="w-80 max-w-[calc(100vw-2rem)] space-y-2 p-3 text-xs"
      >
        <div v-if="display.detail" class="break-words text-foreground">
          {{ display.detail }}
        </div>

        <dl v-if="hasFacts" class="space-y-1.5">
          <div
            v-for="fact in display.facts"
            :key="`${fact.label}:${fact.value}`"
            class="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-2"
          >
            <dt class="text-muted-foreground">{{ fact.label }}</dt>
            <dd class="min-w-0 break-words whitespace-pre-wrap">
              {{ fact.value }}
            </dd>
          </div>
        </dl>
      </PopoverContent>
    </Popover>
    <div
      v-else
      :class="[
        'max-w-[72%] min-w-0 cursor-default rounded-md px-2.5 py-0.5 text-right text-[11px] leading-4 font-normal italic shadow-xs',
        toneClass,
      ]"
    >
      {{ display.summary }}
    </div>
  </div>
</template>
