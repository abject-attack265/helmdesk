/**
 * 管理收件箱会话列表预览的自动翻译。
 */
import inboxActions from '@/routes/app/inbox';
import type { ListConversationItemData } from '@/types/generated';
import axios from 'axios';
import { type ComputedRef, type Ref, onUnmounted, watch } from 'vue';

const DEBOUNCE_MS = 500;
const BATCH_SIZE = 50;

export interface UseInboxConversationPreviewAutoTranslateOptions {
  conversationList: ComputedRef<ListConversationItemData[]>;
  sourceLocale: Ref<string>;
  targetLocale: Ref<string>;
  enabled: Ref<boolean>;
}

export function useInboxConversationPreviewAutoTranslate(
  options: UseInboxConversationPreviewAutoTranslateOptions,
): void {
  // SSR 阶段不注册会话预览翻译监听。
  if (typeof window === 'undefined') {
    return;
  }

  const { conversationList, sourceLocale, targetLocale, enabled } = options;

  const attempted = new Set<string>();
  let timer: number | null = null;
  let running = false;
  let rerunRequested = false;

  function previewKey(item: ListConversationItemData): string {
    return `${item.id}:${item.last_message_at ?? ''}`;
  }

  async function run(): Promise<void> {
    if (!enabled.value) {
      return;
    }
    if (running) {
      rerunRequested = true;
      return;
    }
    const items = conversationList.value.filter(
      (item) =>
        item.last_message_can_translate &&
        !item.last_message_translation_previews[targetLocale.value] &&
        !attempted.has(previewKey(item)),
    );
    if (items.length === 0) {
      return;
    }

    running = true;
    const ids = items.map((item) => item.id);
    const keys = items.map(previewKey);
    keys.forEach((key) => attempted.add(key));
    try {
      for (let i = 0; i < ids.length; i += BATCH_SIZE) {
        await axios.post(inboxActions.conversationPreviews.translate.url(), {
          conversation_ids: ids.slice(i, i + BATCH_SIZE),
          source_locale: sourceLocale.value,
          target_locale: targetLocale.value,
        });
      }
    } catch (error) {
      console.warn('[inbox-preview-translation] 会话列表预览翻译入队失败', {
        conversationIds: ids,
        sourceLocale: sourceLocale.value,
        targetLocale: targetLocale.value,
        error,
      });
      keys.forEach((key) => attempted.delete(key));
    } finally {
      running = false;
      if (rerunRequested && enabled.value) {
        rerunRequested = false;
        schedule();
      }
    }
  }

  function schedule(): void {
    if (timer !== null) {
      window.clearTimeout(timer);
    }
    timer = window.setTimeout(() => {
      timer = null;
      void run();
    }, DEBOUNCE_MS);
  }

  watch(
    () => [
      enabled.value,
      conversationList.value
        .map(
          (item) =>
            `${item.id}:${item.last_message_can_translate ? '1' : '0'}:${item.last_message_translation_previews[targetLocale.value] ?? ''}:${item.last_message_at ?? ''}`,
        )
        .join('|'),
    ],
    () => {
      if (enabled.value) {
        schedule();
      }
    },
    { immediate: true },
  );

  watch([sourceLocale, targetLocale, enabled], () => {
    attempted.clear();
    if (enabled.value) {
      schedule();
    }
  });

  onUnmounted(() => {
    if (timer !== null) {
      window.clearTimeout(timer);
    }
  });
}
