<?php

namespace App\Data\User;

use App\Services\LocalePreference;
use DateTimeZone;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 用户语言和时区偏好表单。
 */
class FormUpdateLanguageSettingsData extends Data
{
    public function __construct(
        public string $locale,
        public string $timezone,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(LocalePreference::frontendLocales())],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers())],
        ];
    }
}
