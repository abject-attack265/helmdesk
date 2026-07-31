/**
 * 文件说明：从姓名取首字母缩写，用于头像 fallback。
 */
export function getInitials(fullName?: string): string {
  if (!fullName) return '';

  const names = fullName.trim().split(' ');

  if (names.length === 1) return names[0].charAt(0).toUpperCase();

  return `${names[0].charAt(0)}${names[names.length - 1].charAt(0)}`.toUpperCase();
}

export function useInitials() {
  return { getInitials };
}
