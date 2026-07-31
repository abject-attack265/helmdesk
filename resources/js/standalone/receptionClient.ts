/**
 * 文件说明：访客接待 HTTP 客户端。发往访客接待端点(/api/chat/*)的请求统一在此注入凭证与上下文头：
 *   - Authorization: Bearer <user_token>（签名身份）
 *   - X-Helmdesk-Visitor-Token（不透明会话）
 *   - X-Helmdesk-Entry-Mode / 访客环境头(locale/timezone) / X-Helmdesk-Query-Params
 * 对象存储直传与 Laravel 访客附件端点使用各自的上传客户端。
 */
import type { ReceptionCredentials } from '@/standalone/receptionCredentials';

export interface ReceptionRequestError extends Error {
  status?: number;
}

interface ReceptionClientOptions {
  credentials: ReceptionCredentials;
  /** 访客环境头(locale/timezone)，由画布按 UI 状态解析后提供。 */
  environmentHeaders?: () => Record<string, string>;
  /** 响应非 JSON / 解析失败时的错误文案。 */
  parseErrorMessage: string;
  /** !response.ok 且响应体未携带 message 时的错误文案。 */
  requestErrorMessage: string;
}

/**
 * 构造发往接待端点的凭证与上下文头集合。供接待客户端与访客附件上传复用同一份凭证来源。
 */
export function buildReceptionHeaders(
  credentials: ReceptionCredentials,
  environmentHeaders?: () => Record<string, string>,
): Record<string, string> {
  const headers: Record<string, string> = {
    'X-Helmdesk-Entry-Mode': credentials.entryMode,
    ...(environmentHeaders?.() ?? {}),
  };

  const userToken = credentials.userToken();
  if (userToken) {
    headers['Authorization'] = `Bearer ${userToken}`;
  }

  const sessionToken = credentials.sessionToken();
  if (sessionToken) {
    headers['X-Helmdesk-Visitor-Token'] = sessionToken;
  }

  const queryParams = credentials.queryParams();
  if (Object.keys(queryParams).length > 0) {
    headers['X-Helmdesk-Query-Params'] = JSON.stringify(queryParams);
  }

  return headers;
}

/**
 * 创建带凭证的接待客户端。返回的 request 直接返回解析后的 JSON，非 2xx 抛出带 status 的错误。
 */
export function createReceptionClient(options: ReceptionClientOptions) {
  async function request<T>(
    method: 'GET' | 'POST',
    path: string,
    body?: unknown,
  ): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...buildReceptionHeaders(options.credentials, options.environmentHeaders),
    };

    const init: RequestInit = { method, credentials: 'same-origin', headers };
    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }

    const response = await fetch(path, init);
    const contentType = response.headers.get('content-type') ?? '';
    const payload = contentType.includes('application/json')
      ? await parseJson(response, options.parseErrorMessage)
      : null;

    if (!response.ok) {
      const message =
        (payload && typeof payload === 'object' && 'message' in payload
          ? String((payload as { message?: unknown }).message ?? '')
          : '') || options.requestErrorMessage;
      const error = new Error(message) as ReceptionRequestError;
      error.status = response.status;
      throw error;
    }

    if (payload === null) {
      throw new Error(options.parseErrorMessage);
    }

    return payload as T;
  }

  /**
   * 发送一次「访客正在输入」信号：尽力而为的 fire-and-forget。
   * 端点返回 204 空体，无需解析；网络/服务异常一律静默吞掉，绝不打扰访客输入。
   * 复用与正式请求同一套凭证头，让服务端据访客会话 token 推迟该会话的聚合 flush。
   */
  async function notifyTyping(path: string): Promise<void> {
    const headers = buildReceptionHeaders(
      options.credentials,
      options.environmentHeaders,
    );

    try {
      await fetch(path, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        keepalive: true,
      });
    } catch {
      // typing 仅为体验优化，失败无副作用，静默忽略。
    }
  }

  return { request, notifyTyping };
}

/**
 * 解析响应 JSON，失败时抛出统一的格式异常错误。
 */
async function parseJson(
  response: Response,
  errorMessage: string,
): Promise<unknown> {
  try {
    return await response.json();
  } catch (error) {
    console.error('Reception API returned invalid JSON.', error);
    throw new Error(errorMessage);
  }
}
