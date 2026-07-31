<!--
  AI 助手客户简报展示 InboxContactProfileData 中的交接简报和译文。
-->
<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import inboxActions from '@/routes/app/inbox';
import type {
  ContactHandoffBriefData,
  InboxContactProfileData,
  MessageTranslationData,
} from '@/types/generated';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
  contactProfile: InboxContactProfileData;
  targetLocale: string;
  canTranslate: boolean;
  translationEnabled: boolean;
}>();

const { t } = useI18n();

type ContextTranslation = {
  brief: MessageTranslationData;
  next_actions: MessageTranslationData[];
};

const queuedTranslationKey = ref<string | null>(null);

const handoffBrief = computed<ContactHandoffBriefData | null>(
  () => props.contactProfile.handoff_brief,
);
const translation = computed<ContextTranslation | null>(() => {
  const translations = handoffBrief.value?.translations as
    Record<string, unknown> | undefined;
  const value = translations?.[props.targetLocale];

  if (value === undefined) {
    return null;
  }
  if (typeof value !== 'object' || value === null) {
    throw new Error('联系人当前情况译文必须是对象');
  }

  return value as ContextTranslation;
});
const hasTranslation = computed(() => translation.value !== null);
const translationActive = computed(
  () => props.translationEnabled && hasTranslation.value,
);
const displayBrief = computed(() =>
  translationActive.value
    ? translation.value!.brief.text
    : (handoffBrief.value?.brief ?? null),
);
const displayNextActions = computed(() =>
  translationActive.value
    ? translation.value!.next_actions.map((item) => item.text)
    : (handoffBrief.value?.next_actions ?? []),
);
const translationRequestKey = computed(() => {
  if (
    !props.translationEnabled ||
    !props.canTranslate ||
    !handoffBrief.value ||
    hasTranslation.value
  ) {
    return null;
  }

  return [
    props.contactProfile.id,
    handoffBrief.value.updated_at,
    props.targetLocale,
  ].join(':');
});

watch(
  translationRequestKey,
  (requestKey) => {
    if (requestKey === null || requestKey === queuedTranslationKey.value) {
      return;
    }

    const contactId = props.contactProfile.id;
    const targetLocale = props.targetLocale;
    queuedTranslationKey.value = requestKey;
    void axios
      .post(
        inboxActions.contacts.handoffBrief.translate.url({
          contactId,
        }),
        {
          target_locale: targetLocale,
          force: false,
        },
      )
      .catch((error: unknown) => {
        queuedTranslationKey.value = null;
        console.warn('[ai-assistant-context] 客户情况翻译入队失败', {
          contactId,
          targetLocale,
          error,
        });
      });
  },
  { immediate: true },
);
</script>

<template>
  <section
    v-if="handoffBrief"
    class="shrink-0 space-y-3 border-b border-border/60 px-3 py-3"
  >
    <div class="space-y-1">
      <h3 class="text-xs font-medium text-muted-foreground">
        {{ t('当前情况') }}
      </h3>
      <p class="text-sm leading-5 break-words whitespace-pre-wrap">
        {{ displayBrief }}
      </p>
    </div>

    <div v-if="displayNextActions.length > 0" class="space-y-1">
      <h3 class="text-xs font-medium text-muted-foreground">
        {{ t('下一步') }}
      </h3>
      <ul class="space-y-1 text-sm">
        <li
          v-for="item in displayNextActions"
          :key="item"
          class="flex gap-2 leading-5"
        >
          <span aria-hidden="true">·</span>
          <span class="break-words">{{ item }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
