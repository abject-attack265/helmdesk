/**
 * 聊天记录搜索结果的关键词高亮工具：按 CJK 字符 / 非 CJK 词元切分搜索词，
 * 对消息文本做 HTML 转义后包裹 <mark> 高亮片段，供收件箱各搜索结果列表复用。
 */

const cjkSegmentPattern = /([⺀-鿿豈-﫿︰-﹏\u{20000}-\u{2fa1f}]+)/gu;
const cjkOnlyPattern = /^[⺀-鿿豈-﫿︰-﹏\u{20000}-\u{2fa1f}]+$/u;
const nonCjkTokenPattern = /[\p{L}\p{N}_@-]+/gu;

function escapeHtml(content: string): string {
  return content
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function escapeRegExp(content: string): string {
  return content.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightTokens(search: string): string[] {
  const tokens: string[] = [];
  const segments = search
    .trim()
    .toLowerCase()
    .split(cjkSegmentPattern)
    .filter(Boolean);

  for (const segment of segments) {
    if (cjkOnlyPattern.test(segment)) {
      tokens.push(...Array.from(segment));
      continue;
    }

    tokens.push(...(segment.match(nonCjkTokenPattern) ?? []));
  }

  return Array.from(new Set(tokens)).sort((a, b) => b.length - a.length);
}

export function highlightMessageContent(
  content: string | null,
  search: string,
): string {
  if (!content) return '';

  const safeContent = escapeHtml(content);
  if (!search) return safeContent;

  const tokens = highlightTokens(search);

  if (tokens.length === 0) {
    return safeContent;
  }

  const regex = new RegExp(`(${tokens.map(escapeRegExp).join('|')})`, 'giu');

  return safeContent.replace(
    regex,
    '<mark class="bg-foreground/15 text-foreground rounded-sm px-0.5">$1</mark>',
  );
}
