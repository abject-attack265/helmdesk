<?php

namespace App\Data\Teammate;

use App\Enums\UserPermission;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

class FormCreateTeammateData extends Data
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $nickname = null,
        public array $permissions = [],
        public ?string $avatar_id = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'nickname' => ['nullable', 'string', 'max:50'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(UserPermission::values())],
            'avatar_id' => ['nullable', 'string', 'max:26'],
        ];
    }
}
