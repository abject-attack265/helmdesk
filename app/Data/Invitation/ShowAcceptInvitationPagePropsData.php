<?php

namespace App\Data\Invitation;

use Spatie\LaravelData\Data;

class ShowAcceptInvitationPagePropsData extends Data
{
    public function __construct(
        public string $token,
        public string $app_name,
        public string $inviter_name,
        public string $email,
        public string $expires_at,
    ) {}
}
