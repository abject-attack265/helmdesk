<?php

namespace App\Console\Commands;

use App\Actions\Integration\SyncIntegrationToolsAction;
use App\Actions\Reception\Plan\EnsureReceptionPlanVersionAction;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\ReceptionPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * 本地演示命令：为指定（或第一个）应用接入一个进程内 mock_business_system provider 的集成。
 *
 * 创建 provider=MockBusinessSystem 的 Integration（占位 endpoint，不发 HTTP）→ 经 registry 走
 * MockBusinessSystemProvider 进程内同步出 2 个工具 → 若应用有接待方案，授予该方案全部工具的
 * integration grant 并触发方案重新编译/发布。仅 local 环境可用，避免误把演示数据写进生产。
 */
class SeedMockBusinessSystemIntegrationCommand extends Command
{
    /** @var string 命令名称和参数签名。 */
    protected $signature = 'integration:seed-mock';

    /** @var string 命令说明。 */
    protected $description = '为应用接入一个 mock 的「业务系统」集成并同步工具（仅 local）。';

    /**
     * 创建 / 复用 mock 集成、同步工具、授予接待方案并发布。
     */
    public function handle(SyncIntegrationToolsAction $sync, EnsureReceptionPlanVersionAction $ensureVersion): int
    {
        if (! app()->environment('local')) {
            $this->components->error('该命令仅在 local 环境可用。');

            return self::FAILURE;
        }

        // 同一应用按 provider 复用（进程内 mock 同 provider 只需一条），避免重复 seed 堆积多条。
        $integration = Integration::query()
            ->where('provider', IntegrationProvider::MockBusinessSystem)
            ->first();

        if ($integration === null) {
            /** @var Integration $integration */
            $integration = Integration::query()->create([
                'provider' => IntegrationProvider::MockBusinessSystem,
                'slug' => 'business-system-mock-'.Str::lower(Str::random(6)),
                'name' => '业务系统（mock）',
                'transport' => IntegrationProvider::MockBusinessSystem->transport(),
                // 进程内 provider 不发请求，endpoint 仅为非空占位，MockBusinessSystemProvider 不使用。
                'endpoint_url' => 'mock://in-process',
                'credentials' => null,
                'headers' => null,
                'timeout_seconds' => 15,
                'sort_order' => (Integration::query()->max('sort_order') ?? 0) + 1,
            ]);
        }

        $result = $sync->syncServer($integration);
        if (! $result['success']) {
            $this->components->error('同步 mock 工具失败：'.$result['message']);

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '已同步 mock 工具：共 %d 个（新增 %d）。',
            $result['total'],
            $result['added'],
        ));

        $plan = ReceptionPlan::query()
            ->orderBy('created_at')
            ->first();

        if ($plan !== null) {
            // 空白名单 = 授予该集成当前全部已启用工具。
            $plan->integrationGrants()->updateOrCreate(
                ['integration_id' => $integration->id],
                ['tool_whitelist' => null],
            );

            $ensureVersion->handle($plan->refresh(), null);

            $this->components->info(sprintf('已授予接待方案「%s」并重新发布版本。', $plan->name));
        } else {
            $this->components->warn('该应用暂无接待方案，已跳过授权；可在接待方案中手动授予该集成。');
        }

        $this->components->info('去 Integrations 后台可看到「业务系统（mock）」已连接 + 2 个工具，接待会话里 AI 即可调用。');
        $this->components->info('在接待会话选中该联系人后，右侧「资料」tab 底部即可看到该联系人的业务数据面板。');
        $this->components->info('mock 为进程内实现（不发 HTTP、不依赖端口），通过 make 启动后即时可用，演示返回固定的订单和客户数据。');

        return self::SUCCESS;
    }
}
