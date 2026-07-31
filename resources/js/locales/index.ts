/**
 * 文件说明：前端国际化入口，汇总可用语言和类型定义。
 */
import en from './en';
import zhCN from './zh-CN';

export type Locale = 'zh-CN' | 'en';
export type Messages = Record<keyof typeof zhCN, string>;

export const locales: Record<Locale, Messages> = {
  'zh-CN': zhCN,
  en,
};

// 默认语言为英文：仅当浏览器/用户语言明确为中文时才显示中文，其余一律回落到英文
export const defaultLocale: Locale = 'en';

export const availableLocales: Array<{ value: Locale; label: string }> = [
  { value: 'zh-CN', label: '简体中文' },
  { value: 'en', label: 'English' },
];
