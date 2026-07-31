/**
 * 文件说明：访客接待凭证抽象。统一管理两类凭证及其来源——
 *   - 签名身份 user_token（独立页读页面 URL / WebView 注入，widget 读宿主页 postMessage 下发的 host context）
 *   - 不透明会话 session_token（由服务端响应回填后本地持有）
 * 供接待客户端与访客附件上传统一消费，避免各调用点各自拼凭证、各自漂移。
 */
import type { WidgetHostBridge } from '@/widget/useWidgetHostBridge';
import { inject, ref, type InjectionKey } from 'vue';

export type ReceptionEntryMode = 'standalone' | 'widget';

// 与后端保留参数黑名单对齐：身份/认证/路由参数不得混入业务自定义参数映射。
const RESERVED_QUERY_KEYS = new Set([
  'user_token',
  'session_token',
  '_token',
  'signature',
  'sig',
]);

export interface ReceptionCredentials {
  /** 入口形态，决定 X-Helmdesk-Entry-Mode 与各通道的凭证来源。 */
  readonly entryMode: ReceptionEntryMode;
  /** 签名身份 JWT，作为 Authorization: Bearer 发往访客接待端点；无身份时为 null。 */
  userToken(): string | null;
  /** 不透明会话 token，作为 X-Helmdesk-Visitor-Token 发往接待端点与访客附件上传。 */
  sessionToken(): string | null;
  /** 用服务端响应里的会话 token 更新本地凭证，供后续请求带上；空值不覆盖已持有的 token。 */
  rememberSessionToken(token: string | null | undefined): void;
  /** 业务自定义 query 参数（utm 等），作为 X-Helmdesk-Query-Params 透传，已剔除保留 key。 */
  queryParams(): Record<string, string>;
}

/**
 * provide/inject key：根组件（StandaloneRoot / WidgetRoot）向 StandaloneCanvas 下发凭证。
 */
export const RECEPTION_CREDENTIALS_INJECTION_KEY: InjectionKey<ReceptionCredentials> =
  Symbol('helmdesk:reception-credentials');

/**
 * 在 StandaloneCanvas 中按需获取凭证；后台预览等非接待上下文返回 null。
 */
export function injectReceptionCredentials(): ReceptionCredentials | null {
  return inject(RECEPTION_CREDENTIALS_INJECTION_KEY, null);
}

/**
 * 过滤业务自定义 query 参数：剔除保留 key、空值，仅保留字符串。
 */
function sanitizeQueryParams(
  source: Record<string, string>,
): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [key, value] of Object.entries(source)) {
    if (RESERVED_QUERY_KEYS.has(key.toLowerCase())) {
      continue;
    }
    if (typeof value === 'string' && value.trim() !== '') {
      out[key] = value;
    }
  }

  return out;
}

/**
 * 从页面 URL 解析独立页的签名身份与业务参数。
 */
function readUrlCredentials(): {
  userToken: string | null;
  queryParams: Record<string, string>;
} {
  if (typeof window === 'undefined') {
    return { userToken: null, queryParams: {} };
  }

  const search = new URLSearchParams(window.location?.search ?? '');
  const userToken = (search.get('user_token') ?? '').trim() || null;
  const params: Record<string, string> = {};
  search.forEach((value, key) => {
    params[key] = value;
  });

  return { userToken, queryParams: sanitizeQueryParams(params) };
}

/**
 * 创建一个本地持有会话 token 的更新器：仅在收到非空 token 时覆盖。
 */
function createSessionHolder() {
  const session = ref<string | null>(null);

  return {
    get: () => session.value,
    remember: (token: string | null | undefined) => {
      const next = (token ?? '').trim();
      if (next !== '') {
        session.value = next;
      }
    },
  };
}

/**
 * 独立页凭证：签名身份与业务参数来自页面 URL（链接 / 二维码 / 原生 WebView 注入），
 * 优先采用调用方显式注入的 user_token（例如 bootstrap 注入），否则回退到 URL。
 */
export function createStandaloneCredentials(
  injectedUserToken?: string | null,
): ReceptionCredentials {
  const url = readUrlCredentials();
  const userToken = (injectedUserToken ?? '').trim() || url.userToken;
  const session = createSessionHolder();

  return {
    entryMode: 'standalone',
    userToken: () => userToken,
    sessionToken: session.get,
    rememberSessionToken: session.remember,
    queryParams: () => url.queryParams,
  };
}

/**
 * Widget 凭证：签名身份与业务参数来自宿主页通过 postMessage 下发的 host context。
 * host context 异步到达，读取时点为请求构造时刻（画布在首次请求前会等待握手）。
 */
export function createWidgetCredentials(
  bridge: WidgetHostBridge,
): ReceptionCredentials {
  const session = createSessionHolder();

  return {
    entryMode: 'widget',
    userToken: () => {
      const token = bridge.hostContext.value?.user_token?.trim();
      return token ? token : null;
    },
    sessionToken: session.get,
    rememberSessionToken: session.remember,
    queryParams: () =>
      sanitizeQueryParams(bridge.hostContext.value?.query_params ?? {}),
  };
}
