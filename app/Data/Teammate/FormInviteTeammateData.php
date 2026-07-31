<?php

namespace App\Data\Teammate;

use App\Enums\UserPermission;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class FormInviteTeammateData extends Data
{
    /** @param list<string> $permissions */
    public function __construct(
        public string $email,
        public ?string $nickname = null,
        public array $permissions = [],
    ) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(UserPermission::values())],
        ];
    }
}
