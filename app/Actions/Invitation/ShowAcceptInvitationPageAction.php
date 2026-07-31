<?php

namespace App\Actions\Invitation;

use App\Data\Invitation\ShowAcceptInvitationPagePropsData;
use App\Models\Invitation;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowAcceptInvitationPageAction
{
    use AsAction;

    public function asController(Request $request, string $token)
    {
        $invitation = Invitation::findByPlainToken($token);

        if ($invitation === null || ! $invitation->isAcceptable()) {
            return Inertia::render('auth/AcceptInvitation', ['invalid' => true]);
        }

        $invitation->loadMissing('inviter');

        $props = new ShowAcceptInvitationPagePropsData(
            token: $token,
            app_name: app(GeneralSettings::class)->name,
            inviter_name: $invitation->inviter?->name ?? __('teammate.invitation.unknown_inviter'),
            email: $invitation->email,
            expires_at: $invitation->expires_at->toIso8601String(),
        );

        return Inertia::render('auth/AcceptInvitation', ['invitation' => $props->toArray()]);
    }
}
