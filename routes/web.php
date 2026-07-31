<?php

use App\Actions\AiChat\SendAiAssistantMessageAction;
use App\Actions\AiChat\ShowAiAssistantMessagesAction;
use App\Actions\AiChat\StopAiAssistantMessageAction;
use App\Actions\AppSetting\AiCallLog\ShowAiCallLogDetailAction;
use App\Actions\AppSetting\AiCallLog\ShowAiCallLogListAction;
use App\Actions\AppSetting\AiModel\CreateAiModelAction;
use App\Actions\AppSetting\AiModel\DeleteAiModelAction;
use App\Actions\AppSetting\AiModel\ShowAiModelListAction;
use App\Actions\AppSetting\AiModel\ShowCreateAiModelPageAction;
use App\Actions\AppSetting\AiModel\ShowEditAiModelPageAction;
use App\Actions\AppSetting\AiModel\ToggleAiModelAction;
use App\Actions\AppSetting\AiModel\UpdateAiModelAction;
use App\Actions\AppSetting\AiProvider\CheckAiProviderAction;
use App\Actions\AppSetting\AiProvider\CreateAiProviderAction;
use App\Actions\AppSetting\AiProvider\DeleteAiProviderAction;
use App\Actions\AppSetting\AiProvider\ShowAiProviderListAction;
use App\Actions\AppSetting\AiProvider\ShowCreateAiProviderPageAction;
use App\Actions\AppSetting\AiProvider\ShowEditAiProviderPageAction;
use App\Actions\AppSetting\AiProvider\UpdateAiProviderCredentialsAction;
use App\Actions\AppSetting\TranslationProvider\CheckTranslationProviderAction;
use App\Actions\AppSetting\TranslationProvider\CreateTranslationProviderAction;
use App\Actions\AppSetting\TranslationProvider\DeleteTranslationProviderAction;
use App\Actions\AppSetting\TranslationProvider\ShowCreateTranslationProviderPageAction;
use App\Actions\AppSetting\TranslationProvider\ShowEditTranslationProviderPageAction;
use App\Actions\AppSetting\TranslationProvider\ShowTranslationProviderListAction;
use App\Actions\AppSetting\TranslationProvider\ToggleTranslationProviderActiveAction;
use App\Actions\AppSetting\TranslationProvider\UpdateTranslationProviderAction;
use App\Actions\Attachment\ShowAttachmentContentAction;
use App\Actions\CannedReply\CreateCannedReplyAction;
use App\Actions\CannedReply\DeleteCannedReplyAction;
use App\Actions\CannedReply\SearchCannedRepliesForComposerAction;
use App\Actions\CannedReply\ShowCannedReplyListAction;
use App\Actions\CannedReply\UpdateCannedReplyAction;
use App\Actions\CannedReply\UseAndRenderCannedReplyAction;
use App\Actions\Channel\Telegram\CheckTelegramBotTokenAction;
use App\Actions\Channel\Telegram\CreateTelegramChannelAction;
use App\Actions\Channel\Telegram\DeleteTelegramChannelAction;
use App\Actions\Channel\Telegram\ListTelegramChannelsAction;
use App\Actions\Channel\Telegram\ListTelegramChannelTrashAction;
use App\Actions\Channel\Telegram\ReceiveTelegramWebhookAction;
use App\Actions\Channel\Telegram\RegisterTelegramWebhookAction;
use App\Actions\Channel\Telegram\RestoreTelegramChannelAction;
use App\Actions\Channel\Telegram\ShowCreateTelegramChannelPageAction;
use App\Actions\Channel\Telegram\ShowTelegramChannelDetailPageAction;
use App\Actions\Channel\Telegram\UpdateTelegramChannelBasicAction;
use App\Actions\Channel\Web\CreateWebChannelAction;
use App\Actions\Channel\Web\DeleteWebChannelAction;
use App\Actions\Channel\Web\ListWebChannelsAction;
use App\Actions\Channel\Web\ListWebChannelTrashAction;
use App\Actions\Channel\Web\Public\ResolveWidgetBootstrapJsonAction;
use App\Actions\Channel\Web\Public\ShowStandaloneChatPageAction;
use App\Actions\Channel\Web\Public\ShowWidgetFrameAction;
use App\Actions\Channel\Web\Public\ShowWidgetLoaderScriptAction;
use App\Actions\Channel\Web\RegenerateWebChannelUserTokenSecretAction;
use App\Actions\Channel\Web\RestoreWebChannelAction;
use App\Actions\Channel\Web\ShowCreateWebChannelPageAction;
use App\Actions\Channel\Web\ShowWebChannelDetailPageAction;
use App\Actions\Channel\Web\ShowWebChannelPreviewFrameAction;
use App\Actions\Channel\Web\UpdateWebChannelAccessAction;
use App\Actions\Channel\Web\UpdateWebChannelBasicAction;
use App\Actions\Channel\Web\UpdateWebChannelEmbedAction;
use App\Actions\Channel\Web\UpdateWebChannelVisitorInterfaceAction;
use App\Actions\Channel\Web\UpdateWebChannelWidgetAction;
use App\Actions\Channel\WechatOfficialAccount\CreateWechatOfficialAccountChannelAction;
use App\Actions\Channel\WechatOfficialAccount\DeleteWechatOfficialAccountChannelAction;
use App\Actions\Channel\WechatOfficialAccount\ListWechatOfficialAccountChannelsAction;
use App\Actions\Channel\WechatOfficialAccount\ListWechatOfficialAccountChannelTrashAction;
use App\Actions\Channel\WechatOfficialAccount\ReceiveWechatOfficialAccountWebhookAction;
use App\Actions\Channel\WechatOfficialAccount\RestoreWechatOfficialAccountChannelAction;
use App\Actions\Channel\WechatOfficialAccount\ShowCreateWechatOfficialAccountChannelPageAction;
use App\Actions\Channel\WechatOfficialAccount\ShowWechatOfficialAccountChannelDetailPageAction;
use App\Actions\Channel\WechatOfficialAccount\UpdateWechatOfficialAccountChannelBasicAction;
use App\Actions\Contact\CreateContactAction;
use App\Actions\Contact\CreateContactIdentityAction;
use App\Actions\Contact\DeleteContactAction;
use App\Actions\Contact\DeleteContactIdentityAction;
use App\Actions\Contact\GetContactTrashListAction;
use App\Actions\Contact\MergeContactsAction;
use App\Actions\Contact\ReplaceContactIdentityAction;
use App\Actions\Contact\RestoreContactAction;
use App\Actions\Contact\ShowContactDetailAction;
use App\Actions\Contact\ShowContactListAction;
use App\Actions\Contact\UpdateContactAction;
use App\Actions\Contact\UpdateContactImportanceAction;
use App\Actions\Conversation\AttachConversationTagAction;
use App\Actions\Conversation\DetachConversationTagAction;
use App\Actions\Conversation\ShowConversationDetailAction;
use App\Actions\CustomAttribute\ArchiveAttributeDefinitionAction;
use App\Actions\CustomAttribute\CreateAttributeDefinitionAction;
use App\Actions\CustomAttribute\ReorderAttributeDefinitionsAction;
use App\Actions\CustomAttribute\RestoreAttributeDefinitionAction;
use App\Actions\CustomAttribute\ShowAttributeDefinitionListAction;
use App\Actions\CustomAttribute\ShowAttributeDefinitionTrashAction;
use App\Actions\CustomAttribute\UpdateAttributeDefinitionAction;
use App\Actions\CustomAttribute\UpdateContactAttributeValuesAction;
use App\Actions\Dashboard\RedirectCurrentAppDashboardAction;
use App\Actions\Dashboard\RedirectLastDashboardAction;
use App\Actions\Dashboard\ShowDashboardPageAction;
use App\Actions\Experience\AdoptExperienceCandidateAction;
use App\Actions\Experience\DeleteExperienceExtractionAction;
use App\Actions\Experience\DiscardExperienceCandidateAction;
use App\Actions\Experience\ShowCreateExperienceExtractionPageAction;
use App\Actions\Experience\ShowExperienceExtractionConversationsPageAction;
use App\Actions\Experience\ShowExperienceExtractionListPageAction;
use App\Actions\Experience\ShowExperienceExtractionResultsPageAction;
use App\Actions\Experience\StartExperienceExtractionAction;
use App\Actions\Inbox\ClaimInboxConversationAction;
use App\Actions\Inbox\CloseInboxConversationAction;
use App\Actions\Inbox\LoadInboxContactTimelineAction;
use App\Actions\Inbox\MarkInboxConversationReadAction;
use App\Actions\Inbox\PolishInboxReplyAction;
use App\Actions\Inbox\PreviewInboxReplyTranslationAction;
use App\Actions\Inbox\RecallInboxConversationMessageAction;
use App\Actions\Inbox\RedirectLastInboxAction;
use App\Actions\Inbox\ReleaseInboxConversationToAiAction;
use App\Actions\Inbox\RenewInboxConversationActivityAction;
use App\Actions\Inbox\ReopenInboxConversationAction;
use App\Actions\Inbox\ReplyInboxConversationAction;
use App\Actions\Inbox\RetryInboxConversationMessageAction;
use App\Actions\Inbox\SearchInstanceInboxAction;
use App\Actions\Inbox\ShowInboxAction;
use App\Actions\Inbox\TransferInboxConversationAction;
use App\Actions\Inbox\TranslateInboxContactHandoffBriefAction;
use App\Actions\Inbox\TranslateInboxConversationMessagesAction;
use App\Actions\Inbox\TranslateInboxConversationPreviewsAction;
use App\Actions\Inbox\TranslateInboxConversationSummariesAction;
use App\Actions\Integration\BuildContactIntegrationPanelsAction;
use App\Actions\Integration\CheckIntegrationAction;
use App\Actions\Integration\CreateIntegrationAction;
use App\Actions\Integration\DeleteIntegrationAction;
use App\Actions\Integration\Mock\InvokeMockBusinessSystemToolAction;
use App\Actions\Integration\Mock\ShowMockBusinessSystemContactPanelAction;
use App\Actions\Integration\Mock\ShowMockBusinessSystemToolsAction;
use App\Actions\Integration\ShowCreateIntegrationPageAction;
use App\Actions\Integration\ShowEditIntegrationPageAction;
use App\Actions\Integration\ShowInstanceIntegrationsAction;
use App\Actions\Integration\SyncAllIntegrationToolsAction;
use App\Actions\Integration\ToggleIntegrationToolAction;
use App\Actions\Integration\UpdateIntegrationAction;
use App\Actions\Invitation\AcceptInvitationAction;
use App\Actions\Invitation\ShowAcceptInvitationPageAction;
use App\Actions\KnowledgeBase\CreateKnowledgeBaseAction;
use App\Actions\KnowledgeBase\DeleteKnowledgeBaseAction;
use App\Actions\KnowledgeBase\Document\CreateManualKnowledgeDocumentAction;
use App\Actions\KnowledgeBase\Document\DeleteKnowledgeDocumentAction;
use App\Actions\KnowledgeBase\Document\MoveKnowledgeDocumentAction;
use App\Actions\KnowledgeBase\Document\StreamKnowledgeDocumentPreviewFileAction;
use App\Actions\KnowledgeBase\Document\UpdateManualKnowledgeDocumentAction;
use App\Actions\KnowledgeBase\Document\UploadKnowledgeDocumentAction;
use App\Actions\KnowledgeBase\Group\CreateKnowledgeGroupAction;
use App\Actions\KnowledgeBase\Group\DeleteKnowledgeGroupAction;
use App\Actions\KnowledgeBase\Group\UpdateKnowledgeGroupAction;
use App\Actions\KnowledgeBase\Indexing\ReindexKnowledgeDocumentAction;
use App\Actions\KnowledgeBase\ListKnowledgeBasesAction;
use App\Actions\KnowledgeBase\Qa\CreateKnowledgeQaEntryAction;
use App\Actions\KnowledgeBase\Qa\DeleteKnowledgeQaEntryAction;
use App\Actions\KnowledgeBase\Qa\MoveKnowledgeQaEntryAction;
use App\Actions\KnowledgeBase\Qa\UpdateKnowledgeQaEntryAction;
use App\Actions\KnowledgeBase\RunKnowledgeRecallTestAction;
use App\Actions\KnowledgeBase\ShowCreateKnowledgeBasePageAction;
use App\Actions\KnowledgeBase\ShowEditKnowledgeBasePageAction;
use App\Actions\KnowledgeBase\UpdateKnowledgeBaseAction;
use App\Actions\Manage\GetSystemSettingsAction;
use App\Actions\Manage\UpdateSystemSettingsAction;
use App\Actions\Reception\Plan\CreateReceptionPlanAction;
use App\Actions\Reception\Plan\DeleteReceptionPlanAction;
use App\Actions\Reception\Plan\ListReceptionPlanTrashAction;
use App\Actions\Reception\Plan\RestoreReceptionPlanAction;
use App\Actions\Reception\Plan\ShowCreateReceptionPlanPageAction;
use App\Actions\Reception\Plan\ShowReceptionPlanDetailPageAction;
use App\Actions\Reception\Plan\ShowReceptionPlanIndexPageAction;
use App\Actions\Reception\Plan\UpdateReceptionPlanAction;
use App\Actions\Security\CancelTwoFactorChallengeAction;
use App\Actions\Security\ConfirmAppOwnerTwoFactorAction;
use App\Actions\Security\EnableAppOwnerTwoFactorAction;
use App\Actions\Security\LogoutWebAction;
use App\Actions\Security\ShowAppOwnerTwoFactorSetupPageAction;
use App\Actions\Security\SkipAppOwnerTwoFactorSetupAction;
use App\Actions\StorageSetting\CheckStorageSettingAction;
use App\Actions\StorageSetting\GetStorageSettingAction;
use App\Actions\StorageSetting\StorageProfile\CheckStorageProfileAction;
use App\Actions\StorageSetting\StorageProfile\CreateStorageProfileAction;
use App\Actions\StorageSetting\StorageProfile\DeleteStorageProfileAction;
use App\Actions\StorageSetting\StorageProfile\ShowCreateStorageProfilePageAction;
use App\Actions\StorageSetting\StorageProfile\ShowEditStorageProfilePageAction;
use App\Actions\StorageSetting\StorageProfile\UpdateStorageProfileAction;
use App\Actions\StorageSetting\UpdateStorageSettingAction;
use App\Actions\Tag\AttachContactTagAction;
use App\Actions\Tag\CreateTagAction;
use App\Actions\Tag\CreateTagGroupAction;
use App\Actions\Tag\DeleteTagAction;
use App\Actions\Tag\DeleteTagGroupAction;
use App\Actions\Tag\DetachContactTagAction;
use App\Actions\Tag\ListTagUsageAction;
use App\Actions\Tag\MergeTagsAction;
use App\Actions\Tag\RestoreTagAction;
use App\Actions\Tag\ShowTagListAction;
use App\Actions\Tag\ShowTagTrashAction;
use App\Actions\Tag\UpdateTagAction;
use App\Actions\Tag\UpdateTagGroupAction;
use App\Actions\Teammate\CreateTeammateAction;
use App\Actions\Teammate\InviteTeammateAction;
use App\Actions\Teammate\RemoveTeammateAction;
use App\Actions\Teammate\ResendInvitationAction;
use App\Actions\Teammate\RevokeInvitationAction;
use App\Actions\Teammate\ShowCreateTeammatePageAction;
use App\Actions\Teammate\ShowEditTeammatePageAction;
use App\Actions\Teammate\ShowInviteTeammatePageAction;
use App\Actions\Teammate\ShowTeammateListAction;
use App\Actions\Teammate\UpdateTeammateAction;
use App\Actions\Teammate\UpdateTeammateOnlineStatusAction;
use App\Actions\User\DeleteProfileAction;
use App\Actions\User\ShowAppearanceSettingsPageAction;
use App\Actions\User\ShowLanguageSettingsPageAction;
use App\Actions\User\ShowNotificationSettingsPageAction;
use App\Actions\User\ShowPasswordSettingsPageAction;
use App\Actions\User\ShowProfileSettingsPageAction;
use App\Actions\User\ShowTwoFactorAuthenticationSettingsPageAction;
use App\Actions\User\UpdateLanguageSettingsAction;
use App\Actions\User\UpdateMyOnlineStatusAction;
use App\Actions\User\UpdateNotificationSettingsAction;
use App\Actions\User\UpdatePasswordAction;
use App\Actions\User\UpdateProfileAction;
use App\Http\Middleware\AuthenticateSettings;
use App\Http\Middleware\ConfirmPasswordWhenTwoFactorRequiresIt;
use App\Http\Middleware\EnsureAppOwner;
use App\Http\Middleware\EnsureAppOwnerTwoFactorConfirmed;
use App\Http\Middleware\MercureSubscriberCookie;
use App\Http\Middleware\ShareSystemContext;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');
Route::post('/two-factor-challenge/cancel', CancelTwoFactorChallengeAction::class)
    ->middleware('guest:web')
    ->name('two-factor.login.cancel');

