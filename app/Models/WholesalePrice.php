<?php

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WholesalePrice extends Model
{
    use BelongsToTenant;

    protected $guarded = [];
}
