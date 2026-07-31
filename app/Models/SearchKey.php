<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchKey extends Model
{
    public $timestamps = false;

    protected $table = 'search_keys';

    protected $guarded = [];
}
