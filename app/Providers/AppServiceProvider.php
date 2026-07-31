<?php

namespace App\Providers;

use App\Contracts\ContactTagFilterStrategy;
use App\Enums\IntegrationProvider;
use App\Enums\UserPermission;
use App\Models\CannedReply;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\Invitation;
use App\Models\User;
use App\Observers\CannedReplyObserver;
use App\Observers\ContactObserver;
use App\Observers\ConversationEventObserver;
use App\Observers\ConversationMessageObserver;
use App\Observers\ConversationObserver;
use App\Observers\UserObserver;
use App\Policies\CannedReplyPolicy;
use App\Services\Database\SqliteVecExtensionLoader;
use App\Services\Integration\IntegrationProviderRegistry;
use App\Services\Integration\Providers\BusinessSystemToolProvider;
use App\Services\Integration\Providers\McpToolProvider;
use App\Services\Integration\Providers\MockBusinessSystemProvider;
use App\Services\KnowledgeBase\Parsing\DocumentParserManager;
use App\Services\KnowledgeBase\Parsing\DocxDocumentParser;
use App\Services\KnowledgeBase\Parsing\HtmlDocumentParser;
use App\Services\KnowledgeBase\Parsing\PdfDocumentParser;
use App\Services\KnowledgeBase\Parsing\TextDocumentParser;
use App\Services\KnowledgeBase\Parsing\XlsxDocumentParser;
use App\Services\Realtime\MercureBroadcaster;
use App\Services\Realtime\MercurePublisher;
use App\Services\Search\TntSearchEngine;
use App\Services\Tag\PivotContactTagFilterStrategy;
use App\Settings\GeneralSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use TeamTNT\Scout\TNTSearchScoutServiceProvider;
use TeamTNT\TNTSearch\TNTSearch;

