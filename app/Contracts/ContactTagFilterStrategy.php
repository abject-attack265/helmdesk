<?php

namespace App\Contracts;

use App\Data\Contact\ContactTagFilterData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * 联系人标签筛选策略。
 */
interface ContactTagFilterStrategy
{
    /**
     * @param  Builder<\App\Models\Contact>|Relation<\App\Models\Contact, *, *>  $query
     * @return Builder<\App\Models\Contact>|Relation<\App\Models\Contact, *, *>
     */
    public function apply(Builder|Relation $query, ContactTagFilterData $spec): Builder|Relation;
}
