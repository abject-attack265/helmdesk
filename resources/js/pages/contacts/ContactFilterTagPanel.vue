<!--
  联系人筛选中的标签面板。
-->
<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { useI18n } from '@/composables/useI18n';
import type {
  EnumOptionData,
  TagMatchMode,
  TagOptionData,
} from '@/types/generated';
import { Check, X } from '@lucide/vue';

const { t } = useI18n();

const props = defineProps<{
  availableTags: TagOptionData[];
  matchModeOptions: EnumOptionData[];
  includeIds: string[];
  excludeIds: string[];
  includeMode: TagMatchMode;
  excludeMode: TagMatchMode;
  untaggedOnly: boolean;
}>();

const emit = defineEmits<{
  'update:includeIds': [value: string[]];
  'update:excludeIds': [value: string[]];
  'update:includeMode': [value: TagMatchMode];
  'update:excludeMode': [value: TagMatchMode];
  'update:untaggedOnly': [value: boolean];
  navigate: [];
}>();

const isIncluded = (tagId: string): boolean =>
  !props.untaggedOnly && props.includeIds.includes(tagId);

const isExcluded = (tagId: string): boolean =>
  !props.untaggedOnly && props.excludeIds.includes(tagId);

const withoutId = (list: string[], tagId: string): string[] =>
  list.filter((id) => id !== tagId);

/** 包含和排除互斥；选择任一标签时关闭只看无标签。 */
const toggleInclude = (tagId: string) => {
  if (props.untaggedOnly) {
    emit('update:untaggedOnly', false);
    emit('update:includeIds', [tagId]);
    emit('update:excludeIds', []);
    emit('navigate');
    return;
  }

  if (props.includeIds.includes(tagId)) {
    emit('update:includeIds', withoutId(props.includeIds, tagId));
  } else {
    emit('update:excludeIds', withoutId(props.excludeIds, tagId));
    emit('update:includeIds', [...props.includeIds, tagId]);
  }

  emit('navigate');
};

const toggleExclude = (tagId: string) => {
  if (props.untaggedOnly) {
    emit('update:untaggedOnly', false);
    emit('update:includeIds', []);
    emit('update:excludeIds', [tagId]);
    emit('navigate');
    return;
  }

  if (props.excludeIds.includes(tagId)) {
    emit('update:excludeIds', withoutId(props.excludeIds, tagId));
  } else {
    emit('update:includeIds', withoutId(props.includeIds, tagId));
    emit('update:excludeIds', [...props.excludeIds, tagId]);
  }

  emit('navigate');
};

const setIncludeMode = (mode: TagMatchMode) => {
  if (props.includeMode === mode) {
    return;
  }
  emit('update:includeMode', mode);
  if (props.includeIds.length > 0 && !props.untaggedOnly) {
    emit('navigate');
  }
};

const setExcludeMode = (mode: TagMatchMode) => {
  if (props.excludeMode === mode) {
    return;
  }
  emit('update:excludeMode', mode);
  if (props.excludeIds.length > 0 && !props.untaggedOnly) {
    emit('navigate');
  }
};

const toggleUntaggedOnly = (value: boolean) => {
  emit('update:untaggedOnly', value);
  if (value) {
    emit('update:includeIds', []);
    emit('update:excludeIds', []);
  }
  emit('navigate');
};
</script>

<template>
  <div>
    <div class="border-b px-3 py-2">
      <label class="flex cursor-pointer items-center gap-2 text-sm">
        <Checkbox
          :model-value="untaggedOnly"
          @update:model-value="(v) => toggleUntaggedOnly(Boolean(v))"
        />
        <span>{{ t('只看没有标签的联系人') }}</span>
      </label>
    </div>

    <div v-if="!untaggedOnly">
      <div
        class="grid grid-cols-2 gap-3 border-b bg-muted/30 px-3 py-2 text-xs"
      >
        <div>
          <div class="mb-1 flex items-center gap-1.5 text-muted-foreground">
            <Check class="h-3 w-3 text-foreground/70" />
            <span>{{ t('包含') }}</span>
            <span
              v-if="includeIds.length > 0"
              class="font-medium text-foreground"
              >({{ includeIds.length }})</span
            >
          </div>
          <div class="flex rounded-md border bg-background p-0.5">
            <button
              v-for="opt in matchModeOptions"
              :key="`inc-${opt.value}`"
              type="button"
              class="flex-1 rounded px-2 py-0.5 transition-colors"
              :class="
                includeMode === opt.value
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:bg-muted'
              "
              @click="setIncludeMode(opt.value as TagMatchMode)"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>
        <div>
          <div class="mb-1 flex items-center gap-1.5 text-muted-foreground">
            <X class="h-3 w-3 text-foreground/70" />
            <span>{{ t('排除') }}</span>
            <span
              v-if="excludeIds.length > 0"
              class="font-medium text-foreground"
              >({{ excludeIds.length }})</span
            >
          </div>
          <div class="flex rounded-md border bg-background p-0.5">
            <button
              v-for="opt in matchModeOptions"
              :key="`exc-${opt.value}`"
              type="button"
              class="flex-1 rounded px-2 py-0.5 transition-colors"
              :class="
                excludeMode === opt.value
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:bg-muted'
              "
              @click="setExcludeMode(opt.value as TagMatchMode)"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>
      </div>

      <div class="max-h-64 space-y-0.5 overflow-y-auto p-1.5">
        <div
          v-for="tagOpt in availableTags"
          :key="tagOpt.id"
          class="group flex items-center gap-2 rounded-sm px-1.5 py-1 text-sm hover:bg-accent"
        >
          <span
            class="h-2.5 w-2.5 shrink-0 rounded-full"
            :style="{ backgroundColor: tagOpt.color ?? '#94a3b8' }"
          />
          <span class="flex-1 truncate">{{ tagOpt.name }}</span>
          <div class="flex gap-1">
            <button
              type="button"
              :title="t('包含此标签')"
              :aria-label="t('包含此标签')"
              :aria-pressed="isIncluded(tagOpt.id)"
              class="flex h-6 w-6 items-center justify-center rounded-md border transition-colors"
              :class="
                isIncluded(tagOpt.id)
                  ? 'border-foreground bg-foreground text-background shadow-sm ring-1 ring-border'
                  : 'border-border text-muted-foreground hover:border-foreground hover:bg-muted hover:text-foreground'
              "
              @click="toggleInclude(tagOpt.id)"
            >
              <Check
                class="h-3.5 w-3.5"
                :class="
                  isIncluded(tagOpt.id)
                    ? 'text-background'
                    : 'text-muted-foreground'
                "
              />
            </button>
            <button
              type="button"
              :title="t('排除此标签')"
              :aria-label="t('排除此标签')"
              :aria-pressed="isExcluded(tagOpt.id)"
              class="flex h-6 w-6 items-center justify-center rounded-md border transition-colors"
              :class="
                isExcluded(tagOpt.id)
                  ? 'border-foreground bg-foreground text-background shadow-sm ring-1 ring-rose-500/20'
                  : 'border-border text-muted-foreground hover:border-foreground hover:bg-muted hover:text-foreground'
              "
              @click="toggleExclude(tagOpt.id)"
            >
              <X
                class="h-3.5 w-3.5"
                :class="
                  isExcluded(tagOpt.id)
                    ? 'text-background'
                    : 'text-rose-600/80 dark:text-rose-400/80'
                "
              />
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
