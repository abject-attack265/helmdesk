<?php

namespace App\Data;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Data;

/**
 * 简单分页数据。
 * 随列表页 props 返回，前端分页组件用它渲染上一页、下一页和当前页状态。
 */
class SimplePaginationData extends Data
{
    /** 页面列表分页默认每页条数；新增列表页一律引用，不要另写字面量。 */
    public const int DEFAULT_PER_PAGE = 10;

    public function __construct(
        public int $current_page,
        public int $last_page,
        public int $per_page,
        public int $total,
    ) {}

    /**
     * 从 Laravel 分页器构造，统一兜底 last_page 至少为 1。
     */
    public static function fromPaginator(LengthAwarePaginator $paginator): self
    {
        return new self(
            current_page: $paginator->currentPage(),
            last_page: max(1, $paginator->lastPage()),
            per_page: $paginator->perPage(),
            total: $paginator->total(),
        );
    }

    /**
     * 空数据集对应的占位分页，常用于"先创建实例、有数据再覆写"的页面 props。
     */
    public static function placeholder(int $perPage): self
    {
        return new self(
            current_page: 1,
            last_page: 1,
            per_page: $perPage,
            total: 0,
        );
    }
}
