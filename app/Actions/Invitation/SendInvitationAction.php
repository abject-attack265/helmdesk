<?php

namespace App\Actions\Invitation;

use App\Models\Invitation;
use App\Notifications\InvitationNotification;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Notification;
use Lorisleiva\Actions\Concerns\AsAction;

class SendInvitationAction
{
    use AsAction;

    public function handle(Invitation $invitation, #[\SensitiveParameter] string $plainToken): void
    {
        $invitation->loadMissing('inviter');

        Notification::route('mail', $invitation->email)->notify(
            new InvitationNotification(
                acceptUrl: route('invitations.accept.show', ['token' => $plainToken]),
                appName: app(GeneralSettings::class)->name,
                inviterName: $invitation->inviter?->name ?? __('teammate.invitation.unknown_inviter'),
            )
        );
    }
}
