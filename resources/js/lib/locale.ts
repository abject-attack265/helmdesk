/**
 * 统一前端 locale 比较规则，保持与后端 LocalePreference::matches 一致。
 */

export function localeMatches(
  first: string | null | undefined,
  second: string | null | undefined,
): boolean {
  const left = normalizeComparableLocale(first);
  const right = normalizeComparableLocale(second);

  if (left === '' || right === '') {
    return false;
  }

  if (left === right) {
    return true;
  }

  return left.split('-')[0] === right.split('-')[0];
}

function normalizeComparableLocale(locale: string | null | undefined): string {
  return locale?.trim().replaceAll('_', '-').toLowerCase() ?? '';
}