/**
 * 注册应用基础设施、认证通知和后台授权规则。
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * 注册应用服务。
     */
    public function register(): void
    {
        $this->app->bind(ContactTagFilterStrategy::class, PivotContactTagFilterStrategy::class);

        // 专用格式优先匹配，文本解析器处理其明确支持的文本类型。
        $this->app->singleton(DocumentParserManager::class, function ($app): DocumentParserManager {
            return new DocumentParserManager([
                $app->make(PdfDocumentParser::class),
                $app->make(DocxDocumentParser::class),
                $app->make(XlsxDocumentParser::class),
                $app->make(HtmlDocumentParser::class),
                $app->make(TextDocumentParser::class),
            ]);
        });

        // 集成工具 provider 注册表：按 provider 分发到具体平台实现。
        // 以解析闭包登记，每次按需从容器现解析（McpToolProvider 依赖的 McpRuntimeClient 测试中可被替换为 mock）。
        $this->app->singleton(IntegrationProviderRegistry::class, function ($app): IntegrationProviderRegistry {
            return new IntegrationProviderRegistry([
                IntegrationProvider::Mcp->value => fn () => $app->make(McpToolProvider::class),
                IntegrationProvider::BusinessSystem->value => fn () => $app->make(BusinessSystemToolProvider::class),
                IntegrationProvider::MockBusinessSystem->value => fn () => $app->make(MockBusinessSystemProvider::class),
            ]);
        });
    }

    /**
     * 启动应用服务。
     */
    public function boot(): void
    {
        CannedReply::observe(CannedReplyObserver::class);
        Contact::observe(ContactObserver::class);
        Conversation::observe(ConversationObserver::class);
        ConversationEvent::observe(ConversationEventObserver::class);
        ConversationMessage::observe(ConversationMessageObserver::class);
        User::observe(UserObserver::class);

        Broadcast::extend('mercure', fn ($app): MercureBroadcaster => new MercureBroadcaster(
            $app->make(MercurePublisher::class),
        ));

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            app(SqliteVecExtensionLoader::class)->ensureLoadedFor($event->connection);
        });

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command !== 'migrate:fresh') {
                return;
            }

            foreach (['sqlite_rag', 'sqlite_cache', 'sqlite_session', 'sqlite_jobs'] as $connectionName) {
                $database = config("database.connections.{$connectionName}.database");
                DB::purge($connectionName);

                if ($database !== ':memory:' && is_file($database)) {
                    unlink($database);
                }

                if ($database !== ':memory:') {
                    touch($database);
                }
            }
        });

        app(EngineManager::class)->extend('tntsearch', function ($app): TntSearchEngine {
            $tnt = new TNTSearch;
            $driver = config('database.default');
            $config = config('scout.tntsearch') + config("database.connections.{$driver}");

            $tnt->loadConfig($config);
            $tnt->setDatabaseHandle(app('db')->connection()->getReadPdo());
            $tnt->engine->maxDocs = config('scout.tntsearch.maxDocs', 500);
            TNTSearchScoutServiceProvider::setFuzziness($tnt);
            TNTSearchScoutServiceProvider::setAsYouType($tnt);

            return new TntSearchEngine($tnt);
        });

        // 经 HTTPS 反代 / 内网穿透时，到达后端的请求是明文 HTTP，URL 生成默认跟随请求 scheme 会产出 http://，
        // 使 HTTPS 页面加载 http 资源被浏览器按混合内容拦截。APP_URL 配置为 https 时统一强制按 https 生成。
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureAuthMailMessages();
        $this->configureAttachmentRateLimiting();

        // 快捷回复按归属和范围控制。
        Gate::policy(CannedReply::class, CannedReplyPolicy::class);

        Gate::define('app.owner', function (User $actor): bool {
            return (string) app(GeneralSettings::class)->owner_id === (string) $actor->id;
        });

        Gate::define('user.permission', function (User $actor, UserPermission|string $permission): bool {
            return $actor->hasPermission($permission);
        });

        foreach (UserPermission::cases() as $permission) {
            Gate::define($permission->value, function (User $actor) use ($permission): bool {
                return $actor->hasPermission($permission);
            });
        }

        Gate::define('users.removeMember', function (User $actor, User $target): bool {
            if ((string) $actor->id === (string) $target->id) {
                return false;
            }

            if ((string) app(GeneralSettings::class)->owner_id === (string) $target->id) {
                return false;
            }

            return $actor->hasPermission(UserPermission::UsersDelete);
        });

        Gate::define('users.updateMember', function (User $actor, User $target): bool {
            if ((string) app(GeneralSettings::class)->owner_id === (string) $target->id
                && (string) $actor->id !== (string) $target->id) {
                return false;
            }

            return $actor->hasPermission(UserPermission::UsersEdit);
        });

        Gate::define('users.manageInvitation', function (User $actor, Invitation $invitation): bool {
            if ((string) app(GeneralSettings::class)->owner_id === (string) $actor->id) {
                return true;
            }

            return $actor->hasPermission(UserPermission::UsersCreate)
                && (string) $invitation->invited_by === (string) $actor->id;
        });
    }

    /**
     * 配置访客附件申请和完成接口的独立 IP 限流。
     */
    private function configureAttachmentRateLimiting(): void
    {
        RateLimiter::for('visitor-attachment-presign', function (Request $request) {
            return Limit::perMinute(20)->by((string) $request->ip());
        });

        RateLimiter::for('visitor-attachment-finalize', function (Request $request) {
            return Limit::perMinute(60)->by((string) $request->ip());
        });
    }

    /**
     * 注册认证相关邮件的自定义内容。
     */
    private function configureAuthMailMessages(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject(__('mail.password_reset.subject'))
                ->line(__('mail.password_reset.line'))
                ->action(__('mail.password_reset.action'), $url);
        });

        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            return URL::temporarySignedRoute(
                'verification.verify',
                Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
            );
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject(__('mail.email_verification.subject'))
                ->line(__('mail.email_verification.line'))
                ->action(__('mail.email_verification.action'), $url);
        });
    }
}
