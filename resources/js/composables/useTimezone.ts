/**
 * 文件说明：时区选项列表（常用优先）与当前生效时区的解析。
 */
import { getTimeZones } from '@vvo/tzdb';
import { onMounted, ref } from 'vue';

export type Timezone = string;

type TimezoneLocale = 'zh-CN' | 'en';

type TimezoneOption = {
  value: string;
  label: string;
  offset: string;
};

const allTimeZones = getTimeZones({ includeUtc: true });

// IANA 旧别名 → tzdb 规范名映射（如 Asia/Saigon → Asia/Ho_Chi_Minh）。
// 浏览器/操作系统可能上报已废弃的别名，而下方 timezoneData 只含规范名；不做别名归一，
// 这些地区用户在模块加载（getBrowserTimezone）时就会抛错，导致整个 SPA 无法启动。
// zone.group 已包含规范名自身与其全部别名，足以覆盖 tzdb 收录的所有标识。
const canonicalByAlias = new Map<string, string>();
for (const zone of allTimeZones) {
  for (const name of [zone.name, ...zone.group]) {
    canonicalByAlias.set(name, zone.name);
  }
}

/** 把任意 IANA 时区（含旧别名）归一为 tzdb 规范名；无法识别返回 null。 */
const resolveCanonicalTimezone = (
  tz: string | null | undefined,
): string | null => (tz ? (canonicalByAlias.get(tz) ?? null) : null);

// 兜底时区取 tzdb 收录的 UTC 规范名（'Etc/UTC' 而非 'UTC'），否则下游按 timezoneData 查找会 miss。
const FALLBACK_TIMEZONE: Timezone =
  resolveCanonicalTimezone('UTC') ?? 'Etc/UTC';

const formatOffsetFromMinutes = (minutes: number) => {
  const sign = minutes >= 0 ? '+' : '-';
  const abs = Math.abs(minutes);
  const hh = String(Math.floor(abs / 60)).padStart(2, '0');
  const mm = String(abs % 60).padStart(2, '0');
  return `UTC${sign}${hh}:${mm}`;
};

const getContinentKey = (tz: string) => tz.split('/')[0] || '';

const getLocalizedTimeZoneName = (tz: string, locale: TimezoneLocale) => {
  const dtf = new Intl.DateTimeFormat(locale, {
    timeZone: tz,
    timeZoneName: 'long',
  });
  const parts = dtf.formatToParts(new Date());
  const tzName = parts.find((p) => p.type === 'timeZoneName')?.value;
  if (tzName) return tzName;

  const fromDb = allTimeZones.find((z) => z.name === tz);
  if (fromDb?.alternativeName) return fromDb.alternativeName;
  return tz.split('/').slice(-1)[0]?.replace(/_/g, ' ') || tz;
};

// 生成时区数据（无硬编码城市/翻译；label 在 getTimezones 里根据 locale 生成）
export const timezoneData = allTimeZones.map((z) => ({
  value: z.name,
  offsetMinutes: z.currentTimeOffsetInMinutes,
  offset: formatOffsetFromMinutes(z.currentTimeOffsetInMinutes),
  continent: z.continentName,
  continentCode: z.continentCode,
  countryName: z.countryName,
  countryCode: z.countryCode,
  alternativeName: z.alternativeName,
  mainCities: z.mainCities,
}));

const getStoredTimezone = (): Timezone | null => {
  if (typeof window === 'undefined') {
    return null;
  }

  return localStorage.getItem('timezone') as Timezone | null;
};

const getBrowserTimezone = (): Timezone => {
  if (typeof window === 'undefined') {
    return FALLBACK_TIMEZONE;
  }

  const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
  const canonical = resolveCanonicalTimezone(browserTz);
  if (canonical) {
    return canonical;
  }

  // 兜底：未知/无法识别的浏览器时区不应让整个应用崩溃，退回 UTC 并告警。
  console.warn(`无法识别的浏览器时区「${browserTz}」，已回退到 UTC`);
  return FALLBACK_TIMEZONE;
};

const timezone = ref<Timezone>(getBrowserTimezone());

export function initializeTimezone(userTimezone?: string | null) {
  if (typeof window === 'undefined') {
    return;
  }

  if (userTimezone) {
    const canonical = resolveCanonicalTimezone(userTimezone);
    if (canonical) {
      timezone.value = canonical;
      localStorage.setItem('timezone', canonical);
      return;
    }

    // 用户保存的时区无法识别时不抛错（避免整页崩溃），回退到本地/浏览器时区。
    console.warn(`无法识别的用户时区「${userTimezone}」，改用本地/浏览器时区`);
  }

  const savedTimezone = resolveCanonicalTimezone(getStoredTimezone());
  if (savedTimezone) {
    timezone.value = savedTimezone;
  }
}

export function useTimezone() {
  onMounted(() => {
    const savedTimezone = resolveCanonicalTimezone(getStoredTimezone());

    if (savedTimezone) {
      timezone.value = savedTimezone;
    }
  });

  function updateTimezone(newTimezone: Timezone) {
    const canonical = resolveCanonicalTimezone(newTimezone);
    if (!canonical) {
      throw new Error(`Unsupported timezone: ${newTimezone}`);
    }
    timezone.value = canonical;
    localStorage.setItem('timezone', canonical);
  }

  const getOffsetMinutes = (tz: string) => {
    const data = timezoneData.find((x) => x.value === tz);
    if (!data) {
      throw new Error(`Unsupported timezone: ${tz}`);
    }

    return data.offsetMinutes;
  };

  // “常用优先”：当前/浏览器/同大洲/offset 接近
  function getTimezones(lang: TimezoneLocale): TimezoneOption[] {
    const browser = getBrowserTimezone();
    const current = timezone.value;
    const currentContinent = getContinentKey(current);
    const browserContinent = getContinentKey(browser);
    const currentOffset = getOffsetMinutes(current);

    const scored = timezoneData.map((z, idx) => {
      const continent = getContinentKey(z.value);
      const offsetDiff = Math.abs(z.offsetMinutes - currentOffset);
      let score = 100;

      if (z.value === current) score = 0;
      else if (z.value === browser) score = 1;
      else if (continent && continent === currentContinent) score = 2;
      else if (continent && continent === browserContinent) score = 3;
      else if (offsetDiff <= 60) score = 4;
      else if (offsetDiff <= 120) score = 5;
      else score = 10;

      return {
        idx,
        score,
        offsetDiff,
        value: z.value,
        offset: z.offset,
      };
    });

    scored.sort((a, b) => {
      if (a.score !== b.score) return a.score - b.score;
      if (a.offsetDiff !== b.offsetDiff) return a.offsetDiff - b.offsetDiff;
      return a.idx - b.idx; // 保持 tzdb 原本排序的稳定性
    });

    return scored.map((x) => ({
      value: x.value,
      offset: x.offset,
      label: `${getLocalizedTimeZoneName(x.value, lang)} ${x.offset}`,
    }));
  }

  function getCurrentTimezoneInfo(lang: TimezoneLocale) {
    const data = timezoneData.find((tz) => tz.value === timezone.value);
    if (!data) {
      throw new Error(`Unsupported timezone: ${timezone.value}`);
    }

    return {
      value: data.value,
      label: `${getLocalizedTimeZoneName(timezone.value, lang)} ${data.offset}`,
      offset: data.offset,
    };
  }

  return {
    timezone,
    timezoneData,
    updateTimezone,
    getTimezones,
    getCurrentTimezoneInfo,
  };
}
