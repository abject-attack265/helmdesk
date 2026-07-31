<?php

namespace App\Data\Invitation;

use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Data;

class FormAcceptInvitationData extends Data
{
    public function __construct(
        public string $name,
        public string $password,
    ) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];
    }
}
