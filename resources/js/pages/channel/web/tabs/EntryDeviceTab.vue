<!--
  网站渠道聊天入口页签，用于设置按钮样式、位置和消息提醒。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import FormActions from '@/components/common/FormActions.vue';
import ImageUploadField from '@/components/common/ImageUploadField.vue';
import InputError from '@/components/common/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useChannelPreviewDraft } from '@/composables/useChannelPreviewDraft';
import { useI18n } from '@/composables/useI18n';
import type {
  WebChannelData,
  WebChannelFormOptionsData,
} from '@/types/generated';
import type { FormComponentRef } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';

const props = defineProps<{
  channel: WebChannelData;
  formOptions: WebChannelFormOptionsData;
}>();

type EntryMode = WebChannelData['widget']['entry']['mode'];
type EntryPosition = WebChannelData['widget']['entry']['position'];
type EntryStyle = WebChannelData['widget']['entry']['style'];
type EntryIconSize = WebChannelData['widget']['entry']['icon_size'];
type SelectModelValue =
  string | number | bigint | Record<string, unknown> | null;

const { t } = useI18n();
const draft = useChannelPreviewDraft();
const formRef = ref<FormComponentRef | null>(null);
const manualDirty = ref(false);
const defaultIconUploading = ref(false);
const activeIconUploading = ref(false);
const imageUploading = computed(
  () => defaultIconUploading.value || activeIconUploading.value,
);

/** 返回聊天入口表单是否含有未保存内容。 */
function hasUnsavedChanges(): boolean {
  return Boolean(formRef.value?.isDirty || manualDirty.value);
}

/** 返回聊天入口表单是否正在提交。 */
function isProcessing(): boolean {
  return Boolean(formRef.value?.processing || imageUploading.value);
}

defineExpose({ hasUnsavedChanges, isProcessing });

const entryMode = ref<EntryMode>(props.channel.widget.entry.mode);
const entryPosition = ref<EntryPosition>(props.channel.widget.entry.position);
const entryStyle = ref<EntryStyle>(props.channel.widget.entry.style);
const entryIconSize = ref<EntryIconSize>(props.channel.widget.entry.icon_size);
const entryBottomOffset = ref(props.channel.widget.entry.bottom_offset);
const unreadBadgeEnabled = ref(props.channel.widget.unread_badge_enabled);
const inlineToastEnabled = ref(props.channel.widget.inline_toast_enabled);
const mobileFullscreenEnabled = ref(
  props.channel.widget.mobile_fullscreen_enabled,
);
const entryDefaultIconPreview = ref(
  props.channel.widget.entry.default_icon_url ?? '',
);
const entrySelectedIconPreview = ref(
  props.channel.widget.entry.active_icon_url ?? '',
);

const usesDefaultBubble = computed(() => entryMode.value === 'bubble');
const usesCustomIconStyle = computed(
  () => usesDefaultBubble.value && entryStyle.value === 'custom',
);
const declarativeTriggerSnippet = `<button data-helmdesk-open>${t('联系客服')}</button>`;

// 入口与设备配置同步进预览草稿，驱动右侧小部件形态示意。
watchEffect(() => {
  draft.entryMode = entryMode.value;
  draft.entryPosition = entryPosition.value;
  draft.entryStyle = entryStyle.value;
  draft.entryIconSize = entryIconSize.value;
  draft.entryBottomOffset = entryBottomOffset.value;
  draft.mobileFullscreenEnabled = mobileFullscreenEnabled.value;
  draft.entryDefaultIconUrl = usesCustomIconStyle.value
    ? entryDefaultIconPreview.value || null
    : null;
  draft.entrySelectedIconUrl = usesCustomIconStyle.value
    ? entrySelectedIconPreview.value || null
    : null;
});

const updateEntryMode = (value: SelectModelValue) => {
  if (typeof value !== 'string') {
    return;
  }

  entryMode.value = value as EntryMode;
  manualDirty.value = true;
};

const updateEntryPosition = (value: SelectModelValue) => {
  if (typeof value !== 'string') {
    return;
  }

  entryPosition.value = value as EntryPosition;
  manualDirty.value = true;
};

