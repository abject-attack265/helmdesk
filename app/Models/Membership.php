<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 系统成员资料。
 *
 * 一个用户最多对应一条记录；没有记录的用户尚未加入后台成员列表。
 */
class Membership extends Model
{
    protected $table = 'memberships';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_active_at' => 'datetime',
        ];
    }
}
