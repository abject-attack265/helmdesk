/**
 * 文件说明：国际区号选项、按 locale 推断默认区号及手机号拼接与校验。
 */
export type PhoneDialCodeOption = {
  value: string;
  label: string;
  description: string;
  countryCode: string;
  flag: string;
  keywords: string[];
};

const preferredCountryOrder = [
  'United States',
  'Canada',
  'United Kingdom',
  'China',
  'Hong Kong',
  'Macau',
  'Taiwan',
  'Japan',
  'South Korea',
  'Singapore',
  'Australia',
] as const;

const phoneDialCodeRows = [
  ['AF', 'Afghanistan', '+93'],
  ['AX', 'Aland Islands', '+358'],
  ['AL', 'Albania', '+355'],
  ['DZ', 'Algeria', '+213'],
  ['AS', 'American Samoa', '+1'],
  ['AD', 'Andorra', '+376'],
  ['AO', 'Angola', '+244'],
  ['AI', 'Anguilla', '+1'],
  ['AG', 'Antigua and Barbuda', '+1'],
  ['AR', 'Argentina', '+54'],
  ['AM', 'Armenia', '+374'],
  ['AW', 'Aruba', '+297'],
  ['AU', 'Australia', '+61'],
  ['AT', 'Austria', '+43'],
  ['AZ', 'Azerbaijan', '+994'],
  ['BS', 'Bahamas', '+1'],
  ['BH', 'Bahrain', '+973'],
  ['BD', 'Bangladesh', '+880'],
  ['BB', 'Barbados', '+1'],
  ['BY', 'Belarus', '+375'],
  ['BE', 'Belgium', '+32'],
  ['BZ', 'Belize', '+501'],
  ['BJ', 'Benin', '+229'],
  ['BM', 'Bermuda', '+1'],
  ['BT', 'Bhutan', '+975'],
  ['BO', 'Bolivia', '+591'],
  ['BA', 'Bosnia and Herzegovina', '+387'],
  ['BW', 'Botswana', '+267'],
  ['BR', 'Brazil', '+55'],
  ['IO', 'British Indian Ocean Territory', '+246'],
  ['VG', 'British Virgin Islands', '+1'],
  ['BN', 'Brunei', '+673'],
  ['BG', 'Bulgaria', '+359'],
  ['BF', 'Burkina Faso', '+226'],
  ['BI', 'Burundi', '+257'],
  ['KH', 'Cambodia', '+855'],
  ['CM', 'Cameroon', '+237'],
  ['CA', 'Canada', '+1'],
  ['CV', 'Cape Verde', '+238'],
  ['BQ', 'Caribbean Netherlands', '+599'],
  ['KY', 'Cayman Islands', '+1'],
  ['CF', 'Central African Republic', '+236'],
  ['TD', 'Chad', '+235'],
  ['CL', 'Chile', '+56'],
  ['CN', 'China', '+86'],
  ['CX', 'Christmas Island', '+61'],
  ['CC', 'Cocos (Keeling) Islands', '+61'],
  ['CO', 'Colombia', '+57'],
  ['KM', 'Comoros', '+269'],
  ['CG', 'Congo', '+242'],
  ['CK', 'Cook Islands', '+682'],
  ['CR', 'Costa Rica', '+506'],
  ['HR', 'Croatia', '+385'],
  ['CU', 'Cuba', '+53'],
  ['CW', 'Curacao', '+599'],
  ['CY', 'Cyprus', '+357'],
  ['CZ', 'Czechia', '+420'],
  ['DK', 'Denmark', '+45'],
  ['DJ', 'Djibouti', '+253'],
  ['DM', 'Dominica', '+1'],
  ['DO', 'Dominican Republic', '+1'],
  ['CD', 'DR Congo', '+243'],
  ['EC', 'Ecuador', '+593'],
  ['EG', 'Egypt', '+20'],
  ['SV', 'El Salvador', '+503'],
  ['GQ', 'Equatorial Guinea', '+240'],
  ['ER', 'Eritrea', '+291'],
  ['EE', 'Estonia', '+372'],
  ['SZ', 'Eswatini', '+268'],
  ['ET', 'Ethiopia', '+251'],
  ['FK', 'Falkland Islands', '+500'],
  ['FO', 'Faroe Islands', '+298'],
  ['FJ', 'Fiji', '+679'],
  ['FI', 'Finland', '+358'],
  ['FR', 'France', '+33'],
  ['GF', 'French Guiana', '+594'],
  ['PF', 'French Polynesia', '+689'],
  ['GA', 'Gabon', '+241'],
  ['GM', 'Gambia', '+220'],
  ['GE', 'Georgia', '+995'],
  ['DE', 'Germany', '+49'],
  ['GH', 'Ghana', '+233'],
  ['GI', 'Gibraltar', '+350'],
  ['GR', 'Greece', '+30'],
  ['GL', 'Greenland', '+299'],
  ['GD', 'Grenada', '+1'],
  ['GP', 'Guadeloupe', '+590'],
  ['GU', 'Guam', '+1'],
  ['GT', 'Guatemala', '+502'],
  ['GG', 'Guernsey', '+44'],
  ['GN', 'Guinea', '+224'],
  ['GW', 'Guinea-Bissau', '+245'],
  ['GY', 'Guyana', '+592'],
  ['HT', 'Haiti', '+509'],
  ['HN', 'Honduras', '+504'],
  ['HK', 'Hong Kong', '+852'],
  ['HU', 'Hungary', '+36'],
  ['IS', 'Iceland', '+354'],
  ['IN', 'India', '+91'],
  ['ID', 'Indonesia', '+62'],
  ['IR', 'Iran', '+98'],
  ['IQ', 'Iraq', '+964'],
  ['IE', 'Ireland', '+353'],
  ['IM', 'Isle of Man', '+44'],
  ['IL', 'Israel', '+972'],
  ['IT', 'Italy', '+39'],
  ['CI', 'Ivory Coast', '+225'],
  ['JM', 'Jamaica', '+1'],
  ['JP', 'Japan', '+81'],
  ['JE', 'Jersey', '+44'],
  ['JO', 'Jordan', '+962'],
  ['KZ', 'Kazakhstan', '+7'],
  ['KE', 'Kenya', '+254'],
  ['KI', 'Kiribati', '+686'],
  ['XK', 'Kosovo', '+383'],
  ['KW', 'Kuwait', '+965'],
  ['KG', 'Kyrgyzstan', '+996'],
  ['LA', 'Laos', '+856'],
  ['LV', 'Latvia', '+371'],
  ['LB', 'Lebanon', '+961'],
  ['LS', 'Lesotho', '+266'],
  ['LR', 'Liberia', '+231'],
  ['LY', 'Libya', '+218'],
  ['LI', 'Liechtenstein', '+423'],
  ['LT', 'Lithuania', '+370'],
  ['LU', 'Luxembourg', '+352'],
  ['MO', 'Macau', '+853'],
  ['MG', 'Madagascar', '+261'],
  ['MW', 'Malawi', '+265'],
  ['MY', 'Malaysia', '+60'],
  ['MV', 'Maldives', '+960'],
  ['ML', 'Mali', '+223'],
  ['MT', 'Malta', '+356'],
  ['MH', 'Marshall Islands', '+692'],
  ['MQ', 'Martinique', '+596'],
  ['MR', 'Mauritania', '+222'],
  ['MU', 'Mauritius', '+230'],
  ['YT', 'Mayotte', '+262'],
  ['MX', 'Mexico', '+52'],
  ['FM', 'Micronesia', '+691'],
  ['MD', 'Moldova', '+373'],
  ['MC', 'Monaco', '+377'],
  ['MN', 'Mongolia', '+976'],
  ['ME', 'Montenegro', '+382'],
  ['MS', 'Montserrat', '+1'],
  ['MA', 'Morocco', '+212'],
  ['MZ', 'Mozambique', '+258'],
  ['MM', 'Myanmar', '+95'],
  ['NA', 'Namibia', '+264'],
  ['NR', 'Nauru', '+674'],
  ['NP', 'Nepal', '+977'],
  ['NL', 'Netherlands', '+31'],
  ['NC', 'New Caledonia', '+687'],
  ['NZ', 'New Zealand', '+64'],
  ['NI', 'Nicaragua', '+505'],
  ['NE', 'Niger', '+227'],
  ['NG', 'Nigeria', '+234'],
  ['NU', 'Niue', '+683'],
  ['NF', 'Norfolk Island', '+672'],
  ['KP', 'North Korea', '+850'],
  ['MK', 'North Macedonia', '+389'],
  ['MP', 'Northern Mariana Islands', '+1'],
  ['NO', 'Norway', '+47'],
  ['OM', 'Oman', '+968'],
  ['PK', 'Pakistan', '+92'],
  ['PW', 'Palau', '+680'],
  ['PS', 'Palestine', '+970'],
  ['PA', 'Panama', '+507'],
  ['PG', 'Papua New Guinea', '+675'],
  ['PY', 'Paraguay', '+595'],
  ['PE', 'Peru', '+51'],
  ['PH', 'Philippines', '+63'],
  ['PL', 'Poland', '+48'],
  ['PT', 'Portugal', '+351'],
  ['PR', 'Puerto Rico', '+1'],
  ['QA', 'Qatar', '+974'],
  ['RE', 'Reunion', '+262'],
  ['RO', 'Romania', '+40'],
  ['RU', 'Russia', '+7'],
  ['RW', 'Rwanda', '+250'],
  ['BL', 'Saint Barthelemy', '+590'],
  ['SH', 'Saint Helena, Ascension and Tristan da Cunha', '+290'],
  ['KN', 'Saint Kitts and Nevis', '+1'],
  ['LC', 'Saint Lucia', '+1'],
  ['MF', 'Saint Martin', '+590'],
  ['PM', 'Saint Pierre and Miquelon', '+508'],
  ['VC', 'Saint Vincent and the Grenadines', '+1'],
  ['WS', 'Samoa', '+685'],
  ['SM', 'San Marino', '+378'],
  ['ST', 'Sao Tome and Principe', '+239'],
  ['SA', 'Saudi Arabia', '+966'],
  ['SN', 'Senegal', '+221'],
  ['RS', 'Serbia', '+381'],
  ['SC', 'Seychelles', '+248'],
  ['SL', 'Sierra Leone', '+232'],
  ['SG', 'Singapore', '+65'],
  ['SX', 'Sint Maarten', '+1'],
  ['SK', 'Slovakia', '+421'],
  ['SI', 'Slovenia', '+386'],
  ['SB', 'Solomon Islands', '+677'],
  ['SO', 'Somalia', '+252'],
  ['ZA', 'South Africa', '+27'],
  ['KR', 'South Korea', '+82'],
  ['SS', 'South Sudan', '+211'],
  ['ES', 'Spain', '+34'],
  ['LK', 'Sri Lanka', '+94'],
  ['SD', 'Sudan', '+249'],
  ['SR', 'Suriname', '+597'],
  ['SJ', 'Svalbard and Jan Mayen', '+47'],
  ['SE', 'Sweden', '+46'],
  ['CH', 'Switzerland', '+41'],
  ['SY', 'Syria', '+963'],
  ['TW', 'Taiwan', '+886'],
  ['TJ', 'Tajikistan', '+992'],
  ['TZ', 'Tanzania', '+255'],
  ['TH', 'Thailand', '+66'],
  ['TL', 'Timor-Leste', '+670'],
  ['TG', 'Togo', '+228'],
  ['TK', 'Tokelau', '+690'],
  ['TO', 'Tonga', '+676'],
  ['TT', 'Trinidad and Tobago', '+1'],
  ['TN', 'Tunisia', '+216'],
  ['TR', 'Turkiye', '+90'],
  ['TM', 'Turkmenistan', '+993'],
  ['TC', 'Turks and Caicos Islands', '+1'],
  ['TV', 'Tuvalu', '+688'],
  ['UG', 'Uganda', '+256'],
  ['UA', 'Ukraine', '+380'],
  ['AE', 'United Arab Emirates', '+971'],
  ['GB', 'United Kingdom', '+44'],
  ['US', 'United States', '+1'],
  ['VI', 'United States Virgin Islands', '+1'],
  ['UY', 'Uruguay', '+598'],
  ['UZ', 'Uzbekistan', '+998'],
  ['VU', 'Vanuatu', '+678'],
  ['VA', 'Vatican City', '+39'],
  ['VE', 'Venezuela', '+58'],
  ['VN', 'Vietnam', '+84'],
  ['WF', 'Wallis and Futuna', '+681'],
  ['EH', 'Western Sahara', '+212'],
  ['YE', 'Yemen', '+967'],
  ['ZM', 'Zambia', '+260'],
  ['ZW', 'Zimbabwe', '+263'],
] as const;

