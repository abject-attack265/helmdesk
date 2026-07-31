<?php

namespace App\Http\Middleware;

use App\Actions\User\TouchSystemUserLastActiveAtAction;
use App\Data\CurrentUserContextData;
use App\Data\System\SystemData;
use App\Enums\UserPermission;
use App\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * 共享系统配置和当前用户信息。
 */
class ShareSystemContext
{
    /**
     * 注入成员活跃时间刷新动作。
     */
    public function __construct(
        private TouchSystemUserLastActiveAtAction $touchSystemUserLastActiveAtAction,
    ) {}

    /**
     * 共享系统配置和当前用户信息给当前请求。
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->membership()->exists()) {
            if ($user !== null) {
                Log::warning('app.entry_without_membership', [
                    'user_id' => (string) $user->id,
                    'entry' => $request->route()?->getName(),
                ]);
            }

            abort(404);
        }

        Inertia::share('app', SystemData::fromSettings(app(GeneralSettings::class))->toArray());

        $this->touchSystemUserLastActiveAtAction->handle((string) $request->user()->id);

        $currentUserContext = CurrentUserContextData::fromUser($request->user());
        $request->attributes->set(CurrentUserContextData::class, $currentUserContext);
        Inertia::share('currentUserContext', $currentUserContext->toArray());
        Inertia::share('canAccessUsers', Gate::allows('user.permission', UserPermission::UsersView));
        Inertia::share('canAccessContacts', Gate::allows('user.permission', UserPermission::ContactsView));
        Inertia::share('canAccessConversations', Gate::allows('user.permission', UserPermission::ConversationsView));
        Inertia::share('canAccessTags', Gate::allows('user.permission', UserPermission::TagsView));
        Inertia::share('canAccessAttributes', Gate::allows('user.permission', UserPermission::AttributesView));
        Inertia::share('canAccessCannedReplies', Gate::allows('user.permission', UserPermission::CannedRepliesView));
        Inertia::share('canAccessKnowledgeBases', Gate::allows('user.permission', UserPermission::KnowledgeBasesView));
        Inertia::share('canAccessReceptionPlans', Gate::allows('user.permission', UserPermission::ReceptionPlansView));
        Inertia::share('canAccessChannels', Gate::allows('user.permission', UserPermission::ChannelsView));
        Inertia::share('canManageSystemSettings', Gate::allows('user.permission', UserPermission::SystemSettingsView));

        return $next($request);
    }
}
