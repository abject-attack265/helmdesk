<?php

namespace App\Actions\Integration\Mock;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 应用内模拟「自有业务系统」provider 的共享常量与守卫。
 *
 * 仅用于本地 / 测试环境演示 BusinessSystemToolProvider 的接入效果（manifest + invoke 两个端点），
 * 生产环境一律不可达。鉴权使用一个固定的共享密钥 header。
 */
final class MockBusinessSystem
{
    /**
     * mock 鉴权 header 名（与 seed 命令写入 Integration.credentials 的 auth_header_name 一致）。
     */
    public const string AUTH_HEADER = 'X-HelmDesk-Mock-Secret';

    /**
     * mock 共享密钥（写死，仅演示用）。
     */
    public const string SECRET = 'helmdesk-mock-secret';

    /**
     * 仅 local / testing 可达，其余环境一律 404，避免把演示端点暴露到生产。
     */
    public static function assertEnvironment(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * 校验请求头里的共享密钥，不匹配返回 false（调用方据此返回 401）。
     */
    public static function isAuthorized(Request $request): bool
    {
        return hash_equals(self::SECRET, (string) $request->header(self::AUTH_HEADER, ''));
    }

    /**
     * 写死的工具清单（query_order / query_customer），形如 listToolDefinitions 的返回结构。
     *
     * HTTP mock 端点与进程内 MockBusinessSystemProvider 共用此单一来源，确保两条路径行为一致。
     *
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public static function toolDefinitions(): array
    {
        return [
            [
                'name' => 'query_order',
                'description' => __('integration.mock.tools.query_order.description'),
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'order_no' => ['type' => 'string', 'description' => __('integration.mock.tools.query_order.order_no')],
                        'email' => ['type' => 'string', 'description' => __('integration.mock.tools.query_order.email')],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name' => 'query_customer',
                'description' => __('integration.mock.tools.query_customer.description'),
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'email' => ['type' => 'string', 'description' => __('integration.mock.tools.query_customer.email')],
                    ],
                    'required' => ['email'],
                ],
            ],
        ];
    }

    /**
     * 按工具名分发并返回写死的假业务数据；未知工具返回兜底 content。
     *
     * @param  array<string, mixed>  $arguments
     * @return array{content: string, data: array<string, mixed>}
     */
    public static function invokeTool(string $name, array $arguments): array
    {
        return match ($name) {
            'query_order' => self::queryOrder($arguments),
            'query_customer' => self::queryCustomer($arguments),
            default => [
                'content' => __('integration.mock.tools.unknown', ['name' => $name]),
                'data' => [],
            ],
        };
    }

    /**
     * 写死的联系人业务数据面板 sections（一个「客户概况」key_value section + 一个「最近订单」list section）。
     *
     * @return list<array<string, mixed>>
     */
    public static function contactPanelSections(): array
    {
        return [
            [
                'title' => __('integration.mock.panel.customer_overview'),
                'collapsed' => false,
                'blocks' => [
                    [
                        'kind' => 'key_value',
                        'rows' => [
                            ['label' => __('integration.mock.panel.customer_tier'), 'value' => 'VIP', 'value_type' => 'badge'],
                            ['label' => __('integration.mock.panel.lifetime_orders'), 'value' => '12', 'value_type' => 'text'],
                            ['label' => __('integration.mock.panel.member_since'), 'value' => '2024-03-08', 'value_type' => 'date'],
                            ['label' => __('integration.mock.panel.member_profile'), 'value' => 'https://example.com/members/demo', 'value_type' => 'link'],
                        ],
                    ],
                ],
            ],
            [
                'title' => __('integration.mock.panel.recent_orders'),
                'collapsed' => false,
                'blocks' => [
                    [
                        'kind' => 'list',
                        'title' => __('integration.mock.panel.recent_orders'),
                        'items' => [
                            [
                                'title' => '#SO-1024',
                                'subtitle' => __('integration.mock.panel.noise_cancelling_headphones'),
                                'meta' => [__('integration.mock.panel.amount_299'), '2026-06-10'],
                                'badge' => __('integration.mock.panel.shipped'),
                            ],
                            [
                                'title' => '#SO-1018',
                                'subtitle' => __('integration.mock.panel.mechanical_keyboard'),
                                'meta' => [__('integration.mock.panel.amount_459'), '2026-05-22'],
                                'badge' => __('integration.mock.panel.completed'),
                            ],
                            [
                                'title' => '#SO-1003',
                                'subtitle' => __('integration.mock.panel.usb_c_cable'),
                                'meta' => [__('integration.mock.panel.amount_58'), '2026-04-30'],
                                'badge' => __('integration.mock.panel.refunded'),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 生成一条假订单结论。
     *
     * @param  array<string, mixed>  $arguments
     * @return array{content: string, data: array<string, mixed>}
     */
    private static function queryOrder(array $arguments): array
    {
        $orderNo = is_string($arguments['order_no'] ?? null) && $arguments['order_no'] !== ''
            ? $arguments['order_no']
            : 'SO-20260612-0001';

        $order = [
            'order_no' => $orderNo,
            'status' => __('integration.mock.order.status'),
            'tracking_no' => 'SF1234567890',
            'amount' => __('integration.mock.order.amount'),
            'placed_at' => '2026-06-10 14:32',
        ];

        $content = __('integration.mock.order.content', $order);

        return ['content' => $content, 'data' => $order];
    }

    /**
     * 生成一条假客户结论。
     *
     * @param  array<string, mixed>  $arguments
     * @return array{content: string, data: array<string, mixed>}
     */
    private static function queryCustomer(array $arguments): array
    {
        $email = is_string($arguments['email'] ?? null) && $arguments['email'] !== ''
            ? $arguments['email']
            : 'demo@example.com';

        $customer = [
            'email' => $email,
            'name' => __('integration.mock.customer.name'),
            'level' => __('integration.mock.customer.level'),
            'order_count' => 12,
        ];

        $content = __('integration.mock.customer.content', $customer);

        return ['content' => $content, 'data' => $customer];
    }
}