const updateEntryStyle = (value: SelectModelValue) => {
  if (typeof value !== 'string') {
    return;
  }

  entryStyle.value = value as EntryStyle;
  manualDirty.value = true;
};

const updateEntryIconSize = (value: SelectModelValue) => {
  if (typeof value !== 'string') {
    return;
  }

  entryIconSize.value = value as EntryIconSize;
  manualDirty.value = true;
};

const updateEntryBottomOffset = (value: string | number) => {
  const number = Number(value);

  if (!Number.isFinite(number)) {
    return;
  }

  entryBottomOffset.value = number;
  manualDirty.value = true;
};
</script>

<template>
  <Form
    ref="formRef"
    :action="
      Web.UpdateWebChannelWidgetAction.url({
        channel: props.channel.id,
      })
    "
    method="put"
    class="space-y-6"
    disable-while-processing
    set-defaults-on-success
    @input.capture="manualDirty = true"
    @change.capture="manualDirty = true"
    @success="manualDirty = false"
  >
    <template #default="{ errors, processing }">
      <input type="hidden" name="entry_mode" :value="entryMode" />
      <input type="hidden" name="entry_position" :value="entryPosition" />
      <input type="hidden" name="entry_style" :value="entryStyle" />
      <input type="hidden" name="entry_icon_size" :value="entryIconSize" />
      <input
        type="hidden"
        name="entry_bottom_offset"
        :value="entryBottomOffset"
      />
      <input
        type="hidden"
        name="unread_badge_enabled"
        :value="usesDefaultBubble && unreadBadgeEnabled ? '1' : '0'"
      />
      <input
        type="hidden"
        name="inline_toast_enabled"
        :value="usesDefaultBubble && inlineToastEnabled ? '1' : '0'"
      />
      <input
        type="hidden"
        name="mobile_fullscreen_enabled"
        :value="mobileFullscreenEnabled ? '1' : '0'"
      />

      <div class="space-y-8">
        <section class="space-y-5">
          <div class="grid gap-2">
            <Label for="widget_entry_mode" required>
              {{ t('聊天按钮') }}
            </Label>
            <Select
              :model-value="entryMode"
              :disabled="processing || imageUploading"
              @update:model-value="updateEntryMode"
            >
              <SelectTrigger id="widget_entry_mode" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in props.formOptions.widget_entry_mode_options"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="errors.entry_mode" />
          </div>

          <div v-if="!usesDefaultBubble" class="space-y-3 text-sm">
            <p class="text-muted-foreground">
              {{ t('使用网站自己的按钮后，系统不会再显示默认聊天按钮。') }}
            </p>
            <pre
              class="rounded-md border bg-muted/30 p-3 break-all whitespace-pre-wrap"
              >{{ declarativeTriggerSnippet }}</pre>
            <p class="text-muted-foreground">
              {{ t('请把上面的代码交给开发人员，用网站按钮打开聊天。') }}
            </p>
          </div>
        </section>

        <section class="space-y-5">
          <div class="grid gap-2">
            <Label for="widget_entry_position" required>
              {{ usesDefaultBubble ? t('按钮位置') : t('聊天窗位置') }}
            </Label>
            <Select
              :model-value="entryPosition"
              :disabled="processing || imageUploading"
              @update:model-value="updateEntryPosition"
            >
              <SelectTrigger id="widget_entry_position" class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in props.formOptions
                    .widget_entry_position_options"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="errors.entry_position" />
          </div>

          <template v-if="usesDefaultBubble">
            <div class="grid gap-2">
              <Label for="widget_entry_style" required>
                {{ t('按钮样式') }}
              </Label>
              <Select
                :model-value="entryStyle"
                :disabled="processing || imageUploading"
                @update:model-value="updateEntryStyle"
              >
                <SelectTrigger id="widget_entry_style" class="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in props.formOptions
                      .widget_entry_style_options"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="errors.entry_style" />
            </div>

            <div class="grid gap-2">
              <Label for="widget_entry_icon_size" required>
                {{ t('按钮大小') }}
              </Label>
              <Select
                :model-value="entryIconSize"
                :disabled="processing || imageUploading"
                @update:model-value="updateEntryIconSize"
              >
                <SelectTrigger id="widget_entry_icon_size" class="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem
                    v-for="option in props.formOptions.widget_icon_size_options"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <InputError :message="errors.entry_icon_size" />
            </div>

            <div class="grid gap-2">
              <Label for="widget_entry_bottom_offset" required>
                {{ t('距页面底部（像素）') }}
              </Label>
              <Input
                id="widget_entry_bottom_offset"
                type="number"
                min="0"
                max="120"
                :model-value="entryBottomOffset"
                :disabled="processing"
                required
                @update:model-value="updateEntryBottomOffset"
              />
              <InputError :message="errors.entry_bottom_offset" />
            </div>

            <div v-show="entryStyle === 'custom'" class="grid gap-4">
              <ImageUploadField
                :label="t('聊天收起时的图标')"
                name="entry_default_icon_id"
                purpose="channel_icon"
                variant="logo"
                :upload-context="{}"
                :initial-preview="
                  props.channel.widget.entry.default_icon_url ?? ''
                "
                :initial-value="
                  props.channel.widget.entry.default_icon_id ?? ''
                "
                :help-text="t('不上传则入口使用系统默认图标。')"
                :error="errors.entry_default_icon_id"
                :disabled="processing"
                input-id="widget_entry_default_icon"
                @update:preview="entryDefaultIconPreview = $event"
                @update:uploading="defaultIconUploading = $event"
              />

              <ImageUploadField
                :label="t('聊天展开时的图标')"
                name="entry_active_icon_id"
                purpose="channel_icon"
                variant="logo"
                :upload-context="{}"
                :initial-preview="
                  props.channel.widget.entry.active_icon_url ?? ''
                "
                :initial-value="props.channel.widget.entry.active_icon_id ?? ''"
                :help-text="t('需要和聊天收起时的图标一起上传。')"
                :error="errors.entry_active_icon_id"
                :disabled="processing"
                input-id="widget_entry_active_icon"
                @update:preview="entrySelectedIconPreview = $event"
                @update:uploading="activeIconUploading = $event"
              />
            </div>
          </template>
        </section>

        <section class="space-y-5">
          <div class="grid gap-2">
            <Label for="widget_mobile_fullscreen_enabled">
              {{ t('在手机上全屏显示') }}
            </Label>
            <Switch
              id="widget_mobile_fullscreen_enabled"
              v-model="mobileFullscreenEnabled"
              :disabled="processing"
              @update:model-value="manualDirty = true"
            />
            <p class="text-sm text-muted-foreground">
              {{
                t(
                  '开启后，访客在手机上打开聊天时会全屏显示，更方便输入和查看消息。',
                )
              }}
            </p>
          </div>
        </section>

        <section v-if="usesDefaultBubble" class="space-y-5">
          <div class="grid gap-2">
            <Label for="widget_unread_badge_enabled">
              {{ t('显示未读提醒') }}
            </Label>
            <Switch
              id="widget_unread_badge_enabled"
              v-model="unreadBadgeEnabled"
              :disabled="processing"
              @update:model-value="manualDirty = true"
            />
            <p class="text-sm text-muted-foreground">
              {{ t('有新消息时，在聊天按钮右上角显示提醒。') }}
            </p>
          </div>
          <div class="grid gap-2">
            <Label for="widget_inline_toast_enabled">
              {{ t('显示新消息预览') }}
            </Label>
            <Switch
              id="widget_inline_toast_enabled"
              v-model="inlineToastEnabled"
              :disabled="processing"
              @update:model-value="manualDirty = true"
            />
            <p class="text-sm text-muted-foreground">
              {{
                t(
                  '有新消息时，在聊天按钮旁显示消息预览，访客点击即可打开聊天。',
                )
              }}
            </p>
          </div>
        </section>

        <FormActions
          :submit-label="t('保存')"
          :processing="processing || imageUploading"
          :submit-disabled="imageUploading"
          :cancel-href="Web.ListWebChannelsAction.url()"
          :cancel-label="t('返回')"
        />
      </div>
    </template>
  </Form>
</template>
