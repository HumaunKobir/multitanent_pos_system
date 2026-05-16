<?php

namespace App\Models;

use App\Enums\CommonStatus;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'status'];

    protected $casts = [
        'status' => CommonStatus::class,
    ];
}
