/**
 * 文件说明：补充 vditor 的最小模块类型，覆盖当前编辑器封装使用到的 API。
 */
declare module 'vditor' {
  export interface IOptions {
    mode?: 'wysiwyg' | 'ir' | 'sv';
    lang?: string;
    height?: number | string;
    placeholder?: string;
    value?: string;
    cache?: { enable?: boolean };
    counter?: { enable?: boolean };
    toolbar?: string[];
    preview?: { actions?: string[] };
    after?: () => void;
    input?: (value: string) => void;
  }

  export default class Vditor {
    constructor(element: string | HTMLElement, options?: IOptions);
    destroy(): void;
    disabled(): void;
    enable(): void;
    getValue(): string;
    setValue(value: string): void;
  }
}
