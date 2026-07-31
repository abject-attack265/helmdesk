<?php

namespace App\Services\Tag;

use App\Contracts\ContactTagFilterStrategy;
use App\Data\Contact\ContactTagConditionData;
use App\Data\Contact\ContactTagFilterData;
use App\Enums\TagMatchMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * 基于联系人标签关联表的筛选策略。
 */
class PivotContactTagFilterStrategy implements ContactTagFilterStrategy
{
    private const string RELATION = 'tags';

    private const string TAG_KEY = 'tags.id';

    private const string PIVOT_TABLE = 'contact_tag_assignments';

    /**
     * 把联系人标签筛选应用到查询上。
     */
    public function apply(Builder|Relation $query, ContactTagFilterData $spec): Builder|Relation
    {
        if ($spec->untagged_only) {
            return $query->whereDoesntHave(self::RELATION);
        }

        $this->applyInclude($query, $spec);
        $this->applyExclude($query, $spec);

        return $query;
    }

    /**
     * 处理联系人标签包含条件。
     */
    private function applyInclude(Builder|Relation $query, ContactTagFilterData $spec): void
    {
        if ($spec->include === []) {
            return;
        }

        if ($spec->include_mode === TagMatchMode::All) {
            foreach ($spec->include as $condition) {
                $query->whereHas(
                    self::RELATION,
                    fn (Builder $q) => $this->matchSingle($q, $condition),
                );
            }

            return;
        }

        /**
         * include any + 任一条件携带了 per-assignment 元数据（时间/打标人/来源）时，
         * 无法合并成一次 whereIn，只能展开成 OR 链。
         */
        if ($this->anyConditionHasConstraints($spec->include)) {
            $query->where(function (Builder $outer) use ($spec) {
                foreach ($spec->include as $condition) {
                    $outer->orWhereHas(
                        self::RELATION,
                        fn (Builder $q) => $this->matchSingle($q, $condition),
                    );
                }
            });

            return;
        }

        $query->whereHas(
            self::RELATION,
            fn (Builder $q) => $q->whereIn(self::TAG_KEY, $spec->includeTagIds()),
        );
    }

    /**
     * 处理联系人标签排除条件。
     */
    private function applyExclude(Builder|Relation $query, ContactTagFilterData $spec): void
    {
        if ($spec->exclude === []) {
            return;
        }

        if ($spec->exclude_mode === TagMatchMode::All) {
            $this->applyExcludeAll($query, $spec->exclude);

            return;
        }

        if ($this->anyConditionHasConstraints($spec->exclude)) {
            foreach ($spec->exclude as $condition) {
                $query->whereDoesntHave(
                    self::RELATION,
                    fn (Builder $q) => $this->matchSingle($q, $condition),
                );
            }

            return;
        }

        $query->whereDoesntHave(
            self::RELATION,
            fn (Builder $q) => $q->whereIn(self::TAG_KEY, $spec->excludeTagIds()),
        );
    }

    /**
     * 排除同时命中全部标签条件的联系人。
     *
     * @param  list<ContactTagConditionData>  $conditions
     */
    private function applyExcludeAll(Builder|Relation $query, array $conditions): void
    {
        $tagIds = array_map(static fn (ContactTagConditionData $c) => $c->tag_id, $conditions);
        $distinctTagCount = count(array_unique($tagIds));

        $subquery = DB::table(self::PIVOT_TABLE)
            ->select('contact_id')
            ->where(function (QueryBuilder $q) use ($conditions) {
                foreach ($conditions as $condition) {
                    $q->orWhere(function (QueryBuilder $row) use ($condition) {
                        $row->where('tag_id', $condition->tag_id);

                        if ($condition->tagged_after !== null) {
                            $row->where('created_at', '>=', $condition->tagged_after);
                        }
                        if ($condition->tagged_before !== null) {
                            $row->where('created_at', '<', $condition->tagged_before);
                        }
                        if ($condition->assigned_by_user_id !== null) {
                            $row->where('assigned_by_user_id', $condition->assigned_by_user_id);
                        }
                        if ($condition->source !== null) {
                            $row->where('source', $condition->source);
                        }
                    });
                }
            })
            ->groupBy('contact_id')
            ->havingRaw('COUNT(DISTINCT tag_id) = ?', [$distinctTagCount]);

        $query->whereNotIn($query->getModel()->getQualifiedKeyName(), $subquery);
    }

    /**
     * 把单个联系人标签条件落到关系查询上。
     */
    private function matchSingle(Builder $q, ContactTagConditionData $condition): void
    {
        $q->where(self::TAG_KEY, $condition->tag_id);

        if (! $condition->hasAssignmentConstraints()) {
            return;
        }

        if ($condition->tagged_after !== null) {
            $q->where(self::PIVOT_TABLE.'.created_at', '>=', $condition->tagged_after);
        }

        if ($condition->tagged_before !== null) {
            $q->where(self::PIVOT_TABLE.'.created_at', '<', $condition->tagged_before);
        }

        if ($condition->assigned_by_user_id !== null) {
            $q->where(self::PIVOT_TABLE.'.assigned_by_user_id', $condition->assigned_by_user_id);
        }

        if ($condition->source !== null) {
            $q->where(self::PIVOT_TABLE.'.source', $condition->source);
        }
    }

    /**
     * 检查标签条件是否带有打标维度约束。
     *
     * @param  list<ContactTagConditionData>  $conditions
     */
    private function anyConditionHasConstraints(array $conditions): bool
    {
        foreach ($conditions as $condition) {
            if ($condition->hasAssignmentConstraints()) {
                return true;
            }
        }

        return false;
    }
}