Route::get('/attachments/{attachment}/content', ShowAttachmentContentAction::class)
    ->whereUlid('attachment')
    ->name('attachments.content');

// 网站渠道详情页右侧实时预览所嵌入的 iframe 文档（哑壳，渠道草稿由父页面 postMessage 注入）
Route::get('/channels/web/preview', ShowWebChannelPreviewFrameAction::class)
    ->middleware(['auth:web', EnsureAppOwnerTwoFactorConfirmed::class])
    ->name('channels.web.preview');

// 访客独立聊天页（公开，无需认证）：code 约束 wch_ 前缀，不匹配自动 404。
Route::get('/ch/{code}', ShowStandaloneChatPageAction::class)
    ->where('code', 'wch_[a-z0-9]+')
    ->name('public.web.standalone');

// Telegram Bot 入站 webhook（公开，CSRF 豁免见 bootstrap/app.php）。
Route::post('/webhook/telegram/{code}', ReceiveTelegramWebhookAction::class)->name('public.telegram.webhook');

// 微信公众号服务器配置验证与消息回调。
Route::match(['get', 'post'], '/webhook/wechat/{code}', ReceiveWechatOfficialAccountWebhookAction::class)
    ->where('code', 'wxoa_[a-z0-9]+')
    ->name('public.wechat.webhook');

