<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { SelectTriggerProps } from 'reka-ui';
import { SelectIcon, SelectTrigger } from 'reka-ui';
import { ChevronDown } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = defineProps<
    SelectTriggerProps & {
        class?: HTMLAttributes['class'];
    }
>();
</script>

<template>
    <SelectTrigger
        v-bind="props"
        :class="
            cn(
                'flex h-10 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50',
                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                'data-[placeholder]:text-muted-foreground',
                'dark:bg-input/30',
                props.class,
            )
        "
    >
        <slot />
        <SelectIcon as-child>
            <ChevronDown class="h-4 w-4 opacity-50" />
        </SelectIcon>
    </SelectTrigger>
</template>
