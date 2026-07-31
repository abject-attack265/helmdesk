<!--
  快捷回复创建与编辑表单。
  页面设置使用范围和回复内容，并在右侧展示填写效果。
-->
<script setup lang="ts">
import FormActions from '@/components/common/FormActions.vue';
import HeadingSmall from '@/components/common/HeadingSmall.vue';
import InputError from '@/components/common/InputError.vue';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
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
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from '@/composables/useI18n';
import appRoutes from '@/routes/app';
import cannedReplyRoutes from '@/routes/app/canned-replies';
import type {
  CannedReplyTokenOptionData,
  FormCreateCannedReplyData,
  FormUpdateCannedReplyData,
  ListCannedReplyItemData,
} from '@/types/generated';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

interface Props {
  mode: 'create' | 'edit';
  variant?: 'page' | 'dialog';
  cannedReply?: ListCannedReplyItemData | null;
  availableTokens: CannedReplyTokenOptionData[];
  defaultIsPersonal?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'page',
  cannedReply: null,
  defaultIsPersonal: true,
});

const emit = defineEmits<{
  saved: [];
  cancel: [];
}>();

const { t } = useI18n();
const initialIsPersonal =
  props.mode === 'edit'
    ? Boolean(props.cannedReply?.is_personal)
    : props.defaultIsPersonal;

const ownerScope = ref<'personal' | 'app'>(
  initialIsPersonal ? 'personal' : 'app',
);

const createForm = useForm<FormCreateCannedReplyData>({
  name: props.cannedReply?.name ?? '',
  shortcut: props.cannedReply?.shortcut ?? null,
  content: props.cannedReply?.content ?? '',
  is_personal: initialIsPersonal,
});

const editForm = useForm<FormUpdateCannedReplyData>({
  name: props.cannedReply?.name ?? '',
  shortcut: props.cannedReply?.shortcut ?? null,
  content: props.cannedReply?.content ?? '',
  is_personal: initialIsPersonal,
});

const form = computed(() => (props.mode === 'create' ? createForm : editForm));
const ownerScopeError = computed(
  () => (form.value.errors as { is_personal?: string }).is_personal,
);
const contentLayoutClass = computed(() =>
  props.variant === 'page'
    ? 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,360px)]'
    : 'space-y-4',
);
const formSpacingClass = computed(() =>
  props.variant === 'page' ? 'space-y-6' : 'space-y-4',
);
const contentTextareaClass = computed(() =>
  props.variant === 'page' ? 'min-h-48' : 'min-h-32 max-h-48',
);
const previewClass = computed(() =>
  props.variant === 'page' ? 'min-h-48' : 'min-h-24 max-h-36 overflow-y-auto',
);

const contentTextareaRef = ref<{ el: HTMLTextAreaElement | null } | null>(null);
const insertVariableOpen = ref(false);

const groupedTokens = computed(() => {
  const groups = new Map<
    string,
    { kindLabel: string; items: CannedReplyTokenOptionData[] }
  >();

  for (const token of props.availableTokens) {
    const existing = groups.get(token.kind);
    if (existing) {
      existing.items.push(token);
    } else {
      groups.set(token.kind, { kindLabel: token.kind_label, items: [token] });
    }
  }

  return Array.from(groups, ([kind, group]) => ({ kind, ...group }));
});

const insertToken = async (token: string) => {
  const textarea = contentTextareaRef.value?.el;

  if (!textarea) {
    form.value.content += token;
    insertVariableOpen.value = false;
    return;
  }

  const start = textarea.selectionStart ?? form.value.content.length;
  const end = textarea.selectionEnd ?? start;
  const before = form.value.content.slice(0, start);
  const after = form.value.content.slice(end);

  form.value.content = `${before}${token}${after}`;
  insertVariableOpen.value = false;

  await nextTick();
  textarea.focus();
  const cursor = before.length + token.length;
  textarea.setSelectionRange(cursor, cursor);
};

const previewSamples: Record<string, string> = {
  '{{contact.name}}': '张小明',
  '{{contact.email}}': 'contact@example.com',
  '{{contact.primary_phone}}': '138 0000 0000',
  '{{conversation.id}}': '01HXYZ',
  '{{conversation.subject}}': t('示例会话主题'),
  '{{teammate.name}}': t('客服小美'),
  '{{app.name}}': t('我的应用'),
};

const previewContent = computed(() => {
  let rendered = form.value.content;
  for (const [token, value] of Object.entries(previewSamples)) {
    rendered = rendered.split(token).join(value);
  }
  return rendered;
});

const setOwnerScope = (scope: 'personal' | 'app') => {
  ownerScope.value = scope;
  if (props.mode === 'create') {
    createForm.is_personal = scope === 'personal';
  } else {
    editForm.is_personal = scope === 'personal';
  }
};

const submitLabel = computed(() =>
  props.mode === 'create' ? t('保存') : t('保存修改'),
);

const cancelHref = computed(() => appRoutes.cannedReplies.index.url());

