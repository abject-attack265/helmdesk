<?php

namespace App\Data\Teammate;

use App\Enums\UserOnlineStatus;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 客服列表更新接待状态时提交的表单数据。
 */
class FormUpdateTeammateOnlineStatusData extends Data
{
    /**
     * 创建接待状态表单数据。
     */
    public function __construct(
        public UserOnlineStatus $online_status,
    ) {}

    /**
     * 定义接待状态的可选值。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'online_status' => ['required', 'integer', Rule::enum(UserOnlineStatus::class)],
        ];
    }
}