// 网站渠道小部件嵌入（公开，无需认证）。
Route::get('/embed/widget.js', ShowWidgetLoaderScriptAction::class)->name('public.web.widget.script');
Route::get('/embed/widget/{code}/bootstrap', ResolveWidgetBootstrapJsonAction::class)
    ->where('code', 'wch_[a-z0-9]+')
    ->name('public.web.widget.bootstrap');
Route::get('/embed/widget/{code}', ShowWidgetFrameAction::class)
    ->where('code', 'wch_[a-z0-9]+')
    ->name('public.web.widget.frame');

Route::get('/invitations/{token}', ShowAcceptInvitationPageAction::class)
    ->where('token', '[A-Za-z0-9]+')
    ->name('invitations.accept.show');
Route::post('/invitations/{token}', AcceptInvitationAction::class)
    ->where('token', '[A-Za-z0-9]+')
    ->middleware('throttle:10,1')
    ->name('invitations.accept.store');

// 个人设置（全局，不绑定应用）
Route::middleware([AuthenticateSettings::class, ShareSystemContext::class])->prefix('settings')->group(function () {
    Route::redirect('/', '/settings/profile');

    // 个人资料
    Route::get('profile', ShowProfileSettingsPageAction::class)->name('settings.profile.edit');
    Route::patch('profile', UpdateProfileAction::class)->name('settings.profile.update');
    Route::delete('profile', DeleteProfileAction::class)->name('settings.profile.destroy');

    // 密码
    Route::get('password', ShowPasswordSettingsPageAction::class)->name('settings.password.edit');
    Route::put('password', UpdatePasswordAction::class)->middleware('throttle:6,1')->name('settings.password.update');

    // 两步认证
    Route::get('two-factor', ShowTwoFactorAuthenticationSettingsPageAction::class)
        ->middleware(ConfirmPasswordWhenTwoFactorRequiresIt::class)
        ->name('settings.two-factor.show');

    // 语言和时区
    Route::get('language', ShowLanguageSettingsPageAction::class)->name('settings.language.edit');
    Route::put('language', UpdateLanguageSettingsAction::class)->name('settings.language.update');

    // 外观
    Route::get('appearance', ShowAppearanceSettingsPageAction::class)->name('settings.appearance.edit');

    // 通知
    Route::get('notifications', ShowNotificationSettingsPageAction::class)->name('settings.notifications.edit');
    Route::put('notifications', UpdateNotificationSettingsAction::class)->name('settings.notifications.update');
});