const submit = (event: Event) => {
  event.preventDefault();

  const visitOptions = {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
  };

  if (props.mode === 'create') {
    createForm.is_personal = ownerScope.value === 'personal';
    createForm.post(cannedReplyRoutes.store.url(), visitOptions);
    return;
  }

  if (!props.cannedReply) {
    return;
  }

  editForm.is_personal = ownerScope.value === 'personal';
  editForm.put(
    cannedReplyRoutes.update.url({
      cannedReply: props.cannedReply.id,
    }),
    visitOptions,
  );
};

const setShortcut = (value: string) => {
  form.value.shortcut = value === '' ? null : value.toLowerCase();
};
</script>

<template>
  <form :class="formSpacingClass" @submit="submit">
    <HeadingSmall
      v-if="variant === 'page'"
      :title="mode === 'create' ? t('添加快捷回复') : t('编辑快捷回复')"
    />

    <div class="grid gap-2">
      <Label>{{ t('使用范围') }}</Label>
      <Select
        :model-value="ownerScope"
        :disabled="form.processing"
        @update:model-value="
          (value) => setOwnerScope(value as 'personal' | 'app')
        "
      >
        <SelectTrigger class="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="personal">
            {{ t('仅自己使用') }}
          </SelectItem>
          <SelectItem value="app">
            {{ t('团队共享') }}
          </SelectItem>
        </SelectContent>
      </Select>
      <InputError :message="ownerScopeError" />
    </div>

    <div :class="contentLayoutClass">
      <div class="space-y-4">
        <div class="grid gap-2">
          <Label for="canned-reply-name" required>{{ t('名称') }}</Label>
          <Input
            id="canned-reply-name"
            v-model="form.name"
            :disabled="form.processing"
            required
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="canned-reply-shortcut">
            {{ t('快捷指令（可选）') }}
          </Label>
          <Input
            id="canned-reply-shortcut"
            :model-value="form.shortcut ?? ''"
            :disabled="form.processing"
            @update:model-value="(value) => setShortcut(String(value))"
          />
          <p class="text-xs text-muted-foreground">
            {{
              t(
                '只填写 / 后面的内容，例如 refund。回复时输入 /refund 即可调用。',
              )
            }}
          </p>
          <InputError :message="form.errors.shortcut" />
        </div>

        <div class="grid gap-2">
          <div class="flex items-center justify-between gap-4">
            <Label for="canned-reply-content" required>{{
              t('回复内容')
            }}</Label>
            <div class="flex items-center gap-2">
              <Popover v-model:open="insertVariableOpen">
                <PopoverTrigger as-child>
                  <Button type="button" variant="outline" size="sm">
                    {{ t('插入自动内容') }}
                    <ChevronDown class="ml-1 h-3.5 w-3.5" />
                  </Button>
                </PopoverTrigger>
                <PopoverContent align="end" class="w-72 p-0">
                  <div class="max-h-72 space-y-3 overflow-y-auto p-2">
                    <div
                      v-for="group in groupedTokens"
                      :key="group.kind"
                      class="space-y-1"
                    >
                      <p
                        class="px-2 pt-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                      >
                        {{ group.kindLabel }}
                      </p>
                      <button
                        v-for="token in group.items"
                        :key="token.token"
                        type="button"
                        class="flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-1.5 text-left text-sm transition hover:bg-muted"
                        @click="insertToken(token.token)"
                      >
                        <span class="font-medium">{{ token.label }}</span>
                      </button>
                    </div>
                  </div>
                </PopoverContent>
              </Popover>
            </div>
          </div>
          <Textarea
            id="canned-reply-content"
            ref="contentTextareaRef"
            v-model="form.content"
            rows="10"
            :disabled="form.processing"
            :class="[contentTextareaClass, 'leading-6']"
            required
          />
          <InputError :message="form.errors.content" />
        </div>
      </div>

      <aside class="space-y-3">
        <p class="text-sm font-medium">{{ t('示例效果') }}</p>
        <div
          :class="[
            previewClass,
            'rounded-md border bg-muted/30 p-3 text-sm leading-6 whitespace-pre-wrap',
          ]"
        >
          <span v-if="previewContent">{{ previewContent }}</span>
        </div>
      </aside>
    </div>

    <DialogFooter v-if="variant === 'dialog'" class="gap-2">
      <Button
        type="button"
        variant="secondary"
        :disabled="form.processing"
        @click="emit('cancel')"
      >
        {{ t('取消') }}
      </Button>
      <Button type="submit" :disabled="form.processing">
        {{ form.processing ? t('保存中...') : submitLabel }}
      </Button>
    </DialogFooter>

    <FormActions
      v-else
      :submit-label="submitLabel"
      :processing="form.processing"
      :cancel-href="cancelHref"
      :cancel-label="t('取消')"
    >
      <template #submit>
        {{ form.processing ? t('保存中...') : submitLabel }}
      </template>
    </FormActions>
  </form>
</template>
