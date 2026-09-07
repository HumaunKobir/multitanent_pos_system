<?php

namespace App\Models;

use App\Traits\HasBranchCatalog;
use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class Warranty extends Model
{
    use HasBranchCatalog, UsesTenantConnection;

    protected $fillable = ['branch_id', 'catalog_group_id', 'name', 'duration', 'status'];
}
