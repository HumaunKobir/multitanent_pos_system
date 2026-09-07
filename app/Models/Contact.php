<?php

namespace App\Models;

use App\Traits\HasBranch;
use App\Traits\UsesTenantConnection;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasBranch, HasFactory, UsesTenantConnection;

    protected $fillable = ['branch_id', 'name', 'email', 'phone', 'message'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
