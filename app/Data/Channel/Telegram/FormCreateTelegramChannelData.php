<?php

namespace App\Data\Channel\Telegram;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/** Telegram 渠道创建页提交的基本信息。 */
class FormCreateTelegramChannelData extends Data
{
    /** 创建 Telegram 渠道基本信息。 */
    public function __construct(
        public string $name,
        public string $reception_plan_id,
        public ReceptionLanguage $default_visitor_locale = ReceptionLanguage::ChineseSimplified,
        public ?string $description = null,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reception_plan_id' => ['required', 'string', 'ulid'],
            'default_visitor_locale' => ['required', 'string', Rule::in(array_column(ReceptionLanguage::cases(), 'value'))],
        ];
    }
}