const sanitizePhoneDigits = (value: string): string => {
  return value.replace(/\D+/g, '');
};

const extractRegion = (locale: string): string | null => {
  const match = locale.match(/-([A-Z]{2})$/i);

  if (!match) {
    return null;
  }

  return match[1].toUpperCase();
};

const sortCountryNames = (countryNames: string[]): string[] => {
  return [...countryNames].sort((left, right) => {
    const leftPriority = preferredCountryOrder.indexOf(
      left as (typeof preferredCountryOrder)[number],
    );
    const rightPriority = preferredCountryOrder.indexOf(
      right as (typeof preferredCountryOrder)[number],
    );

    if (leftPriority !== -1 || rightPriority !== -1) {
      if (leftPriority === -1) {
        return 1;
      }

      if (rightPriority === -1) {
        return -1;
      }

      return leftPriority - rightPriority;
    }

    return left.localeCompare(right);
  });
};

const summarizeCountries = (countryNames: string[]): string => {
  const sortedCountryNames = sortCountryNames(countryNames);

  if (sortedCountryNames.length <= 3) {
    return sortedCountryNames.join(', ');
  }

  return `${sortedCountryNames.slice(0, 3).join(', ')} +${sortedCountryNames.length - 3}`;
};

const countryFlagEmoji = (countryCode: string): string => {
  if (!/^[A-Z]{2}$/.test(countryCode)) {
    return '';
  }

  return String.fromCodePoint(
    ...countryCode.split('').map((char) => 0x1f1e6 + char.charCodeAt(0) - 65),
  );
};

