/**
 * 文件说明：全局模块类型增强——补充 Vite glob、Inertia PageProps 与 Vue 组件自定义属性。
 */
import { AppPageProps } from '@/types/index';

// 补充 Vite 注入到 import.meta 上的 glob 类型。
declare module 'vite/client' {
  interface ImportMeta {
    readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
  }
}

declare module '@inertiajs/core' {
  interface PageProps extends InertiaPageProps, AppPageProps {}
}

declare module 'vue' {
  interface ComponentCustomProperties {
    $inertia: typeof Router;
    $page: Page;
    $headManager: ReturnType<typeof createHeadManager>;
  }
}
