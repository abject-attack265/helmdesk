<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import type { PermissionGroupData } from '@/types/generated';
import { computed, ref } from 'vue';

const props = defineProps<{
  groups: PermissionGroupData[];
  modelValue: string[];
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string[]];
}>();

const { t } = useI18n();
const open = ref(false);
const draft = ref<string[]>([]);

const allValues = computed(() =>
  props.groups.flatMap((group) =>
    group.permissions.map((permission) => String(permission.value)),
  ),
);

const openDialog = () => {
  if (props.disabled) {
    return;
  }

  draft.value = [...props.modelValue];
  open.value = true;
};

const toggle = (value: string | number, checked: boolean) => {
  const permission = String(value);

  draft.value = checked
    ? [...new Set([...draft.value, permission])]
    : draft.value.filter((item) => item !== permission);
};

const confirm = () => {
  emit('update:modelValue', [...draft.value]);
  open.value = false;
};
</script>

<template>
  <div>
    <Button
      type="button"
      variant="outline"
      :disabled="props.disabled"
      @click="openDialog"
    >
      {{ t('配置权限') }}
      <Badge v-if="props.modelValue.length" variant="secondary" class="ml-1.5">
        {{ t('已选 {count} 项', { count: props.modelValue.length }) }}
      </Badge>
      <span v-else class="ml-1.5 text-muted-foreground">{{ t('未分配') }}</span>
    </Button>

    <Dialog :open="open" @update:open="open = $event">
      <DialogContent class="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{{ t('配置权限') }}</DialogTitle>
          <DialogDescription>{{
            t('选择这名客服可以使用的功能。')
          }}</DialogDescription>
        </DialogHeader>

        <div class="flex items-center justify-between border-b pb-2">
          <span class="text-sm text-muted-foreground">
            {{ t('已选 {count} 项', { count: draft.length }) }}
          </span>
          <div class="flex gap-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              @click="draft = [...allValues]"
            >
              {{ t('全选') }}
            </Button>
            <Button type="button" variant="ghost" size="sm" @click="draft = []">
              {{ t('清空所选') }}
            </Button>
          </div>
        </div>

        <div class="max-h-[60vh] space-y-5 overflow-y-auto pr-1">
          <div v-for="group in props.groups" :key="group.key" class="space-y-3">
            <div class="text-sm font-medium">{{ group.label }}</div>
            <div class="grid gap-2 sm:grid-cols-2">
              <label
                v-for="permission in group.permissions"
                :key="String(permission.value)"
                class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm"
              >
                <Checkbox
                  :model-value="draft.includes(String(permission.value))"
                  @update:model-value="
                    (checked) => toggle(permission.value, checked === true)
                  "
                />
                <span>{{ permission.label }}</span>
              </label>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" @click="open = false">{{
            t('取消')
          }}</Button>
          <Button type="button" @click="confirm">{{ t('确定') }}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </div>
</template>