export const localeDialCodeMap: Record<string, string> = Object.fromEntries(
  phoneDialCodeRows.map(([countryCode, , dialCode]) => [countryCode, dialCode]),
);

export const phoneDialCodeOptions: PhoneDialCodeOption[] = Array.from(
  phoneDialCodeRows.reduce(
    (map, [countryCode, countryName, dialCode]) => {
      const existingOption = map.get(dialCode) ?? {
        value: dialCode,
        label: dialCode,
        countries: [] as Array<{ code: string; name: string }>,
        keywords: new Set<string>(),
      };

      existingOption.countries.push({ code: countryCode, name: countryName });
      existingOption.keywords.add(countryName.toLowerCase());
      existingOption.keywords.add(countryCode.toLowerCase());
      existingOption.keywords.add(dialCode.slice(1));
      map.set(dialCode, existingOption);

      return map;
    },
    new Map<
      string,
      {
        value: string;
        label: string;
        countries: Array<{ code: string; name: string }>;
        keywords: Set<string>;
      }
    >(),
  ),
)
  .map(([, option]) => {
    const sortedCountries = sortCountryNames(
      option.countries.map((country) => country.name),
    );
    const primaryCountry = option.countries.find(
      (country) => country.name === sortedCountries[0],
    );
    const countryCode = primaryCountry?.code ?? option.countries[0]?.code ?? '';

    return {
      value: option.value,
      label: option.label,
      description: summarizeCountries(
        option.countries.map((country) => country.name),
      ),
      countryCode,
      flag: countryFlagEmoji(countryCode),
      keywords: Array.from(option.keywords),
    };
  })
  .sort((left, right) =>
    left.value.localeCompare(right.value, undefined, { numeric: true }),
  );

