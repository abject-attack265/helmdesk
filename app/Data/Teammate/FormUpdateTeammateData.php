<?php

namespace App\Data\Teammate;

use App\Enums\UserPermission;
use App\Services\LocalePreference;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 成员编辑页面提交的公开资料、登录凭据和资源权限。
 */
class FormUpdateTeammateData extends Data
{
    /**
     * 创建成员编辑表单数据。
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $locale,
        public ?string $nickname = null,
        public ?string $password = null,
        public ?string $avatar_id = null,
        /** @var list<string> */
        public array $permissions = [],
    ) {}

    /**
     * 定义成员编辑表单的字段规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $userId = request()->route('id');

        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'locale' => ['required', 'string', Rule::in(LocalePreference::frontendLocales())],
            'nickname' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar_id' => ['nullable', 'string', 'max:26'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(UserPermission::values())],
        ];
    }
}
