/**
 * 文件说明：iframe 侧与宿主页之间的 postMessage 桥接 composable，统一管理 origin 锁定、消息分发与对外消息发送。
 */
import {
  inject,
  onBeforeUnmount,
  onMounted,
  readonly,
  ref,
  type InjectionKey,
  type Ref,
} from 'vue';

/**
 * iframe → host 出站消息类型。
 */
export type WidgetOutboundMessageType =
  | 'helmdesk:widget:ready'
  | 'helmdesk:widget:close'
  | 'helmdesk:widget:unread'
  | 'helmdesk:widget:toast';

/**
 * host → iframe 入站消息类型。
 */
export type WidgetInboundMessageType =
  | 'helmdesk:host:context'
  | 'helmdesk:host:visibility'
  | 'helmdesk:host:track'
  | 'helmdesk:host:shutdown';

/**
 * 宿主页通过 `helmdesk:host:track` 透传的自定义事件 payload。
 */
export interface WidgetHostTrackEvent {
  event: string;
  properties?: Record<string, unknown>;
}

/**
 * 宿主页通过 `helmdesk:host:context` 一次性回传的环境信息。
 *
 * user_token 是宿主页后端签发的访客身份 JWT。
 * widget 端据此作为 Authorization: Bearer 透传给接待端点，实现登录用户身份穿透。
 */
export interface WidgetHostContext {
  page_url: string;
  page_title: string;
  referrer: string;
  query_params: Record<string, string>;
  user_token: string | null;
}

interface WidgetInboundMessage {
  type: WidgetInboundMessageType;
  payload?: unknown;
}

export interface WidgetHostBridge {
  hostOrigin: Readonly<Ref<string | null>>;
  hostContext: Readonly<Ref<WidgetHostContext | null>>;
  hostVisible: Readonly<Ref<boolean | null>>;
  lastTrackEvent: Readonly<Ref<WidgetHostTrackEvent | null>>;
  shutdownRequested: Readonly<Ref<boolean>>;
  sendToHost: (type: WidgetOutboundMessageType, payload?: unknown) => void;
}

/**
 * provide/inject key：iframe 入口组件向子组件下发桥接对象，供其后续主动发消息或读取宿主信息。
 */
export const WIDGET_HOST_BRIDGE_INJECTION_KEY: InjectionKey<WidgetHostBridge> =
  Symbol('helmdesk:widget-host-bridge');

/**
 * 在 StandaloneCanvas 等子组件中按需获取宿主桥接；不在 widget iframe 内消费时返回 null。
 */
export function injectWidgetHostBridge(): WidgetHostBridge | null {
  return inject(WIDGET_HOST_BRIDGE_INJECTION_KEY, null);
}

const INBOUND_TYPE_PATTERN = /^helmdesk:host:[a-z]+$/;

/**
 * 判断给定消息是否符合 host → iframe 协议形状。
 */
function isInboundMessage(value: unknown): value is WidgetInboundMessage {
  if (value === null || typeof value !== 'object') {
    return false;
  }

  const candidate = value as { type?: unknown };
  return (
    typeof candidate.type === 'string' &&
    INBOUND_TYPE_PATTERN.test(candidate.type)
  );
}

/**
 * 把任意 payload 收窄为 host context 形状；非法值返回 null。
 */
function normalizeHostContext(payload: unknown): WidgetHostContext | null {
  if (payload === null || typeof payload !== 'object') {
    return null;
  }

  const candidate = payload as Record<string, unknown>;
  const pageUrl =
    typeof candidate.page_url === 'string' ? candidate.page_url : '';
  const pageTitle =
    typeof candidate.page_title === 'string' ? candidate.page_title : '';
  const referrer =
    typeof candidate.referrer === 'string' ? candidate.referrer : '';
  const queryParams: Record<string, string> = {};
  const rawParams = candidate.query_params;
  if (
    rawParams !== null &&
    typeof rawParams === 'object' &&
    !Array.isArray(rawParams)
  ) {
    Object.entries(rawParams as Record<string, unknown>).forEach(
      ([key, value]) => {
        if (typeof value === 'string') {
          queryParams[key] = value;
        }
      },
    );
  }

  const userToken =
    typeof candidate.user_token === 'string' && candidate.user_token.trim()
      ? candidate.user_token.trim()
      : null;

  return {
    page_url: pageUrl,
    page_title: pageTitle,
    referrer: referrer,
    query_params: queryParams,
    user_token: userToken,
  };
}

/**
 * 把任意 payload 收窄为 host:visibility 的 `{ visible: boolean }` 形状；非法值返回 null。
 */
