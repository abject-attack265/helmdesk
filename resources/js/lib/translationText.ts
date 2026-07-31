/**
 * 判断消息文本是否包含值得交给翻译供应商处理的语言文字。
 */

/** 判断文本是否至少包含一个 Unicode 字母。 */
export function hasTranslatableLetters(text: string): boolean {
  return /\p{L}/u.test(text);
}