// 单一 web guard 登出
Route::post('/logout/web', LogoutWebAction::class)->middleware(['auth:web'])->name('logout.web');

// 首个注册用户是系统管理员，注册后进入 Google Authenticator 引导。
Route::prefix('app/owner')
    ->middleware(['auth:web', EnsureAppOwner::class])
    ->group(function () {
        Route::get('two-factor-setup', ShowAppOwnerTwoFactorSetupPageAction::class)->name('app.owner.two-factor.setup');
        Route::post('two-factor-setup/enable', EnableAppOwnerTwoFactorAction::class)->name('app.owner.two-factor.enable');
        Route::post('two-factor-setup/confirm', ConfirmAppOwnerTwoFactorAction::class)
            ->middleware('throttle:6,1')
            ->name('app.owner.two-factor.confirm');
        Route::post('two-factor-setup/skip', SkipAppOwnerTwoFactorSetupAction::class)->name('app.owner.two-factor.skip');
    });

Route::middleware(['auth:web', EnsureAppOwnerTwoFactorConfirmed::class, EnsureEmailIsVerified::class])->group(function () {
    Route::get('/dashboard', RedirectLastDashboardAction::class)->name('dashboard');
    Route::get('/inbox', RedirectLastInboxAction::class)->name('inbox');
});

