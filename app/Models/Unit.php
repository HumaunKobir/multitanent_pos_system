<?php

namespace App\Models;

use App\Traits\HasBranchCatalog;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasBranchCatalog;

    protected $fillable = ['branch_id', 'catalog_group_id', 'name', 'status'];
}
