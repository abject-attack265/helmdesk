/**
 * Avatar fallback 文案：CJK 取首个字符，西文取首末词首字母。
 */

const CJK_PATTERN = /[\u4e00-\u9fff\u3400-\u4dbf\u3040-\u30ff\uac00-\ud7af]/u;

function pickFirstCjk(value: string): string | null {
  for (const char of value) {
    if (CJK_PATTERN.test(char)) {
      return char;
    }
  }

  return null;
}

function pickWesternInitials(value: string): string | null {
  const words = value
    .split(/[\s.\-_]+/u)
    .map((word) => word.replace(/[^\p{L}\p{N}]/gu, ''))
    .filter((word) => word.length > 0);

  if (words.length === 0) {
    return null;
  }

  if (words.length === 1) {
    return words[0]!.slice(0, 1).toUpperCase();
  }

  const first = words[0]!.slice(0, 1);
  const last = words[words.length - 1]!.slice(0, 1);

  return `${first}${last}`.toUpperCase();
}

export function getAvatarInitial(name?: string | null, fallback = '?'): string {
  const trimmed = name?.toString().trim();
  if (!trimmed) {
    return fallback;
  }

  if (trimmed.includes('@')) {
    const local = trimmed.split('@')[0]?.trim();
    if (local) {
      const localInitial =
        pickFirstCjk(local) ?? pickWesternInitials(local) ?? local.slice(0, 1);
      return localInitial.toUpperCase();
    }
  }

  const cjk = pickFirstCjk(trimmed);
  if (cjk) {
    return cjk;
  }

  const western = pickWesternInitials(trimmed);
  if (western) {
    return western;
  }

  return trimmed.slice(0, 1).toUpperCase() || fallback;
}
