<?php

namespace App\Services\CannedReply;

use App\Data\CannedReply\CannedReplyRenderContextData;
use App\Enums\CannedReplyTokenKind;

/**
 * 快捷回复模版变量解析器。
 * 解析 {{namespace.field}} 严格无空格点号格式；
 * 支持 contact / conversation / teammate / app 命名空间。
 */
class CannedReplyVariableResolver
{
    /**
     * 严格匹配 {{namespace.field}}：namespace 与 field 都只允许小写字母、数字、下划线。
     * 中间允许零个空白以避免误伤 `{{ contact.name }}` 这种用户笔误（解析时按 trim 处理）。
     */
    private const string TOKEN_PATTERN = '/\{\{\s*([a-z][a-z0-9_]*)\.([a-z][a-z0-9_]*)\s*\}\}/i';

    /**
     * 渲染模版正文。
     *
     * @return array{content: string, warnings: array<int, string>}
     */
    public function render(string $template, CannedReplyRenderContextData $context): array
    {
        $warnings = [];

        $rendered = preg_replace_callback(
            self::TOKEN_PATTERN,
            function (array $match) use ($context, &$warnings): string {
                $namespace = strtolower(trim($match[1]));
                $field = strtolower(trim($match[2]));
                $original = $match[0];

                $kind = CannedReplyTokenKind::tryFrom($namespace);
                if ($kind === null) {
                    return $original;
                }

                $resolved = $this->resolveStatic($kind, $field, $context);
                if ($resolved === null) {
                    $warnings[] = __('canned_reply.warnings.missing_value', ['token' => $original]);

                    return $original;
                }

                return $resolved;
            },
            $template,
        );

        return [
            'content' => $rendered ?? $template,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * 提取模版中实际使用的所有 token，便于编辑页提示缺失字段。
     *
     * @return array<int, string>
     */
    public function extractTokens(string $template): array
    {
        if (! preg_match_all(self::TOKEN_PATTERN, $template, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[] = $match[0];
        }

        return array_values(array_unique($tokens));
    }

    /**
     * 返回前端"插入变量"按钮可用的全部静态 token 列表。
     *
     * @return array<int, array{kind: string, kind_label: string, key: string, token: string, label: string}>
     */
    public function availableTokens(): array
    {
        $tokens = [];

        foreach (self::staticTokenSpec() as $kind => $fields) {
            $kindEnum = CannedReplyTokenKind::from($kind);
            foreach ($fields as $field) {
                $token = sprintf('{{%s.%s}}', $kind, $field);
                $tokens[] = [
                    'kind' => $kind,
                    'kind_label' => $kindEnum->label(),
                    'key' => $field,
                    'token' => $token,
                    'label' => __('canned_reply.tokens.'.$kind.'_'.$field),
                ];
            }
        }

        return $tokens;
    }

    /**
     * 解析单个静态 token；缺值返回 null。
     */
    private function resolveStatic(CannedReplyTokenKind $kind, string $field, CannedReplyRenderContextData $context): ?string
    {
        $value = match ([$kind->value, $field]) {
            ['contact', 'name'] => $context->contact_name,
            ['contact', 'email'] => $context->contact_email,
            ['contact', 'primary_phone'] => $context->contact_primary_phone,
            ['conversation', 'id'] => $context->conversation_id,
            ['conversation', 'subject'] => $context->conversation_subject,
            ['teammate', 'name'] => $context->teammate_name,
            ['app', 'name'] => $context->app_name,
            default => null,
        };

        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * 返回支持解析的静态 token 字段表。
     *
     * @return array<string, array<int, string>>
     */
    private static function staticTokenSpec(): array
    {
        return [
            CannedReplyTokenKind::Contact->value => ['name', 'email', 'primary_phone'],
            CannedReplyTokenKind::Conversation->value => ['subject', 'id'],
            CannedReplyTokenKind::Teammate->value => ['name'],
            CannedReplyTokenKind::Instance->value => ['name'],
        ];
    }
}
