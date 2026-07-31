<!--
  自定义字段创建与编辑对话框。
  创建时可设置内部标识和填写方式。
-->
<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- 控件直接写入共享的 Inertia Form */
import InputError from '@/components/common/InputError.vue';
import OptionListEditor from '@/components/custom-attribute/OptionListEditor.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogClose,
  DialogContent,
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
import type {
  EnumOptionData,
  FormCreateAttributeDefinitionData,
  FormUpdateAttributeDefinitionData,
  ListAttributeDefinitionItemData,
} from '@/types/generated';
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type AttributeForm = InertiaForm<
  FormCreateAttributeDefinitionData | FormUpdateAttributeDefinitionData
>;

const { t } = useI18n();

const props = defineProps<{
  mode: 'create' | 'edit';
  open: boolean;
  form: AttributeForm;
  typeOptions: EnumOptionData[];
  /** 选项型字段的选项列表（v-model） */
  options: Array<{ code: string; label: string }>;
  /** 创建字段时是否已手动修改内部标识 */
  keyManuallyEdited?: boolean;
  /** 编辑字段时展示的只读标识与填写方式 */
  editingDef?: ListAttributeDefinitionItemData | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  'update:options': [value: Array<{ code: string; label: string }>];
  'update:keyManuallyEdited': [value: boolean];
  submit: [];
}>();

const SELECT_TYPES = ['single_select', 'multi_select'];
const FILTERABLE_TYPES = ['single_select', 'boolean', 'date', 'number'];

const isCreate = computed(() => props.mode === 'create');

const openProxy = computed<boolean>({
  get: () => props.open,
  set: (value) => emit('update:open', value),
});

const optionsProxy = computed<Array<{ code: string; label: string }>>({
  get: () => props.options,
  set: (value) => emit('update:options', value),
});

const createForm = computed(
  () => props.form as InertiaForm<FormCreateAttributeDefinitionData>,
);

const name = computed<string>({
  get: () => props.form.name,
  set: (value) => {
    props.form.name = value;
  },
});

const description = computed<string>({
  get: () => props.form.description ?? '',
  set: (value) => {
    props.form.description = value === '' ? null : value;
  },
});

const currentType = computed(() =>
  isCreate.value ? createForm.value.type : (props.editingDef?.type ?? ''),
);

const isSelectType = computed(() => SELECT_TYPES.includes(currentType.value));
const typeSupportsFiltering = computed(() =>
  FILTERABLE_TYPES.includes(currentType.value),
);

const handleKeyInput = () => {
  emit('update:keyManuallyEdited', true);
};
</script>

<template>
  <Dialog v-model:open="openProxy">
    <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
      <DialogHeader class="space-y-3">
        <DialogTitle>
          {{ isCreate ? t('添加字段') : t('编辑字段') }}
        </DialogTitle>
      </DialogHeader>

      <form class="space-y-4" @submit.prevent="emit('submit')">
        <template v-if="isCreate">
          <div class="space-y-2">
            <Label for="attribute-name" required>{{ t('字段名称') }}</Label>
            <Input
              id="attribute-name"
              v-model="name"
              :disabled="form.processing"
              required
            />
            <InputError :message="form.errors.name" />
          </div>

          <div class="space-y-2">
            <Label for="attribute-key" required>{{ t('内部标识') }}</Label>
            <Input
              id="attribute-key"
              v-model="createForm.key"
              :disabled="form.processing"
              class="font-mono"
              required
              @input="handleKeyInput"
            />
            <p class="text-xs text-muted-foreground">
              {{
                t(
                  '用于系统识别这个字段。请以小写字母开头，只使用小写字母、数字和下划线，创建后不能修改。',
                )
              }}
            </p>
            <InputError :message="form.errors.key" />
          </div>

          <div class="space-y-2">
            <Label for="attribute-type" required>{{ t('填写方式') }}</Label>
            <Select
              v-model="createForm.type"
              :disabled="form.processing"
              required
            >
              <SelectTrigger id="attribute-type" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="opt in typeOptions"
                  :key="String(opt.value)"
                  :value="String(opt.value)"
                >
                  {{ opt.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="form.errors.type" />
          </div>
        </template>

        <template v-else>
          <div class="space-y-2">
            <Label>{{ t('内部标识') }}</Label>
            <Input :model-value="editingDef?.key" disabled class="font-mono" />
            <p class="text-xs text-muted-foreground">
              {{ t('内部标识创建后不能修改。') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label>{{ t('填写方式') }}</Label>
            <Input :model-value="editingDef?.type_label" disabled />
            <p class="text-xs text-muted-foreground">
              {{ t('填写方式创建后不能修改。') }}
            </p>
          </div>

          <div class="space-y-2">
            <Label for="attribute-name" required>{{ t('字段名称') }}</Label>
            <Input
              id="attribute-name"
              v-model="name"
              :disabled="form.processing"
              required
            />
            <InputError :message="form.errors.name" />
          </div>
        </template>

        <div class="space-y-2">
          <Label for="attribute-desc">{{ t('字段说明') }}</Label>
          <Input
            id="attribute-desc"
            v-model="description"
            :disabled="form.processing"
          />
          <InputError :message="form.errors.description" />
        </div>

        <div v-if="isSelectType">
          <OptionListEditor
            v-model="optionsProxy"
            :disabled="form.processing"
            :errors="form.errors.config"
          />
        </div>

        <div v-if="typeSupportsFiltering" class="flex items-center gap-2">
          <Checkbox
            id="attribute-filterable"
            v-model="form.is_filterable"
            :disabled="form.processing"
          />
          <Label for="attribute-filterable" class="cursor-pointer">
            {{ t('可用于联系人筛选') }}
          </Label>
        </div>
        <InputError :message="form.errors.is_filterable" />

        <div class="space-y-2">
          <div class="flex items-center gap-2">
            <Checkbox
              id="attribute-ai-readable"
              v-model="form.is_ai_readable"
              :disabled="form.processing"
            />
            <Label for="attribute-ai-readable" class="cursor-pointer">
              {{ t('AI 可查看') }}
            </Label>
          </div>
          <p class="text-xs text-muted-foreground">
            {{ t('开启后，接待 AI 会参考已填写的内容，避免重复询问。') }}
          </p>
          <InputError :message="form.errors.is_ai_readable" />
        </div>

        <div class="space-y-2">
          <div class="flex items-center gap-2">
            <Checkbox
              id="attribute-ai-writable"
              v-model="form.is_ai_writable"
              :disabled="form.processing"
            />
            <Label for="attribute-ai-writable" class="cursor-pointer">
              {{ t('AI 可填写') }}
            </Label>
          </div>
          <p class="text-xs text-muted-foreground">
            {{
              t(
                '开启后，AI 会从会话中提取信息并填写。只会填写空白内容或更新之前由 AI 填写的内容，不会覆盖人工填写或其他方式写入的内容。',
              )
            }}
          </p>
          <InputError :message="form.errors.is_ai_writable" />
        </div>

        <DialogFooter class="gap-2">
          <DialogClose as-child>
            <Button
              type="button"
              variant="secondary"
              :disabled="form.processing"
            >
              {{ t('取消') }}
            </Button>
          </DialogClose>
          <Button type="submit" :disabled="form.processing">
            {{ t('保存') }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
