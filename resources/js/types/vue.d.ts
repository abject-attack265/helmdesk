/**
 * 文件说明：补充 Vue 单文件组件模块声明，供 TS 入口文件导入 .vue 组件时识别默认导出。
 */
declare module '*.vue' {
  import type { DefineComponent } from 'vue';

  const component: DefineComponent<
    Record<string, unknown>,
    Record<string, unknown>,
    unknown
  >;
  export default component;
}
