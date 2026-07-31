/**
 * 文件说明：className 合并（cn）与 Inertia 链接 URL/激活态判定工具。
 */
import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function urlIsActive(
  urlToCheck: NonNullable<InertiaLinkProps['href']>,
  currentUrl: string,
  options?: {
    /**
     * - exact: 完全匹配（包含 query/hash）
     * - path: 只匹配 pathname（忽略 query/hash）
     * - prefix: pathname 前缀匹配（带边界），用于“父级菜单在子路由也保持高亮”
     */
    mode?: 'exact' | 'path' | 'prefix';
  },
) {
  const mode = options?.mode ?? 'exact';
  const checkUrl = toUrl(urlToCheck);

  if (mode === 'exact') {
    return checkUrl === currentUrl;
  }

  const checkPath = normalizePathname(getPathname(checkUrl));
  const currentPath = normalizePathname(getPathname(currentUrl));

  if (mode === 'path') {
    return checkPath === currentPath;
  }

  // 前缀匹配只允许命中完整路径段。
  return currentPath === checkPath || currentPath.startsWith(`${checkPath}/`);
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
  return typeof href === 'string' ? href : href?.url;
}

function getPathname(url: string) {
  try {
    return new URL(url, 'http://localhost').pathname;
  } catch {
    return url.split('#')[0].split('?')[0];
  }
}

function normalizePathname(pathname: string) {
  if (pathname.length <= 1) return pathname;
  return pathname.replace(/\/+$/, '');
}
