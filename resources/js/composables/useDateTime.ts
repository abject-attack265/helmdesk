import dayjs from 'dayjs';
import 'dayjs/locale/en';
import 'dayjs/locale/zh-cn';
import timezonePlugin from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';

import { useI18n } from '@/composables/useI18n';
import { useTimezone } from '@/composables/useTimezone';
import type { Locale } from '@/locales';

dayjs.extend(utc);
dayjs.extend(timezonePlugin);

const toDayjsLocale = (locale: Locale) => {
  switch (locale) {
    case 'zh-CN':
      return 'zh-cn';
    case 'en':
      return 'en';
  }

  const unsupportedLocale: never = locale;
  throw new Error(`Unsupported date locale: ${unsupportedLocale}`);
};

export function useDateTime() {
  const { locale, t } = useI18n();
  const { timezone } = useTimezone();

  function formatDateTime(
    date: Date | string,
    format = 'YYYY-MM-DD HH:mm:ss',
  ): string {
    return dayjs(date)
      .tz(timezone.value)
      .locale(toDayjsLocale(locale.value))
      .format(format);
  }

  /**
   * 列表行使用的紧凑相对时间。
   */
  function formatRelativeShort(date: Date | string): string {
    const localized = dayjs(date)
      .tz(timezone.value)
      .locale(toDayjsLocale(locale.value));
    const now = dayjs().tz(timezone.value);
    const diffSeconds = now.diff(localized, 'second');

    if (diffSeconds < 0) {
      return localized.format('HH:mm');
    }
    if (diffSeconds < 60) {
      return t('刚刚');
    }

    const diffMinutes = now.diff(localized, 'minute');
    if (diffMinutes < 60) {
      return t('{n} 分钟前', { n: diffMinutes });
    }

    if (localized.isSame(now, 'day')) {
      return localized.format('HH:mm');
    }

    if (localized.isSame(now, 'year')) {
      return localized.format('MM-DD');
    }

    return localized.format('YYYY-MM-DD');
  }

  /**
   * 紧凑时间文案及 hover 用完整时间。
   */
  function formatRelativeShortWithTooltip(date: Date | string): {
    short: string;
    full: string;
  } {
    return {
      short: formatRelativeShort(date),
      full: formatDateTime(date, 'YYYY-MM-DD HH:mm'),
    };
  }

  return {
    formatDateTime,
    formatRelativeShort,
    formatRelativeShortWithTooltip,
  };
}
