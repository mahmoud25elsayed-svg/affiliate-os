<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Affiliate extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
