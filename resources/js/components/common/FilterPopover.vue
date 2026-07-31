<script setup lang="ts">
import type { BadgeVariants } from '@/components/ui/badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { useI18n } from '@/composables/useI18n';
import { cn } from '@/lib/utils';
import { ListFilter } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed, watch } from 'vue';

export interface FilterPopoverGroup {
  value: string;
  label: string;
  count?: number | string;
  visible?: boolean;
}

const props = withDefaults(
  defineProps<{
    activeCount?: number;
    activeBadgeLabel?: number | string;
    align?: 'start' | 'center' | 'end';
    badgeClass?: HTMLAttributes['class'];
    badgeVariant?: BadgeVariants['variant'];
    contentClass?: HTMLAttributes['class'];
    defaultGroup?: string;
    groups?: FilterPopoverGroup[];
    iconClass?: HTMLAttributes['class'];
    sideOffset?: number;
    title?: string;
    triggerClass?: HTMLAttributes['class'];
  }>(),
  {
    activeCount: 0,
    align: 'end',
    groups: () => [],
    sideOffset: 4,
  },
);

const emit = defineEmits<{
  clear: [];
}>();

const open = defineModel<boolean>('open', { default: false });
const activeGroup = defineModel<string>('group');

const { t } = useI18n();

const visibleGroups = computed(() =>
  props.groups.filter((group) => group.visible !== false),
);

const hasActiveFilters = computed(() => props.activeCount > 0);
const shouldShowGroupTabs = computed(() => visibleGroups.value.length > 1);
const shouldShowHeader = computed(
  () => shouldShowGroupTabs.value || hasActiveFilters.value || props.title,
);
const activeBadge = computed(() => props.activeBadgeLabel ?? props.activeCount);

watch(
  [visibleGroups, () => props.defaultGroup],
  ([groups, defaultGroup]) => {
    if (groups.length === 0) {
      return;
    }

    if (
      activeGroup.value &&
      groups.some((group) => group.value === activeGroup.value)
    ) {
      return;
    }

    activeGroup.value =
      groups.find((group) => group.value === defaultGroup)?.value ??
      groups[0].value;
  },
  { immediate: true },
);
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        type="button"
        variant="outline"
        size="sm"
        :class="cn('relative', props.triggerClass)"
      >
        <ListFilter :class="cn('mr-1.5 h-4 w-4', props.iconClass)" />
        {{ t('筛选') }}
        <Badge
          v-if="hasActiveFilters"
          :variant="badgeVariant"
          :class="cn('ml-1.5 h-5 min-w-5 px-1 text-xs', props.badgeClass)"
        >
          {{ activeBadge }}
        </Badge>
      </Button>
    </PopoverTrigger>
    <PopoverContent
      :class="cn('w-104 p-0', props.contentClass)"
      :align="align"
      :side-offset="sideOffset"
    >
      <div
        v-if="shouldShowHeader"
        class="flex items-center justify-between gap-2 border-b px-3 py-2"
      >
        <div
          v-if="shouldShowGroupTabs"
          class="flex rounded-md border bg-background p-0.5 text-xs"
        >
          <button
            v-for="group in visibleGroups"
            :key="group.value"
            type="button"
            class="rounded px-2.5 py-1 transition-colors"
            :class="
              activeGroup === group.value
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:bg-muted'
            "
            @click="activeGroup = group.value"
          >
            {{ group.label }}
            <span v-if="group.count" class="ml-1 opacity-70">
              {{ group.count }}
            </span>
          </button>
        </div>
        <span v-else-if="title" class="text-xs font-medium">
          {{ title }}
        </span>
        <span v-else />

        <button
          v-if="hasActiveFilters"
          type="button"
          class="text-xs text-muted-foreground hover:text-foreground"
          @click="emit('clear')"
        >
          {{ t('清空全部') }}
        </button>
      </div>

      <template v-if="visibleGroups.length > 0">
        <div
          v-for="group in visibleGroups"
          :key="group.value"
          v-show="activeGroup === group.value"
        >
          <slot :name="group.value" />
        </div>
      </template>
      <slot v-else />
    </PopoverContent>
  </Popover>
</template>
