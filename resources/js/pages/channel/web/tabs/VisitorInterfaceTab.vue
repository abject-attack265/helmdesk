<!--
  网站渠道聊天界面页签，用于设置标题、客服信息、欢迎语和主题色。
  修改会同步显示在右侧预览中。
-->
<script setup lang="ts">
import Web from '@/actions/App/Actions/Channel/Web';
import FormActions from '@/components/common/FormActions.vue';
import ImageUploadField from '@/components/common/ImageUploadField.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useChannelPreviewDraft } from '@/composables/useChannelPreviewDraft';
import { useI18n } from '@/composables/useI18n';
import type {
  WebChannelData,
  WebChannelFormOptionsData,
} from '@/types/generated';
import type { FormComponentRef } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  channel: WebChannelData;
  formOptions: WebChannelFormOptionsData;
}>();

const { t } = useI18n();
const draft = useChannelPreviewDraft();
const formRef = ref<FormComponentRef | null>(null);
const manualDirty = ref(false);
const pageIconUploading = ref(false);
const serviceAvatarUploading = ref(false);
const imageUploading = computed(
  () => pageIconUploading.value || serviceAvatarUploading.value,
);

/** 返回聊天界面表单是否含有未保存内容。 */
function hasUnsavedChanges(): boolean {
  return Boolean(formRef.value?.isDirty || manualDirty.value);
}

/** 返回聊天界面表单是否正在提交。 */
function isProcessing(): boolean {
  return Boolean(formRef.value?.processing || imageUploading.value);
}

defineExpose({ hasUnsavedChanges, isProcessing });

// 后端颜色列表序列化后统一取值渲染色板。
const themeColorOptions = computed<string[]>(() =>
  Object.values(props.formOptions.theme_color_options),
);

// 主题色接受 6 位十六进制颜色，预设色可直接选择。
const THEME_COLOR_PATTERN = /^#[0-9A-Fa-f]{6}$/;

// 合法颜色统一为大写格式，未输入完整时保持原值。
function normalizeThemeColor(value: string): string | null {
  const trimmed = value.trim();
  const withHash = trimmed.startsWith('#') ? trimmed : `#${trimmed}`;
  return THEME_COLOR_PATTERN.test(withHash) ? withHash.toUpperCase() : null;
}

// 输入合法颜色时同步更新预览。
const hexInput = ref(draft.themeColor);
watch(
  () => draft.themeColor,
  (value) => {
    if (normalizeThemeColor(hexInput.value) !== value) {
      hexInput.value = value;
    }
  },
);

// 选预设或拾色器：统一归一化后写回草稿。
function selectThemeColor(value: string) {
  const normalized = normalizeThemeColor(value);
  if (normalized) {
    draft.themeColor = normalized;
    manualDirty.value = true;
  }
}

function onHexInput(value: string) {
  hexInput.value = value;
  manualDirty.value = true;
  const normalized = normalizeThemeColor(value);
  if (normalized) {
    draft.themeColor = normalized;
  }
}

// 当前主题色不在预设里即视为自定义，高亮自定义拾色器入口。
const isCustomThemeColor = computed(
  () =>
    !themeColorOptions.value.some(
      (color) => color.toUpperCase() === draft.themeColor.toUpperCase(),
    ),
);

const HOME_WELCOME_MAX_LENGTH = 50;

// 猜你想问：编辑行可含空白占位，归一化后随表单提交并同步进预览草稿。
const MAX_SUGGESTION_ITEMS = 6;
const suggestionItems = ref<string[]>(
  props.channel.suggestions.items.length > 0
    ? [...props.channel.suggestions.items]
    : [''],
);
const normalizedSuggestionItems = computed(() =>
  suggestionItems.value
    .map((item) => item.trim())
    .filter(Boolean)
    .slice(0, MAX_SUGGESTION_ITEMS),
);
watch(
  normalizedSuggestionItems,
  (items) => {
    draft.suggestionItems = items;
  },
  { immediate: true },
);
const addSuggestion = () => {
  if (suggestionItems.value.length >= MAX_SUGGESTION_ITEMS) {
    return;
  }
  suggestionItems.value.push('');
  manualDirty.value = true;
};
const removeSuggestion = (index: number) => {
  suggestionItems.value.splice(index, 1);
  if (suggestionItems.value.length === 0) {
    suggestionItems.value.push('');
  }
  manualDirty.value = true;
};
</script>