export const getDefaultPhonePrefix = (fallbackLocale?: string): string => {
  if (typeof navigator !== 'undefined') {
    const browserLocales = [
      ...(navigator.languages ?? []),
      navigator.language,
    ].filter((locale): locale is string => Boolean(locale));

    for (const locale of browserLocales) {
      const region = extractRegion(locale);

      if (region && localeDialCodeMap[region]) {
        return localeDialCodeMap[region];
      }
    }
  }

  const fallbackRegion = fallbackLocale ? extractRegion(fallbackLocale) : null;

  if (fallbackRegion && localeDialCodeMap[fallbackRegion]) {
    return localeDialCodeMap[fallbackRegion];
  }

  return '';
};

export const normalizePhoneDialCode = (value: string): string => {
  const trimmed = value.trim();

  if (trimmed === '') {
    return '';
  }

  const digits = sanitizePhoneDigits(
    trimmed.startsWith('+') ? trimmed.slice(1) : trimmed,
  );

  if (digits === '') {
    return '';
  }

  return `+${digits}`;
};

export const isLikelyValidDialCode = (value: string): boolean => {
  const normalized = normalizePhoneDialCode(value);

  return /^\+[1-9]\d{0,3}$/.test(normalized);
};

export const buildPhoneNumber = (
  dialCode: string,
  localNumber: string,
): string => {
  const normalizedDialCode = normalizePhoneDialCode(dialCode);
  const localNumberDigits = sanitizePhoneDigits(localNumber);

  if (!isLikelyValidDialCode(normalizedDialCode) || localNumberDigits === '') {
    return '';
  }

  return `${normalizedDialCode}${localNumberDigits}`;
};

