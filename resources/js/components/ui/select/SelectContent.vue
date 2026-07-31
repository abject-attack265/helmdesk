<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import type { SelectContentEmits, SelectContentProps } from 'reka-ui';
import {
    SelectContent,
    SelectPortal,
    SelectViewport,
    useForwardPropsEmits,
} from 'reka-ui';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<
        SelectContentProps & {
            class?: HTMLAttributes['class'];
        }
    >(),
    {
        position: 'popper',
        sideOffset: 4,
    },
);

const emits = defineEmits<SelectContentEmits>();
const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
    <SelectPortal>
        <SelectContent
            v-bind="{ ...forwarded, ...$attrs }"
            :class="
                cn(
                    'relative z-50 min-w-[8rem] max-h-96 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                    props.position === 'popper' &&
                        'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
                    props.class,
                )
            "
        >
            <div class="flex max-h-96 flex-col">
                <slot name="header" />

                <SelectViewport
                    :class="
                        cn(
                            'min-h-0 flex-1 p-1',
                            props.position === 'popper' &&
                                'w-full min-w-[var(--reka-select-trigger-width)]',
                        )
                    "
                >
                    <slot />
                </SelectViewport>
            </div>
        </SelectContent>
    </SelectPortal>
</template>
