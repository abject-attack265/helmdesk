<!--
  网站渠道访客信息页签，用于设置传入信息的保存位置。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import FormActions from '@/components/common/FormActions.vue';
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
  WebChannelData,
  WebChannelFormOptionsData,
  WebChannelQueryParamMappingData,
} from '@/types/generated';
import type { FormComponentRef } from '@inertiajs/core';
import { Form, router } from '@inertiajs/vue3';
import { Eye, EyeOff, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

type ParamTarget = WebChannelQueryParamMappingData['target'];
type ParamWriteMode = WebChannelQueryParamMappingData['write_mode'];

type MappingRow = {
  param_name: string;
  target: ParamTarget;
  target_key: string;
  write_mode: ParamWriteMode;
};

const props = defineProps<{
  channel: WebChannelData;
  formOptions: WebChannelFormOptionsData;
}>();

const { t } = useI18n();

const maskedSecret = computed(() => props.channel.user_token_secret_masked);
const currentSecret = computed(() => props.channel.user_token_secret);
const secretCopied = ref(false);
const secretCopyFailed = ref(false);
const isRegeneratingSecret = ref(false);
const secretRevealed = ref(false);
const formRef = ref<FormComponentRef | null>(null);
const manualDirty = ref(false);
const hasPendingChanges = computed(() =>
  Boolean(formRef.value?.isDirty || manualDirty.value),
);

/** 返回访客信息表单是否含有未保存内容。 */
function hasUnsavedChanges(): boolean {
  return hasPendingChanges.value;
}

/** 返回访客信息表单是否正在提交或更新密钥。 */
function isProcessing(): boolean {
  return Boolean(formRef.value?.processing || isRegeneratingSecret.value);
}

defineExpose({ hasUnsavedChanges, isProcessing });

function regenerateSecret(): void {
  if (hasPendingChanges.value) {
    return;
  }

  router.post(
    Web.RegenerateWebChannelUserTokenSecretAction.url({
      channel: props.channel.id,
    }),
    {},
    {
      preserveScroll: true,
      onStart: () => {
        isRegeneratingSecret.value = true;
      },
      onFinish: () => {
        isRegeneratingSecret.value = false;
      },
    },
  );
}

async function copySecret(): Promise<void> {
  if (!currentSecret.value) {
    return;
  }

  try {
    await navigator.clipboard.writeText(currentSecret.value);
    secretCopyFailed.value = false;
    secretCopied.value = true;
    window.setTimeout(() => {
      secretCopied.value = false;
    }, 2000);
  } catch {
    secretCopied.value = false;
    secretCopyFailed.value = true;
  }
}

const mappings = ref<MappingRow[]>(
  (props.channel.query_param_mappings ?? []).map((mapping) => ({
    param_name: mapping.param_name,
    target: mapping.target,
    target_key: mapping.target_key ?? '',
    write_mode: mapping.write_mode,
  })),
);
const addMappingOpen = ref(false);
const draftMapping = ref<MappingRow>(newMappingRow());

const targetOptions = computed(
  () => props.formOptions.query_param_target_options,
);
const writeModeOptions = computed(
  () => props.formOptions.query_param_write_mode_options,
);
const writableAttributeOptions = computed(
  () => props.formOptions.writable_attribute_definition_options,
);

function targetRequiresKey(target: ParamTarget): boolean {
  return target === 'attribute' || target === 'tag';
}

function targetKeyLabel(target: ParamTarget): string {
  if (target === 'attribute') {
    return t('自定义字段');
  }
  if (target === 'tag') {
    return t('标签名称');
  }
  return t('保存位置');
}

function targetKeyHint(target: ParamTarget): string {
  if (target === 'attribute') {
    return t(
      '这里只显示允许外部填写的自定义字段。单选字段的传入内容需要和已有选项一致。',
    );
  }
  if (target === 'tag') {
    return t(
      '用 {value} 代表传入的内容，例如“活动-{value}”。传入内容只能包含字母、数字、下划线或短横线，最多 40 个字符。',
    );
  }
  return '';
}

function newMappingRow(): MappingRow {
  return {
    param_name: '',
    target: 'tag' as ParamTarget,
    target_key: '',
    write_mode: 'only_if_empty' as ParamWriteMode,
  };
}

function resetDraftMapping(): void {
  draftMapping.value = newMappingRow();
}

const canAddMapping = computed(() => {
  const paramName = draftMapping.value.param_name.trim();
  if (
    paramName === '' ||
    paramName.length > 64 ||
    !/^[a-zA-Z0-9_.-]+$/.test(paramName)
  ) {
    return false;
  }

  if (!targetRequiresKey(draftMapping.value.target)) {
    return true;
  }

  if (draftMapping.value.target_key.trim() === '') {
    return false;
  }

  return (
    draftMapping.value.target !== 'attribute' ||
    writableAttributeOptions.value.some(
      (option) => option.value === draftMapping.value.target_key,
    )
  );
});

function addMapping(): void {
  if (!canAddMapping.value) {
    return;
  }

  mappings.value.push({ ...draftMapping.value });
  manualDirty.value = true;
  resetDraftMapping();
  addMappingOpen.value = false;
}

function removeMapping(index: number): void {
  mappings.value.splice(index, 1);
  manualDirty.value = true;
}

function onTargetChange(index: number, value: unknown): void {
  if (typeof value !== 'string') {
    return;
  }
  const nextTarget = value as ParamTarget;
  if (mappings.value[index].target !== nextTarget) {
    mappings.value[index].target_key = '';
  }
  mappings.value[index].target = nextTarget;
  manualDirty.value = true;
}

function onDraftTargetChange(value: unknown): void {
  if (typeof value !== 'string') {
    return;
  }
  const nextTarget = value as ParamTarget;
  if (draftMapping.value.target !== nextTarget) {
    draftMapping.value.target_key = '';
  }
  draftMapping.value.target = nextTarget;
}
</script>

<template>
  <Form
    ref="formRef"
    :action="
      Web.UpdateWebChannelEmbedAction.url({
        channel: props.channel.id,
      })
    "
    method="put"
    class="space-y-8"
    disable-while-processing
    set-defaults-on-success
    @input.capture="manualDirty = true"
    @change.capture="manualDirty = true"
    @success="manualDirty = false"
  >
    <template #default="{ errors, processing }">
      <template v-for="(mapping, index) in mappings" :key="`mapping-${index}`">
        <input
          type="hidden"
          :name="`query_param_mappings[${index}][param_name]`"
          :value="mapping.param_name"
        />
        <input
          type="hidden"
          :name="`query_param_mappings[${index}][target]`"
          :value="mapping.target"
        />
        <input
          type="hidden"
          :name="`query_param_mappings[${index}][target_key]`"
          :value="mapping.target_key"
        />
        <!-- 普通传入信息统一按公开参数处理；已登录访客身份由签名密钥单独验证。 -->
        <input
          type="hidden"
          :name="`query_param_mappings[${index}][trust]`"
          value="always"
        />
        <input
          type="hidden"
          :name="`query_param_mappings[${index}][write_mode]`"
          :value="mapping.write_mode"
        />
      </template>

      <section class="space-y-3">
        <div>
          <Label>{{ t('识别已登录访客') }}</Label>
          <p class="mt-1 text-sm text-muted-foreground">
            {{
              t(
                '网站可以用密钥安全地识别已登录访客，避免他人冒用身份。此功能需要开发人员接入。',
              )
            }}
          </p>
        </div>

        <div
          class="flex flex-wrap items-center gap-3 rounded-md border bg-muted/30 px-3 py-2 text-sm"
        >
          <span
            v-if="maskedSecret"
            class="font-mono text-sm break-all text-foreground"
          >
            {{ secretRevealed && currentSecret ? currentSecret : maskedSecret }}
          </span>
          <span v-else class="text-muted-foreground">
            {{ t('还未生成密钥') }}
          </span>
          <div class="ml-auto flex items-center gap-2">
            <Button
              v-if="maskedSecret"
              type="button"
              variant="outline"
              size="sm"
              :disabled="!currentSecret"
              :aria-label="secretRevealed ? t('隐藏密钥') : t('显示密钥')"
              @click="secretRevealed = !secretRevealed"
            >
              <EyeOff v-if="secretRevealed" class="h-4 w-4" />
              <Eye v-else class="h-4 w-4" />
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              :disabled="!currentSecret"
              @click="copySecret"
            >
              {{ secretCopied ? t('已复制') : t('复制') }}
            </Button>

            <Button
              v-if="!maskedSecret"
              type="button"
              variant="outline"
              size="sm"
              :disabled="isRegeneratingSecret || hasPendingChanges"
              :title="
                hasPendingChanges
                  ? t('请先保存或放弃访客信息中的修改。')
                  : undefined
              "
              @click="regenerateSecret"
            >
              {{ isRegeneratingSecret ? t('生成中...') : t('生成密钥') }}
            </Button>

            <Dialog v-else>
              <DialogTrigger as-child>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  :disabled="isRegeneratingSecret || hasPendingChanges"
                  :title="
                    hasPendingChanges
                      ? t('请先保存或放弃访客信息中的修改。')
                      : undefined
                  "
                >
                  {{ t('重置密钥') }}
                </Button>
              </DialogTrigger>
              <DialogContent class="sm:max-w-md">
                <DialogHeader>
                  <DialogTitle>{{ t('重置这个密钥？') }}</DialogTitle>
                  <DialogDescription>
                    {{
                      t(
                        '重置后，网站将无法继续使用旧密钥识别访客。请确认开发人员已准备好更新。',
                      )
                    }}
                  </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                  <DialogClose as-child>
                    <Button type="button" variant="outline">
                      {{ t('取消') }}
                    </Button>
                  </DialogClose>
                  <DialogClose as-child>
                    <Button
                      type="button"
                      :disabled="isRegeneratingSecret || hasPendingChanges"
                      @click="regenerateSecret"
                    >
                      {{
                        isRegeneratingSecret ? t('重置中...') : t('确认重置')
                      }}
                    </Button>
                  </DialogClose>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </div>
        </div>
        <p v-if="secretCopyFailed" class="text-sm text-destructive">
          {{ t('复制失败，请手动复制。') }}
        </p>
      </section>

      <section class="space-y-3 border-t pt-8">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-sm text-muted-foreground">
              {{
                t(
                  '把网站传来的信息自动填写到联系人资料、自定义字段或标签中。只适合来源、活动等非敏感信息。',
                )
              }}
            </p>
          </div>
          <Dialog
            v-model:open="addMappingOpen"
            @update:open="resetDraftMapping"
          >
            <DialogTrigger as-child>
              <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="processing || mappings.length >= 32"
              >
                {{ t('添加传入规则') }}
              </Button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-lg">
              <DialogHeader>
                <DialogTitle>{{ t('添加传入规则') }}</DialogTitle>
                <DialogDescription>
                  {{ t('设置网站传来的信息要保存到哪里，保存页面后生效。') }}
                </DialogDescription>
              </DialogHeader>

              <div class="space-y-4">
                <div class="grid gap-2">
                  <Label for="draft_param_name" required>
                    {{ t('网站参数') }}
                  </Label>
                  <Input
                    id="draft_param_name"
                    v-model="draftMapping.param_name"
                    maxlength="64"
                    pattern="[a-zA-Z0-9_.-]+"
                    required
                  />
                </div>

                <div class="grid gap-2">
                  <Label>{{ t('保存到') }}</Label>
                  <Select
                    :model-value="draftMapping.target"
                    @update:model-value="onDraftTargetChange"
                  >
                    <SelectTrigger class="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="option in targetOptions"
                        :key="option.value"
                        :value="option.value"
                      >
                        {{ option.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div
                  v-if="targetRequiresKey(draftMapping.target)"
                  class="grid gap-2"
                >
                  <Label for="draft_target_key" required>
                    {{ targetKeyLabel(draftMapping.target) }}
                  </Label>
                  <Select
                    v-if="draftMapping.target === 'attribute'"
                    v-model="draftMapping.target_key"
                    :disabled="writableAttributeOptions.length === 0"
                  >
                    <SelectTrigger class="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="option in writableAttributeOptions"
                        :key="option.value"
                        :value="option.value"
                      >
                        {{ option.label }} · {{ option.type_label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <Input
                    v-else
                    id="draft_target_key"
                    v-model="draftMapping.target_key"
                    maxlength="120"
                    required
                  />
                  <p class="text-xs text-muted-foreground">
                    {{ targetKeyHint(draftMapping.target) }}
                  </p>
                </div>

                <div class="grid gap-2">
                  <Label>{{ t('已有内容时') }}</Label>
                  <Select v-model="draftMapping.write_mode">
                    <SelectTrigger class="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem
                        v-for="option in writeModeOptions"
                        :key="option.value"
                        :value="option.value"
                      >
                        {{ option.label }}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <DialogFooter>
                  <DialogClose as-child>
                    <Button type="button" variant="outline">
                      {{ t('取消') }}
                    </Button>
                  </DialogClose>
                  <Button
                    type="button"
                    :disabled="!canAddMapping"
                    @click="addMapping"
                  >
                    {{ t('添加') }}
                  </Button>
                </DialogFooter>
              </div>
            </DialogContent>
          </Dialog>
        </div>

        <div class="min-w-0 rounded-lg border">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="border-b bg-muted/30 text-muted-foreground">
                <tr class="text-left">
                  <th class="min-w-48 px-4 py-3">
                    {{ t('网站参数') }}
                  </th>
                  <th class="min-w-44 px-4 py-3">
                    {{ t('保存到') }}
                  </th>
                  <th class="min-w-64 px-4 py-3">
                    {{ t('具体位置') }}
                  </th>
                  <th class="min-w-44 px-4 py-3">
                    {{ t('已有内容时') }}
                  </th>
                  <th class="w-20 px-4 py-3 text-right whitespace-nowrap">
                    {{ t('操作') }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(mapping, index) in mappings"
                  :key="`row-${index}`"
                  class="border-t bg-background"
                >
                  <td class="px-4 py-3 align-top">
                    <div class="grid gap-1.5">
                      <Label :for="`param_name_${index}`" class="sr-only">
                        {{ t('网站参数') }}
                      </Label>
                      <Input
                        :id="`param_name_${index}`"
                        v-model="mapping.param_name"
                        maxlength="64"
                        pattern="[a-zA-Z0-9_.-]+"
                        :disabled="processing"
                        required
                      />
                      <InputError
                        :message="
                          errors[`query_param_mappings.${index}.param_name`]
                        "
                      />
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="grid gap-1.5">
                      <Label class="sr-only">{{ t('保存到') }}</Label>
                      <Select
                        :model-value="mapping.target"
                        :disabled="processing"
                        @update:model-value="onTargetChange(index, $event)"
                      >
                        <SelectTrigger class="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem
                            v-for="option in targetOptions"
                            :key="option.value"
                            :value="option.value"
                          >
                            {{ option.label }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                      <InputError
                        :message="
                          errors[`query_param_mappings.${index}.target`]
                        "
                      />
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="grid gap-1.5">
                      <Label :for="`target_key_${index}`" class="sr-only">
                        {{ targetKeyLabel(mapping.target) }}
                      </Label>
                      <Select
                        v-if="mapping.target === 'attribute'"
                        v-model="mapping.target_key"
                        :disabled="
                          processing || writableAttributeOptions.length === 0
                        "
                      >
                        <SelectTrigger class="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem
                            v-for="option in writableAttributeOptions"
                            :key="option.value"
                            :value="option.value"
                          >
                            {{ option.label }} · {{ option.type_label }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                      <Input
                        v-else-if="targetRequiresKey(mapping.target)"
                        :id="`target_key_${index}`"
                        v-model="mapping.target_key"
                        maxlength="120"
                        :disabled="processing"
                        required
                      />
                      <span
                        v-else
                        class="flex min-h-9 items-center text-muted-foreground"
                      >
                        -
                      </span>
                      <p
                        v-if="targetRequiresKey(mapping.target)"
                        class="text-xs text-muted-foreground"
                      >
                        {{ targetKeyHint(mapping.target) }}
                      </p>
                      <InputError
                        :message="
                          errors[`query_param_mappings.${index}.target_key`]
                        "
                      />
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top">
                    <div class="grid gap-1.5">
                      <Label class="sr-only">{{ t('已有内容时') }}</Label>
                      <Select
                        v-model="mapping.write_mode"
                        :disabled="processing"
                      >
                        <SelectTrigger class="w-full">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem
                            v-for="option in writeModeOptions"
                            :key="option.value"
                            :value="option.value"
                          >
                            {{ option.label }}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                      <InputError
                        :message="
                          errors[`query_param_mappings.${index}.write_mode`]
                        "
                      />
                    </div>
                  </td>
                  <td class="px-4 py-3 align-top whitespace-nowrap">
                    <div class="flex min-h-9 items-center justify-end">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        :aria-label="t('删除规则')"
                        :title="t('删除规则')"
                        :disabled="processing"
                        @click="removeMapping(index)"
                      >
                        <Trash2 class="size-4" />
                      </Button>
                    </div>
                  </td>
                </tr>

                <tr v-if="mappings.length === 0">
                  <td
                    class="px-4 py-8 text-center text-muted-foreground"
                    colspan="5"
                  >
                    {{ t('还没有传入规则') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <FormActions
        :submit-label="t('保存')"
        :processing="processing"
        :cancel-href="Web.ListWebChannelsAction.url()"
        :cancel-label="t('返回')"
      />
    </template>
  </Form>
</template>
