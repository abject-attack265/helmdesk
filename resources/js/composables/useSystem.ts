/**
 * 从 Inertia 共享 props 读取系统配置。
 */
import type { SystemData } from '@/types/generated';
import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

function getContextForError() {
  const page = usePage();
  const url =
    (page as any)?.url ??
    (typeof window !== 'undefined' ? window.location.pathname : '');
  const component = (page as any)?.component ?? 'unknown';
  return { url, component };
}

export function useCurrentSystem(): ComputedRef<SystemData | null> {
  const page = usePage();

  return computed(
    () => ((page.props as any)?.app as SystemData | undefined) ?? null,
  );
}

/**
 * 系统页面使用：读取 ShareSystemContext 共享的系统配置。
 */
export function useRequiredSystem(): ComputedRef<SystemData> {
  const page = usePage();

  return computed(() => {
    const app = ((page.props as any)?.app as SystemData | undefined) ?? null;

    if (!app) {
      const { url, component } = getContextForError();
      throw new Error(
        `系统配置缺失：该页面/组件需要 ShareSystemContext 提供 app。component=${component} url=${url}`,
      );
    }

    return app;
  });
}