Route::middleware(['auth:web', EnsureAppOwnerTwoFactorConfirmed::class, EnsureEmailIsVerified::class, ShareSystemContext::class, MercureSubscriberCookie::class])->prefix('app')->group(function () {
    Route::get('/', RedirectCurrentAppDashboardAction::class)->name('app.home');
    Route::get('/dashboard', ShowDashboardPageAction::class)->name('app.dashboard');
    Route::get('/inbox', ShowInboxAction::class)->name('app.inbox.show');
    Route::put('/online-status', UpdateMyOnlineStatusAction::class)->name('app.online-status.update');

    // 收件箱页面按线程定位，写操作使用当前会话或时间线中的具体消息。
    Route::prefix('inbox')->group(function () {
        Route::get('contacts/{contactId}/timeline', LoadInboxContactTimelineAction::class)->name('app.inbox.contacts.timeline');
        Route::get('contacts/{contactId}/integration-panels', BuildContactIntegrationPanelsAction::class)->name('app.inbox.contacts.integration-panels');
        Route::post('contacts/{contactId}/handoff-brief/translate', TranslateInboxContactHandoffBriefAction::class)->name('app.inbox.contacts.handoff-brief.translate');
        Route::post('conversation-previews/translate', TranslateInboxConversationPreviewsAction::class)->name('app.inbox.conversation-previews.translate');
        Route::get('search', SearchInstanceInboxAction::class)->name('app.inbox.search');
        Route::post('{conversation}/read', MarkInboxConversationReadAction::class)->name('app.inbox.conversations.read');
        Route::post('{conversation}/reply', ReplyInboxConversationAction::class)->name('app.inbox.conversations.reply');
        Route::post('{conversation}/activity', RenewInboxConversationActivityAction::class)->middleware('throttle:120,1')->name('app.inbox.conversations.activity');
        Route::post('{conversation}/reply/polish', PolishInboxReplyAction::class)->name('app.inbox.conversations.reply.polish');
        Route::post('{conversation}/reply/translation-preview', PreviewInboxReplyTranslationAction::class)->name('app.inbox.conversations.reply.translation-preview');
        Route::post('{conversation}/messages/translate', TranslateInboxConversationMessagesAction::class)->name('app.inbox.conversations.messages.translate');
        Route::post('{conversation}/summaries/translate', TranslateInboxConversationSummariesAction::class)->name('app.inbox.conversations.summaries.translate');
        Route::post('{conversation}/messages/{message}/recall', RecallInboxConversationMessageAction::class)->name('app.inbox.conversations.messages.recall');
        Route::post('{conversation}/messages/{message}/retry', RetryInboxConversationMessageAction::class)->name('app.inbox.conversations.messages.retry');
        Route::post('{conversation}/claim', ClaimInboxConversationAction::class)->name('app.inbox.conversations.claim');
        Route::post('{conversation}/transfer', TransferInboxConversationAction::class)->name('app.inbox.conversations.transfer');
        Route::post('{conversation}/release-to-ai', ReleaseInboxConversationToAiAction::class)->name('app.inbox.conversations.release-to-ai');
        Route::post('{conversation}/reopen', ReopenInboxConversationAction::class)->name('app.inbox.conversations.reopen');
        Route::post('{conversation}/close', CloseInboxConversationAction::class)->name('app.inbox.conversations.close');
        // 会话标签人工增删（含历史会话）：删除写抑制墓碑，AI 重算不复打。
        Route::post('{conversation}/tags', AttachConversationTagAction::class)->name('app.inbox.conversations.tags.attach');
        Route::delete('{conversation}/tags/{tagId}', DetachConversationTagAction::class)->whereUlid('tagId')->name('app.inbox.conversations.tags.detach');
    });

    // AI 浮动助手：同步 ack 一轮对话，流式增量由后台 job 发布到该轮对话主题。
    // 节流走命名 limiter（FortifyServiceProvider::configureRateLimiting 注册），
    // 按用户维度计，避免同一 NAT 公网 IP 下多个用户互相挤压配额。
    Route::get('/ai-chat/messages', ShowAiAssistantMessagesAction::class)
        ->name('app.ai-chat.messages.index');
    Route::post('/ai-chat/messages', SendAiAssistantMessageAction::class)
        ->middleware('throttle:ai-chat-send')
        ->name('app.ai-chat.messages.store');
    Route::post('/ai-chat/stop', StopAiAssistantMessageAction::class)
        ->middleware('throttle:ai-chat-stop')
        ->name('app.ai-chat.stop');

    // 快捷回复。
    // search/use-and-render 走 XHR，给收件箱 composer 用。
    Route::prefix('canned-replies')->group(function () {
        Route::get('/', ShowCannedReplyListAction::class)->middleware('can:canned_replies.view')->name('app.canned-replies.index');
        Route::post('/', CreateCannedReplyAction::class)->middleware('can:canned_replies.edit')->name('app.canned-replies.store');
        Route::get('/search', SearchCannedRepliesForComposerAction::class)->name('app.canned-replies.search');
        Route::put('/{cannedReply}', UpdateCannedReplyAction::class)->middleware('can:canned_replies.edit')->whereUlid('cannedReply')->name('app.canned-replies.update');
        Route::delete('/{cannedReply}', DeleteCannedReplyAction::class)->middleware('can:canned_replies.delete')->whereUlid('cannedReply')->name('app.canned-replies.destroy');
        Route::post('/{cannedReply}/use-and-render', UseAndRenderCannedReplyAction::class)->whereUlid('cannedReply')->name('app.canned-replies.use-and-render');
    });

    // 后台设置
    Route::prefix('manage')->group(function () {
        // 系统
        Route::get('system/settings', GetSystemSettingsAction::class)->middleware('can:system_settings.view')->name('app.manage.system.settings.show');
        Route::put('system/settings', UpdateSystemSettingsAction::class)->middleware('can:system_settings.edit')->name('app.manage.system.settings.update');

        Route::prefix('storage')->middleware('can:system_settings.view')->group(function () {
            Route::get('/', GetStorageSettingAction::class)->name('app.manage.storage.index');
            Route::put('/', UpdateStorageSettingAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.update');
            Route::put('/check', CheckStorageSettingAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.check');
            Route::get('/profiles/create', ShowCreateStorageProfilePageAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.profiles.create');
            Route::post('/profiles', CreateStorageProfileAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.profiles.store');
            Route::get('/profiles/{profile}/edit', ShowEditStorageProfilePageAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.profiles.edit');
            Route::put('/profiles/{profile}', UpdateStorageProfileAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.profiles.update');
            Route::put('/profiles/{profile}/check', CheckStorageProfileAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.profiles.check');
            Route::delete('/profiles/{profile}', DeleteStorageProfileAction::class)->middleware('can:system_settings.edit')->name('app.manage.storage.profiles.destroy');
        });

        // AI 供应商
        Route::prefix('ai-providers')->middleware('can:system_settings.view')->group(function () {
            Route::get('/', ShowAiProviderListAction::class)->name('app.manage.ai-providers.index');
            Route::get('/create', ShowCreateAiProviderPageAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-providers.create');
            Route::post('/', CreateAiProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-providers.store');
            Route::get('/{provider}/edit', ShowEditAiProviderPageAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-providers.edit');
            Route::put('/{provider}', UpdateAiProviderCredentialsAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-providers.update');
            Route::post('/{provider}/check', CheckAiProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-providers.check');
            Route::delete('/{provider}', DeleteAiProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-providers.destroy');
        });

        // 翻译供应商。
        Route::prefix('translation-providers')->middleware('can:system_settings.view')->group(function () {
            Route::get('/', ShowTranslationProviderListAction::class)->name('app.manage.translation-providers.index');
            Route::get('/create', ShowCreateTranslationProviderPageAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.create');
            Route::post('/', CreateTranslationProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.store');
            Route::post('/check', CheckTranslationProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.check-new');
            Route::get('/{id}/edit', ShowEditTranslationProviderPageAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.edit');
            Route::put('/{id}', UpdateTranslationProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.update');
            Route::put('/{id}/active', ToggleTranslationProviderActiveAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.toggle-active');
            Route::post('/{id}/check', CheckTranslationProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.check');
            Route::delete('/{id}', DeleteTranslationProviderAction::class)->middleware('can:system_settings.edit')->name('app.manage.translation-providers.destroy');
        });

        // AI 模型
        Route::prefix('ai-models')->middleware('can:system_settings.view')->group(function () {
            Route::get('/', ShowAiModelListAction::class)->name('app.manage.ai-models.index');
            Route::get('/create', ShowCreateAiModelPageAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-models.create');
            Route::post('/', CreateAiModelAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-models.store');
            Route::get('/{model}/edit', ShowEditAiModelPageAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-models.edit');
            Route::put('/{model}', UpdateAiModelAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-models.update');
            Route::put('/{model}/toggle', ToggleAiModelAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-models.toggle');
            Route::delete('/{model}', DeleteAiModelAction::class)->middleware('can:system_settings.edit')->name('app.manage.ai-models.destroy');
        });

        // AI 调用日志：在后台设置内查看完整调用记录。
        Route::prefix('ai-call-logs')->middleware('can:system_settings.view')->group(function () {
            Route::get('/', ShowAiCallLogListAction::class)->name('app.manage.ai-call-logs.index');
            Route::get('/{id}', ShowAiCallLogDetailAction::class)->name('app.manage.ai-call-logs.show');
        });

        // 客服
        Route::get('teammates', ShowTeammateListAction::class)->middleware('can:users.view')->name('app.manage.teammates.index');
        Route::get('teammates/create', ShowCreateTeammatePageAction::class)->middleware('can:users.create')->name('app.manage.teammates.create');
        Route::get('teammates/invite', ShowInviteTeammatePageAction::class)->middleware('can:users.create')->name('app.manage.teammates.invite');
        Route::post('teammates', CreateTeammateAction::class)->middleware('can:users.create')->name('app.manage.teammates.store');
        Route::post('teammates/invitations', InviteTeammateAction::class)->middleware('can:users.create')->name('app.manage.teammates.invitations.store');
        Route::post('teammates/invitations/{invitation}/resend', ResendInvitationAction::class)->middleware('can:users.create')->whereUlid('invitation')->name('app.manage.teammates.invitations.resend');
        Route::delete('teammates/invitations/{invitation}', RevokeInvitationAction::class)->middleware('can:users.create')->whereUlid('invitation')->name('app.manage.teammates.invitations.destroy');
        Route::get('teammates/{id}/edit', ShowEditTeammatePageAction::class)->middleware('can:users.edit')->name('app.manage.teammates.edit');
        Route::put('teammates/{id}', UpdateTeammateAction::class)->middleware('can:users.edit')->name('app.manage.teammates.update');
        Route::put('teammates/{id}/online-status', UpdateTeammateOnlineStatusAction::class)->middleware('can:users.edit')->name('app.manage.teammates.online-status.update');
        Route::delete('teammates/{id}', RemoveTeammateAction::class)->middleware('can:users.delete')->name('app.manage.teammates.destroy');

        // 知识库
        Route::prefix('knowledge-bases')->middleware('can:knowledge_bases.view')->group(function () {
            Route::get('/', ListKnowledgeBasesAction::class)->name('app.manage.knowledge-bases.index');
            Route::get('/create', ShowCreateKnowledgeBasePageAction::class)->middleware('can:knowledge_bases.create')->name('app.manage.knowledge-bases.create');
            Route::post('/', CreateKnowledgeBaseAction::class)->middleware('can:knowledge_bases.create')->name('app.manage.knowledge-bases.store');
            Route::get('/{knowledgeBase}/edit', ShowEditKnowledgeBasePageAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.edit');
            Route::put('/{knowledgeBase}', UpdateKnowledgeBaseAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.update');
            Route::delete('/{knowledgeBase}', DeleteKnowledgeBaseAction::class)->middleware('can:knowledge_bases.delete')->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.destroy');
            Route::post('/{knowledgeBase}/recall-test', RunKnowledgeRecallTestAction::class)->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.recall-test');

            // 文档分组
            Route::post('/{knowledgeBase}/groups', CreateKnowledgeGroupAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.groups.store');
            Route::put('/{knowledgeBase}/groups/{group}', UpdateKnowledgeGroupAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->whereUlid('group')->name('app.manage.knowledge-bases.groups.update');
            Route::delete('/{knowledgeBase}/groups/{group}', DeleteKnowledgeGroupAction::class)->middleware('can:knowledge_bases.delete')->whereUlid('knowledgeBase')->whereUlid('group')->name('app.manage.knowledge-bases.groups.destroy');

            // 文档
            Route::post('/{knowledgeBase}/documents', UploadKnowledgeDocumentAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.documents.store');
            Route::post('/{knowledgeBase}/documents/manual', CreateManualKnowledgeDocumentAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.documents.manual.store');
            Route::get('/{knowledgeBase}/documents/{document}/preview-file', StreamKnowledgeDocumentPreviewFileAction::class)->whereUlid('knowledgeBase')->whereUlid('document')->name('app.manage.knowledge-bases.documents.preview-file');
            Route::put('/{knowledgeBase}/documents/{document}/manual', UpdateManualKnowledgeDocumentAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->whereUlid('document')->name('app.manage.knowledge-bases.documents.manual.update');
            Route::put('/{knowledgeBase}/documents/{document}/group', MoveKnowledgeDocumentAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->whereUlid('document')->name('app.manage.knowledge-bases.documents.move');
            Route::post('/{knowledgeBase}/documents/{document}/reindex', ReindexKnowledgeDocumentAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->whereUlid('document')->name('app.manage.knowledge-bases.documents.reindex');
            Route::delete('/{knowledgeBase}/documents/{document}', DeleteKnowledgeDocumentAction::class)->middleware('can:knowledge_bases.delete')->whereUlid('knowledgeBase')->whereUlid('document')->name('app.manage.knowledge-bases.documents.destroy');

            // 问答
            Route::post('/{knowledgeBase}/qa-entries', CreateKnowledgeQaEntryAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.knowledge-bases.qa-entries.store');
            Route::put('/{knowledgeBase}/qa-entries/{entry}', UpdateKnowledgeQaEntryAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->whereUlid('entry')->name('app.manage.knowledge-bases.qa-entries.update');
            Route::put('/{knowledgeBase}/qa-entries/{entry}/group', MoveKnowledgeQaEntryAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->whereUlid('entry')->name('app.manage.knowledge-bases.qa-entries.move');
            Route::delete('/{knowledgeBase}/qa-entries/{entry}', DeleteKnowledgeQaEntryAction::class)->middleware('can:knowledge_bases.delete')->whereUlid('knowledgeBase')->whereUlid('entry')->name('app.manage.knowledge-bases.qa-entries.destroy');
        });

        // 经验提炼（绑定问答知识库）：任务列表 → 创建任务（筛选勾选人工会话）→ 任务会话清单 / 经验结果（审核采纳进绑定的问答库）。
        Route::prefix('experience-extraction')->middleware('can:knowledge_bases.view')->group(function () {
            Route::get('/{knowledgeBase}', ShowExperienceExtractionListPageAction::class)->whereUlid('knowledgeBase')->name('app.manage.experience-extraction.index');
            Route::get('/{knowledgeBase}/create', ShowCreateExperienceExtractionPageAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.experience-extraction.create');
            Route::post('/{knowledgeBase}/runs', StartExperienceExtractionAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('knowledgeBase')->name('app.manage.experience-extraction.start');
            Route::get('/{extraction}/conversations', ShowExperienceExtractionConversationsPageAction::class)->whereUlid('extraction')->name('app.manage.experience-extraction.conversations');
            Route::delete('/{extraction}', DeleteExperienceExtractionAction::class)->middleware('can:knowledge_bases.delete')->whereUlid('extraction')->name('app.manage.experience-extraction.destroy');
            Route::get('/{extraction}/results', ShowExperienceExtractionResultsPageAction::class)->whereUlid('extraction')->name('app.manage.experience-extraction.results');
            Route::put('/candidates/{candidate}/adopt', AdoptExperienceCandidateAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('candidate')->name('app.manage.experience-extraction.candidates.adopt');
            Route::put('/candidates/{candidate}/discard', DiscardExperienceCandidateAction::class)->middleware('can:knowledge_bases.edit')->whereUlid('candidate')->name('app.manage.experience-extraction.candidates.discard');
        });

        // 集成：系统所有者管理外部服务连接和可用工具。
        Route::prefix('integrations')->middleware(EnsureAppOwner::class)->group(function () {
            Route::get('/', ShowInstanceIntegrationsAction::class)->name('app.manage.integrations.index');
            Route::get('/create', ShowCreateIntegrationPageAction::class)->name('app.manage.integrations.create');
            Route::post('/', CreateIntegrationAction::class)->name('app.manage.integrations.store');
            Route::post('/check', CheckIntegrationAction::class)->name('app.manage.integrations.check-unsaved');
            Route::post('/sync', SyncAllIntegrationToolsAction::class)->name('app.manage.integrations.sync-all');
            Route::get('/{server}/edit', ShowEditIntegrationPageAction::class)->name('app.manage.integrations.edit');
            Route::put('/{server}', UpdateIntegrationAction::class)->name('app.manage.integrations.update');
            Route::delete('/{server}', DeleteIntegrationAction::class)->name('app.manage.integrations.destroy');
            Route::post('/{server}/check', CheckIntegrationAction::class)->name('app.manage.integrations.check');
            Route::put('/{server}/tools/{tool}/toggle', ToggleIntegrationToolAction::class)->whereUlid('tool')->name('app.manage.integrations.tools.toggle');
        });

        // 接待方案
        Route::prefix('reception/plans')->middleware('can:reception_plans.view')->group(function () {
            Route::get('/', ShowReceptionPlanIndexPageAction::class)->name('app.manage.reception.plans.index');
            Route::get('/create', ShowCreateReceptionPlanPageAction::class)->middleware('can:reception_plans.create')->name('app.manage.reception.plans.create');
            Route::get('/trash', ListReceptionPlanTrashAction::class)->name('app.manage.reception.plans.trash');
            Route::post('/', CreateReceptionPlanAction::class)->middleware('can:reception_plans.create')->name('app.manage.reception.plans.store');
            Route::get('/{plan}', ShowReceptionPlanDetailPageAction::class)->whereUlid('plan')->name('app.manage.reception.plans.show');
            Route::put('/{plan}', UpdateReceptionPlanAction::class)->middleware('can:reception_plans.edit')->whereUlid('plan')->name('app.manage.reception.plans.update');
            Route::delete('/{plan}', DeleteReceptionPlanAction::class)->middleware('can:reception_plans.delete')->whereUlid('plan')->name('app.manage.reception.plans.destroy');
            Route::put('/{plan}/restore', RestoreReceptionPlanAction::class)->middleware('can:reception_plans.edit')->whereUlid('plan')->name('app.manage.reception.plans.restore');
        });

        // 渠道
        Route::prefix('channels')->middleware('can:channels.view')->group(function () {
            // 网站渠道
            Route::prefix('web')->group(function () {
                Route::get('/', ListWebChannelsAction::class)->name('app.manage.channels.web.index');
                Route::get('/create', ShowCreateWebChannelPageAction::class)->middleware('can:channels.create')->name('app.manage.channels.web.create');
                Route::get('/trash', ListWebChannelTrashAction::class)->name('app.manage.channels.web.trash');
                Route::post('/', CreateWebChannelAction::class)->middleware('can:channels.create')->name('app.manage.channels.web.store');
                Route::get('/{channel}', ShowWebChannelDetailPageAction::class)->whereUlid('channel')->name('app.manage.channels.web.show');
                Route::put('/{channel}/basic', UpdateWebChannelBasicAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.web.basic.update');
                Route::put('/{channel}/visitor-interface', UpdateWebChannelVisitorInterfaceAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.web.visitor-interface.update');
                Route::put('/{channel}/widget', UpdateWebChannelWidgetAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.web.widget.update');
                Route::put('/{channel}/access', UpdateWebChannelAccessAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.web.access.update');
                Route::put('/{channel}/embed', UpdateWebChannelEmbedAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.web.embed.update');
                Route::post('/{channel}/user-token-secret', RegenerateWebChannelUserTokenSecretAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.web.user-token-secret.regenerate');
                Route::put('/{channel}/restore', RestoreWebChannelAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.web.restore');
                Route::delete('/{channel}', DeleteWebChannelAction::class)->middleware('can:channels.delete')->whereUlid('channel')->name('app.manage.channels.web.destroy');
            });

            // Telegram Bot 渠道
            Route::prefix('telegram')->group(function () {
                Route::get('/', ListTelegramChannelsAction::class)->name('app.manage.channels.telegram.index');
                Route::get('/create', ShowCreateTelegramChannelPageAction::class)->middleware('can:channels.create')->name('app.manage.channels.telegram.create');
                Route::get('/trash', ListTelegramChannelTrashAction::class)->name('app.manage.channels.telegram.trash');
                Route::post('/', CreateTelegramChannelAction::class)->middleware('can:channels.create')->name('app.manage.channels.telegram.store');
                Route::post('/check-bot-token', CheckTelegramBotTokenAction::class)->middleware('can:channels.create')->name('app.manage.channels.telegram.bot-token.check');
                Route::get('/{channel}', ShowTelegramChannelDetailPageAction::class)->whereUlid('channel')->name('app.manage.channels.telegram.show');
                Route::put('/{channel}/basic', UpdateTelegramChannelBasicAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.telegram.basic.update');
                Route::post('/{channel}/webhook', RegisterTelegramWebhookAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.telegram.webhook.register');
                Route::put('/{channel}/restore', RestoreTelegramChannelAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.telegram.restore');
                Route::delete('/{channel}', DeleteTelegramChannelAction::class)->middleware('can:channels.delete')->whereUlid('channel')->name('app.manage.channels.telegram.destroy');
            });

            // 微信公众号原生消息渠道
            Route::prefix('wechat-official-account')->group(function () {
                Route::get('/', ListWechatOfficialAccountChannelsAction::class)->name('app.manage.channels.wechat-official-account.index');
                Route::get('/create', ShowCreateWechatOfficialAccountChannelPageAction::class)->middleware('can:channels.create')->name('app.manage.channels.wechat-official-account.create');
                Route::get('/trash', ListWechatOfficialAccountChannelTrashAction::class)->name('app.manage.channels.wechat-official-account.trash');
                Route::post('/', CreateWechatOfficialAccountChannelAction::class)->middleware('can:channels.create')->name('app.manage.channels.wechat-official-account.store');
                Route::get('/{channel}', ShowWechatOfficialAccountChannelDetailPageAction::class)->whereUlid('channel')->name('app.manage.channels.wechat-official-account.show');
                Route::put('/{channel}/basic', UpdateWechatOfficialAccountChannelBasicAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.wechat-official-account.basic.update');
                Route::put('/{channel}/restore', RestoreWechatOfficialAccountChannelAction::class)->middleware('can:channels.edit')->whereUlid('channel')->name('app.manage.channels.wechat-official-account.restore');
                Route::delete('/{channel}', DeleteWechatOfficialAccountChannelAction::class)->middleware('can:channels.delete')->whereUlid('channel')->name('app.manage.channels.wechat-official-account.destroy');
            });
        });

        // 标签
        Route::prefix('tags')->middleware('can:tags.view')->group(function () {
            Route::get('/', ShowTagListAction::class)->name('app.manage.tags.index');
            Route::get('/trash', ShowTagTrashAction::class)->name('app.manage.tags.trash');
            Route::post('/', CreateTagAction::class)->middleware('can:tags.edit')->name('app.manage.tags.store');
            Route::post('/merge', MergeTagsAction::class)->middleware('can:tags.edit')->name('app.manage.tags.merge');
            // 标签组
            Route::post('/groups', CreateTagGroupAction::class)->middleware('can:tags.edit')->name('app.manage.tags.groups.store');
            Route::put('/groups/{id}', UpdateTagGroupAction::class)->middleware('can:tags.edit')->whereUlid('id')->name('app.manage.tags.groups.update');
            Route::delete('/groups/{id}', DeleteTagGroupAction::class)->middleware('can:tags.delete')->whereUlid('id')->name('app.manage.tags.groups.destroy');
            Route::put('{id}', UpdateTagAction::class)->middleware('can:tags.edit')->name('app.manage.tags.update');
            Route::put('{id}/restore', RestoreTagAction::class)->middleware('can:tags.edit')->name('app.manage.tags.restore');
            Route::delete('{id}', DeleteTagAction::class)->middleware('can:tags.delete')->name('app.manage.tags.destroy');
            Route::get('{id}/usage', ListTagUsageAction::class)->name('app.manage.tags.usage');
        });

        // 自定义属性
        Route::prefix('attributes')->middleware('can:attributes.view')->group(function () {
            Route::get('/', ShowAttributeDefinitionListAction::class)->name('app.manage.attributes.index');
            Route::get('/trash', ShowAttributeDefinitionTrashAction::class)->name('app.manage.attributes.trash');
            Route::post('/', CreateAttributeDefinitionAction::class)->middleware('can:attributes.edit')->name('app.manage.attributes.store');
            Route::put('reorder', ReorderAttributeDefinitionsAction::class)->middleware('can:attributes.edit')->name('app.manage.attributes.reorder');
            Route::put('{id}/archive', ArchiveAttributeDefinitionAction::class)->middleware('can:attributes.delete')->whereUlid('id')->name('app.manage.attributes.archive');
            Route::put('{id}/restore', RestoreAttributeDefinitionAction::class)->middleware('can:attributes.edit')->whereUlid('id')->name('app.manage.attributes.restore');
            Route::put('{id}', UpdateAttributeDefinitionAction::class)->middleware('can:attributes.edit')->whereUlid('id')->name('app.manage.attributes.update');
        });
    });

    // 联系人
    Route::prefix('contacts')->middleware('can:contacts.view')->group(function () {
        Route::get('/trash', GetContactTrashListAction::class)->name('app.contacts.trash');
        Route::get('/{type}/index', ShowContactListAction::class)
            ->whereIn('type', ['all', 'contacts', 'visitors'])
            ->name('app.contacts.index');
        Route::post('/', CreateContactAction::class)->middleware('can:contacts.edit')->name('app.contacts.store');
        Route::post('/merge', MergeContactsAction::class)->middleware('can:contacts.edit')->name('app.contacts.merge');
        Route::get('/{id}/detail', ShowContactDetailAction::class)->name('app.contacts.show');
        Route::put('/{id}', UpdateContactAction::class)->middleware('can:contacts.edit')->name('app.contacts.update');
        Route::put('/{id}/importance', UpdateContactImportanceAction::class)->middleware('can:contacts.edit')->whereUlid('id')->name('app.contacts.importance.update');
        Route::delete('/{id}', DeleteContactAction::class)->middleware('can:contacts.delete')->name('app.contacts.destroy');
        Route::put('/{id}/restore', RestoreContactAction::class)->middleware('can:contacts.edit')->name('app.contacts.restore');
        Route::post('/{contactId}/identities', CreateContactIdentityAction::class)->middleware('can:contacts.edit')->name('app.contacts.identities.store');
        Route::put('/{contactId}/identities/{identityId}', ReplaceContactIdentityAction::class)->middleware('can:contacts.edit')->name('app.contacts.identities.replace');
        Route::delete('/{contactId}/identities/{identityId}', DeleteContactIdentityAction::class)->middleware('can:contacts.edit')->name('app.contacts.identities.destroy');
        Route::put('/{id}/attributes', UpdateContactAttributeValuesAction::class)->middleware('can:contacts.edit')->whereUlid('id')->name('app.contacts.attributes.update');
        Route::post('/{id}/tags', AttachContactTagAction::class)->middleware('can:contacts.edit')->name('app.contacts.tags.attach');
        Route::delete('/{id}/tags/{tagId}', DetachContactTagAction::class)->middleware('can:contacts.edit')->name('app.contacts.tags.detach');
    });

    // 会话详情（只读抽屉数据源：经验提炼页复用；会话记录列表页已下线）
    Route::prefix('/conversations')->middleware('can:conversations.view')->group(function () {
        Route::get('/{id}/detail', ShowConversationDetailAction::class)
            ->whereUlid('id')
            ->name('app.conversations.show');
    });
});

// 应用内「自有业务系统」mock：演示 business_system provider 的接入契约（manifest + invoke），
// 仅 local / testing 可达（Action 内部 abort 404），服务端到服务端调用，免 CSRF。
Route::prefix('mock-business-system')->group(function () {
    Route::get('helmdesk/tools', ShowMockBusinessSystemToolsAction::class)
        ->name('mock.business-system.tools');
    Route::post('helmdesk/tools/{name}/invoke', InvokeMockBusinessSystemToolAction::class)
        ->name('mock.business-system.invoke');
    Route::get('helmdesk/contact-panel', ShowMockBusinessSystemContactPanelAction::class)
        ->name('mock.business-system.contact-panel');
});
