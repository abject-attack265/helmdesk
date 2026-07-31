/**
 * 封装 Mercure 主题、EventSource 创建与接待事件订阅。
 */

/** 后台实例接待频道的统一事件负载。 */
export interface ReceptionInstancePayload {
  event: string;
  occurred_at: string;
  thread_id?: string | null;
  conversation_id?: string;
  contact_id?: string | null;
  assigned_user_id?: string | null;
  previous_assigned_user_id?: string | null;
  inbox_status?: string;
  message_id?: string | null;
  last_message_preview?: string | null;
  contact_name?: string | null;
  channel_name?: string | null;
}

/** 访客端接收的会话变更通知。 */
export interface ReceptionConversationPayload {
  event: string;
  conversation_id: string;
  occurred_at: string;
}

/** 会话公开主题中的接待方活动状态。 */
export interface ReceptionAgentActivityPayload {
  conversation_id: string;
  active: boolean;
  hold_ms: number;
  revision: number;
}

export function receptionInboxTopic(): string {
  return 'urn:helmdesk:reception:inbox';
}

export function receptionConversationTopic(conversationId: string): string {
  return `urn:helmdesk:reception:conversation:${conversationId}`;
}

export function aiChatTopic(roundId: string): string {
  return `urn:helmdesk:ai-chat:${roundId}`;
}

export function openMercureEventSource(topic: string): EventSource {
  return new EventSource(
    `/.well-known/mercure?${new URLSearchParams({ topic }).toString()}`,
    {
      withCredentials: true,
    },
  );
}

function warnMalformedEvent(): void {
  console.warn('忽略格式异常的 Mercure 实时事件。');
}

function eventPayload(event: Event): Record<string, unknown> | null {
  const data = (event as MessageEvent<unknown>).data;
  if (typeof data !== 'string') {
    warnMalformedEvent();
    return null;
  }

  try {
    const payload: unknown = JSON.parse(data);
    if (
      typeof payload !== 'object' ||
      payload === null ||
      Array.isArray(payload)
    ) {
      warnMalformedEvent();
      return null;
    }

    return payload as Record<string, unknown>;
  } catch {
    warnMalformedEvent();
    return null;
  }
}

function isOptionalString(value: unknown): boolean {
  return value === undefined || typeof value === 'string';
}

function isOptionalNullableString(value: unknown): boolean {
  return value === undefined || value === null || typeof value === 'string';
}

function isReceptionInstancePayload(
  payload: Record<string, unknown>,
): payload is Record<string, unknown> & ReceptionInstancePayload {
  return (
    typeof payload.event === 'string' &&
    typeof payload.occurred_at === 'string' &&
    isOptionalNullableString(payload.thread_id) &&
    isOptionalString(payload.conversation_id) &&
    isOptionalNullableString(payload.contact_id) &&
    isOptionalNullableString(payload.assigned_user_id) &&
    isOptionalNullableString(payload.previous_assigned_user_id) &&
    isOptionalString(payload.inbox_status) &&
    isOptionalNullableString(payload.message_id) &&
    isOptionalNullableString(payload.last_message_preview) &&
    isOptionalNullableString(payload.contact_name) &&
    isOptionalNullableString(payload.channel_name)
  );
}

function isReceptionConversationPayload(
  payload: Record<string, unknown>,
): payload is Record<string, unknown> & ReceptionConversationPayload {
  return (
    typeof payload.event === 'string' &&
    typeof payload.conversation_id === 'string' &&
    typeof payload.occurred_at === 'string'
  );
}

function isReceptionAgentActivityPayload(
  payload: Record<string, unknown>,
): payload is Record<string, unknown> & ReceptionAgentActivityPayload {
  return (
    typeof payload.conversation_id === 'string' &&
    typeof payload.active === 'boolean' &&
    typeof payload.hold_ms === 'number' &&
    Number.isInteger(payload.hold_ms) &&
    payload.hold_ms >= 0 &&
    (!payload.active || payload.hold_ms > 0) &&
    typeof payload.revision === 'number' &&
    Number.isInteger(payload.revision) &&
    payload.revision >= 0
  );
}

/** 创建校验会话归属和载荷结构的接待活动事件处理器。 */
function createReceptionAgentActivityHandler(
  conversationId: string,
  handler: (payload: ReceptionAgentActivityPayload) => void,
): (event: Event) => void {
  return (event: Event): void => {
    const payload = eventPayload(event);
    if (
      !payload ||
      !isReceptionAgentActivityPayload(payload) ||
      payload.conversation_id !== conversationId
    ) {
      if (payload) {
        console.warn('[realtime] 忽略异常的接待活动事件', {
          conversationId,
        });
      }
      return;
    }

    handler(payload);
  };
}

export function subscribeReceptionInstance(
  handler: (payload: ReceptionInstancePayload) => void,
): () => void {
  const source = openMercureEventSource(receptionInboxTopic());
  source.addEventListener('reception', (event) => {
    const payload = eventPayload(event);
    if (!payload || !isReceptionInstancePayload(payload)) {
      if (payload) {
        warnMalformedEvent();
      }
      return;
    }

    handler(payload);
  });

  return () => source.close();
}

export function subscribeReceptionConversation(
  conversationId: string,
  handlers: {
    onUpdate: (payload: ReceptionConversationPayload) => void;
    onAgentActivity: (payload: ReceptionAgentActivityPayload) => void;
  },
): () => void {
  const source = openMercureEventSource(
    receptionConversationTopic(conversationId),
  );
  source.addEventListener('reception', (event) => {
    const payload = eventPayload(event);
    if (!payload || !isReceptionConversationPayload(payload)) {
      if (payload) {
        warnMalformedEvent();
      }
      return;
    }

    handlers.onUpdate(payload);
  });

  source.addEventListener(
    'agent_activity',
    createReceptionAgentActivityHandler(
      conversationId,
      handlers.onAgentActivity,
    ),
  );

  return () => source.close();
}

/** 订阅会话接待方活动状态，供后台收件箱展示实时处理提示。 */
export function subscribeReceptionActivity(
  conversationId: string,
  handler: (payload: ReceptionAgentActivityPayload) => void,
): () => void {
  const source = openMercureEventSource(
    receptionConversationTopic(conversationId),
  );
  source.addEventListener(
    'agent_activity',
    createReceptionAgentActivityHandler(conversationId, handler),
  );

  return () => source.close();
}
