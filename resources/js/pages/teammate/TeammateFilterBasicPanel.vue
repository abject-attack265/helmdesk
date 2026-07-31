<!-- 客服列表基础筛选面板。 -->
<script setup lang="ts">
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import type { EnumOptionData } from '@/types/generated';

const { t } = useI18n();

defineProps<{
  onlineStatus: string;
  onlineStatusOptions: EnumOptionData[];
}>();

defineEmits<{
  'update:onlineStatus': [value: string];
}>();
</script>

<template>
  <div class="space-y-3 p-3">
    <div class="grid gap-2">
      <Label>{{ t('在线状态') }}</Label>
      <Select
        :model-value="onlineStatus"
        @update:model-value="$emit('update:onlineStatus', String($event))"
      >
        <SelectTrigger class="h-9 w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">{{ t('全部状态') }}</SelectItem>
          <SelectItem
            v-for="option in onlineStatusOptions"
            :key="String(option.value)"
            :value="String(option.value)"
          >
            {{ option.label }}
          </SelectItem>
        </SelectContent>
      </Select>
    </div>
  </div>
</template>
