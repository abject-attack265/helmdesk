<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useI18n } from '@/composables/useI18n';
import {
  isLikelyValidDialCode,
  normalizePhoneDialCode,
  phoneDialCodeOptions,
} from '@/lib/phone';
import { cn } from '@/lib/utils';
import { onClickOutside, useVModel } from '@vueuse/core';
import { Check, ChevronDown, Search } from '@lucide/vue';
import type { CSSProperties, HTMLAttributes } from 'vue';
import { computed, nextTick, onUnmounted, ref } from 'vue';

const props = defineProps<{
  modelValue?: string;
  disabled?: boolean;
  emptyText?: string;
  align?: 'start' | 'end';
  portal?: boolean;
  class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', payload: string): void;
}>();

const { t } = useI18n();

const modelValue = useVModel(props, 'modelValue', emit, {
  passive: true,
  defaultValue: '',
});

const rootRef = ref<HTMLElement | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);
const searchQuery = ref('');
const open = ref(false);
const dropdownStyle = ref<CSSProperties>({});

const normalizedModelValue = computed(() =>
  normalizePhoneDialCode(modelValue.value || ''),
);

const filteredOptions = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();

  if (query === '') {
    return phoneDialCodeOptions;
  }

  return phoneDialCodeOptions.filter((option) => {
    return (
      option.label.toLowerCase().includes(query) ||
      option.description.toLowerCase().includes(query) ||
      option.keywords.some((keyword) => keyword.includes(query))
    );
  });
});

const customDialCode = computed(() => {
  const normalized = normalizePhoneDialCode(searchQuery.value);

  if (!isLikelyValidDialCode(normalized)) {
    return null;
  }

  if (phoneDialCodeOptions.some((option) => option.value === normalized)) {
    return null;
  }

  return normalized;
});

const selectedOption = computed(() => {
  return phoneDialCodeOptions.find(
    (option) => option.value === normalizedModelValue.value,
  );
});

const triggerLabel = computed(() => {
  return selectedOption.value?.label || normalizedModelValue.value || '';
});

const triggerFlag = computed(() => selectedOption.value?.flag || '');

const dropdownClass = computed(() =>
  props.portal
    ? 'fixed z-[100] rounded-md border bg-background p-2 shadow-md'
    : cn(
        'absolute z-50 mt-2 w-80 max-w-[calc(100vw-1rem)] rounded-md border bg-background p-2 shadow-md',
        props.align === 'end' ? 'right-0' : 'left-0',
      ),
);

const positionDropdown = (): void => {
  const root = rootRef.value;
  if (!root || typeof window === 'undefined') {
    return;
  }

  const viewportPadding = 8;
  const rect = root.getBoundingClientRect();
  const width = Math.min(320, window.innerWidth - viewportPadding * 2);
  const desiredLeft = props.align === 'end' ? rect.right - width : rect.left;
  const left = Math.min(
    window.innerWidth - width - viewportPadding,
    Math.max(viewportPadding, desiredLeft),
  );

  dropdownStyle.value = {
    width: `${width}px`,
    top: `${rect.bottom + 8}px`,
    left: `${left}px`,
  };
};

const closeMenu = () => {
  open.value = false;
  searchQuery.value = '';
  window.removeEventListener('resize', positionDropdown);
  window.removeEventListener('scroll', positionDropdown, true);
};

const openMenu = async () => {
  if (props.disabled) {
    return;
  }

  open.value = true;
  searchQuery.value = '';

  await nextTick();
  if (props.portal) {
    positionDropdown();
    window.addEventListener('resize', positionDropdown);
    window.addEventListener('scroll', positionDropdown, true);
  }
  searchInputRef.value?.focus();
};

const toggleMenu = async () => {
  if (open.value) {
    closeMenu();

    return;
  }

  await openMenu();
};

const selectDialCode = (value: string) => {
  modelValue.value = value;
  closeMenu();
};

const handleSearchKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    closeMenu();

    return;
  }

  if (event.key !== 'Enter' || event.isComposing) {
    return;
  }

  event.preventDefault();

  if (customDialCode.value) {
    selectDialCode(customDialCode.value);

    return;
  }

  if (filteredOptions.value[0]) {
    selectDialCode(filteredOptions.value[0].value);
  }
};

onClickOutside(
  rootRef,
  () => {
    closeMenu();
  },
  { ignore: [dropdownRef] },
);

onUnmounted(() => {
  window.removeEventListener('resize', positionDropdown);
  window.removeEventListener('scroll', positionDropdown, true);
});
</script>

<template>
  <div ref="rootRef" class="relative">
    <Button
      type="button"
      variant="outline"
      :disabled="disabled"
      :class="
        cn(
          'h-9 w-full justify-between px-2 font-normal',
          !triggerLabel && 'text-muted-foreground',
          props.class,
        )
      "
      @click="toggleMenu"
    >
      <span class="flex min-w-0 flex-1 items-center gap-1.5">
        <span v-if="triggerFlag" class="shrink-0 text-base leading-none">
          {{ triggerFlag }}
        </span>
        <span v-if="triggerLabel" class="shrink-0 whitespace-nowrap">
          {{ triggerLabel }}
        </span>
        <span v-else class="truncate">
          {{ t('国家码') }}
        </span>
      </span>
      <ChevronDown class="ml-1 h-4 w-4 shrink-0 text-muted-foreground" />
    </Button>

    <Teleport to="body" :disabled="!props.portal">
      <div
        v-if="open"
        ref="dropdownRef"
        :class="dropdownClass"
        :style="props.portal ? dropdownStyle : undefined"
      >
        <div class="relative">
          <Search
            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
          />
          <Input
            ref="searchInputRef"
            v-model="searchQuery"
            class="pl-9"
            @keydown="handleSearchKeydown"
          />
        </div>

        <div class="mt-2 max-h-64 overflow-y-auto">
          <button
            v-if="customDialCode"
            type="button"
            class="flex w-full items-center justify-between rounded-sm px-3 py-2 text-left text-sm hover:bg-muted"
            @click="selectDialCode(customDialCode)"
          >
            <div class="min-w-0">
              <div class="font-medium">{{ customDialCode }}</div>
              <div class="text-xs text-muted-foreground">
                {{ `使用 ${customDialCode}` }}
              </div>
            </div>
            <Check class="h-4 w-4 text-primary" />
          </button>

          <button
            v-for="option in filteredOptions"
            :key="`${option.value}-${option.description}`"
            type="button"
            class="flex w-full items-center gap-3 rounded-sm px-3 py-2 text-left text-sm hover:bg-muted"
            :title="`${option.description} ${option.value}`"
            @click="selectDialCode(option.value)"
          >
            <span class="w-5 shrink-0 text-base leading-none">
              {{ option.flag }}
            </span>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-medium">
                {{ option.description }}
              </div>
              <div class="text-xs text-muted-foreground">
                {{ option.countryCode }}
              </div>
            </div>
            <span class="shrink-0 font-medium text-foreground">
              {{ option.label }}
            </span>
            <Check
              class="h-4 w-4 shrink-0 text-primary"
              :class="
                option.value === normalizedModelValue
                  ? 'opacity-100'
                  : 'opacity-0'
              "
            />
          </button>

          <div
            v-if="filteredOptions.length === 0 && !customDialCode"
            class="px-3 py-6 text-center text-sm text-muted-foreground"
          >
            {{ emptyText || '没有匹配的国家码' }}
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
