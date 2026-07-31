<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { SelectItemProps } from 'reka-ui';
import { SelectItem, SelectItemIndicator, SelectItemText } from 'reka-ui';
import { reactiveOmit } from '@vueuse/core';
import { Check } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = defineProps<
    SelectItemProps & {
        class?: HTMLAttributes['class'];
        // 隐藏选中态对钩，并去掉为对钩预留的左侧留白
        hideIndicator?: boolean;
    }
>();

const delegatedProps = reactiveOmit(props, 'class', 'hideIndicator');
</script>

<template>
    <SelectItem
        v-bind="delegatedProps"
        :class="
            cn(
                'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pr-2 text-sm outline-none',
                hideIndicator ? 'pl-2' : 'pl-8',
                'focus:bg-accent focus:text-accent-foreground',
                'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                props.class,
            )
        "
    >
        <span
            v-if="!hideIndicator"
            class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center"
        >
            <SelectItemIndicator>
                <Check class="h-4 w-4" />
            </SelectItemIndicator>
        </span>

        <SelectItemText>
            <slot />
        </SelectItemText>
    </SelectItem>
</template>
