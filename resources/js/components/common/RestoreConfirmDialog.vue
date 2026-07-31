<!--
  回收站列表中单行的「确认恢复」弹窗，封装触发按钮、详情卡片和取消/恢复操作。
-->
<script setup lang="ts">
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';

defineProps<{
  title: string;
  description?: string;
  processing: boolean;
  submitting: boolean;
  errorMessage?: string;
}>();

const emit = defineEmits<{
  confirm: [];
  'update:open': [value: boolean];
}>();

const { t } = useI18n();
</script>

<template>
  <Dialog @update:open="emit('update:open', $event)">
    <DialogTrigger as-child>
      <Button variant="outline" size="sm" :disabled="processing">
        {{ t('恢复') }}
      </Button>
    </DialogTrigger>
    <DialogContent>
      <DialogHeader class="space-y-3">
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription v-if="description">
          {{ description }}
        </DialogDescription>
      </DialogHeader>

      <div class="rounded-md bg-muted/30 p-3 text-sm">
        <slot />
      </div>

      <InputError :message="errorMessage" />

      <DialogFooter class="gap-2">
        <DialogClose as-child>
          <Button variant="secondary" :disabled="processing">
            {{ t('取消') }}
          </Button>
        </DialogClose>
        <Button
          variant="outline"
          :disabled="processing"
          @click="emit('confirm')"
        >
          {{ submitting ? t('恢复中...') : t('确认恢复') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
