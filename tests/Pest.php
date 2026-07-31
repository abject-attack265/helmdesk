<?php

use App\Jobs\Conversation\GenerateConversationSubjectJob;
use App\Models\Membership;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

$ragDbPath = __DIR__.'/../storage/framework/testing/rag.sqlite';
if (! is_dir(dirname($ragDbPath))) {
    mkdir(dirname($ragDbPath), 0755, true);
}
if (file_exists($ragDbPath)) {
    unlink($ragDbPath);
}
touch($ragDbPath);

require_once __DIR__.'/Support/KnowledgeRecallHelpers.php';
require_once __DIR__.'/Support/TntSearchScoutHelpers.php';
require_once __DIR__.'/Support/AttachmentStorageHelpers.php';
require_once __DIR__.'/Support/AiModelHelpers.php';
require_once __DIR__.'/Support/KnowledgeEngineHelpers.php';

// 主题生成派发由专用测试验证，其余功能测试不执行该同步队列任务。
pest()->extend(TestCase::class)
    ->beforeEach(function (): void {
        Queue::fake([GenerateConversationSubjectJob::class]);
    })
    ->in('Feature');

/**
 * @param  array<string, mixed>  $userAttributes
 * @param  array<string, mixed>  $appAttributes
 * @return array{0: GeneralSettings, 1: User}
 */
function createInstanceWithOwner(array $userAttributes = [], array $appAttributes = []): array
{
    $user = User::factory()->create($userAttributes);
    $app = createSystemSettings(array_merge([
        'owner_id' => $user->id,
    ], $appAttributes));

    Membership::query()->create(['user_id' => $user->id]);

    return [$app, $user];
}

/**
 * 把用户加入系统。
 *
 * @param  array<string, mixed>  $attributes
 */
function attachMember(User $user, array $attributes = []): Membership
{
    Membership::query()->updateOrCreate(
        ['user_id' => $user->id],
        $attributes,
    );

    return $user->membership()->firstOrFail();
}

function systemSettings(): GeneralSettings
{
    return app(GeneralSettings::class);
}

function createSystemSettings(array $attributes = []): GeneralSettings
{
    $settings = app(GeneralSettings::class);
    $settings->refresh();
    $settings->fill($attributes)->save();

    return $settings;
}
