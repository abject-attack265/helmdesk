<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { useI18n } from '@/composables/useI18n';
import { LoaderCircle } from '@lucide/vue';

const props = defineProps<{
  open: boolean;
  title: string;
  description?: string;
  detailTitle?: string;
  detailDescription?: string;
  processing?: boolean;
  confirmLabel?: string;
  processingLabel?: string;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
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
    <DialogContent class="sm:max-w-md" :show-close-button="!processing">
      <DialogHeader class="space-y-3">
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription v-if="description">
          {{ description }}
        </DialogDescription>
      </DialogHeader>

      <div
        v-if="detailTitle || detailDescription"
        class="rounded-md bg-muted/30 p-3 text-sm"
      >
        <div v-if="detailTitle" class="font-medium">{{ detailTitle }}</div>
        <div
          v-if="detailDescription"
          class="text-muted-foreground"
          :class="{ 'mt-1': detailTitle }"
        >
          {{ detailDescription }}
        </div>
      </div>

      <DialogFooter class="gap-2">
        <Button
          variant="outline"
          :disabled="processing"
          @click="updateOpen(false)"
        >
          {{ t('取消') }}
        </Button>
        <Button
          variant="destructive"
          :disabled="processing"
          @click="emit('confirm')"
        >
          <LoaderCircle v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
          {{
            processing
              ? (processingLabel ?? t('删除中...'))
              : (confirmLabel ?? t('确认删除'))
          }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
