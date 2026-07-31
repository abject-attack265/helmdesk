<?php

namespace App\Data\Contact;

use App\Enums\TagMatchMode;
use App\Services\Time\LocalDayBoundary;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

/**
 * 联系人标签筛选数据。
 * 由后端组装后传给 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ContactTagFilterData extends Data
{
    /**
     * @param  list<ContactTagConditionData>  $include
     * @param  list<ContactTagConditionData>  $exclude
     */
    public function __construct(
        public array $include,
        public TagMatchMode $include_mode,
        public array $exclude,
        public TagMatchMode $exclude_mode,
        public bool $untagged_only,
    ) {}

    public static function unfiltered(): self
    {
        return new self(
            include: [],
            include_mode: TagMatchMode::Any,
            exclude: [],
            exclude_mode: TagMatchMode::Any,
            untagged_only: false,
        );
    }

    /**
     * 从 HTTP 请求构造 Spec。
     *
     * 当 untagged_only 为真时，其余条件一律忽略，避免语义冲突。
     * $timezone 为查看者时区：打标时间筛选按其本地日历日取边界，与列表里渲染的时间口径一致。
     */
    public static function fromRequest(Request $request, string $timezone): self
    {
        $untaggedOnly = self::parseBoolean($request->query('untagged_only'), 'untagged_only');

        if ($untaggedOnly) {
            return new self(
                include: [],
                include_mode: TagMatchMode::Any,
                exclude: [],
                exclude_mode: TagMatchMode::Any,
                untagged_only: true,
            );
        }

        $includeIds = self::normalizeIds($request->query('include_tag_ids', []));
        $excludeIds = self::normalizeIds($request->query('exclude_tag_ids', []));

        // include 与 exclude 不做交集消歧，按原样下发交由 SQL 求值（含 A 且不含 B 是合法表达）。
        $includeMode = self::resolveMode($request->query('include_tag_mode'), 'include_tag_mode');
        $excludeMode = self::resolveMode($request->query('exclude_tag_mode'), 'exclude_tag_mode');

        $taggedAfter = self::parseLocalDayStart($request->query('tag_tagged_after'), 'tag_tagged_after', $timezone);
        $taggedBefore = self::parseLocalDayEndExclusive($request->query('tag_tagged_before'), 'tag_tagged_before', $timezone);

        return new self(
            include: array_map(
                static fn (string $id) => new ContactTagConditionData(
                    tag_id: $id,
                    tagged_after: $taggedAfter,
                    tagged_before: $taggedBefore,
                ),
                $includeIds,
            ),
            include_mode: $includeMode,
            exclude: array_map(
                static fn (string $id) => new ContactTagConditionData(tag_id: $id),
                $excludeIds,
            ),
            exclude_mode: $excludeMode,
            untagged_only: false,
        );
    }

    public function isEmpty(): bool
    {
        return $this->include === []
            && $this->exclude === []
            && ! $this->untagged_only;
    }

    /**
     * 过滤掉不在可用标签集合中的 tag_id（防刷/防过期）。
     *
     * @param  array<int, string>  $allowedTagIds
     */
    public function restrictedTo(array $allowedTagIds): self
    {
        $allowed = array_flip($allowedTagIds);

        $filter = static fn (array $conditions): array => array_values(array_filter(
            $conditions,
            static fn (ContactTagConditionData $c) => isset($allowed[$c->tag_id]),
        ));

        return new self(
            include: $filter($this->include),
            include_mode: $this->include_mode,
            exclude: $filter($this->exclude),
            exclude_mode: $this->exclude_mode,
            untagged_only: $this->untagged_only,
        );
    }

    /**
     * @return list<string>
     */
    public function includeTagIds(): array
    {
        return array_map(static fn (ContactTagConditionData $c) => $c->tag_id, $this->include);
    }

    /**
     * @return list<string>
     */
    public function excludeTagIds(): array
    {
        return array_map(static fn (ContactTagConditionData $c) => $c->tag_id, $this->exclude);
    }

    /**
     * @param  mixed  $raw
     * @return list<string>
     */
    private static function normalizeIds($raw): array
    {
        if (! is_array($raw)) {
            $raw = $raw === null || $raw === '' ? [] : [$raw];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($v) => is_string($v) ? trim($v) : '', $raw),
            static fn (string $v) => $v !== '',
        )));

        return $ids;
    }

    private static function parseBoolean(mixed $raw, string $field): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }

        return match ($raw) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => throw ValidationException::withMessages([
                $field => __('validation.boolean', ['attribute' => $field]),
            ]),
        };
    }

    private static function resolveMode(mixed $raw, string $field): TagMatchMode
    {
        if ($raw === null || $raw === '') {
            return TagMatchMode::Any;
        }

        if ($raw instanceof TagMatchMode) {
            return $raw;
        }

        if (is_string($raw) && ($mode = TagMatchMode::tryFrom($raw)) instanceof TagMatchMode) {
            return $mode;
        }

        throw ValidationException::withMessages([
            $field => __('validation.in', ['attribute' => $field]),
        ]);
    }

    /**
     * 取该本地日期的最早时刻（UTC），作为闭区间下界。
     */
    private static function parseLocalDayStart(mixed $raw, string $field, string $timezone): ?Carbon
    {
        $date = self::parseLocalDate($raw, $field);

        return $date === null ? null : LocalDayBoundary::startUtc($date, $timezone);
    }

    /**
     * 取该本地日期次日的最早时刻（UTC），作为开区间上界，让所选日期当天整天都落在区间内。
     */
    private static function parseLocalDayEndExclusive(mixed $raw, string $field, string $timezone): ?Carbon
    {
        $date = self::parseLocalDate($raw, $field);

        return $date === null ? null : LocalDayBoundary::endUtcExclusive($date, $timezone);
    }

    /**
     * 校验并取出 Y-m-d 日期串；格式不符或日期不存在（如 2026-02-30）抛验证异常。
     */
    private static function parseLocalDate(mixed $raw, string $field): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $invalid = fn (): ValidationException => ValidationException::withMessages([
            $field => __('validation.date_format', ['attribute' => $field, 'format' => 'YYYY-MM-DD']),
        ]);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $matches) !== 1) {
            throw $invalid();
        }

        if (! checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
            throw $invalid();
        }

        return $raw;
    }
}
