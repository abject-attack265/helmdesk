<!--
  业务面板 list 积木块渲染器：可选标题 + 条目列表，每项 title/subtitle/meta（间隔排列）/badge（中性）/link。
  消费 ContactPanelBlockData。
-->
<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { ContactPanelBlockData } from '@/types/generated';

const props = defineProps<{ block: ContactPanelBlockData }>();
</script>

<template>
  <div class="space-y-2">
    <div
      v-if="props.block.title"
      class="text-xs font-medium text-muted-foreground"
    >
      {{ props.block.title }}
    </div>
    <ul class="space-y-2">
      <li
        v-for="(item, index) in props.block.items"
        :key="index"
        class="rounded-md border px-3 py-2"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <component
              :is="item.link ? 'a' : 'span'"
              :href="item.link ?? undefined"
              :target="item.link ? '_blank' : undefined"
              :rel="item.link ? 'noopener noreferrer' : undefined"
              class="block truncate text-sm font-medium text-foreground"
              :class="
                item.link ? 'underline underline-offset-2 hover:opacity-80' : ''
              "
            >
              {{ item.title }}
            </component>
            <div
              v-if="item.subtitle"
              class="mt-0.5 truncate text-xs text-muted-foreground"
            >
              {{ item.subtitle }}
            </div>
          </div>
          <Badge
            v-if="item.badge"
            variant="secondary"
            class="shrink-0 font-normal"
          >
            {{ item.badge }}
          </Badge>
        </div>
        <div
          v-if="item.meta.length > 0"
          class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
        >
          <span v-for="(meta, metaIndex) in item.meta" :key="metaIndex">
            {{ meta }}
          </span>
        </div>
      </li>
    </ul>
  </div>
</template>
