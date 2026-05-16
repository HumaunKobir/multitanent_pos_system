<?php

namespace App\Models;

use App\Enums\CommonStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    protected $fillable = ['name', 'description', 'branch_id', 'status'];

    protected $casts = [
        'status' => CommonStatus::class,
    ];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
