<!--
  收件箱中的集成业务信息面板，使用 ContactPanelData 分区展示联系人在外部系统中的资料。
-->
<script setup lang="ts">
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useI18n } from '@/composables/useI18n';
import KeyValueBlock from '@/pages/inbox/panel-blocks/KeyValueBlock.vue';
import ListBlock from '@/pages/inbox/panel-blocks/ListBlock.vue';
import type {
  ContactPanelData,
  ContactPanelSectionData,
} from '@/types/generated';
import { ChevronDown } from '@lucide/vue';

const props = defineProps<{ panel: ContactPanelData }>();

const { t } = useI18n();

/** 统计分区中展示的信息条数。 */
function sectionItemCount(section: ContactPanelSectionData): number {
  return section.blocks.reduce(
    (total, block) => total + block.rows.length + block.items.length,
    0,
  );
}
</script>

<template>
  <div class="space-y-3">
    <div class="min-w-0">
      <div class="truncate text-sm font-medium text-foreground">
        {{ props.panel.integration_name }}
      </div>
      <div class="truncate text-xs text-muted-foreground">
        {{ props.panel.provider_label }}
      </div>
    </div>

    <Collapsible
      v-for="(section, sectionIndex) in props.panel.sections"
      :key="sectionIndex"
      :default-open="!section.collapsed_by_default"
      class="space-y-2"
    >
      <CollapsibleTrigger
        class="group flex w-full items-center justify-between gap-2 text-left"
      >
        <span class="flex min-w-0 items-center gap-1.5">
          <span class="truncate text-xs font-medium text-foreground">
            {{ section.title ?? t('业务信息') }}
          </span>
          <span class="text-xs text-muted-foreground">
            {{ sectionItemCount(section) }}
          </span>
        </span>
        <ChevronDown
          class="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180"
        />
      </CollapsibleTrigger>
      <CollapsibleContent class="space-y-3 pt-1">
        <template
          v-for="(block, blockIndex) in section.blocks"
          :key="blockIndex"
        >
          <KeyValueBlock v-if="block.kind === 'key_value'" :block="block" />
          <ListBlock v-else-if="block.kind === 'list'" :block="block" />
        </template>
      </CollapsibleContent>
    </Collapsible>
  </div>
</template>
