/**
 * 文件说明：邮箱归一化与前端轻量格式校验。
 */

const EMAIL_REGEX =
  /^[A-Za-z0-9._%+-]+@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)+$/;

export const EMAIL_MAX_LENGTH = 254;

export const normalizeEmail = (value: string): string => {
  return value.trim();
};

export const isLikelyValidEmail = (value: string): boolean => {
  const normalized = normalizeEmail(value);

  if (normalized === '' || normalized.length > EMAIL_MAX_LENGTH) {
    return false;
  }

  return EMAIL_REGEX.test(normalized);
};