export const isLikelyValidLocalPhone = (value: string): boolean => {
  const trimmed = value.trim();

  if (trimmed === '') {
    return false;
  }

  if (!/^[0-9\s().-]+$/.test(trimmed)) {
    return false;
  }

  const digits = sanitizePhoneDigits(trimmed);

  return digits.length >= 6 && digits.length <= 14;
};

export const isLikelyValidPhone = (value: string): boolean => {
  const trimmed = value.trim();

  if (trimmed === '') {
    return false;
  }

  if (!/^\+[0-9\s().-]+$/.test(trimmed)) {
    return false;
  }

  const digits = sanitizePhoneDigits(trimmed.slice(1));

  return digits.length >= 6 && digits.length <= 15;
};

const uniqueDialCodesByLength = Array.from(
  new Set(phoneDialCodeOptions.map((option) => option.value)),
).sort((left, right) => right.length - left.length);

export const splitPhoneNumber = (
  value: string,
): { dialCode: string; localNumber: string } => {
  const normalized = value.trim();

  if (!isLikelyValidPhone(normalized)) {
    return {
      dialCode: '',
      localNumber: sanitizePhoneDigits(normalized),
    };
  }

  const digits = `+${sanitizePhoneDigits(normalized.slice(1))}`;

  for (const dialCode of uniqueDialCodesByLength) {
    if (digits.startsWith(dialCode)) {
      return {
        dialCode,
        localNumber: digits.slice(dialCode.length),
      };
    }
  }

  return {
    dialCode: '',
    localNumber: digits.slice(1),
  };
};