<template>
  <Form
    ref="formRef"
    :action="
      Web.UpdateWebChannelVisitorInterfaceAction.url({
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
      <input
        type="hidden"
        name="site_name"
        :value="draft.headerEnabled ? draft.siteName : ''"
      />
      <input
        type="hidden"
        name="subtitle"
        :value="draft.headerEnabled ? draft.subtitle : ''"
      />
      <input
        type="hidden"
        name="header_enabled"
        :value="draft.headerEnabled ? '1' : '0'"
      />
      <input
        v-if="!draft.headerEnabled"
        type="hidden"
        name="icon_id"
        value=""
      />
      <input
        type="hidden"
        name="visitor_identity_mode"
        :value="draft.visitorIdentityMode"
      />
      <template v-if="draft.visitorIdentityMode !== 'unified_service'">
        <input type="hidden" name="service_display_name" value="" />
        <input type="hidden" name="service_avatar_id" value="" />
      </template>
      <input
        type="hidden"
        name="greeting_message"
        :value="draft.greetingMessage"
      />
      <input
        type="hidden"
        name="composer_placeholder"
        :value="draft.composerPlaceholder"
      />
      <input type="hidden" name="theme_color" :value="hexInput" />
      <input
        type="hidden"
        name="home_mode_enabled"
        :value="draft.homeModeEnabled ? '1' : '0'"
      />
      <input
        type="hidden"
        name="home_welcome_message"
        :value="draft.homeModeEnabled ? draft.homeWelcomeMessage : ''"
      />
      <input
        type="hidden"
        name="suggestions_enabled"
        :value="draft.suggestionsEnabled ? '1' : '0'"
      />
      <template
        v-for="(item, index) in normalizedSuggestionItems"
        :key="`${item}-${index}`"
      >
        <input
          type="hidden"
          :name="`suggestion_items[${index}]`"
          :value="item"
        />
      </template>

      <div class="space-y-8">
        <section class="space-y-5">
          <div class="grid gap-2">
            <Label for="visitor_header_enabled">
              {{ t('显示标题栏') }}
            </Label>
            <Switch
              id="visitor_header_enabled"
              v-model="draft.headerEnabled"
              :disabled="processing || imageUploading"
              @update:model-value="manualDirty = true"
            />
          </div>

          <template v-if="draft.headerEnabled">
            <div class="grid gap-2">
              <Label for="visitor_site_name" required>
                {{ t('聊天标题') }}
              </Label>
              <Input
                id="visitor_site_name"
                v-model="draft.siteName"
                maxlength="100"
                :disabled="processing"
                required
              />
              <InputError :message="errors.site_name" />
            </div>

            <div class="grid gap-2">
              <Label for="visitor_subtitle">
                {{ t('副标题（选填）') }}
              </Label>
              <Input
                id="visitor_subtitle"
                v-model="draft.subtitle"
                maxlength="120"
                :disabled="processing"
              />
              <InputError :message="errors.subtitle" />
            </div>

            <ImageUploadField
              :label="t('标题图标（选填）')"
              name="icon_id"
              purpose="channel_icon"
              :upload-context="{}"
              :initial-preview="props.channel.visitor_interface.icon_url ?? ''"
              :initial-value="props.channel.visitor_interface.icon_id ?? ''"
              variant="logo"
              :error="errors.icon_id"
              :disabled="processing"
              input-id="visitor_page_icon"
              @update:uploading="pageIconUploading = $event"
            />
          </template>
        </section>

        <section class="space-y-5">
          <div class="grid gap-2">
            <Label>{{ t('客服显示方式') }}</Label>
            <Select
              v-model="draft.visitorIdentityMode"
              :disabled="processing || imageUploading"
              @update:model-value="manualDirty = true"
            >
              <SelectTrigger class="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem
                  v-for="option in props.formOptions
                    .visitor_identity_mode_options"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </SelectItem>
              </SelectContent>
            </Select>
            <InputError :message="errors.visitor_identity_mode" />
          </div>

          <template v-if="draft.visitorIdentityMode === 'unified_service'">
            <ImageUploadField
              :label="t('客服头像')"
              name="service_avatar_id"
              purpose="avatar"
              :upload-context="{}"
              :initial-preview="
                props.channel.visitor_interface.service_avatar_url ?? ''
              "
              :initial-value="
                props.channel.visitor_interface.service_avatar_id ?? ''
              "
              variant="avatar"
              :error="errors.service_avatar_id"
              :disabled="processing"
              input-id="visitor_service_avatar"
              @update:uploading="serviceAvatarUploading = $event"
            />

            <div class="grid gap-2">
              <Label for="visitor_service_display_name">
                {{ t('客服昵称') }}
              </Label>
              <Input
                id="visitor_service_display_name"
                v-model="draft.serviceDisplayName"
                name="service_display_name"
                maxlength="100"
                :disabled="processing"
              />
              <InputError :message="errors.service_display_name" />
            </div>
          </template>

          <div class="grid gap-2">
            <Label for="visitor_greeting_message">
              {{ t('欢迎语（选填）') }}
            </Label>
            <Textarea
              id="visitor_greeting_message"
              v-model="draft.greetingMessage"
              rows="3"
              maxlength="1000"
              :disabled="processing"
            />
            <InputError :message="errors.greeting_message" />
          </div>

          <div class="grid gap-2">
            <Label for="visitor_composer_placeholder">
              {{ t('输入框提示语（选填）') }}
            </Label>
            <Input
              id="visitor_composer_placeholder"
              v-model="draft.composerPlaceholder"
              maxlength="120"
              :disabled="processing"
            />
            <InputError :message="errors.composer_placeholder" />
          </div>
        </section>

        <section class="space-y-5">
          <div class="grid gap-2">
            <Label>{{ t('主题色') }}</Label>
            <div class="flex flex-wrap items-center gap-2.5">
              <button
                v-for="color in themeColorOptions"
                :key="color"
                type="button"
                :aria-label="color"
                :title="color"
                :disabled="processing"
                class="flex size-8 items-center justify-center rounded-full ring-offset-2 transition-transform hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none"
                :class="
                  draft.themeColor === color
                    ? 'ring-2 ring-foreground'
                    : 'ring-1 ring-border'
                "
                :style="{ backgroundColor: color }"
                @click="selectThemeColor(color)"
              >
                <Check
                  v-if="draft.themeColor === color"
                  class="size-4 text-white"
                  :stroke-width="3"
                />
              </button>

              <Popover>
                <PopoverTrigger as-child>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :class="isCustomThemeColor ? 'border-foreground' : ''"
                    :disabled="processing"
                  >
                    {{ t('自定义颜色') }}
                  </Button>
                </PopoverTrigger>
                <PopoverContent side="top" align="start" class="w-64 space-y-3">
                  <input
                    type="color"
                    class="h-9 w-full cursor-pointer rounded-md border border-border bg-transparent"
                    :value="draft.themeColor"
                    :aria-label="t('自定义颜色')"
                    :disabled="processing"
                    @input="
                      selectThemeColor(
                        ($event.target as HTMLInputElement).value,
                      )
                    "
                  />
                  <Input
                    class="font-mono uppercase"
                    :model-value="hexInput"
                    maxlength="7"
                    placeholder="#2563EB"
                    :aria-label="t('自定义颜色')"
                    :disabled="processing"
                    @update:model-value="onHexInput(String($event))"
                  />
                </PopoverContent>
              </Popover>
            </div>
            <InputError :message="errors.theme_color" />
          </div>

          <div class="grid gap-2">
            <Label for="visitor_home_mode_enabled">
              {{ t('先显示欢迎页') }}
            </Label>
            <Switch
              id="visitor_home_mode_enabled"
              v-model="draft.homeModeEnabled"
              :disabled="processing"
              @update:model-value="manualDirty = true"
            />
            <p class="text-sm text-muted-foreground">
              {{ t('开启后访客先看到欢迎屏，再进入聊天') }}
            </p>
          </div>

          <div v-if="draft.homeModeEnabled" class="grid gap-2">
            <Label for="visitor_home_welcome_message" required>
              {{ t('欢迎页文案') }}
            </Label>
            <Textarea
              id="visitor_home_welcome_message"
              v-model="draft.homeWelcomeMessage"
              rows="2"
              required
              :maxlength="HOME_WELCOME_MAX_LENGTH"
              :disabled="processing"
            />
            <InputError :message="errors.home_welcome_message" />
          </div>
        </section>

        <section class="space-y-5">
          <div class="grid gap-2">
            <Label for="visitor_suggestions_enabled">
              {{ t('显示常见问题') }}
            </Label>
            <Switch
              id="visitor_suggestions_enabled"
              v-model="draft.suggestionsEnabled"
              :disabled="processing"
              @update:model-value="manualDirty = true"
            />
          </div>

          <div v-if="draft.suggestionsEnabled" class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <Label>{{ t('常见问题') }}</Label>
              <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="
                  processing || suggestionItems.length >= MAX_SUGGESTION_ITEMS
                "
                @click="addSuggestion"
              >
                <Plus class="mr-2 size-4" />
                {{ t('添加问题') }}
              </Button>
            </div>

            <div class="space-y-2">
              <div
                v-for="(_, index) in suggestionItems"
                :key="index"
                class="flex items-center gap-2"
              >
                <Input
                  v-model="suggestionItems[index]"
                  maxlength="120"
                  :disabled="processing"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  :title="t('删除')"
                  :aria-label="t('删除问题')"
                  :disabled="processing"
                  @click="removeSuggestion(index)"
                >
                  <Trash2 class="size-4" />
                </Button>
              </div>
            </div>

            <p class="text-sm text-muted-foreground">
              {{ t('最多添加 6 个，访客点击后会直接发送。') }}
            </p>
            <InputError :message="errors.suggestion_items" />
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
