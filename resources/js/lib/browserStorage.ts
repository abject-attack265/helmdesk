/**
 * 封装浏览器本地存储读写，并在存储不可用时记录可定位的告警。
 */

export interface BrowserStorageContext {
  /** 告警日志前缀，如 `[inbox-search]`。 */
  channel: string;
  /** 一并写入告警的业务字段。 */
  details?: Record<string, unknown>;
}

function warnStorageFailure(
  context: BrowserStorageContext,
  operation: 'read' | 'write' | 'remove',
  key: string,
  error: unknown,
): void {
  console.warn(`${context.channel} 本地存储访问失败`, {
    ...context.details,
    operation,
    storageKey: key,
    errorType: error instanceof Error ? error.name : typeof error,
  });
}

/** 读取本地存储项，存储不可用时返回 null。 */
export function readLocalStorageItem(
  key: string,
  context: BrowserStorageContext,
): string | null {
  if (typeof window === 'undefined') {
    return null;
  }

  try {
    return window.localStorage.getItem(key);
  } catch (error) {
    warnStorageFailure(context, 'read', key, error);

    return null;
  }
}

/** 写入本地存储项，返回是否写入成功。 */
export function writeLocalStorageItem(
  key: string,
  value: string,
  context: BrowserStorageContext,
): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  try {
    window.localStorage.setItem(key, value);

    return true;
  } catch (error) {
    warnStorageFailure(context, 'write', key, error);

    return false;
  }
}

/** 删除本地存储项，返回是否删除成功。 */
export function removeLocalStorageItem(
  key: string,
  context: BrowserStorageContext,
): boolean {
  if (typeof window === 'undefined') {
    return false;
  }

  try {
    window.localStorage.removeItem(key);

    return true;
  } catch (error) {
    warnStorageFailure(context, 'remove', key, error);

    return false;
  }
}
