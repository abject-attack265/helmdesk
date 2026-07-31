<!--
  文档与问答共用的分组选择弹窗。
-->
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';

const props = defineProps<{
  open: boolean;
  selectId: string;
  groups: Array<{ id: string; label: string }>;
  error?: string;
  processing: boolean;
}>();

const groupId = defineModel<string>('groupId', { required: true });

const emit = defineEmits<{
  'update:open': [open: boolean];
  confirm: [];
}>();

const { t } = useI18n();

function updateOpen(open: boolean): void {
  if (!open && props.processing) {
    return;
  }

  emit('update:open', open);
}
</script>

<template>
  <Dialog :open="open" @update:open="updateOpen">
    <DialogContent class="sm:max-w-sm" :show-close-button="!processing">
      <DialogHeader class="space-y-3">
        <DialogTitle>{{ t('移动到其他分组') }}</DialogTitle>
      </DialogHeader>

      <div class="grid gap-2">
        <Label :for="selectId" required>{{ t('选择分组') }}</Label>
        <Select v-model="groupId" :disabled="processing">
          <SelectTrigger :id="selectId" class="w-full" aria-required="true">
            <SelectValue :placeholder="t('请选择分组')" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="group in groups"
              :key="group.id"
              :value="group.id"
            >
              {{ group.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p v-if="error" class="text-xs text-destructive">
          {{ error }}
        </p>
      </div>

      <DialogFooter class="gap-2">
        <Button
          type="button"
          variant="secondary"
          :disabled="processing"
          @click="updateOpen(false)"
        >
          {{ t('取消') }}
        </Button>
        <Button
          type="button"
          :disabled="processing || !groupId"
          @click="emit('confirm')"
        >
          {{ t('确认移动') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
