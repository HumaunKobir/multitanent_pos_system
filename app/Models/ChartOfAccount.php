<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';

    protected $fillable = ['gl_account', 'head_type', 'name', 'status'];

    public function headExpenses(): HasMany
    {
        return $this->hasMany(HeadExpense::class, 'head_id');
    }

    public function headIncomes(): HasMany
    {
        return $this->hasMany(HeadIncome::class, 'head_id');
    }
}
