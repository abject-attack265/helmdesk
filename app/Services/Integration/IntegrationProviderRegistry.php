<?php

namespace App\Services\Integration;

use App\Contracts\Integration\IntegrationToolProvider;
use App\Enums\IntegrationProvider;
use Closure;
use InvalidArgumentException;

/**
 * 集成工具 provider 注册表：按 IntegrationProvider 枚举解析对应的 IntegrationToolProvider 实例。
 *
 * 同步 Action 与运行时工具构建器通过本表分发到具体平台实现，自身不感知协议细节；
 * 新增平台只需实现 IntegrationToolProvider 并在 AppServiceProvider 注册时登记进来。
 *
 * 各 provider 以「解析闭包」登记、按需现解析（而非构造时即实例化）：本表是 Octane 单例，
 * 而 McpToolProvider 依赖的 McpRuntimeClient 在测试中会被 app()->instance() 替换为 mock，
 * 延迟解析确保每次取到的是容器当前绑定，避免单例缓存住旧实例。
 */
class IntegrationProviderRegistry
{
    /**
     * @param  array<string, Closure(): IntegrationToolProvider>  $resolvers  以 provider value 为键的解析闭包表
     */
    public function __construct(
        private array $resolvers,
    ) {}

    /**
     * 返回指定 provider 的工具实现；未登记则视为编程/配置错误，尽早失败。
     */
    public function for(IntegrationProvider $provider): IntegrationToolProvider
    {
        $resolver = $this->resolvers[$provider->value]
            ?? throw new InvalidArgumentException("No integration tool provider registered for [{$provider->value}].");

        return $resolver();
    }
}
