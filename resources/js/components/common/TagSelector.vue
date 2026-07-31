<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { useI18n } from '@/composables/useI18n';
import { X } from '@lucide/vue';
import { computed, ref } from 'vue';

interface TagOption {
  id: string;
  name: string;
  color: string | null;
}

const props = defineProps<{
  options: TagOption[];
  selectedTagIds: string[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  attach: [tagId: string];
  detach: [tagId: string];
}>();

const { t } = useI18n();
const popoverOpen = ref(false);
const searchQuery = ref('');

const selectedTags = computed(() =>
  props.options.filter((opt) => props.selectedTagIds.includes(opt.id)),
);

const filteredOptions = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();
  return props.options
    .filter((opt) => !props.selectedTagIds.includes(opt.id))
    .filter((opt) => !query || opt.name.toLowerCase().includes(query));
});

const handleAttach = (tagId: string) => {
  emit('attach', tagId);
  searchQuery.value = '';
  popoverOpen.value = false;
};

const handleDetach = (tagId: string) => {
  emit('detach', tagId);
};
</script>

<template>
  <div class="flex flex-wrap items-center gap-1.5">
    <Badge
      v-for="tag in selectedTags"
      :key="tag.id"
      class="flex items-center gap-1.5 border bg-background pr-1 text-foreground shadow-sm"
    >
      <span
        class="h-2 w-2 shrink-0 rounded-full"
        :style="{ backgroundColor: tag.color ?? '#94a3b8' }"
      />
      {{ tag.name }}
      <button
        class="ml-0.5 rounded-sm text-muted-foreground opacity-70 hover:text-foreground hover:opacity-100"
        :disabled="disabled"
        @click.stop="handleDetach(tag.id)"
      >
        <X class="h-3 w-3" />
      </button>
    </Badge>

    <Popover v-model:open="popoverOpen">
      <PopoverTrigger as-child>
        <Button
          variant="outline"
          size="sm"
          class="h-6 px-2 text-xs"
          :disabled="disabled"
        >
          {{ t('添加标签') }}
        </Button>
      </PopoverTrigger>
      <PopoverContent class="w-56 p-2" align="start">
        <Input v-model="searchQuery" class="mb-2 h-8 text-sm" />
        <div class="max-h-40 overflow-y-auto">
          <button
            v-for="opt in filteredOptions"
            :key="opt.id"
            class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent"
            @click="handleAttach(opt.id)"
          >
            <span
              class="h-3 w-3 shrink-0 rounded-full"
              :style="{ backgroundColor: opt.color ?? '#94a3b8' }"
            />
            {{ opt.name }}
          </button>
          <p
            v-if="filteredOptions.length === 0"
            class="py-2 text-center text-xs text-muted-foreground"
          >
            {{ t('暂无可用标签') }}
          </p>
        </div>
      </PopoverContent>
    </Popover>
  </div>
</template>
