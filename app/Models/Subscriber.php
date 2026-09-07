<?php

namespace App\Models;

use App\Traits\UsesTenantConnection;
use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory, UsesTenantConnection;

    protected $fillable = [
        'email',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }
}
