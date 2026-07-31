/**
 * 后台应用布局订阅接待事件，并触发浏览器通知和声音提醒。
 */
import {
  subscribeReceptionInstance,
  type ReceptionInstancePayload,
} from '@/lib/mercure';
import app from '@/routes/app';
import type { UserNotificationPreferencesData } from '@/types/generated';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, watch, type Ref } from 'vue';
import { showBrowserNotification } from './useBrowserNotification';
import { useI18n } from './useI18n';
import { playNotificationSound } from './useNotificationSound';

interface InstanceNotificationAlertOptions {
  userId: Ref<string>;
  preferences: Ref<UserNotificationPreferencesData>;
  currentThreadId: Ref<string | null>;
}

interface NotificationAlert {
  title: string;
  body: string;
  threadId: string;
}

const THREAD_ALERT_EVENTS = new Set([
  'visitor_message_created',
  'conversation_transferred',
  'handoff_requested',
]);

export function useSystemNotificationAlerts(
  options: InstanceNotificationAlertOptions,
): void {
  if (typeof window === 'undefined') {
    return;
  }

  const { t } = useI18n();
  let unsubscribe: (() => void) | null = null;
  let lastAlertKey: string | null = null;

  function closeSubscription(): void {
    if (unsubscribe) {
      unsubscribe();
      unsubscribe = null;
    }
  }

  function threadUrl(threadId: string): string {
    return app.inbox.show.url({
      query: { thread_id: threadId },
    });
  }

  function buildBody(payload: ReceptionInstancePayload): string {
    const parts = [payload.contact_name, payload.last_message_preview]
      .map((value) => (typeof value === 'string' ? value.trim() : ''))
      .filter((value) => value !== '');

    return parts.length > 0 ? parts.join('：') : t('点击查看会话');
  }

  function resolveAlert(
    payload: ReceptionInstancePayload,
  ): NotificationAlert | null {
    const threadId = payload.thread_id;
    if (!threadId) {
      if (THREAD_ALERT_EVENTS.has(payload.event)) {
        console.warn('[inbox-notifications] 会话事件缺少线程标识', {
          conversationId: payload.conversation_id,
          event: payload.event,
        });
      }

      return null;
    }

    if (
      payload.event === 'visitor_message_created' &&
      payload.assigned_user_id === options.userId.value
    ) {
      return {
        title: t('新的访客消息'),
        body: buildBody(payload),
        threadId,
      };
    }

    if (
      payload.event === 'visitor_message_created' &&
      payload.assigned_user_id === null &&
      payload.inbox_status === 'teammate_pending'
    ) {
      return {
        title: t('新的待接入会话'),
        body: buildBody(payload),
        threadId,
      };
    }

    if (
      payload.event === 'conversation_transferred' &&
      payload.assigned_user_id === options.userId.value &&
      payload.previous_assigned_user_id !== options.userId.value
    ) {
      return {
        title: t('会话已转接给你'),
        body: buildBody(payload),
        threadId,
      };
    }

    if (
      payload.event === 'handoff_requested' &&
      payload.inbox_status === 'teammate_pending'
    ) {
      return {
        title: t('新的待接入会话'),
        body: buildBody(payload),
        threadId,
      };
    }

    return null;
  }

  function isActivelyViewingThread(threadId: string): boolean {
    // 标签页处于前台且正打开该线程时，坐席已经看得到新消息，无需再打扰。
    if (!document.hasFocus()) {
      return false;
    }

    return options.currentThreadId.value === threadId;
  }

  function openThread(threadId: string): void {
    window.focus();

    router.visit(threadUrl(threadId), {
      preserveState: false,
      preserveScroll: false,
    });
  }

  function handlePayload(payload: ReceptionInstancePayload): void {
    const preferences = options.preferences.value;
    const alert = resolveAlert(payload);
    if (!alert) {
      return;
    }

    if (isActivelyViewingThread(alert.threadId)) {
      return;
    }

    const alertKey = `${payload.event}:${alert.threadId}:${payload.message_id ?? payload.occurred_at}`;
    if (alertKey === lastAlertKey) {
      return;
    }
    lastAlertKey = alertKey;

    if (preferences.sound_enabled) {
      playNotificationSound(preferences.sound);
    }

    if (preferences.browser_notifications_enabled) {
      showBrowserNotification({
        title: alert.title,
        body: alert.body,
        url: threadUrl(alert.threadId),
        onClick: () => openThread(alert.threadId),
      });
    }
  }

  function subscribe(): void {
    closeSubscription();

    const preferences = options.preferences.value;
    if (
      !preferences.browser_notifications_enabled &&
      !preferences.sound_enabled
    ) {
      return;
    }

    unsubscribe = subscribeReceptionInstance(handlePayload);
  }

  watch([options.userId, options.preferences], subscribe, { immediate: true });

  onBeforeUnmount(closeSubscription);
}
