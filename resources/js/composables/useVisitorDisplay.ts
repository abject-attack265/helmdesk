/**
 * 文件说明：拼接匿名访客的展示名，用联系人 ID 末四位作区分后缀。
 */
import { useI18n } from '@/composables/useI18n';

const SUFFIX_LENGTH = 4;

export function useVisitorDisplay() {
  const { t } = useI18n();

  function formatVisitorName(
    name: string | null | undefined,
    contactId: string | null | undefined,
  ): string {
    const trimmedName = name?.toString().trim();
    if (trimmedName) {
      return trimmedName;
    }

    const id = (contactId ?? '').toString().trim();
    if (!id) {
      return t('匿名访客');
    }

    const suffix = id.slice(-SUFFIX_LENGTH).toUpperCase();
    return t('匿名访客 #{suffix}', { suffix });
  }

  return { formatVisitorName };
}