function normalizeHostVisibility(payload: unknown): boolean | null {
  if (payload === null || typeof payload !== 'object') {
    return null;
  }

  const candidate = payload as { visible?: unknown };
  return typeof candidate.visible === 'boolean' ? candidate.visible : null;
}

/**
 * 把任意 payload 收窄为 host:track 的 `{ event, properties? }` 形状；非法值返回 null。
 */
function normalizeHostTrack(payload: unknown): WidgetHostTrackEvent | null {
  if (payload === null || typeof payload !== 'object') {
    return null;
  }

  const candidate = payload as { event?: unknown; properties?: unknown };
  if (typeof candidate.event !== 'string' || candidate.event.trim() === '') {
    return null;
  }

  const event: WidgetHostTrackEvent = { event: candidate.event.trim() };
  if (
    candidate.properties &&
    typeof candidate.properties === 'object' &&
    !Array.isArray(candidate.properties)
  ) {
    event.properties = candidate.properties as Record<string, unknown>;
  }

  return event;
}

/**
 * 建立 iframe 与宿主页之间的双向 postMessage 桥接。
 *
 * - 挂载后立即向 `window.parent` 广播 `helmdesk:widget:ready`；
 * - 收到首个合法 `helmdesk:host:*` 消息时锁定可信 origin 与宿主 window 引用；
 * - 之后所有出站消息都精确指定该 origin；
 * - 任何非可信 origin、非协议格式的消息直接忽略。
 */
export function useWidgetHostBridge(): WidgetHostBridge {
  const hostOrigin = ref<string | null>(null);
  const hostContext = ref<WidgetHostContext | null>(null);
  const hostVisible = ref<boolean | null>(null);
  const lastTrackEvent = ref<WidgetHostTrackEvent | null>(null);
  const shutdownRequested = ref(false);
  let hostWindow: Window | null = null;

  /**
   * 把消息发回宿主页，origin 未锁定时使用 `'*'` 仅用于发送 ready 握手。
   */
  function sendToHost(
    type: WidgetOutboundMessageType,
    payload?: unknown,
  ): void {
    if (typeof window === 'undefined' || window.parent === window) {
      return;
    }

    const target = hostWindow ?? window.parent;
    const targetOrigin = hostOrigin.value ?? '*';
    const message: { type: WidgetOutboundMessageType; payload?: unknown } = {
      type,
    };
    if (payload !== undefined) {
      message.payload = payload;
    }

    try {
      target.postMessage(message, targetOrigin);
    } catch (error) {
      console.warn('helmdesk widget bridge send failed', error);
    }
  }

  function handleMessage(event: MessageEvent): void {
    if (typeof window === 'undefined' || event.source === window) {
      return;
    }

    // 仅信任来自直接父窗口的消息，避免宿主页中其他 iframe 抢先发送
    // 合法形状的 host:* 消息，把 bridge 锁到错误的 origin 上。
    if (event.source !== window.parent) {
      return;
    }

    if (hostOrigin.value !== null && event.origin !== hostOrigin.value) {
      return;
    }

    if (!isInboundMessage(event.data)) {
      return;
    }

    if (hostOrigin.value === null) {
      // 当前只允许直接父窗口握手（见上方 event.source !== window.parent 校验），
      // 因此首条合法消息的 source 即可作为可信宿主 window。如果未来放宽到嵌套
      // iframe，需要重新评估是否还能用「第一条消息」直接锁 hostWindow。
      hostOrigin.value = event.origin;
      hostWindow = event.source as Window | null;
    }

    switch (event.data.type) {
      case 'helmdesk:host:context': {
        const next = normalizeHostContext(event.data.payload);
        if (next !== null) {
          hostContext.value = next;
        }
        return;
      }
      case 'helmdesk:host:visibility': {
        const next = normalizeHostVisibility(event.data.payload);
        if (next !== null) {
          hostVisible.value = next;
        }
        return;
      }
      case 'helmdesk:host:track': {
        const next = normalizeHostTrack(event.data.payload);
        if (next !== null) {
          lastTrackEvent.value = next;
        }
        return;
      }
      case 'helmdesk:host:shutdown':
        shutdownRequested.value = true;
        return;
    }
  }

  onMounted(() => {
    if (typeof window === 'undefined') {
      return;
    }

    window.addEventListener('message', handleMessage);
    sendToHost('helmdesk:widget:ready');
  });

  onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
      return;
    }

    window.removeEventListener('message', handleMessage);
  });

  return {
    hostOrigin: readonly(hostOrigin),
    hostContext: readonly(hostContext),
    hostVisible: readonly(hostVisible),
    lastTrackEvent: readonly(lastTrackEvent),
    shutdownRequested: readonly(shutdownRequested),
    sendToHost,
  };
}
