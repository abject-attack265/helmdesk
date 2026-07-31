<!--
  知识库分组创建与编辑弹窗，支持命名并选择上级分组。
-->
<script setup lang="ts">
import KnowledgeBase from '@/actions/App/Actions/KnowledgeBase';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';
import type { KnowledgeGroupData } from '@/types/generated';
import { router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
  open: boolean;
  mode: 'create' | 'edit';
  knowledgeBaseId: string;
  /** 知识库内的顶级分组，用于渲染父级选项。 */
  groups: Array<KnowledgeGroupData>;
  /** 编辑目标（仅编辑模式使用）。 */
  group?: KnowledgeGroupData | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
}>();

const { t } = useI18n();

const NONE_VALUE = '__none__';

const selectedParentId = ref(NONE_VALUE);
const initialFormSnapshot = ref('');

const form = useForm({
  name: '',
  parent_id: '',
});

const isEditMode = computed(() => props.mode === 'edit');

const editingGroupHasChildren = computed(
  () =>
    isEditMode.value &&
    !!props.group?.children &&
    props.group.children.length > 0,
);

/**
 * 编辑顶级分组（含子分组）时，由于 2 级限制，不能再被挂到其它分组下；
 * 编辑分组时也要把自身从「上级分组」选项里去掉。
 */
const parentOptions = computed(() => {
  if (isEditMode.value && editingGroupHasChildren.value) {
    return [];
  }
  if (isEditMode.value && props.group) {
    return props.groups.filter(
      (g) => !g.is_default && g.id !== props.group?.id,
    );
  }
  return props.groups.filter((g) => !g.is_default);
});

const title = computed(() =>
  isEditMode.value ? t('编辑分组') : t('添加分组'),
);

const submitLabel = computed(() => (isEditMode.value ? t('保存') : t('添加')));

function currentFormSnapshot(): string {
  return JSON.stringify({
    name: form.name,
    parent_id: selectedParentId.value,
  });
}

function snapshotInitial(): void {
  initialFormSnapshot.value = currentFormSnapshot();
}

const isDirty = computed(
  () => currentFormSnapshot() !== initialFormSnapshot.value,
);
let removeBeforeListener: (() => void) | null = null;

function initForm(): void {
  form.clearErrors();
  if (isEditMode.value && props.group) {
    form.name = props.group.name;
    selectedParentId.value = props.group.parent_id ?? NONE_VALUE;
    snapshotInitial();
    return;
  }

  form.name = '';
  selectedParentId.value = NONE_VALUE;
  snapshotInitial();
}

watch(
  () => props.open,
  (open) => {
    if (open) {
      initForm();
    } else {
      form.reset();
      form.clearErrors();
    }
  },
);

function submit(): void {
  form.parent_id =
    selectedParentId.value === NONE_VALUE ? '' : selectedParentId.value;

  const onSuccess = () => {
    snapshotInitial();
    emit('update:open', false);
  };

  if (isEditMode.value && props.group) {
    form.put(
      KnowledgeBase.Group.UpdateKnowledgeGroupAction.url({
        knowledgeBase: props.knowledgeBaseId,
        group: props.group.id,
      }),
      { preserveScroll: true, onSuccess },
    );
    return;
  }

  form.post(
    KnowledgeBase.Group.CreateKnowledgeGroupAction.url({
      knowledgeBase: props.knowledgeBaseId,
    }),
    { preserveScroll: true, onSuccess },
  );
}

function confirmDiscardIfDirty(): boolean {
  if (form.processing) {
    return false;
  }
  if (!isDirty.value) {
    return true;
  }

  return window.confirm(t('内容尚未保存，确定离开吗？未保存的修改会丢失。'));
}

function updateOpen(open: boolean): void {
  if (!open && !confirmDiscardIfDirty()) {
    return;
  }
  emit('update:open', open);
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (!props.open || (!isDirty.value && !form.processing)) {
    return;
  }

  event.preventDefault();
  event.returnValue = '';
}

onMounted(() => {
  removeBeforeListener = router.on('before', (event) => {
    if (
      event.detail.visit.method === 'get' &&
      props.open &&
      !confirmDiscardIfDirty()
    ) {
      event.preventDefault();
    }
  });
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  removeBeforeListener?.();
  window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
  <Dialog :open="open" @update:open="updateOpen">
    <DialogContent class="sm:max-w-sm" :show-close-button="!form.processing">
      <DialogHeader class="space-y-3">
        <DialogTitle>{{ title }}</DialogTitle>
        <DialogDescription>
          {{ t('最多可创建两级分组。') }}
        </DialogDescription>
      </DialogHeader>

      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-2">
          <Label for="group-name" required>{{ t('分组名称') }}</Label>
          <Input
            id="group-name"
            v-model="form.name"
            maxlength="120"
            :disabled="form.processing"
            required
          />
          <p v-if="form.errors.name" class="text-xs text-destructive">
            {{ form.errors.name }}
          </p>
        </div>

        <div class="grid gap-2">
          <Label for="group-parent">{{ t('放在') }}</Label>
          <Select
            v-model="selectedParentId"
            :disabled="form.processing || editingGroupHasChildren"
          >
            <SelectTrigger id="group-parent" class="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="NONE_VALUE">
                {{ t('不放入其他分组') }}
              </SelectItem>
              <SelectItem v-for="g in parentOptions" :key="g.id" :value="g.id">
                {{ g.name }}
              </SelectItem>
            </SelectContent>
          </Select>
          <p
            v-if="editingGroupHasChildren"
            class="text-xs text-muted-foreground"
          >
            {{ t('这个分组中还有子分组，请先移动或删除它们。') }}
          </p>
          <p v-if="form.errors.parent_id" class="text-xs text-destructive">
            {{ form.errors.parent_id }}
          </p>
        </div>

        <DialogFooter class="gap-2">
          <Button
            type="button"
            variant="secondary"
            :disabled="form.processing"
            @click="updateOpen(false)"
          >
            {{ t('取消') }}
          </Button>

          <Button type="submit" :disabled="form.processing">
            {{ submitLabel }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
