<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = ['name', 'description', 'branch_id', 'status'];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
